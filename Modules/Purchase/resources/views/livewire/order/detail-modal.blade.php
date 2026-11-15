<?php

use function Livewire\Volt\{state, on};
use Modules\Purchase\Models\PurchaseOrder;

state([
    'show' => false,
    'order' => null,
    'showVoidModal' => false,
    'voidRefundType' => 'vendor_credit',
    'showMergeModal' => false,
    'mergeCandidateIds' => [],
    'showUnmergeCurrentConfirm' => false,
]);

$getMergeCandidates = function () {
    if (!$this->order || !$this->order->vendor_id) return collect();
    if (in_array($this->order->status, ['merged', 'void', 'completed', 'cancelled', 'archived'])) return collect();

    return PurchaseOrder::with(['items', 'mergedOrders'])
        ->where('vendor_id', $this->order->vendor_id)
        ->where('id', '!=', $this->order->id)
        ->whereNotIn('status', ['merged', 'void', 'completed', 'cancelled', 'archived'])
        ->orderBy('id', 'desc')
        ->get();
};

$openMergeModal = function () {
    abort_unless(auth()->user()->hasAnyRole(['Manager', 'Super Admin']), 403);
    $candidates = $this->getMergeCandidates();
    if ($candidates->isEmpty()) {
        \Flux::toast('Tidak ada pesanan aktif lain dari vendor ini yang bisa digabungkan.', variant: 'warning');
        return;
    }
    $this->mergeCandidateIds = $candidates->pluck('id')->toArray();
    $this->showMergeModal = true;
};

$toggleMergeCandidate = function ($id) {
    $id = (int) $id;
    if (in_array($id, $this->mergeCandidateIds)) {
        $this->mergeCandidateIds = array_values(array_filter($this->mergeCandidateIds, fn($item) => $item !== $id));
    } else {
        $this->mergeCandidateIds[] = $id;
    }
};

$executeMergeIntoCurrent = function () {
    abort_unless(auth()->user()->hasAnyRole(['Manager', 'Super Admin']), 403);
    if (empty($this->mergeCandidateIds)) {
        \Flux::toast('Pilih minimal satu pesanan untuk digabungkan.', variant: 'danger');
        return;
    }

    $parent = PurchaseOrder::with(['items', 'payments'])->findOrFail($this->order->id);

    \Illuminate\Support\Facades\DB::transaction(function () use ($parent) {
        foreach ($this->mergeCandidateIds as $childId) {
            $child = PurchaseOrder::with(['items', 'payments', 'mergedOrders'])->findOrFail($childId);

            // 1. Pindahkan items asli milik $child
            \Modules\Purchase\Models\PurchaseOrderItem::where('purchase_order_id', $child->id)
                ->whereNull('original_po_id')
                ->update([
                    'purchase_order_id' => $parent->id,
                    'original_po_id' => $child->id,
                ]);

            // 2. Pindahkan items yang sebelumnya dari anak-anaknya $child (pertahankan original_po_id aslinya)
            \Modules\Purchase\Models\PurchaseOrderItem::where('purchase_order_id', $child->id)
                ->whereNotNull('original_po_id')
                ->update([
                    'purchase_order_id' => $parent->id,
                ]);

            // 3. Pindahkan payments asli milik $child
            \Modules\Purchase\Models\PurchasePayment::where('purchase_order_id', $child->id)
                ->whereNull('original_po_id')
                ->update([
                    'purchase_order_id' => $parent->id,
                    'original_po_id' => $child->id,
                ]);

            // 4. Pindahkan payments dari anak-anaknya $child (pertahankan original_po_id aslinya)
            \Modules\Purchase\Models\PurchasePayment::where('purchase_order_id', $child->id)
                ->whereNotNull('original_po_id')
                ->update([
                    'purchase_order_id' => $parent->id,
                ]);

            // 5. Flattening: alihkan semua anak dari $child langsung menginduk ke $parent
            \Modules\Purchase\Models\PurchaseOrder::where('merged_into_po_id', $child->id)
                ->update([
                    'merged_into_po_id' => $parent->id,
                ]);

            // 6. Set status PO anak $child menjadi merged
            $child->status_before_merge = $child->status;
            $child->merged_into_po_id = $parent->id;
            $child->status = 'merged';
            $child->save();

            // Recalculate PO anak
            $child->load('items', 'payments');
            $child->recalculateTotal();
        }

        // Recalculate PO induk
        $parent->load('items', 'payments');
        $parent->recalculateTotal();
    });

    $this->showMergeModal = false;
    $this->mergeCandidateIds = [];
    $this->order = PurchaseOrder::with(['vendor', 'items.item.unit', 'creator', 'payments', 'mergedInto', 'mergedOrders.items', 'mergedOrders.payments'])->find($parent->id);
    $this->dispatch('status-updated');
    $this->dispatch('refresh-purchase-orders');
    \Flux::toast("PO berhasil digabungkan ke {$parent->po_number}!", variant: 'success');
    \App\Events\KanbanUpdated::safeDispatch('purchase_order');
};

