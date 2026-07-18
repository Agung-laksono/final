<?php
use function Livewire\Volt\{state, layout, title, computed, on, mount};
use Modules\Sales\Models\SalesOrder;
use Illuminate\Support\Facades\Cache;
use App\Models\Setting;

layout('layouts.app');
title('Kanban Sales Order');

// Definisi Kolom Kanban untuk Sales Order
state([
    'columns' => function () {
        // Jika user HANYA memiliki role Gudang (bukan Admin/Manager/Sales)
        if (auth()->user()->hasRole('Gudang') && !auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Sales'])) {
            return [
                'processing' => ['title' => 'Diproses Gudang', 'color' => 'blue'],
                'packing' => ['title' => 'Packing', 'color' => 'purple'],
            ];
        }
        
        // Tampilan default untuk peran lain
        return [
            'pending_approval' => ['title' => 'Menunggu Persetujuan', 'color' => 'amber'],
            'processing' => ['title' => 'Diproses Gudang', 'color' => 'blue'],
            'packing' => ['title' => 'Packing', 'color' => 'purple'],
            'shipping' => ['title' => 'Pengiriman', 'color' => 'orange'],
            'arrived' => ['title' => 'Sampai', 'color' => 'teal'],
            'completed' => ['title' => 'Selesai', 'color' => 'emerald'],
            'archived' => ['title' => 'Arsip', 'color' => 'zinc'],
        ];
    },
    'viewMode' => session('sales_view_mode', 'kanban'), // Default view
    'search' => '',
    'sortBy' => 'created_at',
    'sortDirection' => 'desc',
    'perPage' => 10,
    'setting_version' => 0, // bumped saat setting berubah → paksa re-render
    'columnLimits' => [], // State for per-column limits
]);

mount(function () {
    // Inisialisasi limit per kolom
    $limits = [];
    foreach ($this->columns as $key => $col) {
        $limits[$key] = 10;
    }
    $this->columnLimits = $limits;

    if ($detailId = request()->query('show_detail')) {
        $this->dispatch('open-detail-modal', orderId: $detailId);
    }
});

$loadMore = function () {
    $this->perPage += 15;
};

$loadMoreColumn = function ($status) {
    $limits = $this->columnLimits;
    if (!isset($limits[$status])) {
        $limits[$status] = 10;
    }
    $limits[$status] += 15;
    $this->columnLimits = $limits;
};

$markAsShipped = function ($orderId) {
    $order = SalesOrder::find($orderId);
    if (!$order || $order->status !== 'packing') return;
    
    $order->status = 'shipping';
    $order->save();
    
    $labelIds = \Modules\Sales\Models\SalesOrderFulfillment::where('sales_order_id', $order->id)
        ->whereNotNull('item_label_id')
        ->pluck('item_label_id');
        
    if ($labelIds->isNotEmpty()) {
        $labels = \Modules\Inventory\Models\ItemLabel::whereIn('id', $labelIds)->where('status', 'booked')->get();
        foreach($labels as $lbl) {
            $lbl->status = 'sold';
            $lbl->notes = $lbl->notes . "\n[Shipping]: Telah diserahkan ke Ekspedisi.";
            $lbl->save();
        }
    }
    
    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('sales_order');
    \Flux::toast('Pesanan berhasil diserahkan ke kurir!', variant: 'success');
};

$setViewMode = function ($mode) {
    $this->viewMode = $mode;
    session(['sales_view_mode' => $mode]);
};

$sort = function ($field) {
    if ($this->sortBy === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $field;
        $this->sortDirection = 'asc';
    }
};

