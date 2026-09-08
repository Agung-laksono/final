<?php

use Livewire\Volt\Component;

new class extends Component {
    public $viewMode = 'kanban';
    
    // State untuk Drawer
    public $activeDrawer = null;
    
    public function openDrawer($drawer)
    {
        $this->activeDrawer = $drawer;
        $this->dispatch('modal-open', 'cockpit-drawer');
    public function with()
    {
        return [
            'salesOrders' => \Modules\Sales\Models\SalesOrder::with(['customer'])->whereNotIn('status', ['void', 'completed', 'archived'])->latest('updated_at')->take(5)->get(),
            'purchaseOrders' => \Modules\Purchase\Models\PurchaseOrder::with(['vendor'])->whereNotIn('status', ['void', 'completed', 'archived', 'draft'])->latest('updated_at')->take(5)->get(),
            'productionOrders' => class_exists(\Modules\Production\Models\ProductionOrder::class) ? \Modules\Production\Models\ProductionOrder::whereNotIn('status', ['void', 'completed', 'draft'])->latest('updated_at')->take(5)->get() : collect(),
        ];
    }
}; ?>

<x-kanban.board componentId="master" :viewMode="$viewMode" title="Cockpit (Solo Mode)" subtitle="Master Kanban Alur Kerja">
    
    <!-- KOLOM 1: PENJUALAN -->
    <x-kanban.column statusKey="sales" :column="['title' => 'Penjualan', 'color' => 'emerald']" componentId="master" count="{{ $salesOrders->count() }}" class="bg-zinc-50 dark:bg-zinc-800/50 shadow-sm border border-zinc-200 dark:border-zinc-700">
        <x-slot name="headerActions">
            <flux:button size="sm" variant="primary" class="!px-2 !py-1" x-bind:title="'Buat Pesanan (SO) Baru'" wire:click="$dispatch('modal-open', 'create-so-modal')">
                <flux:icon.plus class="w-4 h-4" />
            </flux:button>
        </x-slot>

        @foreach($salesOrders as $so)
            <div wire:key="so-{{ $so->id }}" class="bg-white dark:bg-zinc-800 p-4 rounded-xl shadow-sm border border-zinc-100 dark:border-zinc-700 hover:border-emerald-500 transition-colors cursor-pointer group">
                <div class="flex justify-between items-start mb-2">
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 rounded">{{ $so->so_number }}</span>
                    <span class="text-[10px] text-zinc-400">{{ $so->updated_at->diffForHumans() }}</span>
                </div>
                <h3 class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">{{ $so->customer->name ?? 'Pelanggan Umum' }}</h3>
                <p class="text-[10px] uppercase font-bold text-zinc-500 mb-3">{{ str_replace('_', ' ', $so->status) }}</p>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Rp {{ number_format($so->total_amount, 0, ',', '.') }}</span>
                </div>
            </div>
        @endforeach
    </x-kanban.column>

    <!-- KOLOM 2: PEMBELIAN -->
    <x-kanban.column statusKey="purchase" :column="['title' => 'Pembelian', 'color' => 'sky']" componentId="master" count="{{ $purchaseOrders->count() }}" class="bg-zinc-50 dark:bg-zinc-800/50 shadow-sm border border-zinc-200 dark:border-zinc-700">
        <x-slot name="headerActions">
            <flux:button size="sm" variant="subtle" class="!px-2 !py-1 text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/30" x-bind:title="'Buka Halaman Pembelian'" wire:click="openDrawer('purchase')">
                <flux:icon.eye class="w-4 h-4" />
            </flux:button>
        </x-slot>

        @foreach($purchaseOrders as $po)
            <div wire:key="po-{{ $po->id }}" class="bg-white dark:bg-zinc-800 p-4 rounded-xl shadow-sm border border-zinc-100 dark:border-zinc-700 hover:border-sky-500 transition-colors cursor-pointer group">
                <div class="flex justify-between items-start mb-2">
                    <span class="text-xs font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-900/30 px-2 py-0.5 rounded">{{ $po->po_number }}</span>
                    <span class="text-[10px] text-zinc-400">{{ $po->updated_at->diffForHumans() }}</span>
                </div>
                <h3 class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">{{ $po->vendor->name ?? 'Vendor' }}</h3>
                <p class="text-[10px] uppercase font-bold text-zinc-500 mb-3">{{ str_replace('_', ' ', $po->status) }}</p>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</span>
                </div>
            </div>
        @endforeach
    </x-kanban.column>

    <!-- KOLOM 3: PRODUKSI -->
    <x-kanban.column statusKey="production" :column="['title' => 'Produksi', 'color' => 'purple']" componentId="master" count="{{ collect($productionOrders)->count() }}" class="bg-zinc-50 dark:bg-zinc-800/50 shadow-sm border border-zinc-200 dark:border-zinc-700">
        <x-slot name="headerActions">
            <flux:button size="sm" variant="subtle" class="!px-2 !py-1 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30" x-bind:title="'Buka Halaman Produksi'" wire:click="openDrawer('production')">
                <flux:icon.eye class="w-4 h-4" />
            </flux:button>
        </x-slot>

        @foreach($productionOrders as $spk)
            <div wire:key="spk-{{ $spk->id }}" class="bg-white dark:bg-zinc-800 p-4 rounded-xl shadow-sm border border-purple-200 dark:border-purple-900/50 cursor-pointer border-l-4 border-l-purple-500">
                <div class="flex justify-between items-start mb-2">
                    <span class="text-xs font-bold text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-2 py-0.5 rounded">{{ $spk->wo_number }}</span>
                    <span class="text-[10px] text-zinc-400">{{ str_replace('_', ' ', $spk->status) }}</span>
                </div>
                <h3 class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">{{ Str::limit($spk->notes, 30, '...') }}</h3>
                <p class="text-xs text-zinc-500 mb-3">Terkait: {{ $spk->notes }}</p>
            </div>
        @endforeach
    </x-kanban.column>
    
    <!-- KOLOM 4: FULFILLMENT -->
    <x-kanban.column statusKey="fulfillment" :column="['title' => 'Fulfillment', 'color' => 'amber']" componentId="master" count="2" class="bg-zinc-50 dark:bg-zinc-800/50 shadow-sm border border-zinc-200 dark:border-zinc-700">
        <x-slot name="headerActions">
            <flux:button size="sm" variant="subtle" class="!px-2 !py-1 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30" x-bind:title="'Buka Halaman Gudang'" wire:click="openDrawer('warehouse')">
                <flux:icon.eye class="w-4 h-4" />
            </flux:button>
        </x-slot>

        <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl shadow-sm border border-zinc-100 dark:border-zinc-700 hover:border-amber-500 transition-colors cursor-pointer">
            <div class="flex justify-between items-start mb-2">
                <span class="text-xs font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2 py-0.5 rounded">PACK-001</span>
                <span class="text-[10px] text-zinc-400">Siap Packing</span>
            </div>
            <h3 class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">SO-2408-095</h3>
            <p class="text-xs text-zinc-500 mb-3">Semua barang sudah tersedia.</p>
            <flux:button size="xs" class="w-full" variant="outline">Mulai Packing</flux:button>
        </div>
    </x-kanban.column>

    <!-- KOLOM 5: PENGIRIMAN -->
    <x-kanban.column statusKey="shipping" :column="['title' => 'Pengiriman', 'color' => 'indigo']" componentId="master" count="1" class="bg-zinc-50 dark:bg-zinc-800/50 shadow-sm border border-zinc-200 dark:border-zinc-700">
        <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl shadow-sm border border-zinc-100 dark:border-zinc-700 cursor-pointer">
            <div class="flex justify-between items-start mb-2">
                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded">DO-2408-050</span>
                <span class="text-[10px] text-indigo-500 flex items-center gap-1"><flux:icon.truck class="w-3 h-3"/> OTW</span>
            </div>
            <h3 class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">Toko Makmur</h3>
            <p class="text-xs text-zinc-500 mb-3">Kurir: Internal (Budi)</p>
            <flux:button size="xs" class="w-full" variant="outline">Tandai Sampai</flux:button>
        </div>
    </x-kanban.column>

    <!-- KOLOM 6: KEUANGAN -->
    <x-kanban.column statusKey="finance" :column="['title' => 'Tagihan & Bayar', 'color' => 'blue']" componentId="master" count="1" class="bg-zinc-50 dark:bg-zinc-800/50 shadow-sm border border-zinc-200 dark:border-zinc-700">
        <x-slot name="headerActions">
            <flux:button size="sm" variant="subtle" class="!px-2 !py-1 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30" x-bind:title="'Buka Halaman Keuangan'" wire:click="openDrawer('finance')">
                <flux:icon.eye class="w-4 h-4" />
            </flux:button>
        </x-slot>

        <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl shadow-sm border border-zinc-100 dark:border-zinc-700 cursor-pointer">
            <div class="flex justify-between items-start mb-2">
                <span class="text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded">INV-2408-040</span>
                <span class="text-[10px] text-red-500 font-bold">UNPAID</span>
            </div>
            <h3 class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">Toko Sejahtera</h3>
            <p class="text-xs text-zinc-500 mb-3">Rp 8.500.000</p>
            <flux:button size="xs" class="w-full" variant="primary">Terima Pembayaran</flux:button>
        </div>
    </x-kanban.column>

    <!-- KOLOM 7: SELESAI -->
    <x-kanban.column statusKey="completed" :column="['title' => 'Selesai', 'color' => 'zinc']" componentId="master" count="9" class="bg-zinc-50 dark:bg-zinc-800/50 shadow-sm border border-zinc-200 dark:border-zinc-700 opacity-70">
        <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl shadow-sm border border-zinc-100 dark:border-zinc-700">
            <div class="flex justify-between items-start mb-2">
                <span class="text-xs font-bold text-zinc-500">SO-2408-038</span>
                <flux:icon.check-circle class="w-4 h-4 text-emerald-500"/>
            </div>
            <h3 class="font-bold text-zinc-600 dark:text-zinc-400 mb-1">Bapak Rudi</h3>
        </div>
    </x-kanban.column>

    <!-- Modals & Drawers -->
    
    <!-- Modal Drawer Global -->
    <flux:modal name="cockpit-drawer" variant="flyout" class="!w-screen sm:!w-[90vw] md:!w-[85vw] lg:!w-[75vw] xl:!w-[65vw] max-w-none h-dvh flex flex-col p-0">
        <div class="flex flex-col h-full bg-zinc-50 dark:bg-zinc-900">
            <!-- Header Modal -->
            <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 flex justify-between items-center shrink-0">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-bold text-zinc-800 dark:text-zinc-100">
                        @if($activeDrawer === 'purchase') Modul Pembelian (Purchase)
                        @elseif($activeDrawer === 'production') Modul Produksi
                        @elseif($activeDrawer === 'warehouse') Modul Gudang / Fulfillment
                        @elseif($activeDrawer === 'finance') Modul Keuangan
                        @endif
                    </h2>
                </div>
                <flux:button size="sm" variant="subtle" class="!px-2" wire:click="$dispatch('modal-close', 'cockpit-drawer')">
                    <flux:icon.x-mark class="w-5 h-5" />
                </flux:button>
            </div>
            
            <!-- Isi Modal -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6" wire:key="drawer-{{ $activeDrawer }}">
                @if($activeDrawer)
                    @include('livewire.command-center.drawer-' . $activeDrawer)
                @endif
            </div>
        </div>
    </flux:modal>

    <!-- Modal Create SO Dummy -->
    <flux:modal name="create-so-modal" class="md:w-[600px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Buat Pesanan (Sales Order) Baru</flux:heading>
                <flux:subheading>Isi formulir ringkas ini untuk memulai alur pesanan baru.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input label="Nama Pelanggan" placeholder="Masukkan nama..." />
                <flux:input label="Item / Produk" placeholder="Cari item..." />
                <flux:input type="number" label="Jumlah (Qty)" placeholder="1" />
            </div>

            <div class="flex justify-end space-x-2">
                <flux:button variant="subtle" wire:click="$dispatch('modal-close', 'create-so-modal')">Batal</flux:button>
                <flux:button variant="primary">Simpan & Mulai Proses</flux:button>
            </div>
        </div>
    </flux:modal>

</x-kanban.board>