$executeUnmergeChild = function ($childId) {
    abort_unless(auth()->user()->hasAnyRole(['Manager', 'Super Admin']), 403);
    $child = PurchaseOrder::findOrFail($childId);
    abort_unless($child->status === 'merged' && $child->merged_into_po_id == $this->order->id, 422);

    $parent = PurchaseOrder::findOrFail($this->order->id);

    // Strict Lock (Alternatif 6): Proteksi keuangan jika ada pembayaran baru setelah penggabungan
    $postMergePayments = \Modules\Purchase\Models\PurchasePayment::where('purchase_order_id', $parent->id)
        ->whereNull('original_po_id')
        ->where('created_at', '>=', $child->updated_at)
        ->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'rejected');
        })
        ->get();

    if ($postMergePayments->isNotEmpty()) {
        $totalPost = number_format($postMergePayments->sum('amount'), 0, ',', '.');
        \Flux::toast("Tidak dapat memisahkan pesanan: Terdapat pembayaran gabungan sebesar Rp {$totalPost} yang dicatat pada PO Induk setelah penggabungan. Harap sesuaikan/batalkan pembayaran tersebut di menu Pembayaran terlebih dahulu sebelum memisahkan pesanan.", variant: 'danger');
        return;
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($child, $parent) {
        \Modules\Purchase\Models\PurchaseOrderItem::where('purchase_order_id', $parent->id)
            ->where('original_po_id', $child->id)
            ->update(['purchase_order_id' => $child->id, 'original_po_id' => null]);

        \Modules\Purchase\Models\PurchasePayment::where('purchase_order_id', $parent->id)
            ->where('original_po_id', $child->id)
            ->update(['purchase_order_id' => $child->id, 'original_po_id' => null]);

        $child->status = $child->status_before_merge ?? 'draft';
        $child->status_before_merge = null;
        $child->merged_into_po_id = null;
        $child->save();

        $child->load('items', 'payments');
        $child->recalculateTotal();

        $parent->load('items', 'payments');
        $parent->recalculateTotal();
    });

    $this->order = PurchaseOrder::with(['vendor', 'items.item.unit', 'creator', 'payments', 'mergedInto', 'mergedOrders.items', 'mergedOrders.payments'])->find($parent->id);
    $this->dispatch('status-updated');
    $this->dispatch('refresh-purchase-orders');
    \Flux::toast("PO {$child->po_number} berhasil dipisahkan!", variant: 'success');
    \App\Events\KanbanUpdated::safeDispatch('purchase_order');
};

$executeUnmergeCurrent = function () {
    abort_unless(auth()->user()->hasAnyRole(['Manager', 'Super Admin']), 403);
    $child = PurchaseOrder::findOrFail($this->order->id);
    abort_unless($child->status === 'merged' && $child->merged_into_po_id, 422);

    $parent = PurchaseOrder::findOrFail($child->merged_into_po_id);

    // Strict Lock (Alternatif 6): Proteksi keuangan jika ada pembayaran baru setelah penggabungan
    $postMergePayments = \Modules\Purchase\Models\PurchasePayment::where('purchase_order_id', $parent->id)
        ->whereNull('original_po_id')
        ->where('created_at', '>=', $child->updated_at)
        ->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'rejected');
        })
        ->get();

    if ($postMergePayments->isNotEmpty()) {
        $this->showUnmergeCurrentConfirm = false;
        $totalPost = number_format($postMergePayments->sum('amount'), 0, ',', '.');
        \Flux::toast("Tidak dapat memisahkan pesanan: Terdapat pembayaran gabungan sebesar Rp {$totalPost} yang dicatat pada PO Induk setelah penggabungan. Harap sesuaikan/batalkan pembayaran tersebut di menu Pembayaran terlebih dahulu sebelum memisahkan pesanan.", variant: 'danger');
        return;
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($child, $parent) {
        \Modules\Purchase\Models\PurchaseOrderItem::where('purchase_order_id', $parent->id)
            ->where('original_po_id', $child->id)
            ->update(['purchase_order_id' => $child->id, 'original_po_id' => null]);

        \Modules\Purchase\Models\PurchasePayment::where('purchase_order_id', $parent->id)
            ->where('original_po_id', $child->id)
            ->update(['purchase_order_id' => $child->id, 'original_po_id' => null]);

        $child->status = $child->status_before_merge ?? 'draft';
        $child->status_before_merge = null;
        $child->merged_into_po_id = null;
        $child->save();

        $child->load('items', 'payments');
        $child->recalculateTotal();

        $parent->load('items', 'payments');
        $parent->recalculateTotal();
    });

    $this->showUnmergeCurrentConfirm = false;
    $this->order = PurchaseOrder::with(['vendor', 'items.item.unit', 'creator', 'payments', 'mergedInto', 'mergedOrders.items', 'mergedOrders.payments'])->find($child->id);
    $this->dispatch('status-updated');
    $this->dispatch('refresh-purchase-orders');
    \Flux::toast("PO {$child->po_number} berhasil dipulihkan!", variant: 'success');
    \App\Events\KanbanUpdated::safeDispatch('purchase_order');
};

