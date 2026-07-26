<?php
$content = file_get_contents('resources/views/livewire/global/item-gallery-modal.blade.php');

// 1. Update master card @click
$clickOld = <<<BLADE
                            @click="
                              if (! {{ \$item->is_active ? 'true' : 'false' }}) return;
                              
                              let hasVariants = {{ \$item->customVariants->isNotEmpty() ? 'true' : 'false' }};
                              let variantsList = [];
                              
                              @if(\$item->customVariants->isNotEmpty())
                                  @foreach(\$item->customVariants as \$variant)
                                      variantsList.push({
                                          id: {{ \$variant->id }},
                                          customer: '{{ addslashes(\$variant->salesOrder?->customer?->name ?? 'Pelanggan Umum') }}',
                                          image: '{{ \$variant->custom_attachments ? asset('storage/' . \$variant->custom_attachments[0]) : '' }}',
                                          image_raw: '{{ \$variant->custom_attachments ? \$variant->custom_attachments[0] : '' }}',
                                          unit_price: {{ (float) \$variant->unit_price }},
                                          date: '{{ \$variant->created_at?->format('d M Y') ?? '' }}',
                                          attributes: '{{ addslashes(!empty(\$variant->custom_attributes) ? collect(\$variant->custom_attributes)->map(function(\$v, \$k) { return is_numeric(\$k) ? (is_array(\$v) ? implode(': ', \$v) : \$v) : \$k . ': ' . (is_array(\$v) ? implode(': ', \$v) : \$v); })->implode(' | ') : '') }}',
                                          note: '{{ addslashes(\$variant->note ?? '') }}',
                                          custom_attributes: @json(\$variant->custom_attributes ?? []),
                                          custom_attachments: @json(\$variant->custom_attachments ?? [])
                                      });
                                  @endforeach
                              @endif

                              const activeV = activeVariants[{{ \$item->id }}];
                              if (activeV) {
                                  // Add selected active variant immediately
                                  \$dispatch('add-variant-to-cart', [{ item: {
                                      item_id: {{ \$item->id }},
                                      name: @js(\$item->name),
                                      code: @js(\$item->code ?? '0001'),
                                      unit_price: activeV.unit_price || {{ \$context === 'sales' ? (\$item->selling_price ?? 0) : (\$item->purchase_price ?? 0) }},
                                      image: activeV.image_raw || @js(\$item->image),
                                      unit: @js(\$item->unit?->name ?? 'pcs'),
                                      has_history: true,
                                      custom_attributes: activeV.custom_attributes || [],
                                      custom_attachments: activeV.custom_attachments || [],
                                      note: activeV.note ? activeV.note + '<br><strong>Salinan Pesanan: ' + activeV.customer + '</strong>' : '<strong>Salinan Pesanan: ' + activeV.customer + '</strong>'
                                  } }]);
                              } else if (hasVariants) {
                                  openVariantModal({ item_id: {{ \$item->id }}, name: @js(\$item->name), code: @js(\$item->code ?? '0001'), unit_price: {{ \$context === 'sales' ? (\$item->selling_price ?? 0) : (\$item->purchase_price ?? 0) }}, image: @js(\$item->image), unit: @js(\$item->unit?->name ?? 'pcs'), has_history: {{ in_array(\$item->id, \$itemsWithHistory) ? 'true' : 'false' }}, custom_attributes: [], custom_attachments: [], note: '' }, true, variantsList);
                              } else {
                                  \$dispatch('item-selected', { item: { item_id: {{ \$item->id }}, name: @js(\$item->name), code: @js(\$item->code ?? '0001'), unit_price: {{ \$context === 'sales' ? (\$item->selling_price ?? 0) : (\$item->purchase_price ?? 0) }}, image: @js(\$item->image), unit: @js(\$item->unit?->name ?? 'pcs'), has_history: {{ in_array(\$item->id, \$itemsWithHistory) ? 'true' : 'false' }}, custom_attributes: [], custom_attachments: [], note: '' } });
                              }
                            "
BLADE;

$clickNew = <<<BLADE
                            @click="
                              if (! {{ \$item->is_active ? 'true' : 'false' }}) return;
                              
                              playSelectSound();
                              const activeV = activeVariants[{{ \$item->id }}];
                              
                              if (activeV) {
                                  \$dispatch('add-variant-to-cart', [{ item: {
                                      item_id: {{ \$item->id }},
                                      name: @js(\$item->name),
                                      code: @js(\$item->code ?? '0001'),
                                      unit_price: activeV.unit_price || {{ \$context === 'sales' ? (\$item->selling_price ?? 0) : (\$item->purchase_price ?? 0) }},
                                      image: activeV.image_raw || @js(\$item->image),
                                      unit: @js(\$item->unit?->name ?? 'pcs'),
                                      has_history: true,
                                      custom_attributes: activeV.custom_attributes || [],
                                      custom_attachments: activeV.custom_attachments || [],
                                      note: activeV.note ? activeV.note + '<br><strong>Salinan Pesanan: ' + activeV.customer + '</strong>' : '<strong>Salinan Pesanan: ' + activeV.customer + '</strong>'
                                  } }]);
                              } else {
                                  \$dispatch('item-selected', { item: { item_id: {{ \$item->id }}, name: @js(\$item->name), code: @js(\$item->code ?? '0001'), unit_price: {{ \$context === 'sales' ? (\$item->selling_price ?? 0) : (\$item->purchase_price ?? 0) }}, image: @js(\$item->image), unit: @js(\$item->unit?->name ?? 'pcs'), has_history: {{ in_array(\$item->id, \$itemsWithHistory) ? 'true' : 'false' }}, custom_attributes: [], custom_attachments: [], note: '' } });
                              }
                            "
