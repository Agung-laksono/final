<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchasePayment;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceTransaction;

new class extends Component {
    use WithFileUploads;
    public $search = '';
    
    // Modal states
    public $showPaymentModal = false;
    public $selectedPoId = null;
    public $selectedPo = null;
    public $paymentAmount = '';
    public $paymentDate = '';
    public $financeAccountId = '';
    public $paymentNotes = '';
    public $proof = null;
    
    public $columnLimits = [
        'unpaid' => 10,
        'partial' => 10,
        'paid' => 10,
        'hold' => 10,
        'refund' => 10,
    ];

    public function loadMoreColumn($status)
    {
        if (!isset($this->columnLimits[$status])) {
            $this->columnLimits[$status] = 10;
        }
        $this->columnLimits[$status] += 15;
    }
    
    public $columns = [
        'unpaid' => ['title' => 'Belum Dibayar', 'color' => 'slate'],
        'partial' => ['title' => 'Dibayar Sebagian', 'color' => 'amber'],
        'paid' => ['title' => 'Lunas', 'color' => 'emerald'],
        'hold' => ['title' => 'Bermasalah / Ditahan', 'color' => 'red'],
        'refund' => ['title' => 'Menunggu Refund', 'color' => 'rose'],
    ];

    public function with()
    {
        // Jangan eager load 'items' dulu untuk seluruh data, agar hemat memori
        $query = PurchaseOrder::with(['vendor', 'payments'])
            ->whereNotIn('status', ['draft']); // Hanya yang sudah rilis
            
        if ($this->search) {
            $query->where('po_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('vendor', function($q) {
                      $q->where('name', 'like', '%' . $this->search . '%');
                  });
        }
        
        $allOrders = $query->latest()->get();
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isFinance    = $user->hasAnyRole(['Kepala Finance', 'Staf Finance']);
        
        $queryAcc = FinanceAccount::where('is_active', true);
        
        if (!$isSuperAdmin && !$isFinance) {
            if ($user->brand_id) {
                $brand = \App\Models\Brand::with('financeAccounts')->find($user->brand_id);
                if ($brand && $brand->financeAccounts->isNotEmpty()) {
                    $queryAcc->whereIn('id', $brand->financeAccounts->pluck('id'));
                } else {
                    $queryAcc->where('id', -1);
                }
            } else {
                $queryAcc->where('id', -1);
            }
        }
        
        $accounts = $queryAcc->get();

        $grouped = [
            'unpaid' => collect(),
            'partial' => collect(),
            'paid' => collect(),
            'hold' => collect(),
            'refund' => collect(),
        ];
        
        foreach ($allOrders as $po) {
            $paidAmount = $po->payments->where('status', 'verified')->sum('amount');
            $isPaid = $paidAmount >= $po->total_amount && $po->total_amount > 0;
            
            if ($po->status === 'cancelled') {
                if ($paidAmount > 0) {
                    $grouped['refund']->push($po);
                }
            } elseif ($po->status === 'hold') {
                $grouped['hold']->push($po);
            } else {
                if ($isPaid) {
                    $grouped['paid']->push($po);
                } elseif ($paidAmount > 0) {
                    $grouped['partial']->push($po);
                } else {
                    $grouped['unpaid']->push($po);
                }
            }
        }
        
        $finalOrders = [];
        $neededIds = [];
        $counts = [];
        
        foreach ($grouped as $key => $collection) {
            $counts[$key] = $collection->count();
            $limited = $collection->take($this->columnLimits[$key] ?? 10);
            $finalOrders[$key] = $limited;
            $neededIds = array_merge($neededIds, $limited->pluck('id')->toArray());
        }
        
        if (!empty($neededIds)) {
            $itemsEager = PurchaseOrder::with('items')->whereIn('id', $neededIds)->get()->keyBy('id');
            foreach ($finalOrders as $key => $collection) {
                $finalOrders[$key] = $collection->map(function($po) use ($itemsEager) {
                    $po->setRelation('items', $itemsEager[$po->id]->items ?? collect());
                    return $po;
                });
            }
        }

        return [
            'orders' => $finalOrders,
            'counts' => $counts,
            'accounts' => $accounts,
        ];
    }

    public function openPaymentModal($poId)
    {
        $this->resetValidation();
        $po = PurchaseOrder::with('payments')->find($poId);
        
        if ($po) {
            $this->selectedPoId = $po->id;
            $this->selectedPo = $po;
            
            // Hitung sisa tagihan
            $paid = $po->payments->where('status', 'verified')->sum('amount');
            $sisa = $po->total_amount - $paid;
            
            $this->paymentAmount = ''; // Kosongkan agar user isi sendiri sesuai SO
            $this->paymentDate = date('Y-m-d');
            $this->paymentNotes = '';
            $this->financeAccountId = ''; // Harus dipilih manual
            $this->proof = null;
            
            $this->showPaymentModal = true;
        }
    }
    
    public function updatedPaymentAmount($value)
    {
        if (!$this->selectedPo) return;
        
        $paid = $this->selectedPo->payments->where('status', 'verified')->sum('amount');
        $sisa = $this->selectedPo->total_amount - $paid;
        
        $this->validate([
            'paymentAmount' => 'numeric|max:' . max(0, $sisa)
        ], [
            'paymentAmount.max' => 'Nominal tidak boleh melebihi sisa tagihan (Maks: Rp ' . number_format(max(0, $sisa), 0, ',', '.') . ').'
        ]);
    }

    public function processPayment()
    {
        $paid = $this->selectedPo->payments->where('status', 'verified')->sum('amount');
        $sisa = $this->selectedPo->total_amount - $paid;

        $this->validate([
            'paymentAmount' => 'required|numeric|min:1|max:' . max(0, $sisa),
            'paymentDate' => 'required|date',
            'financeAccountId' => [
                'required',
                'exists:finance_accounts,id',
                function ($attribute, $value, $fail) {
                    $account = FinanceAccount::find($value);
                    if ($account && $account->current_balance < $this->paymentAmount) {
                        $fail('Saldo rekening (' . $account->name . ') tidak mencukupi.');
                    }
                }
            ],
            'paymentNotes' => 'nullable|string',
        ], [
            'paymentAmount.max' => 'Nominal tidak boleh melebihi sisa tagihan (Maks: Rp ' . number_format(max(0, $sisa), 0, ',', '.') . ').'
        ]);
        
        if ($this->proof && !is_string($this->proof)) {
            $this->validate(['proof' => 'image|max:2048']);
        }

        DB::beginTransaction();
        try {
            $proofPath = null;
            if ($this->proof) {
                if (is_string($this->proof) && str_starts_with($this->proof, 'data:image')) {
                    list($type, $data) = explode(';', $this->proof);
                    list(, $data)      = explode(',', $data);
                    $data = base64_decode($data);
                    
                    $filename = 'purchase_payments/' . uniqid() . '.webp';
                    Storage::disk('public')->put($filename, $data);
                    $proofPath = $filename;
                } else {
                    $proofPath = $this->proof->store('purchase_payments', 'public');
                }
            }

            $payment = PurchasePayment::create([
                'purchase_order_id' => $this->selectedPoId,
                'amount' => $this->paymentAmount,
                'payment_date' => $this->paymentDate,
                'notes' => $this->paymentNotes,
                'finance_account_id' => $this->financeAccountId,
                'proof_path' => $proofPath,
                'created_by' => auth()->id(),
                'status' => 'verified', // Langsung verified
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);

            $financeService = app(\Modules\Finance\Services\FinanceService::class);
            $financeService->recordTransaction(
                accountId: $this->financeAccountId,
                type: 'expense',
                amount: $this->paymentAmount,
                date: $this->paymentDate,
                description: 'Pembayaran PO: ' . $this->selectedPo->po_number . ' - ' . $this->paymentNotes,
                reference: $payment,
                categoryId: null,
                createdBy: auth()->id()
            );

            DB::commit();
            
            $this->selectedPo = PurchaseOrder::with('payments')->find($this->selectedPoId); // reload PO
            $this->paymentAmount = '';
            $this->proof = null;
            
            // Tetap di modal, ganti tab lewat event browser jika diperlukan, atau tidak perlu (opsional)
            $this->dispatch('payment-saved');
            \Flux::toast('Pembayaran berhasil diproses dan dicatat ke buku besar!', variant: 'success');
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Flux::toast('Terjadi kesalahan: ' . $e->getMessage(), variant: 'danger');
        }
    }
    
    public function toggleHold($poId)
    {
        $po = PurchaseOrder::find($poId);
        if ($po) {
            if ($po->status === 'hold') {
                $po->status = 'processing'; // Or back to whatever, but processing is safe
                \Flux::toast('Status tahan (Hold) dicabut.', variant: 'success');
            } else {
                $po->status = 'hold';
                \Flux::toast('Purchase Order ditandai Bermasalah (Hold).', variant: 'warning');
            }
            $po->save();
        }
    }
};
?>

