<?php

use function Livewire\Volt\{state, mount, layout, updated};
use Modules\Inventory\Models\Catalog;
use Modules\Inventory\Models\CatalogItem;
use Modules\Inventory\Models\Item;
use Carbon\Carbon;

layout('layouts.empty');

state([
    'title'      => '',
    'validUntil' => null,
    'isExpired'  => false,
    'items'      => [],
    'phone'      => '',
    'error'      => null,
    'hash'       => '',
    'quantities' => [],
    'isCreator'  => false,
]);

mount(function ($hash = null) {
    if (!$hash) {
        $this->error = 'Link tidak valid atau tidak lengkap.';
        return;
    }

    $catalog = Catalog::where('hash', $hash)->with(['catalogItems', 'items'])->first();

    if ($catalog) {
        if ($catalog->type !== 'vendor') {
            $this->error = 'Link ini bukan form vendor.';
            return;
        }

        if ($catalog->valid_until && now()->isAfter($catalog->valid_until)) {
            $this->isExpired  = true;
            $this->validUntil = $catalog->valid_until;
            return;
        }

        $this->hash       = $hash;
        $this->title      = $catalog->title;
        $this->phone      = $catalog->phone ?? '';
        $this->validUntil = $catalog->valid_until;
        $this->isCreator  = auth()->check();

        $this->quantities = $catalog->catalogItems->pluck('quantity', 'item_id')->toArray();
        $itemIds          = $catalog->catalogItems->pluck('item_id')->toArray();

        $this->items = Item::with(['category', 'unit', 'type'])
            ->whereIn('id', $itemIds)
            ->get()
            ->sortBy(fn($i) => array_search($i->id, $itemIds))
            ->values();
    } else {
        // Fallback to Cache
        $decoded = \Illuminate\Support\Facades\Cache::get('catalog_' . $hash);

        if (!is_array($decoded) || !isset($decoded['items']) || !isset($decoded['exp'])) {
            $this->error = 'Katalog tidak ditemukan atau sudah kadaluarsa.';
            return;
        }

        if (isset($decoded['type']) && $decoded['type'] !== 'vendor') {
            $this->error = 'Link ini bukan form vendor.';
            return;
        }

        $expiry = Carbon::parse($decoded['exp']);
        if ($expiry->isPast()) {
            $this->isExpired  = true;
            $this->validUntil = $expiry;
            return;
        }

        $this->hash       = $hash;
        $this->title      = $decoded['title']  ?? 'Form Vendor';
        $this->phone      = $decoded['phone']  ?? '';
        $this->validUntil = $expiry;
        $this->quantities = $decoded['quantities'] ?? [];
        $this->isCreator  = auth()->check();

        $itemIds = $decoded['items'];
        $this->items = Item::with(['category', 'unit', 'type'])
            ->whereIn('id', $itemIds)
            ->get()
            ->sortBy(fn($i) => array_search($i->id, $itemIds))
            ->values();
    }

    // Default quantities fallback
    foreach ($this->items as $item) {
        if (!isset($this->quantities[$item->id])) {
            $this->quantities[$item->id] = 1;
        }
    }
});

$updateQuantity = function ($itemId, $qty) {
    $qty = max(1, (int)$qty);
    $this->quantities[$itemId] = $qty;

    // 1. Update Database
    $catalog = Catalog::where('hash', $this->hash)->first();
    if ($catalog) {
        CatalogItem::where('catalog_id', $catalog->id)
            ->where('item_id', $itemId)
            ->update(['quantity' => $qty]);
    }

    // 2. Update Cache fallback
    $payload = \Illuminate\Support\Facades\Cache::get('catalog_' . $this->hash);
    if ($payload) {
        $payload['quantities'] = $this->quantities;
        \Illuminate\Support\Facades\Cache::put('catalog_' . $this->hash, $payload, \Carbon\Carbon::parse($payload['exp']));
    }
};