$getBaseQuery = function () {
    $query = SalesOrder::with(['customer', 'creator', 'items', 'fulfillments', 'payments', 'brand', 'courierVendor'])
        ->select('sales_orders.*');
    
    $user = auth()->user();
    $isSuperAdmin = $user->hasRole('Super Admin');
    $isKepala     = $user->hasAnyRole(['Kepala Sales', 'Manager']);
    $isStafSales  = $user->hasAnyRole(['Staf Sales']) && !$isKepala && !$isSuperAdmin;

    // Super Admin & Kepala Sales: tidak ada filter brand — melihat semua SO
    // Staf Sales biasa: dibatasi ke brand miliknya saja
    if (!$isSuperAdmin && !$isKepala) {
        if ($user->brand_id) {
            $query->where('sales_orders.brand_id', $user->brand_id);
        } else {
            // Tidak punya brand: hanya bisa lihat miliknya sendiri
            if ($isStafSales) {
                $query->where('sales_orders.created_by', $user->id);
            }
        }
    }

    // Staf Sales: di kolom pasif (arrived/completed/archived) hanya lihat miliknya sendiri
    // agar kolom tidak membludak dengan kartu orang lain
    if ($isStafSales) {
        $query->where(function($q) use ($user) {
            $q->where('sales_orders.created_by', $user->id)
              ->orWhereNotIn('sales_orders.status', ['arrived', 'completed', 'archived']);
        });
    }

    if ($this->search) {
        $query->where(function($q) {
            $q->where('sales_orders.so_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('customer', function($q2) {
                  $q2->where('name', 'like', '%' . $this->search . '%');
              });
        });
    }
    
    if ($this->sortBy === 'customer') {
        $query->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.id')
              ->orderBy('customers.name', $this->sortDirection);
    } elseif ($this->sortBy === 'creator') {
        $query->leftJoin('users', 'sales_orders.created_by', '=', 'users.id')
              ->orderBy('users.name', $this->sortDirection);
    } else {
        $query->orderBy('sales_orders.' . $this->sortBy, $this->sortDirection);
    }
    
    return $query;
};

$kanbanOrders = computed(function () {
    if ($this->viewMode !== 'kanban') return collect();
    
    $ids = [];
    foreach ($this->columns as $status => $col) {
        $limit = $this->columnLimits[$status] ?? 10;
        
        $query = clone $this->getBaseQuery();
        $statusIds = $query->where('sales_orders.status', $status)
                           ->limit($limit)
                           ->pluck('sales_orders.id')
                           ->toArray();
                           
        $ids = array_merge($ids, $statusIds);
    }
    
    if (empty($ids)) return collect();
    
    // Tarik data dengan eager loading hanya untuk ID yang dibutuhkan
    $orders = SalesOrder::with(['customer', 'creator', 'items', 'fulfillments', 'payments', 'brand'])
        ->whereIn('id', $ids)
        ->get()
        ->sortBy(function($order) use ($ids) {
            return array_search($order->id, $ids);
        });
        
    return $orders->groupBy(function($so) {
        return $so->status ?? 'pending_approval';
    });
});

$tableOrders = computed(function () {
    return $this->getBaseQuery()->paginate($this->perPage);
});