BLADE;
$content = str_replace($clickOld, $clickNew, $content);

// 2. Add More Button and Expanded Cards
$moreButtonOld = <<<BLADE
                                     @endforeach
                                 </div>
                             @endif
                        </div>
BLADE;
$moreButtonNew = <<<BLADE
                                     @endforeach
                                     @if(collect(\$item->customVariants)->filter(fn(\$v) => !empty(\$v->custom_attachments))->groupBy(fn(\$v) => \$v->custom_attachments[0])->count() > 0)
                                         <div wire:click.stop="toggleVariants({{ \$item->id }})" class="w-7 h-5 xl:w-8 shrink-0 bg-white/90 dark:bg-zinc-800/90 rounded md:rounded-lg border shadow-sm text-[8px] font-black flex items-center justify-center cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors text-indigo-600 dark:text-indigo-400 mt-1 mb-1">
                                             {{ in_array(\$item->id, \$expandedItemIds) ? 'Less' : 'More' }}
                                         </div>
                                     @endif
                                 </div>
                             @endif
                        </div>
BLADE;
$content = str_replace($moreButtonOld, $moreButtonNew, $content);

$endOfCardOld = <<<BLADE
                    </div>
                @empty
BLADE;
$endOfCardNew = <<<BLADE
                    </div>
                    
                    {{-- Expanded Variant Cards (Sibling to Master Card) --}}
                    @if(in_array(\$item->id, \$expandedItemIds))
                        @foreach(\$item->customVariants->sortByDesc('created_at')->take(12) as \$v)
                            @php
                                \$customer = \$v->salesOrder?->customer?->name;
                                \$attrs = !empty(\$v->custom_attributes) ? collect(\$v->custom_attributes)->map(function(\$val, \$k) {
                                    \$valStr = is_array(\$val) ? implode(': ', \$val) : \$val;
                                    return is_numeric(\$k) ? \$valStr : \$k . ': ' . \$valStr;
                                })->implode(' | ') : '';
                            @endphp
                            <div @click="
                                playSelectSound();
                                \$dispatch('add-variant-to-cart', [{ item: {
                                    item_id: {{ \$item->id }},
                                    name: @js(\$item->name),
                                    code: @js(\$item->code ?? '0001'),
                                    unit_price: {{ (float) \$v->unit_price }},
                                    image: '{{ \$v->custom_attachments ? \$v->custom_attachments[0] : '' }}',
                                    unit: @js(\$item->unit?->name ?? 'pcs'),
                                    has_history: true,
                                    custom_attributes: @json(\$v->custom_attributes ?? []),
                                    custom_attachments: @json(\$v->custom_attachments ?? []),
                                    note: @js(\$v->note ? \$v->note . '<br><strong>Salinan Pesanan: ' . (\$customer ?? 'Pelanggan Umum') . '</strong>' : '<strong>Salinan Pesanan: ' . (\$customer ?? 'Pelanggan Umum') . '</strong>')
                                } }]);
                             "
                             class="relative bg-white dark:bg-zinc-900 border-2 border-indigo-200 dark:border-indigo-900/60 rounded-xl overflow-hidden hover:border-indigo-500 dark:hover:border-indigo-400 hover:shadow-lg cursor-pointer transition-all duration-300 flex flex-col h-full group">
                                
                                {{-- Badge Pemesan --}}
                                <div class="absolute right-2 top-2 z-10">
                                    <span class="inline-block px-1.5 py-0.5 text-[8px] font-bold bg-indigo-600/90 text-white rounded border border-white/20 backdrop-blur-sm shadow-sm">{{ \$customer ?? 'Pelanggan Umum' }}</span>
                                </div>
    
                                {{-- Gambar Atas --}}
                                <div class="relative w-full aspect-[4/3] bg-zinc-100 dark:bg-zinc-900/50 overflow-hidden border-b border-indigo-100 dark:border-indigo-900/40">
                                    @if(\$v->custom_attachments)
                                        <img src="{{ asset('storage/' . \$v->custom_attachments[0]) }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                    @else
                                        <div class="w-full h-full flex flex-col items-center justify-center text-zinc-300"><flux:icon.photo class="w-10 h-10 mb-1 opacity-50" /></div>
                                    @endif
                                </div>
                                
                                {{-- Informasi Bawah --}}
                                <div class="p-3 flex flex-col flex-1">
                                    <div class="mb-2 flex-1">
                                        <h4 class="font-bold text-zinc-800 dark:text-zinc-200 text-xs sm:text-sm leading-tight flex items-center gap-1.5 flex-wrap">
                                            <span>Custom Order</span>
                                            <span class="px-1 py-0.2 rounded bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 text-[7px] font-black uppercase tracking-wider border border-indigo-200 dark:border-indigo-800">CUSTOM</span>
                                        </h4>
                                        <div class="text-[9px] text-zinc-500 mt-1 line-clamp-2">{{ \$attrs ?: (\$v->note ?: 'Tanpa detail atribut') }}</div>
                                    </div>
                                    <div class="mt-auto flex items-end justify-between pt-1.5 border-t border-indigo-100 dark:border-zinc-800/50">
                                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Rp{{ number_format(\$v->unit_price, 0, ',', '.') }}</span>
                                        <span class="text-[8px] text-zinc-400 font-mono">{{ \$v->created_at?->format('d M Y') }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                @empty
BLADE;
$content = str_replace($endOfCardOld, $endOfCardNew, $content);

file_put_contents('resources/views/livewire/global/item-gallery-modal.blade.php', $content);
echo "Replaced master click and added expanded cards.\n";