on(['open-detail-modal' => function ($orderId) {
    $this->order = PurchaseOrder::with(['vendor', 'items.item.unit', 'creator', 'payments', 'mergedInto', 'mergedOrders.items', 'mergedOrders.payments'])->findOrFail($orderId);
    $this->show = true;
    $this->showVoidModal = false;
    $this->showMergeModal = false;
    $this->showUnmergeCurrentConfirm = false;
    $this->mergeCandidateIds = [];
    $this->voidRefundType = 'vendor_credit';
}]);

$voidOrder = function (\App\Services\VoidService $voidService) {
    abort_unless(auth()->user()->hasAnyRole(['Manager', 'Super Admin']), 403);
    $voidService->voidPurchaseOrder($this->order, $this->voidRefundType);
    $this->showVoidModal = false;
    $this->show = false;
    $this->dispatch('refresh-purchase-orders');
    \Flux::toast('Pesanan Pembelian berhasil dibatalkan (Void).', variant: 'success');
};

$archiveDraft = function () {
    abort_unless(auth()->user()->can('purchase.order.update'), 403);
    if ($this->order->status === 'draft') {
        $this->order->status = 'archived';
        $this->order->save();
        $this->show = false;
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('purchase_order');
        \Flux::toast('Draft berhasil diarsipkan.', variant: 'success');
    }
};

$getStatusBadge = function ($status) {
    if ($status === 'archived' && str_starts_with($this->order->po_number ?? '', 'DRFT-')) {
        return "<span class=\"inline-flex items-center rounded-md bg-zinc-100 px-2 py-1 text-xs font-bold text-zinc-700 ring-1 ring-inset ring-zinc-500/20 shadow-sm border border-zinc-300\">Bekas Draft</span>";
    }

    $map = [
        'draft' => ['label' => 'Draft', 'color' => 'zinc'],
        'pending_approval' => ['label' => 'Menunggu ACC', 'color' => 'amber'],
        'processing' => ['label' => 'Diproses Vendor', 'color' => 'blue'],
        'partially_received' => ['label' => 'Diterima Sebagian', 'color' => 'indigo'],
        'completed' => ['label' => 'Selesai', 'color' => 'emerald'],
        'void' => ['label' => 'Void', 'color' => 'red'],
        'cancelled' => ['label' => 'Dibatalkan', 'color' => 'red'],
        'merged' => ['label' => 'Digabung', 'color' => 'zinc'],
    ];
    
    $mapped = $map[$status] ?? ['label' => $status, 'color' => 'zinc'];
    
    return "<span class=\"inline-flex items-center rounded-md bg-{$mapped['color']}-50 px-2 py-1 text-xs font-medium text-{$mapped['color']}-700 ring-1 ring-inset ring-{$mapped['color']}-600/20\">{$mapped['label']}</span>";
};

?>