$exportCsv = function () {
    $orders = $this->getBaseQuery()->get();
    
    $headers = [
        "Content-type"        => "text/csv",
        "Content-Disposition" => "attachment; filename=sales_orders_".date('Ymd_His').".csv",
        "Pragma"              => "no-cache",
        "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
        "Expires"             => "0"
    ];

    $columns = ['No. SO', 'Tanggal', 'Pelanggan', 'Sales', 'Total (Rp)', 'Status Pembayaran', 'Status', 'Total Item'];

    $callback = function() use($orders, $columns) {
        $file = fopen('php://output', 'w');
        fputcsv($file, $columns);

        foreach ($orders as $order) {
            fputcsv($file, [
                $order->so_number,
                $order->order_date,
                $order->customer->name ?? '-',
                $order->creator->name ?? '-',
                $order->total_amount,
                $order->payment_status,
                $order->status,
                $order->items->sum('qty')
            ]);
        }
        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
};

$updateStatus = function ($orderId, $newStatus) {
    // Nanti ditambahkan validasi permission sesuai role untuk setiap status
    abort_unless(auth()->user()->can('sales.order.update'), 403, 'Anda tidak memiliki izin untuk mengubah status SO.');
    
    if (!array_key_exists($newStatus, $this->columns)) return;
    
    $so = SalesOrder::find($orderId);
    if ($so) {
        $so->status = $newStatus;
        $so->save();
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
    }
};

$markAsArchived = function ($orderId) {
    abort_unless(auth()->user()->can('sales.order.update') || auth()->user()->hasRole('Finance'), 403);
    
    $so = SalesOrder::find($orderId);
    if ($so) {
        $so->status = 'archived';
        $so->save();
        
        \Flux::toast('Pesanan berhasil diarsipkan.', variant: 'success');
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
    }
};

// markAsCompleted closure has been moved to completed-modal.blade.php

on([
    'status-updated' => function () {
        // Kosong saja, tujuannya hanya memancing re-render agar computed $orders dijalankan ulang
    },
    'echo:kanban.sales_order,KanbanUpdated' => function () {},
    'echo:settings,SettingUpdated' => function () {
        Cache::forget('setting_gudang_handles_shipping');
        $this->setting_version++;
    },
]);

?>

<div class="w-full bg-transparent relative">
    <div wire:key="view-kanban-wrapper" class="w-full h-full relative {{ $this->viewMode === 'kanban' ? 'flex flex-col' : 'hidden' }}">
        <x-kanban.board 
                componentId="sales-order"
                searchModel="search"
                searchPlaceholder="Cari SO atau nama pelanggan...">
            
            <x-slot:actions>
                <div class="flex border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden shrink-0">
                    <button type="button" wire:key="kanban-sw-kanban" @click="$wire.setViewMode('kanban')" class="p-1 sm:p-1.5 px-2 sm:px-2.5 transition-colors {{ $this->viewMode === 'kanban' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Kanban">
                        <flux:icon.view-columns wire:loading.remove wire:target="setViewMode('kanban')" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('kanban')" class="w-3.5 h-3.5 sm:w-4 sm:h-4 animate-spin" />
                    </button>
                    <button type="button" wire:key="kanban-sw-table" @click="$wire.setViewMode('table')" class="p-1 sm:p-1.5 px-2 sm:px-2.5 transition-colors {{ $this->viewMode === 'table' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Tabel">
                        <flux:icon.table-cells wire:loading.remove wire:target="setViewMode('table')" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('table')" class="w-3.5 h-3.5 sm:w-4 sm:h-4 animate-spin" />
                    </button>
                </div>
                <div class="w-px h-6 bg-zinc-200 dark:bg-zinc-700 mx-1 sm:mx-2 hidden sm:block"></div>

                @can('sales.order.create')
                    <flux:button variant="primary" size="sm" icon="plus" href="{{ route('sales.orders.create') }}" wire:navigate class="px-2 sm:px-4 shrink-0">
                        <span class="hidden sm:inline">Buat Pesanan</span>
                        <span class="sm:hidden text-xs">Buat</span>
                    </flux:button>
                @endcan
            </x-slot:actions>

        @foreach($columns as $statusKey => $column)
            @php
                $defaultCollapsed = in_array($statusKey, ['completed', 'archived']);
            @endphp
            <x-kanban.column 
                :statusKey="$statusKey" 
                :column="$column" 
                :componentId="'sales'" 
                :count="count($this->kanbanOrders->get($statusKey, []))"
                :defaultCollapsed="$defaultCollapsed"
            >
                    
                    {{-- Deskripsi Aktor --}}
                    <p class="text-[10px] text-zinc-400 uppercase tracking-wider font-semibold mb-2">
                        @if($statusKey === 'pending_approval') Kepala Penjualan
                        @elseif($statusKey === 'processing') Tim Gudang (Inventory)
                        @elseif($statusKey === 'packing') Tim Packing
                        @elseif($statusKey === 'shipping') Sales & Ekspedisi
                        @elseif($statusKey === 'completed') Selesai
                        @endif
                    </p>

                    @forelse($this->kanbanOrders[$statusKey] ?? [] as $order)
                        @php
                            $isOwn = $order->created_by === auth()->id();
                            $isCustom = str_contains($order->notes ?? '', '[CUSTOM]');
                            $isManagerial = auth()->user()->hasAnyRole(['Super Admin', 'Kepala Sales', 'Manager', 'Gudang', 'Shipping', 'Finance']);
                            $canInteract = $isOwn || $isManagerial;
                        @endphp
                        <div wire:key="order-{{ $order->id }}"
                             x-data="{ showFooter: false }"
                             @if($canInteract) 
                                 @click="
                                     if (window.matchMedia('(hover: hover)').matches) {
                                         activeId = '{{ $order->id }}'; 
                                         $dispatch('open-detail-modal', { orderId: {{ $order->id }} });
                                     } else {
                                         if (!showFooter) {
                                             showFooter = true;
                                         } else {
                                             activeId = '{{ $order->id }}'; 
                                             $dispatch('open-detail-modal', { orderId: {{ $order->id }} });
                                         }
                                     }
                                 "
                                 @click.outside="showFooter = false"
                             @endif
                             x-show="processingId !== '{{ $order->id }}'"
                             class="bg-white dark:bg-zinc-800 p-2 rounded-lg shadow-sm border transition-all duration-200 active:scale-[0.98] active:shadow-none {{ $isCustom ? 'border-amber-400 dark:border-amber-500 shadow-amber-500/20 hover:-translate-y-0.5 hover:border-amber-500 hover:shadow-amber-500/30' : 'border-zinc-200 dark:border-zinc-700 hover:-translate-y-0.5 hover:shadow-sm hover:border-'.$column['color'].'-400 dark:hover:border-'.$column['color'].'-500' }} group relative cursor-pointer flex flex-col"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-90">
                            
                            {{-- Header Card --}}
                            <div class="flex justify-between items-center relative z-10">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-mono text-[9px] font-bold text-zinc-600 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-700/50 border border-zinc-200 dark:border-zinc-600 px-1 py-px rounded w-max">
                                        {{ $order->so_number }}
                                    </span>
                                    @if($isCustom)
                                        <span class="text-[8px] font-black text-amber-600 bg-amber-100 border border-amber-200 px-1 py-px rounded shadow-sm flex items-center gap-0.5 w-max">
                                            <flux:icon.sparkles class="w-2 h-2" /> CUSTOM
                                        </span>
                                    @endif
                                    @if($statusKey === 'packing' && $order->is_packed)
                                        <span class="text-[8px] font-black text-purple-600 dark:text-purple-300 bg-purple-100 dark:bg-purple-900/30 border border-purple-200 dark:border-purple-800/50 px-1 py-px rounded shadow-sm flex items-center gap-0.5 w-max" title="Menunggu Ekspedisi">
                                            <flux:icon.truck class="w-2 h-2" /> SIAP KIRIM
                                        </span>
                                    @endif
                                    @if($statusKey === 'shipping' && $order->courierVendor)
                                        @php
                                            $waPhone = preg_replace('/[^0-9]/', '', $order->courierVendor->phone);
                                            if (str_starts_with($waPhone, '0')) {
                                                $waPhone = '62' . substr($waPhone, 1);
                                            }
                                            $waLink = '#';
                                            if ($waPhone) {
                                                $waText = urlencode("Halo, terkait pengiriman pesanan {$order->so_number}");
                                                $waLink = "https://wa.me/{$waPhone}?text={$waText}";
                                            }
                                        @endphp
                                        <a href="{{ $waLink }}" target="_blank" class="text-[8px] font-black text-blue-600 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800/50 px-1.5 py-0.5 rounded shadow-sm flex items-center gap-1 w-max hover:bg-blue-200 dark:hover:bg-blue-800/50 transition-colors" title="Hubungi Kurir via WhatsApp" @click.stop>
                                            @if($order->courierVendor->image)
                                                <img src="{{ Storage::url($order->courierVendor->image) }}" class="w-3 h-3 rounded-full object-cover shadow-sm" alt="V" />
                                            @else
                                                <div class="w-3 h-3 rounded-full bg-blue-500 text-white flex items-center justify-center text-[6px] shadow-sm">
                                                    {{ substr($order->courierVendor->name, 0, 1) }}
                                                </div>
                                            @endif
                                            <span class="truncate max-w-[80px]">{{ $order->courierVendor->name }}</span>
                                        </a>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="flex items-center text-zinc-500 dark:text-zinc-400 font-medium text-[8px]" title="Tanggal Order">
                                        <flux:icon.calendar class="w-2 h-2 mr-0.5" />
                                        {{ \Carbon\Carbon::parse($order->order_date)->format('d M') }}
                                    </div>
                                    @if($order->deadline)
                                        @php
                                            $deadlineDate = \Carbon\Carbon::parse($order->deadline)->startOfDay();
                                            $today = \Carbon\Carbon::now()->startOfDay();
                                            $diffDays = (int) $today->diffInDays($deadlineDate, false);
                                            
                                            if ($diffDays < 0) {
                                                $dText = "Terlewat " . abs($diffDays) . " Hari";
                                                $dColor = "text-red-600 dark:text-red-400";
                                            } elseif ($diffDays === 0) {
                                                $dText = "Hari Ini";
                                                $dColor = "text-red-500 dark:text-red-400";
                                            } else {
                                                $dText = "Sisa " . $diffDays . " Hari";
                                                $dColor = $diffDays <= 3 ? "text-amber-500 dark:text-amber-400" : "text-zinc-500 dark:text-zinc-400";
                                            }
                                        @endphp
                                        <div class="flex items-center {{ $dColor }} font-bold text-[8px]" title="Tenggat: {{ $deadlineDate->format('d M Y') }}">
                                            <flux:icon.clock class="w-2 h-2 mr-0.5" />
                                            {{ $dText }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Customer & Amount Grid --}}
                            <div class="grid grid-cols-2 gap-2 items-center mt-1">
                                <div class="flex items-center gap-1.5 overflow-hidden">
                                    <div class="relative shrink-0">
                                        <flux:avatar src="{{ $order->customer->image ? Storage::url($order->customer->image) : '' }}" fallback="{{ substr($order->customer->name, 0, 2) }}" class="!w-6 !h-6 shadow-sm" />
                                    </div>
                                    <div class="flex flex-col overflow-hidden leading-tight gap-0.5">
                                        <span class="font-bold text-[10px] text-zinc-800 dark:text-zinc-100 truncate" title="{{ $order->customer->name }}">
                                            {{ $order->customer->name }}
                                        </span>
                                        <div class="flex items-center gap-1 overflow-hidden">
                                            @php
                                                $cityName = $order->customer->city ?: 'Tanpa Kota';
                                                $shortCity = str_ireplace(['Kabupaten ', 'Kecamatan '], ['Kab. ', 'Kec. '], $cityName);
                                            @endphp
                                            <span class="text-[6px] font-semibold text-blue-600 dark:text-blue-300 bg-blue-100 dark:bg-blue-500/20 px-1 py-px rounded truncate min-w-0" title="{{ $cityName }}">
                                                {{ $shortCity }}
                                            </span>
                                            <span class="text-[9px] font-black text-zinc-900 dark:text-white tracking-tight shrink-0">
                                                Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                @php
                                    $orderedQty = $order->items->sum('qty');
                                    $fulfilledQty = $order->fulfillments->sum('scanned_qty');
                                    $progressPercent = $orderedQty > 0 ? min(100, round(($fulfilledQty / $orderedQty) * 100)) : 0;
                                    
                                    $totalAmount = (float)$order->total_amount;
                                    $totalVerifiedPayment = $order->payments->where('status', 'verified')->sum('amount');
                                    $paymentPercent = $totalAmount > 0 ? min(100, floor(($totalVerifiedPayment / $totalAmount) * 100)) : 0;
                                @endphp
                                
                                {{-- Progress Bars & Amount --}}
                                <div class="flex flex-col gap-1.5 border-l border-zinc-100 dark:border-zinc-800 pl-2">
                                    {{-- Fulfillment --}}
                                    <div>
                                        <div class="flex justify-between items-end mb-0.5">
                                            <span class="text-[7px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider flex items-center gap-0.5">
                                                <flux:icon.cube class="w-1.5 h-1.5" /> PRODUK
                                                <span class="text-[6px] normal-case tracking-normal font-medium text-zinc-400 dark:text-zinc-500 ml-0.5">({{ $order->items->count() }} SKU)</span>
                                            </span>
                                            <span class="text-[7px] font-semibold {{ $progressPercent >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-200' }}">
                                                {{ $fulfilledQty }}/{{ $orderedQty }}
                                            </span>
                                        </div>
                                        <div class="w-full h-1 bg-zinc-100 dark:bg-zinc-700/50 rounded-full overflow-hidden">
                                            <div class="h-full {{ $progressPercent >= 100 ? 'bg-emerald-500' : 'bg-blue-500' }} rounded-full" style="width: {{ $progressPercent }}%"></div>
                                        </div>
                                    </div>
                                    
                                    {{-- Payment --}}
                                    <div>
                                        <div class="flex justify-between items-end mb-0.5">
                                            <span class="text-[7px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider flex items-center gap-0.5">
                                                <flux:icon.banknotes class="w-1.5 h-1.5" /> PEMBAYARAN
                                            </span>
                                            <span class="text-[6px] font-bold {{ $paymentPercent >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }}" title="Terbayar Rp {{ number_format($totalVerifiedPayment, 0, ',', '.') }} dari Total Rp {{ number_format($totalAmount, 0, ',', '.') }}">
                                                Rp {{ number_format($totalVerifiedPayment, 0, ',', '.') }} ({{ $paymentPercent }}%)
                                            </span>
                                        </div>
                                        <div class="w-full h-1 bg-zinc-100 dark:bg-zinc-700/50 rounded-full overflow-hidden mt-0.5">
                                            <div class="h-full {{ $paymentPercent >= 100 ? 'bg-emerald-500' : 'bg-indigo-500' }} rounded-full" style="width: {{ $paymentPercent }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Footer Actions Wrapper (Collapsible) --}}
                            @if($canInteract)
                            <div class="grid transition-all duration-300 grid-rows-[0fr] [@media(hover:hover)]:group-hover:grid-rows-[1fr]"
                                 :class="showFooter ? '!grid-rows-[1fr]' : ''">
                                <div class="overflow-hidden">
                                    {{-- Footer Kartu (Pembuat & Aksi) --}}
                                    <div class="flex items-center justify-between mt-1 pt-1 border-t border-zinc-100 dark:border-zinc-800 transition-opacity duration-300 opacity-0 [@media(hover:hover)]:group-hover:opacity-100"
                                         :class="showFooter ? '!opacity-100' : ''">
                                        @if($isOwn)
                                            <div class="flex items-center gap-1.5" title="Dibuat oleh Anda">
                                                <flux:avatar src="{{ $order->creator->avatar ? Storage::url($order->creator->avatar) : '' }}" fallback="{{ substr($order->creator->name ?? 'A', 0, 2) }}" class="!w-4 !h-4 text-[7px]" />
                                                <div class="flex flex-col">
                                                    <span class="text-[9px] font-bold text-zinc-900 dark:text-zinc-100 truncate max-w-[80px]">Anda</span>
                                                </div>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1.5" title="Dibuat oleh {{ $order->creator->name ?? 'Sistem' }}">
                                                <flux:avatar src="{{ $order->creator->avatar ? Storage::url($order->creator->avatar) : '' }}" fallback="{{ substr($order->creator->name ?? 'S', 0, 2) }}" class="!w-4 !h-4 text-[7px] opacity-75" />
                                                <div class="flex flex-col">
                                                    <span class="text-[9px] font-medium text-zinc-500 truncate max-w-[80px]">
                                                        {{ explode(' ', $order->creator->name ?? 'Sistem')[0] }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endif
                                        
                                        <div class="flex items-center gap-2">
                                            
                                            {{-- Tombol Pembayaran --}}
                                            @if(!in_array($statusKey, ['completed', 'archived']))
                                                @canany(['sales.payment.create', 'sales.payment.validate'])
                                                    <flux:button size="sm" variant="subtle" icon="banknotes" class="!h-6 !w-6 !p-0 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/50" title="Pembayaran" wire:click.stop="$dispatch('open-payment-modal', { orderId: {{ $order->id }} })" />
                                                @endcanany
                                            @endif
                                            
                                            {{-- Tombol Aksi Spesifik --}}
                                            @if($statusKey === 'pending_approval')
                                                @can('sales.approve.update')
                                                    <flux:button size="sm" variant="subtle" icon="check-circle" class="!h-6 !w-6 !p-0 text-amber-600 hover:text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-900/50" title="Persetujuan" wire:click.stop="$dispatch('open-approval-modal', { orderId: {{ $order->id }} })" />
                                                @endcan
                                            @elseif($statusKey === 'packing')
                                                @php 
                                                    $_sv = $setting_version;
                                                    $gudangHandlesShipping = \Illuminate\Support\Facades\Cache::remember('setting_gudang_handles_shipping', 3600, function () {
                                                        return \App\Models\Setting::where('key', 'gudang_handles_shipping')->value('value');
                                                    }) == '1';
                                                @endphp
                                                
                                                @if($order->is_packed)
                                                    @if(!$gudangHandlesShipping)
                                                        @if(auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Sales', 'Kepala Sales']))
                                                            @if($order->courier_vendor_id)
                                                                <div class="flex gap-1 w-full justify-end">
                                                                    <flux:button size="sm" variant="subtle" class="!h-6 !p-1 text-[10px] text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/50" wire:click.stop="markAsShipped({{ $order->id }})">Telah Diambil</flux:button>
                                                                    <flux:button size="sm" variant="subtle" icon="pencil-square" class="!h-6 !w-6 !p-0 text-zinc-600 hover:text-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-900/50" title="Edit Resi/Ekspedisi" wire:click.stop="$dispatch('open-shipping-modal', { orderId: {{ $order->id }} })" />
                                                                </div>
                                                            @else
                                                                <flux:button size="sm" variant="subtle" icon="truck" class="!h-6 !w-6 !p-0 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/50" title="Input Resi/Ekspedisi" wire:click.stop="$dispatch('open-shipping-modal', { orderId: {{ $order->id }} })" />
                                                            @endif
                                                        @endif
                                                    @endif
                                                @endif
                                            @elseif($statusKey === 'shipping')
                                                @php 
                                                    $_sv = $setting_version;
                                                    $gudangHandlesShipping = \Illuminate\Support\Facades\Cache::remember('setting_gudang_handles_shipping', 3600, function () {
                                                        return \App\Models\Setting::where('key', 'gudang_handles_shipping')->value('value');
                                                    }) == '1';
                                                @endphp
                                                @if(auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Sales', 'Kepala Sales']))
                                                    <flux:button size="sm" variant="subtle" icon="flag" class="!h-6 !w-6 !p-0 text-teal-600 hover:text-teal-700 hover:bg-teal-50 dark:hover:bg-teal-900/50" title="Tandai Barang Telah Sampai" wire:click.stop="$dispatch('open-arrived-modal', { orderId: {{ $order->id }} })" />
                                                @endif
                                                

                                            @elseif($statusKey === 'arrived')
                                                @can('sales.order.complete')
                                                    <flux:button size="sm" variant="subtle" icon="check-badge" class="!h-6 !w-6 !p-0 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/50" title="Tandai Selesai" wire:click.stop="$dispatch('open-completed-modal', { orderId: {{ $order->id }} })" />
                                                @endcan
                                            @elseif($statusKey === 'completed')
                                                @can('sales.order.complete')
                                                    <flux:button size="sm" variant="subtle" icon="inbox-arrow-down" class="!h-6 !w-6 !p-0 text-zinc-600 hover:text-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800" title="Arsipkan Pesanan" wire:click.stop="markAsArchived({{ $order->id }})" />
                                                @endcan
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @else
                            {{-- Locked indicator --}}
                            <div class="mt-1.5 pt-1.5 border-t border-zinc-100 dark:border-zinc-800">
                                <span class="text-[8px] text-zinc-400 dark:text-zinc-500 italic flex items-center gap-1">
                                    <flux:icon.lock-closed class="w-3 h-3 text-zinc-400" /> Terkunci
                                </span>
                            </div>
                            @endif
                        </div>
                    @empty
                        <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400 dark:text-zinc-500">
                            Kosong
                        </div>
                    @endforelse
                    
                    @if(count($this->kanbanOrders->get($statusKey, [])) >= ($columnLimits[$statusKey] ?? 10))
                        <x-kanban.load-more :statusKey="$statusKey" />
                    @endif
            </x-kanban.column>
        @endforeach
    </x-kanban.board>
    </div>
    
    <div wire:key="view-table-wrapper" class="w-full {{ $this->viewMode === 'table' ? 'block' : 'hidden' }}">
        <x-table.header searchModel="search" searchPlaceholder="Cari SO atau Pelanggan...">
                <div class="flex border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden shrink-0 bg-white dark:bg-zinc-900">
                    <button type="button" wire:key="table-sw-kanban" @click="$wire.setViewMode('kanban')" class="p-1.5 px-3 transition-colors {{ $this->viewMode === 'kanban' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Kanban">
                        <flux:icon.view-columns wire:loading.remove wire:target="setViewMode('kanban')" class="w-4 h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('kanban')" class="w-4 h-4 animate-spin" />
                    </button>
                    <button type="button" wire:key="table-sw-table" @click="$wire.setViewMode('table')" class="p-1.5 px-3 transition-colors {{ $this->viewMode === 'table' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Tabel">
                        <flux:icon.table-cells wire:loading.remove wire:target="setViewMode('table')" class="w-4 h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('table')" class="w-4 h-4 animate-spin" />
                    </button>
                </div>
                
                <flux:button variant="subtle" icon="arrow-down-tray" wire:click="exportCsv" wire:loading.attr="disabled" class="shrink-0 bg-white dark:bg-zinc-900 shadow-sm dark:shadow-none dark:border-white/10">CSV</flux:button>
                
                @can('sales.order.create')
                    <flux:button variant="primary" icon="plus" href="{{ route('sales.orders.create') }}" wire:navigate class="shrink-0">Buat</flux:button>
                @endcan
        </x-table.header>
            
        <x-table.wrapper>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-12">
                            <div class="pl-2 sm:pl-4 text-zinc-500 text-center">No</div>
                        </flux:table.column>
                        <flux:table.column sortable :sorted="$this->sortBy === 'created_at'" :direction="$this->sortDirection" wire:click="sort('created_at')">
                            <div class="pl-2 sm:pl-4">No. SO & Tanggal</div>
                        </flux:table.column>
                        <flux:table.column sortable :sorted="$this->sortBy === 'customer'" :direction="$this->sortDirection" wire:click="sort('customer')">Pelanggan</flux:table.column>
                        <flux:table.column sortable :sorted="$this->sortBy === 'total_amount'" :direction="$this->sortDirection" wire:click="sort('total_amount')">Total Transaksi</flux:table.column>
                        <flux:table.column sortable :sorted="$this->sortBy === 'payment_status'" :direction="$this->sortDirection" wire:click="sort('payment_status')">Pembayaran</flux:table.column>
                        <flux:table.column sortable :sorted="$this->sortBy === 'status'" :direction="$this->sortDirection" wire:click="sort('status')">Status</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($this->tableOrders as $index => $order)
                            @php
                                $isOwn = $order->created_by === auth()->id();
                                $isManagerial = auth()->user()->hasAnyRole(['Super Admin', 'Kepala Sales', 'Manager', 'Gudang', 'Shipping', 'Finance']);
                                $canInteract = $isOwn || $isManagerial;
                            @endphp
                            <flux:table.row wire:key="table-order-{{ $order->id }}" 
                                            class="{{ $canInteract ? 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer' : 'opacity-65 cursor-not-allowed select-none bg-zinc-50/20 dark:bg-zinc-900/10' }} transition-colors" 
                                            @click="{{ $canInteract ? '$dispatch(\'open-detail-modal\', { orderId: '.$order->id.' })' : 'null' }}">
                                <flux:table.cell class="whitespace-nowrap">
                                    <div class="pl-2 sm:pl-4 text-center font-medium text-zinc-500">
                                        {{ $loop->iteration }}
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="pl-2 sm:pl-4">
                                        <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $order->so_number }}</div>
                                        <div class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($order->order_date)->format('d M Y') }}</div>
                                    </div>
                                </flux:table.cell>
                                
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:avatar src="{{ $order->customer->image ? Storage::url($order->customer->image) : '' }}" fallback="{{ substr($order->customer->name, 0, 2) }}" size="sm" />
                                        <div>
                                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $order->customer->name }}</div>
                                            <div class="text-[11px] text-zinc-500 flex items-center gap-1 mt-0.5">
                                                <flux:avatar src="{{ $order->creator->avatar ? Storage::url($order->creator->avatar) : '' }}" fallback="{{ substr($order->creator->name ?? 'S', 0, 2) }}" class="w-3.5 h-3.5 text-[8px]" />
                                                <span>{{ $isOwn ? 'Anda' : explode(' ', $order->creator->name ?? '-')[0] }}</span>
                                                @if($order->brand)
                                                    <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                                    <span class="text-zinc-500 italic">{{ $order->brand->name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </flux:table.cell>
                                
                                <flux:table.cell>
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</div>
                                    <div class="text-[10px] text-zinc-500">{{ $order->items->sum('qty') }} Item</div>
                                </flux:table.cell>
                                
                                <flux:table.cell>
                                    @if($order->payment_status === 'paid')
                                        <flux:badge size="sm" color="green" icon="check-circle">Lunas</flux:badge>
                                    @elseif($order->payment_status === 'partial')
                                        <flux:badge size="sm" color="amber" icon="clock">Parsial</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red" icon="x-circle">Belum</flux:badge>
                                    @endif
                                </flux:table.cell>
                                
                                <flux:table.cell>
                                    @php $col = $this->columns[$order->status] ?? ['title' => $order->status, 'color' => 'zinc']; @endphp
                                    <flux:badge size="sm" color="{{ $col['color'] }}" class="whitespace-nowrap">{{ $col['title'] }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="text-center py-8 text-zinc-500">
                                    Tidak ada data pesanan yang ditemukan.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
        </x-table.wrapper>
            
        <div class="px-2 sm:px-4 lg:px-6 py-4">
                <x-load-more :paginator="$this->tableOrders" item-name="Pesanan" />
        </div>
    </div>
    
    {{-- Modals --}}
    <livewire:sales-order.detail-modal />
    <livewire:sales-order.approval-modal />
    <livewire:sales-order.payment-modal />
    <livewire:sales-order.fulfillment-modal />
    <livewire:sales-order.packing-modal />
    <livewire:sales-order.shipping-modal />
    <livewire:sales-order.arrived-modal />
    <livewire:sales-order.completed-modal />
    <livewire:global.vendor-gallery-modal />
    <livewire:global.vendor-form-modal />

    @if(session('new_so_number'))
        <div x-data x-init="$nextTick(() => { $flux.modal('new-so-success-modal').show() })">
            <flux:modal name="new-so-success-modal" class="min-w-[22rem]">
                <div class="p-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 dark:bg-emerald-900/30 mb-4">
                        <flux:icon.check-circle class="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <flux:heading size="xl">Berhasil!</flux:heading>
                    <flux:subheading class="mt-2 text-sm">
                        Sales Order baru berhasil dibuat:
                    </flux:subheading>
                    <div class="mt-3 mb-6 bg-zinc-50 dark:bg-zinc-800/50 py-3 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-wider">{{ session('new_so_number') }}</span>
                    </div>
                    
                    <flux:button variant="primary" class="w-full" @click="$flux.modal('new-so-success-modal').close()">Oke, Mengerti</flux:button>
                </div>
            </flux:modal>
        </div>
    @endif

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 10px;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #334155;
        }
        .vertical-text {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
        }
    </style>
</div>