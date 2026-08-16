<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $progress = 0;
    
    public $step1_done = false; // PWA & System
    public $step2_done = false; // Warehouses
    public $step3_done = false; // Brands
    public $step4_done = false; // Items (Katalog Barang)
    public $step5_done = false; // Customers & Vendors

    public function mount() {
        $this->checkProgress();
    }

    public function checkProgress() {
        // Step 1: PWA & Profil
        $this->step1_done = \App\Models\Setting::where('key', 'pwa_name')->exists() || DB::table('users')->count() > 1;
        
        // Step 2: Warehouses
        $this->step2_done = DB::table('warehouses')->exists();

        // Step 3: Brands
        $this->step3_done = DB::table('brands')->exists();
        
        // Step 4: Items (Otomatis mewajibkan kategori/unit di formnya)
        $this->step4_done = DB::table('items')->exists();
        
        // Step 5: Relasi Bisnis
        $this->step5_done = DB::table('customers')->exists() && DB::table('vendors')->exists();

        // Calculate total progress
        $completed = 0;
        if ($this->step1_done) $completed++;
        if ($this->step2_done) $completed++;
        if ($this->step3_done) $completed++;
        if ($this->step4_done) $completed++;
        if ($this->step5_done) $completed++;

        $this->progress = ($completed / 5) * 100;
    }
};
?>

