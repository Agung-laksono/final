<?php

use function Livewire\Volt\{state, layout, title, computed, rules};
layout('layouts.app');
title('Kategori Akun');
use Modules\Finance\Models\FinanceCategory;




state([
    'showModal' => false,
    'modalMode' => 'create',
    
    'categoryId' => null,
    'name' => '',
    'type' => 'expense',
    'is_active' => true,
]);

rules([
    'name' => 'required|string|max:255',
    'type' => 'required|in:income,expense',
    'is_active' => 'boolean',
]);

$categories = computed(function () {
    return FinanceCategory::orderBy('type')->orderBy('name')->get();
});

$openCreateModal = function () {
    $this->reset(['categoryId', 'name']);
    $this->type = 'expense';
    $this->is_active = true;
    $this->modalMode = 'create';
    $this->showModal = true;
};

$openEditModal = function ($id) {
    $category = FinanceCategory::findOrFail($id);
    
    $this->categoryId = $category->id;
    $this->name = $category->name;
    $this->type = $category->type;
    $this->is_active = $category->is_active;
    
    $this->modalMode = 'edit';
    $this->showModal = true;
};

$saveCategory = function () {
    $this->validate();

    try {
        if ($this->modalMode === 'create') {
            FinanceCategory::create([
                'name' => $this->name,
                'type' => $this->type,
                'is_active' => $this->is_active,
            ]);
            \Flux::toast('Kategori berhasil ditambahkan.', variant: 'success');
        } else {
            $category = FinanceCategory::findOrFail($this->categoryId);
            $category->update([
                'name' => $this->name,
                'type' => $this->type,
                'is_active' => $this->is_active,
            ]);
            \Flux::toast('Kategori berhasil diperbarui.', variant: 'success');
        }
        
        $this->showModal = false;
    } catch (\Exception $e) {
        \Flux::toast('Gagal: ' . $e->getMessage(), variant: 'danger');
    }
};

$toggleStatus = function ($id) {
    $category = FinanceCategory::findOrFail($id);
    $category->update(['is_active' => !$category->is_active]);
    \Flux::toast('Status kategori diubah.', variant: 'success');
};

?>

<div>
    <div class="flex justify-between items-center">
        
        
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            Tambah Kategori
        </flux:button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Pengeluaran -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 bg-rose-50 dark:bg-rose-900/10 flex items-center gap-3">
                <flux:icon.arrow-trending-down class="w-5 h-5 text-rose-500" />
                <h3 class="font-bold text-rose-700 dark:text-rose-400">Kategori Pengeluaran (Expense)</h3>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($this->categories->where('type', 'expense') as $category)
                <div class="px-6 py-4 flex items-center justify-between hover:bg-zinc-50 dark:hover:bg-zinc-800/50 group transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center">
                            <flux:icon.banknotes class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $category->name }}</div>
                            @if(!$category->is_active)
                                <span class="text-xs text-rose-500 font-medium">Non-Aktif</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <flux:button size="sm" variant="ghost" wire:click="toggleStatus({{ $category->id }})" title="{{ $category->is_active ? 'Non-aktifkan' : 'Aktifkan' }}">
                            <flux:icon.power class="w-4 h-4 {{ $category->is_active ? 'text-zinc-400 hover:text-rose-500' : 'text-emerald-500' }}" />
                        </flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="openEditModal({{ $category->id }})">
                            <flux:icon.pencil-square class="w-4 h-4" />
                        </flux:button>
                    </div>
                </div>
                @empty
                <div class="p-6 text-center text-zinc-500 text-sm">Belum ada kategori pengeluaran.</div>
                @endforelse
            </div>
        </div>

        <!-- Pemasukan -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 bg-emerald-50 dark:bg-emerald-900/10 flex items-center gap-3">
                <flux:icon.arrow-trending-up class="w-5 h-5 text-emerald-500" />
                <h3 class="font-bold text-emerald-700 dark:text-emerald-400">Kategori Pemasukan (Income)</h3>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($this->categories->where('type', 'income') as $category)
                <div class="px-6 py-4 flex items-center justify-between hover:bg-zinc-50 dark:hover:bg-zinc-800/50 group transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                            <flux:icon.banknotes class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $category->name }}</div>
                            @if(!$category->is_active)
                                <span class="text-xs text-rose-500 font-medium">Non-Aktif</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <flux:button size="sm" variant="ghost" wire:click="toggleStatus({{ $category->id }})" title="{{ $category->is_active ? 'Non-aktifkan' : 'Aktifkan' }}">
                            <flux:icon.power class="w-4 h-4 {{ $category->is_active ? 'text-zinc-400 hover:text-rose-500' : 'text-emerald-500' }}" />
                        </flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="openEditModal({{ $category->id }})">
                            <flux:icon.pencil-square class="w-4 h-4" />
                        </flux:button>
                    </div>
                </div>
                @empty
                <div class="p-6 text-center text-zinc-500 text-sm">Belum ada kategori pemasukan.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Modal Form Kategori -->
    <flux:modal wire:model="showModal" class="md:w-[400px]">
        <div>
            <div>
                <flux:heading size="lg">{{ $modalMode === 'create' ? 'Tambah Kategori' : 'Edit Kategori' }}</flux:heading>
            </div>

            <form wire:submit="saveCategory" class="space-y-4">
                <flux:input wire:model="name" label="Nama Kategori" placeholder="Misal: Biaya Listrik, Bunga Bank" required />
                
                <flux:select wire:model="type" label="Tipe Kategori" required>
                    <flux:select.option value="expense">Pengeluaran (Expense)</flux:select.option>
                    <flux:select.option value="income">Pemasukan (Income)</flux:select.option>
                </flux:select>
                
                <flux:switch wire:model="is_active" label="Kategori Aktif" />
                
                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700 flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)"> Batal </flux:button>
                    <flux:button icon="check" type="submit" variant="primary"> Simpan Kateg </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