updated([
    'quantities' => function () {
        $catalog = Catalog::where('hash', $this->hash)->first();
        if ($catalog) {
            foreach ($this->quantities as $itemId => $qty) {
                CatalogItem::where('catalog_id', $catalog->id)
                    ->where('item_id', $itemId)
                    ->update(['quantity' => max(1, (int)$qty)]);
            }
        }

        $payload = \Illuminate\Support\Facades\Cache::get('catalog_' . $this->hash);
        if ($payload) {
            $payload['quantities'] = $this->quantities;
            \Illuminate\Support\Facades\Cache::put('catalog_' . $this->hash, $payload, \Carbon\Carbon::parse($payload['exp']));
        }
    }
]);

?>

<div x-data="vendorQuoteForm()" class="bg-zinc-50 dark:bg-zinc-950 min-h-screen">

    @if($error)
        <div class="flex items-center justify-center min-h-screen p-6">
            <div class="bg-white dark:bg-zinc-900 rounded-2xl p-10 text-center shadow-lg max-w-sm">
                <flux:icon.exclamation-triangle class="w-12 h-12 text-red-400 mx-auto mb-4" />
                <h1 class="text-xl font-black text-zinc-900 dark:text-white mb-2">Oops!</h1>
                <p class="text-zinc-500 dark:text-zinc-400">{{ $error }}</p>
            </div>
        </div>
    @elseif($isExpired)
        <div class="flex items-center justify-center min-h-screen p-6">
            <div class="bg-white dark:bg-zinc-900 rounded-2xl p-10 text-center shadow-lg max-w-sm">
                <flux:icon.clock class="w-12 h-12 text-amber-400 mx-auto mb-4" />
                <h1 class="text-xl font-black text-zinc-900 dark:text-white mb-2">Form Kadaluarsa</h1>
                <p class="text-zinc-500 dark:text-zinc-400">Form ini sudah tidak aktif sejak {{ $validUntil->translatedFormat('d M Y, H:i') }}.</p>
            </div>
        </div>
    @else
        {{-- Header --}}
        <div class="bg-emerald-600 dark:bg-emerald-900 shadow-sm sticky top-0 z-50">
            <div class="max-w-4xl mx-auto px-4 py-3 sm:py-4 flex flex-row items-center justify-between gap-3">
                <div class="text-left flex-1 min-w-0">
                    <h1 class="text-lg sm:text-2xl font-bold text-white leading-tight truncate">{{ $title }}</h1>
                    <p class="text-emerald-200 text-[10px] sm:text-xs">Form Pengajuan Harga Vendor</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="bg-emerald-800/50 rounded-lg px-2.5 py-1.5 flex items-center gap-1.5 border border-emerald-500/30 shrink-0">
                        <flux:icon.clock class="w-3.5 h-3.5 text-emerald-300" />
                        <div class="text-[9px] sm:text-xs text-emerald-100 leading-tight">
                            Berlaku hingga:<br>
                            <span class="font-bold text-white">{{ $validUntil->translatedFormat('d M Y, H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info banner (vendor only) --}}
        @if(!$isCreator)
        <div class="max-w-4xl mx-auto px-4 pt-5">
            <div class="bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300 p-4 rounded-xl text-sm flex items-start gap-3 border border-blue-200 dark:border-blue-800">
                <flux:icon.information-circle class="w-5 h-5 shrink-0 mt-0.5" />
                <p>Silakan isi harga penawaran Anda untuk setiap barang di bawah ini. Setelah selesai, klik tombol <strong>Kirim Penawaran ke WhatsApp</strong>.</p>
            </div>
        </div>
        @else
        <div class="max-w-4xl mx-auto px-4 pt-5">
            <div class="bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300 p-4 rounded-xl text-sm flex items-start gap-3 border border-amber-200 dark:border-amber-800">
                <flux:icon.pencil-square class="w-5 h-5 shrink-0 mt-0.5" />
                <p>Anda melihat halaman ini sebagai <strong>Pembuat</strong>. Anda dapat mengedit <strong>Jumlah Kebutuhan (Qty)</strong> pada masing-masing barang di bawah.</p>
            </div>
        </div>
        @endif

        {{-- Items --}}
        <div class="max-w-4xl mx-auto px-4 py-6 mb-28 space-y-4">
            @foreach($items as $item)
                <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden shadow-sm border border-zinc-200 dark:border-zinc-800">
                    {{-- Image --}}
                    <div class="bg-zinc-100 dark:bg-zinc-800 relative">
                        @if($item->image)
                            <img src="{{ asset('storage/' . $item->image) }}" class="w-full h-auto block">
                        @else
                            <div class="w-full aspect-[4/3] flex items-center justify-center">
                                <flux:icon.photo class="w-12 h-12 text-zinc-300 dark:text-zinc-700" />
                            </div>
                        @endif
                    </div>

                    <div class="p-4">
                        {{-- Name --}}
                        <h3 class="font-bold text-zinc-900 dark:text-white leading-tight mb-1">
                            {{ $item->alias ? $item->alias . ' - ' . $item->name : $item->name }}
                        </h3>
                        <div class="text-xs text-zinc-500 flex items-center gap-2 flex-wrap mb-3">
                            <span class="bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded font-mono font-semibold">{{ $item->code ?? $item->sku }}</span>
                            @if($item->length && $item->width && $item->height)
                                <span>P: {{ $item->length }} × L: {{ $item->width }} × T: {{ $item->height }} {{ $item->dimension_unit }}</span>
                            @endif
                        </div>

                        {{-- Mode Pembuat: Qty Editor --}}
                        @if($isCreator)
                        <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                            <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">Jumlah Kebutuhan (Qty):</span>
                            <div class="flex items-center gap-2">
                                <input type="number"
                                       min="1"
                                       value="{{ $quantities[$item->id] ?? 1 }}"
                                       @change="$wire.updateQuantity({{ $item->id }}, $event.target.value)"
                                       @input.debounce.500ms="$wire.updateQuantity({{ $item->id }}, $event.target.value)"
                                       class="w-24 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-700 rounded-lg px-2.5 py-1 text-sm font-bold text-center text-zinc-900 dark:text-white outline-none focus:ring-2 focus:ring-amber-500">
                                <span class="text-xs text-zinc-500 font-medium">{{ $item->unit?->name ?? 'Unit' }}</span>
                            </div>
                        </div>
                        @else
                        {{-- Mode Vendor: Price Inputs --}}
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-1.5 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300 px-3 py-1.5 rounded-lg text-xs font-bold border border-amber-200 dark:border-amber-800">
                                <flux:icon.cube class="w-4 h-4 text-amber-500" />
                                Jumlah Kebutuhan: {{ $quantities[$item->id] ?? 1 }} {{ $item->unit?->name ?? 'Unit' }}
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                                    Harga Penawaran / {{ $item->unit?->name ?? 'Unit' }} (Rp)
                                </label>
                                <div x-data="{
                                    displayValue: '',
                                    qty: {{ $quantities[$item->id] ?? 1 }},
                                    updateValue() {
                                        let raw = this.displayValue.replace(/\D/g, '');
                                        quotes[{{ $item->id }}].price = raw;
                                        this.displayValue = raw ? new Intl.NumberFormat('id-ID').format(raw) : '';
                                    }
                                }">
                                    <input type="text"
                                           x-model="displayValue"
                                           @input="updateValue()"
                                           placeholder="Contoh: 150.000"
                                           class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                                    <template x-if="quotes[{{ $item->id }}].price > 0 && qty > 1">
                                        <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-1.5 flex items-center justify-between">
                                            <span>Subtotal ({{ $quantities[$item->id] ?? 1 }} {{ $item->unit?->name ?? 'Unit' }}):</span>
                                            <strong x-text="formatRupiah(quotes[{{ $item->id }}].price * qty)"></strong>
                                        </p>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Catatan / Estimasi Waktu</label>
                                <textarea x-model="quotes[{{ $item->id }}].notes"
                                          placeholder="Contoh: Butuh waktu 2 hari, bahan melamin doff..."
                                          class="w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none resize-none"
                                          rows="2"></textarea>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Sticky Bottom Bar --}}
        <div class="fixed bottom-0 inset-x-0 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-xl border-t border-zinc-200 dark:border-zinc-800 p-4 z-50">
            <div class="max-w-4xl mx-auto flex items-center justify-between gap-4">
                <div class="hidden sm:block text-sm text-zinc-600 dark:text-zinc-400">
                    Total Form: <strong>{{ count($items) }} Barang</strong>
                </div>
                @if(!$isCreator)
                    <button @click="submitToWhatsapp()"
                            class="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-6 rounded-xl shadow-lg shadow-emerald-500/30 flex items-center justify-center gap-2 transition-transform active:scale-95">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                        Kirim Penawaran ke WhatsApp
                    </button>
                @else
                    <a href="https://wa.me/?text={{ urlencode('Halo, silakan isi penawaran harga Anda melalui tautan berikut:' . "\n\n" . url()->current()) }}"
                       target="_blank"
                       class="w-full sm:w-auto bg-emerald-100 hover:bg-emerald-200 text-emerald-700 font-bold py-3 px-6 rounded-xl flex items-center justify-center gap-2 transition-transform active:scale-95 border border-emerald-300">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                        Bagikan Tautan ke Vendor
                    </a>
                @endif
            </div>
        </div>

        <script>
            (function() {
                const initAlpineData = () => {
                    if (window.Alpine) {
                        Alpine.data('vendorQuoteForm', () => ({
                            quotes: {},
                            itemsData: {!! json_encode($items->map(fn($i) => [
                                'id'   => $i->id,
                                'name' => $i->alias ? $i->alias . ' - ' . $i->name : $i->name,
                                'code' => $i->code ?? $i->sku,
                                'qty'  => $quantities[$i->id] ?? 1,
                                'unit' => $i->unit?->name ?? 'Unit',
                            ])->keyBy('id')) !!},
                            targetPhone: '{{ $phone }}',

                            init() {
                                for (const id in this.itemsData) {
                                    this.quotes[id] = { price: '', notes: '' };
                                }
                            },

                            formatRupiah(number) {
                                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(number);
                            },

                            submitToWhatsapp() {
                                let hasInput = false;
                                let message = `Halo, berikut adalah penawaran harga dari saya untuk form *{{ $title }}*:\n\n`;
                                let total = 0;
                                let counter = 1;

                                for (const id in this.quotes) {
                                    const quote = this.quotes[id];
                                    if (quote.price || quote.notes) {
                                        hasInput = true;
                                        const item = this.itemsData[id];
                                        message += `${counter}. *${item.name}* (${item.code})\n`;
                                        message += `   Jumlah Kebutuhan: ${item.qty} ${item.unit}\n`;
                                        if (quote.price) {
                                            let itemTotal = parseFloat(quote.price) * item.qty;
                                            if (item.qty > 1) {
                                                message += `   Harga Penawaran: ${this.formatRupiah(quote.price)} / ${item.unit} (Subtotal: ${this.formatRupiah(itemTotal)})\n`;
                                            } else {
                                                message += `   Harga Penawaran: ${this.formatRupiah(quote.price)} / ${item.unit}\n`;
                                            }
                                            total += itemTotal;
                                        }
                                        if (quote.notes) {
                                            message += `   Catatan: ${quote.notes}\n`;
                                        }
                                        message += `\n\n`;
                                        counter++;
                                    }
                                }

                                if (!hasInput) {
                                    alert('Silakan isi minimal 1 harga barang sebelum mengirim.');
                                    return;
                                }
                                if (total > 0) {
                                    message += `*Total Penawaran: ${this.formatRupiah(total)}*`;
                                }

                                let phone = this.targetPhone.replace(/\D/g, '');
                                if (phone.startsWith('0')) phone = '62' + phone.substring(1);
                                window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
                            }
                        }));
                    }
                };

                if (window.Alpine) {
                    initAlpineData();
                } else {
                    document.addEventListener('alpine:init', initAlpineData);
                }
            })();
        </script>
    @endif
</div>