<div>
    @if($progress < 100)
    <div class="mb-6">
        <flux:card class="bg-gradient-to-br from-indigo-50 to-white dark:from-zinc-800 dark:to-zinc-900 border-indigo-100 dark:border-zinc-700 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                        <flux:icon.rocket-launch class="w-5 h-5 text-indigo-500" />
                        Panduan Mulai Cepat (Setup)
                    </h2>
                    <p class="text-sm text-zinc-500 mt-1">Selesaikan langkah-langkah di bawah ini untuk mulai menggunakan aplikasi.</p>
                </div>
                <div class="w-full sm:w-48">
                    <div class="flex justify-between text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">
                        <span>Progres Setup</span>
                        <span>{{ round($progress) }}%</span>
                    </div>
                    <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 overflow-hidden">
                        <div class="bg-indigo-500 h-2 rounded-full transition-all duration-500 ease-out" style="width: {{ $progress }}%"></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Step 1 -->
                <div class="flex flex-col p-4 rounded-xl border {{ $step1_done ? 'bg-indigo-50/50 border-indigo-200 dark:bg-indigo-500/10 dark:border-indigo-500/20' : 'bg-white border-zinc-200 dark:bg-zinc-800 dark:border-zinc-700' }} transition-colors relative group">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full {{ $step1_done ? 'bg-indigo-500 text-white' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-700' }}">
                            @if($step1_done)
                                <flux:icon.check class="w-4 h-4" />
                            @else
                                <span class="text-xs font-bold">1</span>
                            @endif
                        </div>
                        <h3 class="font-semibold text-sm text-zinc-900 dark:text-white {{ $step1_done ? 'line-through text-zinc-400' : '' }}">Sistem & Profil</h3>
                    </div>
                    <p class="text-xs text-zinc-500 mb-4 flex-grow {{ $step1_done ? 'opacity-60' : '' }}">Atur PWA, konfigurasi, dan pengguna tambahan.</p>
                    @if(!$step1_done)
                        <flux:button href="/settings/pwa" wire:navigate size="xs" variant="outline" class="w-full justify-center">Atur Sekarang</flux:button>
                    @else
                        <div class="text-xs font-medium text-indigo-500 flex items-center gap-1"><flux:icon.check-circle class="w-3 h-3"/> Selesai</div>
                    @endif
                </div>

                <!-- Step 2 -->
                <div class="flex flex-col p-4 rounded-xl border {{ $step2_done ? 'bg-indigo-50/50 border-indigo-200 dark:bg-indigo-500/10 dark:border-indigo-500/20' : 'bg-white border-zinc-200 dark:bg-zinc-800 dark:border-zinc-700' }} transition-colors relative group">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full {{ $step2_done ? 'bg-indigo-500 text-white' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-700' }}">
                            @if($step2_done)
                                <flux:icon.check class="w-4 h-4" />
                            @else
                                <span class="text-xs font-bold">2</span>
                            @endif
                        </div>
                        <h3 class="font-semibold text-sm text-zinc-900 dark:text-white {{ $step2_done ? 'line-through text-zinc-400' : '' }}">Daftar Gudang</h3>
                    </div>
                    <p class="text-xs text-zinc-500 mb-4 flex-grow {{ $step2_done ? 'opacity-60' : '' }}">Tambahkan gudang tempat stok akan disimpan.</p>
                    @if(!$step2_done)
                        <flux:button href="/inventory/warehouses" wire:navigate size="xs" variant="outline" class="w-full justify-center" :disabled="!$step1_done">Buat Gudang</flux:button>
                    @else
                        <div class="text-xs font-medium text-indigo-500 flex items-center gap-1"><flux:icon.check-circle class="w-3 h-3"/> Selesai</div>
                    @endif
                </div>

                <!-- Step 3 -->
                <div class="flex flex-col p-4 rounded-xl border {{ $step3_done ? 'bg-indigo-50/50 border-indigo-200 dark:bg-indigo-500/10 dark:border-indigo-500/20' : 'bg-white border-zinc-200 dark:bg-zinc-800 dark:border-zinc-700' }} transition-colors relative group">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full {{ $step3_done ? 'bg-indigo-500 text-white' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-700' }}">
                            @if($step3_done)
                                <flux:icon.check class="w-4 h-4" />
                            @else
                                <span class="text-xs font-bold">3</span>
                            @endif
                        </div>
                        <h3 class="font-semibold text-sm text-zinc-900 dark:text-white {{ $step3_done ? 'line-through text-zinc-400' : '' }}">Master Merek</h3>
                    </div>
                    <p class="text-xs text-zinc-500 mb-4 flex-grow {{ $step3_done ? 'opacity-60' : '' }}">Daftarkan merek (brand) dagang Anda.</p>
                    @if(!$step3_done)
                        <flux:button href="/settings/brands" wire:navigate size="xs" variant="outline" class="w-full justify-center" :disabled="!$step2_done">Kelola Merek</flux:button>
                    @else
                        <div class="text-xs font-medium text-indigo-500 flex items-center gap-1"><flux:icon.check-circle class="w-3 h-3"/> Selesai</div>
                    @endif
                </div>

                <!-- Step 4 -->
                <div class="flex flex-col p-4 rounded-xl border {{ $step4_done ? 'bg-indigo-50/50 border-indigo-200 dark:bg-indigo-500/10 dark:border-indigo-500/20' : 'bg-white border-zinc-200 dark:bg-zinc-800 dark:border-zinc-700' }} transition-colors relative group">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full {{ $step4_done ? 'bg-indigo-500 text-white' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-700' }}">
                            @if($step4_done)
                                <flux:icon.check class="w-4 h-4" />
                            @else
                                <span class="text-xs font-bold">4</span>
                            @endif
                        </div>
                        <h3 class="font-semibold text-sm text-zinc-900 dark:text-white {{ $step4_done ? 'line-through text-zinc-400' : '' }}">Katalog Barang</h3>
                    </div>
                    <p class="text-xs text-zinc-500 mb-4 flex-grow {{ $step4_done ? 'opacity-60' : '' }}">Daftarkan produk atau barang dagangan Anda.</p>
                    @if(!$step4_done)
                        <flux:button href="/inventory/items" wire:navigate size="xs" variant="outline" class="w-full justify-center" :disabled="!$step3_done">Input Barang</flux:button>
                    @else
                        <div class="text-xs font-medium text-indigo-500 flex items-center gap-1"><flux:icon.check-circle class="w-3 h-3"/> Selesai</div>
                    @endif
                </div>

                <!-- Step 5 -->
                <div class="flex flex-col p-4 rounded-xl border {{ $step5_done ? 'bg-indigo-50/50 border-indigo-200 dark:bg-indigo-500/10 dark:border-indigo-500/20' : 'bg-white border-zinc-200 dark:bg-zinc-800 dark:border-zinc-700' }} transition-colors relative group">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full {{ $step5_done ? 'bg-indigo-500 text-white' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-700' }}">
                            @if($step5_done)
                                <flux:icon.check class="w-4 h-4" />
                            @else
                                <span class="text-xs font-bold">5</span>
                            @endif
                        </div>
                        <h3 class="font-semibold text-sm text-zinc-900 dark:text-white {{ $step5_done ? 'line-through text-zinc-400' : '' }}">Relasi Bisnis</h3>
                    </div>
                    <p class="text-xs text-zinc-500 mb-4 flex-grow {{ $step5_done ? 'opacity-60' : '' }}">Daftarkan setidaknya 1 Pemasok dan Pelanggan.</p>
                    @if(!$step5_done)
                        <flux:button href="/sales/customers" wire:navigate size="xs" variant="outline" class="w-full justify-center" :disabled="!$step4_done">Tambah Relasi</flux:button>
                    @else
                        <div class="text-xs font-medium text-indigo-500 flex items-center gap-1"><flux:icon.check-circle class="w-3 h-3"/> Selesai</div>
                    @endif
                </div>
            </div>
        </flux:card>
    </div>
    @endif
</div>
