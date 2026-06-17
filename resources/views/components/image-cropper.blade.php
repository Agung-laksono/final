@props([
    'image' => null,
    'label' => 'Foto Barang (Crop Interaktif & Kompres WEBP)',
    'accept' => 'image/*',
    'id' => null
])

@php
    $wireModel = $attributes->wire('model')->value();
    $modalName = 'crop-modal-' . ($id ?? md5($wireModel ?? 'default'));
@endphp

<div x-data="window.imageCropperData('{{ $wireModel }}')" 
     x-init="$watch('isCropping', val => val ? Flux.modal('{{ $modalName }}').show() : Flux.modal('{{ $modalName }}').close())"
     @item-saved.window="resetCropper()"
     @reset-cropper.window="resetCropper()"
     class="relative">

    {{-- Modal Crop (1 Langkah) --}}
    <flux:modal name="{{ $modalName }}" class="md:w-[48rem]" style="max-width: 95vw;" x-on:close="cancelCrop()">
        <div class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Sesuaikan Pemotongan & Kompresi</div>
        
        {{-- Cropper Style Viewport --}}
        <div class="flex justify-center items-center bg-zinc-900 overflow-hidden relative rounded-xl h-[50vh]">
            
            {{-- Loading Indicator --}}
            <div x-show="isProcessing" class="absolute inset-0 z-50 flex items-center justify-center bg-zinc-900/50 backdrop-blur-sm">
                <flux:icon.arrow-path class="w-8 h-8 text-white animate-spin" />
            </div>

            {{-- Wrapper gambar dengan ukuran absolut sesuai rasio --}}
            <div class="relative" :style="`width: ${imgDispW}px; height: ${imgDispH}px;`" x-show="workingImgSrc">
                
                <img :src="workingImgSrc" class="absolute inset-0 w-full h-full" 
                     :style="`cursor: ${dragMode === 'move' ? 'grabbing' : 'grab'}`" 
                     @mousedown.prevent="onPointerDown($event, 'move')"
                     @touchstart.prevent="onPointerDown($event, 'move')" />
                
                {{-- Crop Box (Transparan di tengah, area di luarnya digelapkan oleh box-shadow) --}}
                <div class="absolute ring-1 ring-white/50 cursor-move"
                     :style="`left: ${cropX}px; top: ${cropY}px; width: ${cropW}px; height: ${cropH}px; box-shadow: 0 0 0 9999px rgba(0,0,0,0.6);`"
                     @mousedown.prevent="onPointerDown($event, 'move')"
                     @touchstart.prevent="onPointerDown($event, 'move')">
                    
                    {{-- Grid Lines --}}
                    <div class="absolute inset-0 pointer-events-none grid grid-cols-3 grid-rows-3">
                        <div class="border-r border-b border-white/30"></div>
                        <div class="border-r border-b border-white/30"></div>
                        <div class="border-b border-white/30"></div>
                        <div class="border-r border-b border-white/30"></div>
                        <div class="border-r border-b border-white/30"></div>
                        <div class="border-b border-white/30"></div>
                        <div class="border-r border-white/30"></div>
                        <div class="border-r border-white/30"></div>
                        <div></div>
                    </div>

                    {{-- Corner Handles (Diperbesar untuk touch screen) --}}
                    <div class="absolute -top-2.5 -left-2.5 w-5 h-5 bg-blue-500 cursor-nwse-resize rounded-full shadow border-2 border-white" @mousedown.prevent.stop="onPointerDown($event, 'nw')" @touchstart.prevent.stop="onPointerDown($event, 'nw')"></div>
                    <div class="absolute -top-2.5 -right-2.5 w-5 h-5 bg-blue-500 cursor-nesw-resize rounded-full shadow border-2 border-white" @mousedown.prevent.stop="onPointerDown($event, 'ne')" @touchstart.prevent.stop="onPointerDown($event, 'ne')"></div>
                    <div class="absolute -bottom-2.5 -left-2.5 w-5 h-5 bg-blue-500 cursor-nesw-resize rounded-full shadow border-2 border-white" @mousedown.prevent.stop="onPointerDown($event, 'sw')" @touchstart.prevent.stop="onPointerDown($event, 'sw')"></div>
                    <div class="absolute -bottom-2.5 -right-2.5 w-5 h-5 bg-blue-500 cursor-nwse-resize rounded-full shadow border-2 border-white" @mousedown.prevent.stop="onPointerDown($event, 'se')" @touchstart.prevent.stop="onPointerDown($event, 'se')"></div>
                </div>
            </div>
        </div>

        {{-- Toolbar Bawah (Rasio, Rotate/Flip, Kompresi) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            
            <div class="flex flex-col gap-3">
                {{-- Aspect Ratio --}}
                <div class="flex gap-2 items-center justify-center bg-zinc-100 dark:bg-zinc-800 p-2 rounded-lg">
                    <template x-for="ratio in ['1:1', '4:3', '16:9', 'Bebas']">
                        <button type="button" @click="setRatio(ratio)" 
                                class="px-3 py-1 text-xs font-medium rounded-md transition-colors"
                                :class="ratioLabel === ratio ? 'bg-white dark:bg-zinc-700 shadow text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                                x-text="ratio"></button>
                    </template>
                </div>
                
                {{-- Tools (Rotate/Flip) --}}
                <div class="flex gap-1.5 items-center justify-center bg-zinc-100 dark:bg-zinc-800 p-1.5 rounded-lg w-fit mx-auto">
                    <button type="button" @click="rotateLeft()" class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 transition-colors rounded-md hover:bg-white dark:hover:bg-zinc-700 shadow-sm" title="Putar Kiri">
                        <flux:icon.arrow-uturn-left class="w-4 h-4" />
                    </button>
                    <button type="button" @click="rotateRight()" class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 transition-colors rounded-md hover:bg-white dark:hover:bg-zinc-700 shadow-sm" title="Putar Kanan">
                        <flux:icon.arrow-uturn-right class="w-4 h-4" />
                    </button>
                    <div class="w-px h-4 bg-zinc-300 dark:bg-zinc-600 mx-1"></div>
                    <button type="button" @click="toggleFlipH()" class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 transition-colors rounded-md hover:bg-white dark:hover:bg-zinc-700 shadow-sm" title="Balik Horizontal">
                        <flux:icon.arrows-right-left class="w-4 h-4" />
                    </button>
                    <button type="button" @click="toggleFlipV()" class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 transition-colors rounded-md hover:bg-white dark:hover:bg-zinc-700 shadow-sm" title="Balik Vertikal">
                        <flux:icon.arrows-up-down class="w-4 h-4" />
                    </button>
                </div>
            </div>
            
            <div class="space-y-3">
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-zinc-500">Maks Resolusi <span x-show="imgNatW" class="text-[10px] text-zinc-400 ml-1" x-text="`(Asli: ${imgNatW}x${imgNatH}px)`"></span></span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300" x-text="maxSize + ' px'"></span>
                    </div>
                    <input type="range" x-model.number="maxSize" @input.debounce.300ms="updatePreviewSize()" min="400" max="2000" step="100" class="w-full accent-blue-500">
                </div>
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-zinc-500">Tingkat Kompresi WEBP</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300" x-text="(quality * 100) + '%'"></span>
                    </div>
                    <input type="range" x-model.number="quality" @input.debounce.300ms="updatePreviewSize()" min="0.1" max="1" step="0.1" class="w-full accent-blue-500">
                </div>
            </div>
        </div>

        <div class="flex flex-col-reverse md:flex-row justify-between items-center gap-4 mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-800">
            {{-- Info Asli -> Hasil Estimasi --}}
            <div class="flex items-center gap-3 text-xs w-full md:w-auto bg-zinc-100 dark:bg-zinc-800/50 p-2 rounded-lg px-3 border border-zinc-200 dark:border-zinc-800">
                <div class="text-zinc-500 flex flex-col md:flex-row md:gap-1">
                    <span class="font-medium text-zinc-600 dark:text-zinc-400">Asli:</span>
                    <span x-text="formatSize(originalSize)"></span>
                </div>
                <flux:icon.arrow-right class="w-3 h-3 text-zinc-300" />
                <div class="text-emerald-600 font-medium flex flex-col md:flex-row md:gap-1">
                    <span>Hasil (Estimasi):</span>
                    <span x-text="formatSize(previewSize)"></span>
                </div>
            </div>

            <div class="flex justify-end gap-2 w-full md:w-auto flex-wrap">
                <flux:button type="button" @click="cancelCrop()" variant="ghost">Batal</flux:button>
                <flux:button type="button" @click="applyOriginal()" variant="outline" x-bind:disabled="isProcessing" title="Simpan tanpa crop & kompresi">Gunakan Asli</flux:button>
                <flux:button type="button" @click="applyCrop()" variant="primary" x-bind:disabled="isProcessing">Terapkan & Simpan</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Tampilan saat KOSONG (Belum Ada File) --}}
    @if (!$image)
    <div x-show="!originalFile && !isCropping" x-cloak class="w-full">
        <div class="relative w-full aspect-square rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-700 flex flex-col items-center justify-center overflow-hidden bg-zinc-50 dark:bg-zinc-800/50 p-4">
            
            <div class="text-sm font-medium tracking-wider text-zinc-500 mb-6 text-center">Tambahkan Foto</div>

            <div class="flex gap-4 w-full justify-center">
                {{-- Tombol Kamera (Langsung Buka Kamera di HP) --}}
                <div class="relative group cursor-pointer flex flex-col items-center justify-center p-3 w-[45%] max-w-[120px] bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-sm rounded-xl hover:border-blue-500 dark:hover:border-blue-400 transition-all">
                    <flux:icon.camera class="w-8 h-8 mb-2 text-zinc-400 group-hover:text-blue-500 transition-colors" />
                    <span class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-300 text-center leading-tight">Buka<br>Kamera</span>
                    <input type="file" x-ref="fileInputCamera" @change="handleFile" accept="image/*" capture="environment" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" title="Buka Kamera" />
                </div>

                {{-- Tombol Galeri / File --}}
                <div class="relative group cursor-pointer flex flex-col items-center justify-center p-3 w-[45%] max-w-[120px] bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-sm rounded-xl hover:border-blue-500 dark:hover:border-blue-400 transition-all">
                    <flux:icon.photo class="w-8 h-8 mb-2 text-zinc-400 group-hover:text-blue-500 transition-colors" />
                    <span class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-300 text-center leading-tight">Pilih dari<br>Galeri</span>
                    <input type="file" x-ref="fileInputMain" @change="handleFile" accept="{{ $accept }}" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" title="Unggah Foto" />
                </div>
            </div>
            
        </div>
        <div class="mt-1 flex justify-center">
            <flux:error :name="$wireModel" />
        </div>
    </div>
    @endif

    {{-- Tampilan saat ADA FILE (Preview Hasil Crop) --}}
    <div x-show="originalFile && !isCropping" x-cloak class="w-full">
        <div class="relative w-full rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden bg-zinc-100 dark:bg-zinc-800 shadow-sm flex flex-col items-center justify-center min-h-[100px]">
            {{-- Gambar --}}
            <div class="relative w-full flex justify-center bg-white dark:bg-zinc-900 group">
                <img :src="croppedImgSrc || imgSrc" class="w-full h-auto max-h-[250px] object-contain">
                
                {{-- Tombol Edit Crop --}}
                <button type="button" @click="isCropping = true" class="absolute top-2 right-2 p-1.5 bg-white/90 dark:bg-zinc-800/90 rounded-md shadow-md hover:bg-zinc-100 dark:hover:bg-zinc-700 border border-zinc-200 dark:border-zinc-700 z-20" title="Sesuaikan Pemotongan">
                    <flux:icon.pencil-square class="w-4 h-4 text-zinc-700 dark:text-zinc-300" />
                </button>
            </div>

            {{-- Ganti Foto Buttons (Permanent) --}}
            <div class="flex gap-2 w-full justify-center bg-zinc-50 dark:bg-zinc-800 border-t border-zinc-200 dark:border-zinc-700 p-2">
                <button type="button" @click="removeImage()" class="flex items-center justify-center gap-1 hover:bg-red-50 hover:text-red-600 text-zinc-500 transition px-2 py-1.5 rounded-lg border border-transparent hover:border-red-200 shadow-sm" title="Hapus Foto">
                    <flux:icon.trash class="w-4 h-4" />
                </button>
                <div class="relative cursor-pointer flex items-center justify-center gap-1.5 hover:text-blue-600 text-zinc-600 dark:text-zinc-300 transition bg-white dark:bg-zinc-700 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-600 shadow-sm flex-1">
                    <flux:icon.camera class="w-4 h-4" />
                    <span class="text-[11px] font-semibold">Kamera</span>
                    <input type="file" x-ref="fileInputAltCamera" @change="handleFile" accept="image/*" capture="environment" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" title="Gunakan Kamera">
                </div>
                <div class="relative cursor-pointer flex items-center justify-center gap-1.5 hover:text-blue-600 text-zinc-600 dark:text-zinc-300 transition bg-white dark:bg-zinc-700 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-600 shadow-sm flex-1">
                    <flux:icon.photo class="w-4 h-4" />
                    <span class="text-[11px] font-semibold">Galeri</span>
                    <input type="file" x-ref="fileInputAlt" @change="handleFile" accept="{{ $accept }}" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" title="Pilih dari Galeri">
                </div>
            </div>
        </div>
        
        {{-- Info Ukuran Kompresi (Mini Text di bawah gambar) --}}
        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400 text-center truncate w-full" title="Ukuran Asli -> Kompresi">
            <span x-text="formatSize(originalSize)"></span> <flux:icon.arrow-right class="w-3 h-3 inline text-zinc-300 mx-1" /> <span x-text="formatSize(newSize)" class="font-semibold text-green-600 dark:text-green-400"></span>
        </div>
    </div>
    
    {{-- Preview Gambar (dari Server/Database saat Mode Edit awal, ditimpa ketika originalFile ada) --}}
    @if ($image)
    <div x-show="!originalFile && !isCropping" x-cloak class="w-full">
        <div class="relative w-full rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden bg-zinc-100 dark:bg-zinc-800 shadow-sm flex flex-col items-center justify-center min-h-[100px]">
            {{-- Gambar --}}
            <div class="relative w-full flex justify-center bg-white dark:bg-zinc-900 group">
                @if (is_string($image) && str_starts_with($image, 'data:image'))
                    <img src="{{ $image }}" class="w-full h-auto max-h-[250px] object-contain">
                @elseif (!is_object($image))
                    <img src="{{ asset('storage/' . $image) }}" class="w-full h-auto max-h-[250px] object-contain">
                @endif
            </div>

            {{-- Ganti Foto Buttons (Permanent) --}}
            <div class="flex gap-2 w-full justify-center bg-zinc-50 dark:bg-zinc-800 border-t border-zinc-200 dark:border-zinc-700 p-2">
                <button type="button" @click="removeImage()" class="flex items-center justify-center gap-1 hover:bg-red-50 hover:text-red-600 text-zinc-500 transition px-2 py-1.5 rounded-lg border border-transparent hover:border-red-200 shadow-sm" title="Hapus Foto">
                    <flux:icon.trash class="w-4 h-4" />
                </button>
                <div class="relative cursor-pointer flex items-center justify-center gap-1.5 hover:text-blue-600 text-zinc-600 dark:text-zinc-300 transition bg-white dark:bg-zinc-700 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-600 shadow-sm flex-1">
                    <flux:icon.camera class="w-4 h-4" />
                    <span class="text-[11px] font-semibold">Kamera</span>
                    <input type="file" x-ref="fileInputServerCamera" @change="handleFile" accept="image/*" capture="environment" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" title="Gunakan Kamera">
                </div>
                <div class="relative cursor-pointer flex items-center justify-center gap-1.5 hover:text-blue-600 text-zinc-600 dark:text-zinc-300 transition bg-white dark:bg-zinc-700 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-600 shadow-sm flex-1">
                    <flux:icon.photo class="w-4 h-4" />
                    <span class="text-[11px] font-semibold">Galeri</span>
                    <input type="file" x-ref="fileInputServer" @change="handleFile" accept="{{ $accept }}" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" title="Pilih dari Galeri">
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