<div>
    <x-modal wire:model="show">
        @if($order)
            {{-- Header: PO Number + Status + Date --}}
            <div class="pb-2 border-b border-zinc-200 dark:border-zinc-700 pr-6 relative">
                {{-- Baris 1: Nomor PO + Status + Creator --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-base font-black text-zinc-800 dark:text-zinc-100">{{ $order->po_number }}</span>
                    {!! $this->getStatusBadge($order->status) !!}
                    @if($order->mergedOrders && $order->mergedOrders->count() > 0)
                        <span class="inline-flex items-center gap-1 rounded-md bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 text-xs font-bold text-indigo-700 dark:text-indigo-300 ring-1 ring-inset ring-indigo-600/30 shadow-sm" title="PO Induk: Menggabungkan {{ $order->mergedOrders->count() }} pesanan">
                            <flux:icon.arrow-path-rounded-square class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                            PO INDUK (+{{ $order->mergedOrders->count() }} Gabungan)
                        </span>
                    @endif
                    @if($order->pajak)
                        <span class="inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">PPN</span>
                    @endif
                    <div class="ml-auto flex items-center gap-1.5 text-[11px] text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700" title="Petugas Checkout">
                        <flux:icon.user class="w-3 h-3" />
                        <span class="font-medium">{{ explode(' ', $order->creator?->name ?? 'Sistem')[0] }}</span>
                    </div>
                </div>

                {{-- Baris 2: Tanggal & Deadline --}}
                <div class="flex items-center gap-3 mt-1 text-[11px] text-zinc-400 flex-wrap">
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Order: {{ \Carbon\Carbon::parse($order->order_date)->translatedFormat('d F Y') }}
                    </span>
                    @if($order->expected_delivery_date)
                        @php
                            $deadline = \Carbon\Carbon::parse($order->expected_delivery_date);
                            $now = \Carbon\Carbon::now();
                            $isCompleted = in_array($order->status, ['completed', 'cancelled']);
                            if ($isCompleted) {
                                $dlColor = 'text-emerald-600'; $dlIcon = '✅';
                            } elseif ($deadline->isPast()) {
                                $dlColor = 'text-red-600 font-semibold'; $dlIcon = '🔴';
                            } elseif ($deadline->diffInDays($now) <= 3) {
                                $dlColor = 'text-amber-600 font-semibold'; $dlIcon = '⚠️';
                            } else {
                                $dlColor = 'text-zinc-500'; $dlIcon = '📅';
                            }
                        @endphp
                        <span class="flex items-center gap-1 {{ $dlColor }}">
                            {{ $dlIcon }} Deadline: {{ $deadline->translatedFormat('d F Y') }}
                            @if(!$isCompleted && $deadline->isPast())
                                <span class="text-[10px]">({{ $deadline->locale('id')->diffForHumans() }})</span>
                            @elseif(!$isCompleted && $deadline->isFuture())
                                <span class="text-[10px] text-zinc-400">({{ $deadline->locale('id')->diffForHumans() }})</span>
                            @endif
                        </span>
                    @else
                        <span class="flex items-center gap-1 text-zinc-400 italic">
                            📅 Deadline: Tidak ditentukan
                        </span>
                    @endif
                </div>

                {{-- Baris 3: Tombol Print PO --}}
                @if(!in_array($order->status, ['pending_approval', 'rejected']))
                    <div x-data="{
                        printing: false,
                        printPO(url, filename) {
                            if (this.printing) return;
                            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                            if (isMobile) {
                                window.open(url, '_blank');
                                return;
                            }
                            this.printing = true;
                            const originalTitle = document.title;
                            document.title = filename;
                            const iframe = document.createElement('iframe');
                            iframe.style.display = 'none';
                            iframe.src = url;
                            document.body.appendChild(iframe);
                            iframe.onload = () => {
                                iframe.contentWindow.focus();
                                iframe.contentWindow.print();
                                this.printing = false;
                                setTimeout(() => {
                                    document.title = originalTitle;
                                    document.body.removeChild(iframe);
                                }, 5000);
                            };
                        }
                    }" class="mt-2">
                        <flux:button size="sm" variant="subtle" class="!px-2 !py-1 h-auto text-[10px] w-full sm:w-auto justify-center" icon="printer" x-on:click="printPO('{{ route('purchase.orders.print', $order->id) }}', '{{ $order->po_number }} {{ addslashes($order->vendor?->name ?? '') }}')" x-bind:disabled="printing">
                            <span x-show="!printing">Cetak PO</span>
                            <span x-show="printing" style="display: none;">Mencetak...</span>
                        </flux:button>
                    </div>
                @endif
            </div>

            {{-- Merge / Digabungkan Alert & Status --}}
            @if($order->status === 'merged')
                @php
                    $parentPo = $order->mergedInto;
                    $childPostPayments = $parentPo ? \Modules\Purchase\Models\PurchasePayment::where('purchase_order_id', $parentPo->id)
                        ->whereNull('original_po_id')
                        ->where('created_at', '>=', $order->updated_at)
                        ->where(function ($q) {
                            $q->whereNull('status')->orWhere('status', '!=', 'rejected');
                        })->get() : collect();
                    $isLocked = $childPostPayments->isNotEmpty();
                    $lockTotal = $isLocked ? number_format($childPostPayments->sum('amount'), 0, ',', '.') : 0;
                @endphp
                <div class="mt-3 p-3 bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-300 dark:border-zinc-700 rounded-lg flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-xs text-zinc-700 dark:text-zinc-300">
                        <flux:icon.arrow-path-rounded-square class="w-4 h-4 text-indigo-500 shrink-0" />
                        <div>
                            <span>PO ini berstatus <strong>Digabungkan</strong> ke PO Induk: </span>
                            <strong class="font-mono text-indigo-600 dark:text-indigo-400 font-bold">{{ $order->mergedInto?->po_number }}</strong>
                            @if($isLocked)
                                <div class="text-[11px] text-red-600 dark:text-red-400 font-medium flex items-center gap-1 mt-0.5">
                                    <flux:icon.lock-closed class="w-3 h-3 shrink-0 text-red-500" />
                                    Pemisahan terkunci: terdapat pembayaran baru Rp {{ $lockTotal }} di PO Induk.
                                </div>
                            @endif
                        </div>
                    </div>
                    @if(auth()->user()->hasAnyRole(['Manager', 'Super Admin']))
                        <flux:button size="xs" variant="subtle" icon="{{ $isLocked ? 'lock-closed' : 'scissors' }}" wire:click="$set('showUnmergeCurrentConfirm', true)" class="{{ $isLocked ? 'text-red-600 hover:text-red-700' : 'text-amber-600 hover:text-amber-700' }} shrink-0">
                            {{ $isLocked ? 'Pemisahan Terkunci' : 'Pisahkan PO Ini' }}
                        </flux:button>
                    @endif
                </div>
            @endif

            @if($order->mergedOrders && $order->mergedOrders->count() > 0)
                <div class="mt-3 p-3 bg-indigo-50/70 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800 rounded-lg">
                    <div class="text-xs font-bold text-indigo-900 dark:text-indigo-300 flex items-center gap-1.5 mb-2">
                        <flux:icon.arrow-path-rounded-square class="w-4 h-4 text-indigo-600 shrink-0" />
                        Pesanan Lain Yang Digabung ke Sini ({{ $order->mergedOrders->count() }}):
                    </div>
                    <div class="space-y-1.5">
                        @foreach($order->mergedOrders as $childOrder)
                            @php
                                $childPostPayments = ($order->payments ?? collect())->filter(function ($payment) use ($childOrder) {
                                    return is_null($payment->original_po_id)
                                        && $payment->created_at >= $childOrder->updated_at
                                        && $payment->status !== 'rejected';
                                });
                                $isChildLocked = $childPostPayments->isNotEmpty();
                                $childPostTotal = $isChildLocked ? number_format($childPostPayments->sum('amount'), 0, ',', '.') : 0;
                            @endphp
                            <div class="flex items-center justify-between bg-white dark:bg-zinc-800 px-3 py-1.5 rounded border border-indigo-100 dark:border-zinc-700 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-zinc-800 dark:text-zinc-200">{{ $childOrder->po_number }}</span>
                                    <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                                    <span class="text-zinc-500">{{ \Carbon\Carbon::parse($childOrder->order_date)->format('d M Y') }}</span>
                                    <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                                    <span class="text-zinc-500">{{ $childOrder->items->count() }} item</span>
                                    @if($isChildLocked)
                                        <span class="text-[10px] font-semibold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 px-1.5 py-0.5 rounded flex items-center gap-1" title="Terkunci: Ada pembayaran Rp {{ $childPostTotal }} setelah merger">
                                            <flux:icon.lock-closed class="w-3 h-3 text-red-500" />
                                            Terkunci (Ada Bayar Rp {{ $childPostTotal }})
                                        </span>
                                    @endif
                                </div>
                                @if(auth()->user()->hasAnyRole(['Manager', 'Super Admin']))
                                    @if($isChildLocked)
                                        <flux:button size="xs" variant="ghost" icon="lock-closed" wire:click="executeUnmergeChild({{ $childOrder->id }})" class="text-zinc-400 hover:text-red-600" title="Klik untuk info proteksi">
                                            Terkunci
                                        </flux:button>
                                    @else
                                        <flux:button size="xs" variant="ghost" icon="scissors" wire:click="executeUnmergeChild({{ $childOrder->id }})" wire:confirm="Pisahkan pesanan {{ $childOrder->po_number }} dari PO ini?" class="text-amber-600 hover:text-amber-700">
                                            Pisahkan
                                        </flux:button>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Vendor + Notes in one compact row --}}
            <div class="flex items-start gap-3 bg-zinc-50 dark:bg-zinc-800/40 rounded-lg p-2.5 border border-zinc-200 dark:border-zinc-700 mt-3">
                <flux:avatar src="{{ $order->vendor?->image ? Storage::url($order->vendor->image) : '' }}" fallback="{{ substr($order->vendor?->name ?? '?', 0, 2) }}" size="sm" class="shrink-0" />
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ $order->vendor?->name ?? 'Vendor Terhapus' }}</div>
                    <div class="flex items-center gap-3 text-xs text-zinc-500 mt-0.5 flex-wrap">
                        @if($order->vendor?->phone)
                            <span>📞 {{ $order->vendor->phone }}</span>
                        @endif
                        @if($order->vendor?->address)
                            <span class="truncate max-w-[200px]">📍 {{ $order->vendor->address }}</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Notes (collapsible) --}}
            @if($order->notes)
                <div x-data="{ open: false }" class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                    <button @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2 bg-zinc-50 dark:bg-zinc-800/50 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors text-left">
                        <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 flex items-center gap-1.5">
                            💬 Catatan Pesanan
                            <span class="text-[10px] font-normal text-zinc-400 italic" x-show="!open">— {{ Str::limit(strip_tags($order->notes), 80) }}</span>
                        </span>
                        <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak
                         class="px-3 py-2.5 bg-white dark:bg-zinc-900 text-xs text-zinc-700 dark:text-zinc-300 prose prose-xs max-w-none prose-p:my-1 prose-img:rounded-lg border-t border-zinc-100 dark:border-zinc-800">
                        {!! $order->notes !!}
                    </div>
                </div>
            @endif

            {{-- Items Table - Ultra Compact --}}
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                <table class="w-full text-xs table-mobile-items">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="px-3 py-1.5 font-semibold text-left">Barang</th>
                            <th class="px-2 py-1.5 font-semibold text-right w-12">Qty</th>
                            <th class="px-2 py-1.5 font-semibold text-right w-14">Terima</th>
                            <th class="px-2 py-1.5 font-semibold text-right w-24">Harga</th>
                            <th class="px-3 py-1.5 font-semibold text-right w-28">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($order->items as $item)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-3 py-2">
                                    <div class="flex items-start gap-3">
                                        @php
                                            $itemImage = null;
                                            if (!empty($item->custom_attachments) && is_array($item->custom_attachments) && count($item->custom_attachments) > 0) {
                                                $itemImage = $item->custom_attachments[0]['path'] ?? ($item->custom_attachments[0] ?? null);
                                            }
                                            if (!$itemImage && $item->item) {
                                                $itemImage = $item->item->image;
                                            }
                                        @endphp
                                        @if($itemImage && is_string($itemImage))
                                            <div class="shrink-0 w-10 h-10 rounded overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-zinc-100 cursor-zoom-in hover:opacity-80 transition-opacity mt-0.5"
                                                 @click.stop="$dispatch('open-lightbox', { url: '{{ Storage::url($itemImage) }}' })"
                                                 title="Lihat Gambar">
                                                <img src="{{ Storage::url($itemImage) }}" class="w-full h-full object-cover">
                                            </div>
                                        @else
                                            <div class="shrink-0 w-10 h-10 rounded border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 mt-0.5">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                        <div class="flex-1">
                                            <div class="mb-1">
                                                <span class="inline-block px-1.5 py-0.5 text-[9px] font-mono font-bold bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded border border-zinc-300 dark:border-zinc-600 leading-none shadow-sm">
                                                    {{ $item->item->code }}
                                                </span>
                                            </div>
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100 leading-snug flex items-center gap-1.5 flex-wrap">
                                                @if($item->item->alias)
                                                    <span class="font-bold">{{ $item->item->alias }}</span> <span class="text-xs text-zinc-500 normal-case font-normal">- {{ $item->item->name }}</span>
                                                @else
                                                    {{ $item->item->name }}
                                                @endif
                                                @if(!empty($item->custom_attributes) || !empty($item->custom_attachments) || str_contains(strtoupper($item->notes ?? ''), '[CUSTOM]'))
                                                    <span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-500 border border-amber-200 dark:border-amber-800">CUSTOM</span>
                                                @endif
                                            </div>
                                            @if(!empty($item->custom_attributes))
                                                <div class="mt-1">
                                                    @foreach($item->custom_attributes as $attr)
                                                        <div class="text-[10px] text-amber-600 dark:text-amber-500 leading-tight">
                                                            <span class="font-medium">{{ $attr['key'] ?? '-' }}:</span> {{ $attr['value'] ?? '-' }}
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if($item->notes)
                                                <div x-data="{ open: false }">
                                                    <div @click="open = !open"
                                                         class="mt-1 text-[10px] text-zinc-500 italic cursor-pointer hover:text-blue-500 transition-colors flex items-start gap-0.5 group">
                                                         <span x-show="!open" class="line-clamp-1">{{ strip_tags($item->notes) }}</span>
                                                         <span x-show="open" x-cloak class="text-zinc-400">▲ tutup</span>
                                                         <svg x-show="!open" class="w-2.5 h-2.5 shrink-0 mt-px text-zinc-400 group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                    </div>
                                                    <div x-show="open" x-cloak
                                                         x-transition:enter="transition ease-out duration-150"
                                                         x-transition:enter-start="opacity-0"
                                                         x-transition:enter-end="opacity-100"
                                                         class="mt-1 p-1.5 bg-zinc-50 dark:bg-zinc-800/50 rounded border border-zinc-200 dark:border-zinc-700 text-[10px] text-zinc-600 dark:text-zinc-400 prose prose-xs max-w-none prose-p:my-0.5 prose-img:rounded">
                                                        {!! $item->notes !!}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 py-2 text-right text-zinc-700 dark:text-zinc-300" data-label="Qty">
                                    <span>{{ number_format($item->quantity, 0, ',', '.') }} <span class="text-[10px] text-zinc-500 font-normal">{{ $item->item->unit->name ?? 'pcs' }}</span></span>
                                </td>
                                <td class="px-2 py-2 text-right" data-label="Terima">
                                    @if($item->received_quantity >= $item->quantity)
                                        <span class="text-emerald-600 font-semibold inline-flex items-baseline gap-1">{{ number_format($item->received_quantity, 0, ',', '.') }} <span class="text-[10px] font-normal opacity-70">{{ $item->item->unit->name ?? 'pcs' }}</span></span>
                                    @elseif($item->received_quantity > 0)
                                        <span class="text-amber-600 font-semibold inline-flex items-baseline gap-1">{{ number_format($item->received_quantity, 0, ',', '.') }} <span class="text-[10px] font-normal opacity-70">{{ $item->item->unit->name ?? 'pcs' }}</span></span>
                                    @else
                                        <span class="text-zinc-400 inline-flex items-baseline gap-1">0 <span class="text-[10px] font-normal opacity-70">{{ $item->item->unit->name ?? 'pcs' }}</span></span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right text-zinc-600 dark:text-zinc-400" data-label="Harga">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-zinc-800 dark:text-zinc-200" data-label="Subtotal">{{ number_format($item->quantity * $item->unit_price, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <td colspan="4" class="px-3 py-2 text-right text-xs font-bold text-zinc-600 dark:text-zinc-400">Total</td>
                            <td class="px-3 py-2 text-right font-black text-emerald-600 dark:text-emerald-400">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Footer actions --}}
            <div x-data="{
                clicks: 0,
                timer: null,
                showAdvanced: false,
                registerClick() {
                    this.clicks++;
                    clearTimeout(this.timer);
                    if (this.clicks >= 3) {
                        this.showAdvanced = !this.showAdvanced;
                        this.clicks = 0;
                    } else {
                        this.timer = setTimeout(() => { this.clicks = 0; }, 700);
                    }
                }
            }" class="flex items-center justify-between gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                {{-- Sisi Kiri: Trigger Titik Tiga & Tombol Batalkan (Void) --}}
                <div class="flex items-center gap-2">
                    @if(auth()->user()->hasAnyRole(['Super Admin', 'Manager']))
                        <button type="button" 
                                @click="registerClick()" 
                                class="p-1.5 text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 rounded-md transition select-none opacity-40 hover:opacity-100" 
                                title="Menu Lanjutan">
                            <flux:icon.ellipsis-horizontal class="w-4 h-4" />
                        </button>

                        @if(in_array($order->status, ['draft', 'pending_approval', 'processing']))
                            <div x-show="showAdvanced" x-cloak x-transition.opacity.duration.200ms>
                                <flux:button size="sm" variant="danger" icon="x-mark" wire:click="$set('showVoidModal', true)">
                                    Batalkan (Void)
                                </flux:button>
                            </div>
                        @endif
                    @endif

                    @if($order->status === 'draft')
                        @can('purchase.order.update')
                            <flux:button size="sm" variant="ghost" icon="archive-box" class="text-zinc-500 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="archiveDraft" wire:confirm="Anda yakin ingin mengarsipkan draft ini?">Arsipkan Draft</flux:button>
                        @endcan
                    @endif
                </div>

                {{-- Sisi Kanan: Aksi Utama & Gabungkan PO --}}
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    @if(auth()->user()->hasAnyRole(['Super Admin', 'Manager']) && $order->status !== 'merged')
                        @php $mergeCandidates = $this->getMergeCandidates(); @endphp
                        @if($mergeCandidates->isNotEmpty())
                            <div x-show="showAdvanced" x-cloak x-transition.opacity.duration.200ms>
                                <flux:button size="sm" variant="subtle" icon="arrow-path-rounded-square" wire:click="openMergeModal" class="border-indigo-200 text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-300 dark:hover:bg-indigo-950/40">
                                    Gabungkan PO ({{ $mergeCandidates->count() }} lainnya)
                                </flux:button>
                            </div>
                        @endif
                    @endif

                    @if($order->status === 'draft')
                        @can('purchase.order.update')
                            <flux:button size="sm" variant="primary" icon="arrows-right-left" href="{{ route('purchase.orders.edit', ['id' => $order->id]) }}" wire:navigate>Konversi ke PO</flux:button>
                        @endcan
                    @endif
                    @if(in_array($order->status, ['processing', 'partially_received']))
                        @can('purchase.order.update')
                            <flux:button size="sm" variant="primary" icon="cube" class="flex-1 sm:flex-none justify-center" wire:click="$dispatch('open-receipt-modal', { orderId: {{ $order->id }} }); $set('show', false)">
                                Terima Barang
                            </flux:button>
                        @endcan
                    @endif
                    @if($order->status === 'completed')
                        @can('purchase.order.update')
                            <flux:button size="sm" variant="ghost" icon="archive-box" class="text-zinc-500" wire:click="confirmArchive({{ $order->id }}); $set('show', false)">
                                Arsipkan
                            </flux:button>
                        @endcan
                    @endif
                    <flux:button size="sm" variant="ghost" wire:click="$set('show', false)">Tutup Modal</flux:button>
                </div>
            </div>
        @else
            <div class="p-8 text-center text-zinc-500">Memuat data...</div>
        @endif
    </x-modal>

    <!-- Modal Konfirmasi Void -->
    <x-modal wire:model="showVoidModal" maxWidth="md">
        @if($order)
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4 text-red-600">
                    <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-full">
                        <flux:icon.exclamation-triangle class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-bold">Batalkan Pesanan (Void)</h3>
                </div>
                
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                    Anda yakin ingin membatalkan pesanan <strong>{{ $order->po_number }}</strong>? Tindakan ini tidak dapat dibatalkan.
                </p>

                @php
                    $totalPaid = $order->payments()->where('status', 'verified')->sum('amount');
                @endphp

                @if($totalPaid > 0)
                    <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg mb-4">
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-500 mb-2">
                            ⚠️ Pesanan ini sudah memiliki pembayaran/DP sebesar <strong>Rp {{ number_format($totalPaid, 0, ',', '.') }}</strong>.
                        </p>
                        <flux:radio.group wire:model="voidRefundType" label="Penanganan Dana Masuk:" class="mt-2">
                            <flux:radio value="vendor_credit" label="Simpan sebagai Deposit Vendor (Vendor Credit)" description="Saldo piutang di Vendor ini bertambah." />
                            <flux:radio value="refund_cash" label="Refund Tunai (Kas)" description="Uang dikembalikan ke rekening perusahaan oleh Vendor." />
                        </flux:radio.group>
                    </div>
                @endif

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button variant="ghost" wire:click="$set('showVoidModal', false)">Batal</flux:button>
                    <flux:button variant="danger" wire:click="voidOrder">Ya, Void Pesanan</flux:button>
                </div>
            </div>
        @endif
    </x-modal>

    <!-- Modal Pilih Pesanan untuk Digabungkan (PO) -->
    <x-modal wire:model="showMergeModal" maxWidth="md">
        @if($order)
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-xl">
                        <flux:icon.arrow-path-rounded-square class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Gabungkan ke {{ $order->po_number }}</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Pilih pesanan dari vendor {{ $order->vendor?->name }} untuk digabungkan ke PO ini.</p>
                    </div>
                </div>

                @php $candidates = $this->getMergeCandidates(); @endphp
                <div class="space-y-2 mb-4 max-h-64 overflow-y-auto pr-1">
                    @forelse($candidates as $cand)
                        <label class="flex items-center justify-between p-3 rounded-lg border cursor-pointer transition {{ in_array($cand->id, $mergeCandidateIds) ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 dark:border-indigo-600' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}">
                            <div class="flex items-center gap-3">
                                <input type="checkbox"
                                       wire:click="toggleMergeCandidate({{ $cand->id }})"
                                       @checked(in_array($cand->id, $mergeCandidateIds))
                                       class="w-4 h-4 rounded text-indigo-600 accent-indigo-600 border-zinc-300 dark:border-zinc-600 focus:ring-indigo-500 cursor-pointer" />
                                <div>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $cand->po_number }}</span>
                                        @if($cand->mergedOrders->count() > 0)
                                            <span class="text-[9px] font-black text-indigo-700 dark:text-indigo-300 bg-indigo-100 dark:bg-indigo-950/60 border border-indigo-300 dark:border-indigo-800 px-1.5 py-0.5 rounded">
                                                INDUK (+{{ $cand->mergedOrders->count() }} PO)
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-zinc-500">
                                        {{ \Carbon\Carbon::parse($cand->order_date)->format('d M Y') }} &bull; {{ $cand->items->count() }} item
                                        @if($cand->mergedOrders->count() > 0)
                                            <span class="text-indigo-600 dark:text-indigo-400 font-medium">(membawa {{ $cand->mergedOrders->pluck('po_number')->join(', ') }})</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200">Rp {{ number_format($cand->total_amount, 0, ',', '.') }}</div>
                                <span class="text-[10px] uppercase font-semibold text-zinc-400">{{ $cand->status }}</span>
                            </div>
                        </label>
                    @empty
                        <div class="p-4 text-center text-xs text-zinc-500">Tidak ada PO lain yang tersedia.</div>
                    @endforelse
                </div>

                <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg p-3 mb-5 text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                    <strong>Perhatian:</strong> Semua item dan pembayaran dari pesanan yang dipilih akan dipindahkan ke <strong>{{ $order->po_number }}</strong>. Jika pesanan yang dipilih sudah memiliki pesanan gabungan, seluruhnya akan <strong>otomatis diratakan (flat)</strong> menjadi anak langsung di bawah <strong>{{ $order->po_number }}</strong>.
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showMergeModal', false)">Batal</flux:button>
                    <flux:button variant="primary" icon="arrow-path-rounded-square" wire:click="executeMergeIntoCurrent" :disabled="empty($mergeCandidateIds)">
                        Gabungkan Sekarang
                    </flux:button>
                </div>
            </div>
        @endif
    </x-modal>

    <!-- Modal Konfirmasi Pisahkan Current PO -->
    <x-modal wire:model="showUnmergeCurrentConfirm" maxWidth="sm">
        @if($order)
            @php
                $parentPo = $order->mergedInto;
                $childPostPayments = $parentPo ? \Modules\Purchase\Models\PurchasePayment::where('purchase_order_id', $parentPo->id)
                    ->whereNull('original_po_id')
                    ->where('created_at', '>=', $order->updated_at)
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', '!=', 'rejected');
                    })->get() : collect();
                $isLocked = $childPostPayments->isNotEmpty();
                $lockTotal = $isLocked ? number_format($childPostPayments->sum('amount'), 0, ',', '.') : 0;
            @endphp
            <div class="p-6">
                <h3 class="font-bold text-zinc-900 dark:text-white mb-2">Pisahkan PO Ini?</h3>
                @if($isLocked)
                    <div class="p-3 mb-4 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg text-xs text-red-700 dark:text-red-400 leading-relaxed">
                        <div class="font-bold flex items-center gap-1.5 mb-1 text-red-800 dark:text-red-300">
                            <flux:icon.exclamation-triangle class="w-4 h-4 text-red-600 shrink-0" />
                            Pemisahan Terkunci (Proteksi Keuangan)
                        </div>
                        Pesanan ini memiliki pembayaran gabungan sebesar <strong>Rp {{ $lockTotal }}</strong> yang dicatat pada PO Induk setelah penggabungan. Harap sesuaikan atau batalkan pembayaran tersebut di menu Pembayaran terlebih dahulu sebelum memisahkan pesanan.
                    </div>
                @else
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-5">
                        Pesanan ini akan dipulihkan dari PO Induk ({{ $order->mergedInto?->po_number }}). Item dan pembayaran aslinya akan dikembalikan ke pesanan ini.
                    </p>
                @endif
                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showUnmergeCurrentConfirm', false)">{{ $isLocked ? 'Tutup' : 'Batal' }}</flux:button>
                    @if(!$isLocked)
                        <flux:button variant="danger" icon="scissors" wire:click="executeUnmergeCurrent">
                            Pisahkan
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif
    </x-modal>
</div>
