@props([
    'label' => 'Unggah Foto Barang',
    'id' => 'image-upload-' . uniqid(),
    'model' => null,
    'aspectRatio' => 1,
    'hasError' => false,
    'initialPreview' => null
])

<div x-data="imageUploadComponent('{{ $attributes->wire('model')->value() }}', '{{ $initialPreview }}')" 
     class="space-y-4 w-full"
     x-cloak>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <input type="hidden" x-ref="lwInput" {{ $attributes->wire('model') }}>

    <label class="text-xs font-black uppercase text-slate-400 dark:text-slate-500 tracking-widest ms-2">
        {{ $label }} 
        @if($attributes->has('required') || $hasError)
            <span class="text-rose-500">*</span>
        @endif
    </label>
    
    <div class="relative">
        {{-- Input file untuk galeri --}}
        <input type="file" x-ref="fileInput" id="{{ $id }}" class="hidden" accept="image/*" @change="handleFileSelect($event)">
        {{-- Input file untuk kamera --}}
        <input type="file" x-ref="cameraInput" id="{{ $id }}-camera" class="hidden" accept="image/*" capture="environment" @change="handleFileSelect($event)">
        
        <div @dragover.prevent="dragOver = true"
             @dragleave.prevent="dragOver = false"
             @drop.prevent="handleFileDrop($event)"
             :class="{
                'border-blue-600 bg-blue-600/5 dark:bg-blue-600/10 scale-[1.01]': dragOver,
                'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50': !dragOver && !{{ $hasError ? 'true' : 'false' }},
                'border-rose-300 dark:border-rose-900 bg-rose-50/30 dark:bg-rose-950/20': !dragOver && {{ $hasError ? 'true' : 'false' }},
                'min-h-[220px]': true
             }"
             class="relative border-2 border-dashed rounded-[2.5rem] transition-all duration-300 overflow-hidden flex flex-col items-center justify-center">
            
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-blue-600/5 rounded-full blur-3xl transition-colors"></div>

            <!-- Empty State -->
            <template x-if="!previewUrl && !isCropping">
                <div class="text-center p-8 w-full h-full flex flex-col items-center justify-center relative z-10">
                    <div class="w-16 h-16 bg-white dark:bg-slate-800 rounded-2xl shadow-premium border border-slate-100 dark:border-slate-700 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-slate-400 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <p class="text-sm font-black text-slate-800 dark:text-slate-200 uppercase tracking-tight">Pilih atau Seret Foto</p>
                    <p class="text-[9px] font-bold text-slate-400 dark:text-slate-500 mt-1 uppercase tracking-widest text-center">FOTO AKAN DI-CROP KOTAK OTOMATIS</p>
                    
                    {{-- Tombol Galeri & Kamera --}}
                    <div class="flex items-center gap-3 mt-5">
                        <label for="{{ $id }}" class="cursor-pointer inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-black uppercase tracking-widest hover:border-blue-600 hover:text-blue-600 shadow-sm transition-all active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Galeri
                        </label>
                        <label for="{{ $id }}-camera" class="cursor-pointer inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-blue-600/20 hover:bg-blue-700 transition-all active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Kamera
                        </label>
                    </div>
                </div>
                     {{-- Cropper Modal (Teleported to Body to avoid nesting issues) --}}
                     <template x-teleport="body">
                         <div x-show="isCropping" 
                              style="display: none;"
                              class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-6"
                              x-transition:enter="transition ease-out duration-300"
                              x-transition:enter-start="opacity-0 backdrop-blur-none"
                              x-transition:enter-end="opacity-100 backdrop-blur-sm"
                              x-transition:leave="transition ease-in duration-200"
                              x-transition:leave-start="opacity-100 backdrop-blur-sm"
                              x-transition:leave-end="opacity-0 backdrop-blur-none">
                             
                             {{-- Backdrop overlay --}}
                             <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm" @click="cancelCrop()"></div>
                             
                             {{-- Modal Container --}}
                             <div class="relative w-full max-w-4xl max-h-[90vh] bg-black md:bg-slate-900 rounded-[2.5rem] shadow-2xl flex flex-col overflow-hidden border border-white/10"
                                  x-show="isCropping"
                                  x-transition:enter="transition ease-out duration-300 delay-100"
                                  x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                                  x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                  x-transition:leave="transition ease-in duration-200"
                                  x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                  x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                                 
                                 {{-- Header --}}
                                 <div class="flex items-center justify-between p-4 md:px-8 md:py-6 border-b border-white/10 bg-black md:bg-transparent">
                                     <div class="flex items-center gap-3">
                                         <div class="w-10 h-10 rounded-2xl bg-blue-600/20 flex items-center justify-center text-blue-600">
                                             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                         </div>
                                         <div>
                                             <h3 class="text-white font-black uppercase tracking-widest text-xs md:text-base">Potong Foto</h3>
                                             <p class="text-[9px] md:text-[10px] font-bold text-slate-500 uppercase tracking-widest">Atur komposisi gambar produk</p>
                                         </div>
                                     </div>
                                     <button @click="cancelCrop()" type="button" class="text-slate-400 hover:text-white transition-all p-2 bg-white/5 hover:bg-white/10 rounded-xl">
                                         <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                     </button>
                                 </div>
            
                                 {{-- Canvas Area --}}
                                 <div class="flex-1 w-full min-h-[40vh] md:min-h-[50vh] max-h-[60vh] relative bg-black md:bg-slate-950/30 overflow-hidden">
                                     <img id="crop-image-{{ $id }}" class="block max-w-full w-full">
                                 </div>
                                 
                                 {{-- Controls Area --}}
                                 <div class="p-4 md:px-8 md:py-6 bg-black md:bg-slate-900 border-t border-white/10 flex flex-col-reverse md:flex-row items-center justify-between gap-6 mt-auto">
                                     {{-- Action Buttons --}}
                                     <div class="flex items-center gap-3 w-full md:w-auto">
                                         <button @click="cancelCrop()" type="button" class="hidden md:block px-6 py-3 text-xs font-black uppercase text-slate-400 hover:text-white transition-colors">Batal</button>
                                         <button @click="applyCrop()" type="button" class="w-full md:w-auto px-10 py-4 md:py-3.5 bg-blue-600 text-white text-xs md:text-[11px] font-black uppercase rounded-2xl md:rounded-xl shadow-lg shadow-blue-600/20 hover:scale-[1.02] active:scale-95 transition-transform flex justify-center items-center gap-3">
                                             <svg class="w-5 h-5 md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                             Simpan Hasil Potongan
                                         </button>
                                     </div>
                                     
                                     {{-- Aspect Ratio Controls --}}
                                     <div class="flex items-center gap-2 w-full md:w-auto justify-end bg-white/5 p-1.5 rounded-2xl">
                                         <button @click="aspect = null; cropper.setAspectRatio(NaN)" 
                                                 :class="!aspect ? 'bg-white/10 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
                                                 class="px-4 py-2.5 md:py-2 text-[10px] md:text-[11px] font-black tracking-widest rounded-xl transition-all uppercase flex-1 md:flex-none">
                                             Bebas
                                         </button>
                                         <button @click="aspect = 1; cropper.setAspectRatio(1)" 
                                                 :class="aspect === 1 ? 'bg-white/10 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
                                                 class="px-4 py-2.5 md:py-2 text-[10px] md:text-[11px] font-black tracking-widest rounded-xl transition-all uppercase flex-1 md:flex-none">
                                             Aspect 1:1
                                         </button>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     </template>
            <!-- Final Preview View -->
            <template x-if="previewUrl && !isCropping">
                <div class="relative w-full h-full flex items-center justify-center p-4 z-10">
                    <img :src="previewUrl" class="max-h-[350px] w-auto rounded-3xl shadow-premium object-contain border border-slate-100 dark:border-slate-800">
                    
                    {{-- Loading Overlay --}}
                    <div x-show="isProcessing" class="absolute inset-0 bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm flex flex-col items-center justify-center rounded-3xl z-30">
                        <div class="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mb-2"></div>
                        <p class="text-[8px] font-black text-blue-600 uppercase tracking-widest">Processing & Syncing...</p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Meta Controls (Premium Adjustment Bar - Responsive Fix) -->
        <div x-show="hasImage && !isCropping" 
             class="mt-4 bg-slate-50 dark:bg-slate-900/80 p-4 rounded-[2rem] border border-slate-100 dark:border-slate-800 shadow-inner flex flex-col gap-5 animate-in fade-in slide-in-from-bottom-2 duration-500">
            
            {{-- Row Atas: Kompresi (Penyederhanaan Jarak) --}}
            {{-- Row 1: Opsi Dimensi --}}
            <div class="w-full">
                <p class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[0.2em] mb-2 ms-1">Dimensi Maksimal</p>
                <div class="flex flex-wrap bg-white dark:bg-slate-950 p-1 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <template x-for="dim in dimensionLevels" :key="dim.val">
                        <button @click="dimensionLevel = dim.val; processImage()" 
                                type="button"
                                :class="dimensionLevel === dim.val 
                                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' 
                                    : 'text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300'"
                                class="flex-1 px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-tight transition-all duration-300">
                            <span x-text="dim.label"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Row 2: Opsi Kualitas Kompresi --}}
            <div class="w-full">
                <p class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[0.2em] mb-2 ms-1">Tingkat Kompresi</p>
                <div class="flex flex-wrap bg-white dark:bg-slate-950 p-1 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <template x-for="qual in qualityLevels" :key="qual.key">
                        <button @click="qualityLevel = qual.key; processImage()" 
                                type="button"
                                :class="qualityLevel === qual.key 
                                    ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' 
                                    : 'text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300'"
                                class="flex-1 px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-tight transition-all duration-300">
                            <span x-text="qual.label"></span>
                        </button>
                    </template>
                </div>
            </div>
            
            {{-- Row Bawah: Info Ukuran & Tombol Aksi --}}
            <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-slate-200/60 dark:border-slate-800/60">
                {{-- File Size Info --}}
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-500 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div>
                        <p class="text-[8px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest leading-none mb-0.5">Ukuran</p>
                        <p class="text-[11px] font-black text-blue-600 tracking-tight" x-text="computedSize"></p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex gap-2">
                    <button @click="reCrop()" type="button" class="p-2.5 bg-white dark:bg-slate-800 text-slate-500 hover:text-blue-600 rounded-xl border border-slate-100 dark:border-slate-700 shadow-sm transition-all active:scale-95" title="Ulangi Potong">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </button>
                    <button @click="clear()" type="button" class="p-2.5 bg-rose-50 dark:bg-rose-950/30 text-rose-500 rounded-xl border border-rose-100 dark:border-rose-900 shadow-sm transition-all hover:bg-rose-500 hover:text-white active:scale-95" title="Hapus Foto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function imageUploadComponent(modelName, initialPreview) {
        return {
            dragOver: false,
            isCropping: false,
            isProcessing: false,
            hasImage: initialPreview ? true : false,
            previewUrl: initialPreview || null,
            originalFile: null,
            croppedBaseBlob: null,
            processedBlob: null,
            cropper: null,
            aspect: {{ $aspectRatio ?? 'null' }},
            dimensionLevel: 800,
            qualityLevel: 'medium',
            computedSize: '0 KB',
            
            dimensionLevels: [
                { val: 1000, label: '1000px' },
                { val: 800, label: '800px' },
                { val: 600, label: '600px' },
                { val: 400, label: '400px' },
                { val: 200, label: '200px' }
            ],
            
            qualityLevels: [
                { key: 'high', label: 'Tinggi', val: 0.9 },
                { key: 'medium', label: 'Sedang', val: 0.7 },
                { key: 'low', label: 'Rendah', val: 0.5 }
            ],

            init() {
                // Initialize default preview if exists
                if (initialPreview) {
                    this.hasImage = true;
                    this.previewUrl = initialPreview;
                }
            },

            handleFileSelect(e) {
                const file = e.target.files[0];
                if (file) this.startCropping(file);
            },

            handleFileDrop(e) {
                this.dragOver = false;
                const file = e.dataTransfer.files[0];
                if (file) this.startCropping(file);
            },

            reCrop() {
                if (this.originalFile) this.startCropping(this.originalFile);
            },

            cancelCrop() {
                if (this.cropper) {
                    this.cropper.destroy();
                    this.cropper = null;
                }
                this.isCropping = false;
                
                // If we didn't have an image before, clear the file input
                if (!this.hasImage) {
                    this.$refs.fileInput.value = '';
                    this.$refs.cameraInput.value = '';
                } else if (this.originalFile) {
                    // Re-create previous preview if needed
                    this.previewUrl = URL.createObjectURL(this.originalFile);
                }
            },

            startCropping(file) {
                if (!file) return;
                this.originalFile = file;
                this.isCropping = true;
                this.hasImage = false;
                
                if (this.previewUrl && !initialPreview) {
                    URL.revokeObjectURL(this.previewUrl);
                }
                this.previewUrl = null;

                this.$nextTick(() => {
                    const imgUrl = URL.createObjectURL(file);
                    const img = document.getElementById(`crop-image-{{ $id }}`);
                    
                    if (!img) {
                        console.error("Image element not found!");
                        return;
                    }

                    img.onload = () => {
                        if (this.cropper) this.cropper.destroy();
                        
                        // Beri jeda sedikit agar animasi x-show selesai
                        setTimeout(() => {
                            this.cropper = new window.Cropper(img, {
                                aspectRatio: this.aspect,
                                viewMode: 2,
                                dragMode: 'move',
                                autoCropArea: 0.8,
                                guides: true,
                                center: true,
                                highlight: false,
                                responsive: true,
                            });
                        }, 350);
                    };

                    img.src = imgUrl;
                });
            },

            toggleAspect() {
                this.aspect = (this.aspect === 1 ? null : 1);
                if (this.cropper) this.cropper.setAspectRatio(this.aspect);
            },

            applyCrop() {
                if (!this.cropper) return;
                
                // Mencegah HP lag: Langsung paksa canvas untuk resize gambar raksasa (5-8MB) menjadi maksimal 1200px
                const canvas = this.cropper.getCroppedCanvas({
                    maxWidth: 1200,
                    maxHeight: 1200,
                    fillColor: '#fff',
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                
                canvas.toBlob((blob) => {
                    this.croppedBaseBlob = blob;
                    this.processedBlob = blob;
                    
                    // Send data to Livewire
                    this.$wire.set('{{ $attributes->wire('model')->value() }}', this.previewUrl);
                    
                    this.isCropping = false;
                    this.hasImage = true;
                    this.isProcessing = false;
                    this.cropper.destroy();
                    this.cropper = null;
                    this.processImage();
                }, this.originalFile.type || 'image/jpeg', 0.9);
            },

            cancelCrop() {
                this.isCropping = false;
                this.$dispatch(`close-cropper-{{ $id }}`);
                if (this.cropper) {
                    this.cropper.destroy();
                    this.cropper = null;
                }
                if (!this.previewUrl) this.clear();
            },

            async processImage() {
                this.isProcessing = true;
                
                const selectedQual = this.qualityLevels.find(q => q.key === this.qualityLevel) || this.qualityLevels[1];
                const maxDim = this.dimensionLevel;
                
                // Gunakan croppedBaseBlob sebagai sumber utama agar gambar tidak pecah saat ukurannya dinaikkan lagi
                const finalBlob = await this.resizeAndCompress(this.croppedBaseBlob || this.processedBlob, selectedQual.val, maxDim);
                
                this.finishProcessing(finalBlob);
            },

            finishProcessing(blob) {
                if (this.previewUrl && !initialPreview) URL.revokeObjectURL(this.previewUrl);
                
                this.processedBlob = blob;
                this.previewUrl = URL.createObjectURL(blob);
                this.computedSize = this.formatBytes(blob.size);

                // Convert blob to base64 dan sinkronisasi dengan Livewire backend
                let reader = new FileReader();
                reader.readAsDataURL(blob); 
                reader.onloadend = () => {
                    let base64data = reader.result;                
                    try {
                        if (this.$refs.lwInput) {
                            this.$refs.lwInput.value = base64data;
                            this.$refs.lwInput.dispatchEvent(new Event('input', { bubbles: true }));
                        } else if (modelName && this.$wire) {
                            this.$wire.set(modelName, base64data);
                        }
                    } catch(e) {
                        console.error("Gagal sinkronisasi ke Livewire:", e);
                    }
                    this.isProcessing = false;
                }
            },

            resizeAndCompress(blob, quality, maxDim) {
                return new Promise((resolve) => {
                    const img = new Image();
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        let width = img.width;
                        let height = img.height;

                        if (width > height) {
                            if (width > maxDim) { height *= maxDim / width; width = maxDim; }
                        } else {
                            if (height > maxDim) { width *= maxDim / height; height = maxDim; }
                        }

                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        
                        // Memaksa format WebP (atau JPEG) agar parameter Quality berfungsi.
                        // Jika menggunakan PNG (biasanya screenshot HP), parameter Quality akan diabaikan oleh browser!
                        const outputFormat = 'image/webp';
                        
                        canvas.toBlob((resizedBlob) => {
                            // Jika browser kuno tidak mendukung WebP dan mengembalikan null/blob kosong, fallback ke JPEG
                            if (!resizedBlob || resizedBlob.type !== 'image/webp') {
                                canvas.toBlob((fallbackBlob) => resolve(fallbackBlob), 'image/jpeg', quality);
                            } else {
                                resolve(resizedBlob);
                            }
                        }, outputFormat, quality);
                    };
                    img.src = URL.createObjectURL(blob);
                });
            },

            formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + ['B', 'KB', 'MB'][i];
            },

            clear() {
                this.clearInternal();
                this.$wire.set(modelName, null);
            },

            clearInternal() {
                if (this.previewUrl && !initialPreview) URL.revokeObjectURL(this.previewUrl);
                this.previewUrl = null;
                this.originalFile = null;
                this.processedBlob = null;
                this.hasImage = false;
                this.isCropping = false;
                this.computedSize = '0 KB';
                if (this.cropper) {
                    this.cropper.destroy();
                    this.cropper = null;
                }
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                if (this.$refs.cameraInput) this.$refs.cameraInput.value = '';
            }
        }
    }
</script>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeIn { animation: fadeIn 0.4s ease-out forwards; }
    .cropper-view-box, .cropper-face { border-radius: 1rem; }
    .shadow-premium { box-shadow: 0 10px 40px -10px rgba(0,0,0,0.1); }
</style>
