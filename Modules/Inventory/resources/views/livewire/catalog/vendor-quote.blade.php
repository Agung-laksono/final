<div x-data="vendorQuoteForm()" class="bg-zinc-50 dark:bg-zinc-950 min-h-screen">
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

    {{-- Content --}}
    <div class="max-w-4xl mx-auto px-4 py-6 mb-24">
        <div class="bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300 p-4 rounded-xl mb-6 text-sm flex items-start gap-3 border border-blue-200 dark:border-blue-800">
            <flux:icon.information-circle class="w-5 h-5 shrink-0 mt-0.5" />
            <p>Silakan isi harga penawaran Anda (misal: harga finishing) untuk setiap barang di bawah ini. Setelah selesai, klik tombol <strong>Kirim Penawaran ke WhatsApp</strong> di bagian bawah.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($items as $item)
                <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden shadow-sm border border-zinc-200 dark:border-zinc-800 flex flex-col">
                    {{-- Image --}}
                    <div class="aspect-[4/3] bg-zinc-100 dark:bg-zinc-800 relative">
                        @if($item->image)
                            <img src="{{ asset('storage/' . $item->image) }}" class="w-full h-full object-cover">
                        @else
                            <div class="absolute inset-0 flex items-center justify-center">
                                <flux:icon.photo class="w-12 h-12 text-zinc-300 dark:text-zinc-700" />
                            </div>
                        @endif
                        <div class="absolute top-2 right-2 bg-black/60 backdrop-blur-sm text-white px-2 py-1 rounded text-xs font-mono">
                            {{ $item->sku }}
                        </div>
                    </div>
                    
                    {{-- Details & Form --}}
                    <div class="p-4 flex-1 flex flex-col">
                        <div class="mb-4">
                            <h3 class="font-bold text-zinc-900 dark:text-white leading-tight mb-1">{{ $item->alias ?: $item->name }}</h3>
                            <div class="text-xs text-zinc-500 flex items-center gap-2 flex-wrap">
                                <span class="bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded">{{ $item->category?->name ?? 'Kategori' }}</span>
                                @if($item->length && $item->width && $item->height)
                                    <span>P: {{ $item->length }} × L: {{ $item->width }} × T: {{ $item->height }} {{ $item->dimension_unit }}</span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="mt-auto space-y-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                            <div>
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Harga Borongan / {{ $item->unit?->name ?? 'Unit' }} (Rp)</label>
                                <div x-data="{ 
                                    displayValue: '', 
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
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Sticky Bottom Action Bar --}}
    <div class="fixed bottom-0 inset-x-0 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-xl border-t border-zinc-200 dark:border-zinc-800 p-4 z-50">
        <div class="max-w-4xl mx-auto flex items-center justify-between gap-4">
            <div class="hidden sm:block text-sm text-zinc-600 dark:text-zinc-400">
                Total Form: <strong>{{ count($items) }} Barang</strong>
            </div>
            <button @click="submitToWhatsapp()" 
                    class="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-6 rounded-xl shadow-lg shadow-emerald-500/30 flex items-center justify-center gap-2 transition-transform active:scale-95">
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                Kirim Penawaran ke WhatsApp
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('vendorQuoteForm', () => ({
                quotes: {},
                itemsData: @json($items->map(fn($i) => ['id' => $i->id, 'name' => $i->alias ?: $i->name, 'sku' => $i->sku])->keyBy('id')),
                targetPhone: '{{ $phone }}',
                
                init() {
                    // Initialize empty quotes
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
                            
                            message += `${counter}. *${item.name}* (SKU: ${item.sku})\n`;
                            
                            if (quote.price) {
                                message += `   Harga: ${this.formatRupiah(quote.price)}\n`;
                                total += parseFloat(quote.price);
                            }
                            if (quote.notes) {
                                message += `   Catatan: ${quote.notes}\n`;
                            }
                            message += `\n`;
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
                    
                    // Format phone number (remove leading 0 and replace with 62)
                    let phone = this.targetPhone.replace(/\D/g, '');
                    if (phone.startsWith('0')) {
                        phone = '62' + phone.substring(1);
                    }
                    
                    const waUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
                    window.open(waUrl, '_blank');
                }
            }));
        });
    </script>
</div>