<div>
<x-kanban.board componentId="finance-payables" searchModel="search" searchPlaceholder="Cari No PO atau Vendor...">
    @foreach($columns as $colKey => $column)
        @php
            $defaultCollapsed = false;
        @endphp
        <x-kanban.column 
            :statusKey="$colKey" 
            :column="$column" 
            :componentId="'payables'" 
            :count="$counts[$colKey] ?? 0"
            :defaultCollapsed="$defaultCollapsed"
        >
                        @forelse($orders[$colKey] as $po)
                            @php
                                $paidAmount = $po->payments->where('status', 'verified')->sum('amount');
                                $balance = $po->total_amount - $paidAmount;
                                $progress = $po->total_amount > 0 ? min(100, round(($paidAmount / $po->total_amount) * 100)) : 0;
                                
                                $totalQty = $po->items->sum('quantity');
                                $receivedQty = $po->items->sum('received_quantity');
                                $receivePercent = $totalQty > 0 ? min(100, round(($receivedQty / $totalQty) * 100)) : 0;
                                
                                $deadlineStr = '';
                                $deadlineColor = 'text-zinc-500';
                                if ($po->expected_delivery_date) {
                                    $expected = \Carbon\Carbon::parse($po->expected_delivery_date)->startOfDay();
                                    $today = \Carbon\Carbon::now()->startOfDay();
                                    $diff = $today->diffInDays($expected, false);
                                    
                                    if ($progress == 100) {
                                        $deadlineStr = 'Selesai';
                                        $deadlineColor = 'text-emerald-500';
                                    } else {
                                        if ($diff < 0) {
                                            $deadlineStr = 'Telat ' . abs(intval($diff)) . ' Hari';
                                            $deadlineColor = 'text-red-600 font-bold';
                                        } elseif ($diff == 0) {
                                            $deadlineStr = 'Hari Ini';
                                            $deadlineColor = 'text-amber-600 font-bold';
                                        } else {
                                            $deadlineStr = 'Sisa ' . intval($diff) . ' Hari';
                                        }
                                    }
                                }
                            @endphp
                            
                            <div x-data="{ showFooter: false }"
                                 @click="
                                     if (window.matchMedia('(hover: hover)').matches) {
                                         $wire.openPaymentModal({{ $po->id }})
                                     } else {
                                         if (!showFooter) showFooter = true;
                                         else $wire.openPaymentModal({{ $po->id }})
                                     }
                                 "
                                 @click.outside="showFooter = false"
                                 class="bg-white dark:bg-zinc-800 p-2 rounded-lg shadow-sm border-l-4 border-l-emerald-500 border-y border-r border-zinc-200 dark:border-zinc-700 hover:shadow-lg hover:-translate-y-1 hover:border-r-emerald-300 dark:hover:border-r-emerald-500/50 active:scale-[0.98] transition-all duration-200 cursor-pointer group relative flex flex-col gap-1" wire:key="po-{{ $po->id }}">
                                
                                {{-- Row 1: PO, Vendor & Dates --}}
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] sm:text-[11px] font-bold font-mono text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded shrink-0">{{ $po->po_number }}</span>
                                        <span class="text-[10px] font-bold text-zinc-800 dark:text-zinc-200 truncate max-w-[80px] sm:max-w-[100px]" title="{{ $po->vendor?->name }}">{{ $po->vendor?->name ?? 'Vendor Terhapus' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <div class="flex items-center gap-1.5 text-[9px] font-medium text-zinc-500">
                                            <span title="Dibuat: {{ $po->created_at->format('d M Y') }}">{{ $po->created_at->format('d M') }}</span>
                                            @if($deadlineStr)
                                                <span class="{{ $deadlineColor }} flex items-center gap-0.5"><flux:icon.clock class="w-2.5 h-2.5" /> {{ $deadlineStr }}</span>
                                            @endif
                                        </div>
                                        <flux:dropdown>
                                            <button @click.stop class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 ml-1"><flux:icon.ellipsis-vertical class="w-3.5 h-3.5" /></button>
                                            <flux:menu>
                                                <flux:menu.item icon="exclamation-triangle" wire:click="toggleHold({{ $po->id }})">
                                                    {{ $po->status === 'hold' ? 'Cabut Status Hold' : 'Tandai Bermasalah' }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </div>
                                
                                {{-- Row 2: Progress Stats & Balance --}}
                                <div class="flex justify-between items-center bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-100 dark:border-zinc-700/50 rounded px-2 py-1 mt-0.5">
                                    <div class="flex flex-row items-center gap-2 shrink-0">
                                        <span class="{{ $receivePercent == 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-500' }} flex items-center gap-1 text-[9px] font-semibold" title="Barang Diterima">
                                            <flux:icon.archive-box class="w-3 h-3" /> {{ $receivePercent }}%
                                        </span>
                                        <span class="{{ $progress == 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-blue-600 dark:text-blue-400' }} flex items-center gap-1 text-[9px] font-semibold" title="Sudah Dibayar">
                                            <flux:icon.banknotes class="w-3 h-3" /> {{ $progress }}%
                                        </span>
                                    </div>
                                    <div class="flex flex-row items-center gap-2 shrink-0">
                                        @if($paidAmount > 0)
                                            <span class="font-black text-[10px] sm:text-[11px] text-emerald-700 dark:text-emerald-400" title="Total Tagihan">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</span>
                                        @endif
                                        
                                        @if($balance > 0)
                                            <span class="font-bold text-rose-600 dark:text-rose-400 {{ $paidAmount > 0 ? 'text-[9px]' : 'text-[11px] sm:text-xs' }}" title="Sisa Hutang">-Rp {{ number_format($balance, 0, ',', '.') }}</span>
                                        @else
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400 text-[11px] sm:text-xs">LUNAS</span>
                                        @endif
                                    </div>
                                </div>
                                
                                {{-- Row 3: Action --}}
                                <div class="flex justify-end max-h-0 opacity-0 group-hover:max-h-12 group-hover:opacity-100 group-hover:pt-1 transition-all duration-300 ease-in-out overflow-hidden" :class="showFooter ? '!max-h-12 !opacity-100 !pt-1' : ''">
                                    @if($colKey === 'refund')
                                        <span class="text-[8px] font-black text-white bg-rose-500 px-1.5 py-0.5 rounded shadow-sm">REFUND!</span>
                                    @elseif($colKey === 'hold')
                                        <span class="text-[8px] font-black text-white bg-red-500 px-1.5 py-0.5 rounded shadow-sm">DITAHAN</span>
                                    @elseif($progress < 100)
                                        <button class="bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-bold px-2.5 py-1 rounded shadow-sm flex items-center gap-1 transition-colors" @click.stop="$wire.openPaymentModal({{ $po->id }})">
                                            <span>Bayar</span>
                                            <flux:icon.chevron-right class="w-2.5 h-2.5 stroke-[3]" />
                                        </button>
                                    @endif
                                </div>
                                
                            </div>
                        @empty
                            <div class="h-24 flex flex-col items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-700/50 rounded-xl text-zinc-400 dark:text-zinc-500">
                                <span class="text-sm font-medium">Kosong</span>
                            </div>
                        @endforelse
                        
                        @if(($counts[$colKey] ?? 0) > ($columnLimits[$colKey] ?? 10))
                            <x-kanban.load-more 
                                :statusKey="$colKey" 
                            />
                        @endif
        </x-kanban.column>
        @endforeach
</x-kanban.board>

{{-- Payment Modal --}}
<flux:modal wire:model="showPaymentModal" class="w-full sm:w-[95%] md:w-[32rem] md:max-w-xl !p-0">
    @if($selectedPo)
    @php
        $terbayar = $selectedPo->payments()->whereIn('status', ['verified', 'pending'])->sum('amount');
        $sisa = $selectedPo->total_amount - $terbayar;
    @endphp
    <div class="p-4 sm:p-6" x-data="{ showPreviewModal: false, previewImage: '', tab: 'history' }" x-effect="if ($wire.showPaymentModal) { tab = 'history' }" @payment-saved.window="tab = 'history'">
        <div class="flex items-start gap-3 sm:gap-4">
            <div class="flex-shrink-0 w-8 h-8 sm:w-10 sm:h-10 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center">
                <flux:icon.banknotes class="w-4 h-4 sm:w-5 sm:h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg" class="text-base sm:text-lg leading-tight sm:leading-normal">Pembayaran PO <strong>{{ $selectedPo->po_number }}</strong></flux:heading>
                <div class="mt-1.5 sm:mt-2 flex flex-wrap items-center gap-3 sm:gap-4">
                    <flux:subheading class="!mt-0 text-xs sm:text-sm">
                        Tagihan: <strong>Rp {{ number_format($selectedPo->total_amount, 0, ',', '.') }}</strong>
                    </flux:subheading>
                    
                    @if($selectedPo->vendor)
                    <div class="flex items-center gap-2 sm:gap-2.5">
                        @if($selectedPo->vendor->image)
                            <button type="button" @click="$dispatch('open-lightbox', { url: '{{ Storage::url($selectedPo->vendor->image) }}' })" class="shrink-0 hover:opacity-80 transition-opacity focus:outline-none" title="Lihat Foto Profil">
                                <img src="{{ Storage::url($selectedPo->vendor->image) }}" alt="{{ $selectedPo->vendor->name }}" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full object-cover border border-zinc-200 dark:border-zinc-700 shadow-sm" />
                            </button>
                        @else
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-[10px] sm:text-xs font-bold text-emerald-700 dark:text-emerald-400 shrink-0 uppercase shadow-sm border border-emerald-200 dark:border-emerald-800/50">
                                {{ substr($selectedPo->vendor->name, 0, 2) }}
                            </div>
                        @endif
                        <div class="flex flex-col leading-tight">
                            <span class="text-xs sm:text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ $selectedPo->vendor->name }}</span>
                            @if($selectedPo->vendor->phone)
                                <span class="text-[10px] sm:text-xs text-zinc-500">{{ $selectedPo->vendor->phone }}</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-4 sm:mt-6" x-cloak>
            <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700 pb-px mb-4 sm:mb-6 overflow-x-auto no-scrollbar">
                <button type="button" @click="tab = 'history'" :class="tab === 'history' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="whitespace-nowrap px-2 sm:px-4 py-2 border-b-2 font-bold text-xs sm:text-sm transition-colors flex items-center gap-1.5 sm:gap-2">
                    <flux:icon.clock class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                    <span class="sm:hidden">Riwayat</span>
                    <span class="hidden sm:inline">Riwayat Pembayaran</span>
                </button>
                @if($sisa > 0)
                <button type="button" @click="tab = 'form'" :class="tab === 'form' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="whitespace-nowrap px-2 sm:px-4 py-2 border-b-2 font-bold text-xs sm:text-sm transition-colors flex items-center gap-1.5 sm:gap-2">
                    <flux:icon.plus-circle class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                    <span class="sm:hidden">Catat</span>
                    <span class="hidden sm:inline">Catat Pembayaran</span>
                </button>
                @endif
            </div>

            @if($sisa > 0)
                {{-- TAB: FORM INPUT --}}
                <div x-show="tab === 'form'" style="display: none;" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-4">
                    <!-- Baris 1: Nominal dan Rekening -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <div class="flex justify-between items-end mb-1 sm:mb-2">
                                <flux:label class="!mb-0 text-sm">Nominal Bayar <span class="text-red-500">*</span></flux:label>
                                <span class="text-[10px] font-bold text-rose-500">-{{ number_format($sisa, 0, ',', '.') }}</span>
                            </div>
                            <x-rupiah-input wire:model.live.debounce.300ms="paymentAmount" placeholder="Contoh: 5.000.000" required />
                            <flux:error name="paymentAmount" />
                        </div>
                        <div>
                            <flux:select wire:model.live="financeAccountId" label="Rekening Sumber (Pencairan)" placeholder="Pilih rekening kas/bank..." required>
                                @foreach($accounts as $acc)
                                    @php
                                        $amountToPay = !empty($paymentAmount) ? (float) $paymentAmount : $sisa;
                                        $isDisabled = $acc->current_balance < $amountToPay;
                                    @endphp
                                    <option value="{{ $acc->id }}" {{ $isDisabled ? 'disabled' : '' }} class="{{ $isDisabled ? 'text-zinc-400' : '' }}">
                                        {{ $acc->name }} • Rp {{ number_format($acc->current_balance, 0, ',', '.') }}{{ $isDisabled ? ' (Tidak Cukup)' : '' }}
                                    </option>
                                @endforeach
                            </flux:select>
                            <flux:error name="financeAccountId" />
                        </div>
                    </div>
                    
                    <!-- Baris 2: Tanggal/Catatan dan Gambar -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-4">
                            <flux:input type="date" wire:model="paymentDate" label="Tanggal" required />
                            <div>
                                <flux:textarea wire:model="paymentNotes" label="Catatan Jurnal / Referensi" placeholder="Keterangan tambahan..." />
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <button type="button" wire:click="$set('paymentNotes', 'Pelunasan tagihan {{ $selectedPo->po_number }}')" class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:hover:bg-emerald-800/50 text-emerald-700 dark:text-emerald-400 text-[10px] rounded transition-colors border border-emerald-200 dark:border-emerald-800/50">Pelunasan tagihan {{ $selectedPo->po_number }}</button>
                                    <button type="button" wire:click="$set('paymentNotes', 'DP tagihan {{ $selectedPo->po_number }}')" class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:hover:bg-emerald-800/50 text-emerald-700 dark:text-emerald-400 text-[10px] rounded transition-colors border border-emerald-200 dark:border-emerald-800/50">DP tagihan {{ $selectedPo->po_number }}</button>
                                    <button type="button" wire:click="$set('paymentNotes', 'DP tambahan {{ $selectedPo->po_number }}')" class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:hover:bg-emerald-800/50 text-emerald-700 dark:text-emerald-400 text-[10px] rounded transition-colors border border-emerald-200 dark:border-emerald-800/50">DP tambahan {{ $selectedPo->po_number }}</button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <flux:label class="mb-1 sm:mb-2 text-sm">Bukti Transfer / Kasbon <span class="text-red-500">*</span></flux:label>
                            <x-image-cropper id="payment-cropper" wire:model="proof" :image="$proof && is_string($proof) && !str_starts_with($proof, 'data:image') ? $proof : null" accept="image/*" />
                            <flux:error name="proof" />
                        </div>
                    </div>
                    <flux:button wire:click="processPayment" wire:target="processPayment" wire:loading.attr="disabled" icon="arrow-up-tray" class="w-full mt-2 !bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600">Proses & Catat Jurnal</flux:button>
                </div>
            @endif

            {{-- TAB: RIWAYAT PEMBAYARAN --}}
            <div x-show="tab === 'history'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="space-y-2 sm:space-y-3 max-h-[60vh] sm:max-h-[400px] overflow-y-auto custom-scrollbar pr-1 sm:pr-2">
                    @forelse($selectedPo->payments()->latest()->get() as $payment)
                        <div class="bg-white dark:bg-zinc-900 p-2.5 sm:p-3 rounded-lg border border-zinc-100 dark:border-zinc-800 text-xs sm:text-sm flex gap-2 sm:gap-3 relative overflow-hidden">
                            <div class="absolute top-0 right-0 bg-emerald-500 text-white text-[8px] sm:text-[9px] px-1.5 sm:px-2 py-0.5 rounded-bl-lg font-bold tracking-wide">TERCATAT</div>

                            <div class="flex-1 mt-2.5 sm:mt-3">
                                <div class="flex justify-between items-start mb-0.5 sm:mb-1">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100 text-sm sm:text-base">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                                    <span class="text-[9px] sm:text-[10px] text-zinc-400">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</span>
                                </div>
                                <div class="flex flex-wrap gap-1 sm:gap-2 text-[10px] sm:text-xs text-zinc-500">
                                    <span class="uppercase font-medium text-emerald-600 dark:text-emerald-400">VIA {{ $payment->financeAccount->name ?? 'KAS' }}</span>
                                    <span class="hidden sm:inline">•</span>
                                    <span class="truncate w-full sm:w-auto">{{ $payment->notes ?: 'Tanpa catatan' }}</span>
                                </div>
                            </div>
                            @if($payment->proof_path)
                                <button type="button" @click="$dispatch('open-lightbox', { url: '{{ Storage::url($payment->proof_path) }}' })" class="shrink-0 w-12 h-12 sm:w-16 sm:h-16 mt-2 sm:mt-2 bg-zinc-100 dark:bg-zinc-800 rounded-md overflow-hidden hover:opacity-80 transition-opacity border border-zinc-200 dark:border-zinc-700 focus:outline-none" title="Lihat Bukti">
                                    <img src="{{ Storage::url($payment->proof_path) }}" class="w-full h-full object-cover" />
                                </button>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-zinc-500 text-xs py-4 italic">Belum ada pembayaran</div>
                    @endforelse
                </div>
                
                @php
                    $terbayarHistory = $selectedPo->payments()->where('status', 'verified')->sum('amount');
                    $sisaHistory = $selectedPo->total_amount - $terbayarHistory;
                @endphp
                <div class="mt-3 sm:mt-4 pt-2 sm:pt-3 border-t border-zinc-200 dark:border-zinc-700 text-xs sm:text-sm">
                    <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                        <span>Telah Dibayar:</span>
                        <span class="font-medium text-emerald-600 dark:text-emerald-400">Rp {{ number_format($terbayarHistory, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between mt-0.5 sm:mt-1 font-bold">
                        <span class="text-zinc-800 dark:text-zinc-200">Sisa Hutang:</span>
                        <span class="{{ $sisaHistory <= 0 ? 'text-zinc-400' : 'text-red-600 dark:text-red-400' }}">Rp {{ number_format(max(0, $sisaHistory), 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
    @endif
</flux:modal>
</div>
