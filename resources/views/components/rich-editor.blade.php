@props(['height' => '400px', 'id' => 'editor-' . uniqid()])

<div 
    x-data="richEditorComponent({
        value: @entangle($attributes->wire('model')),
        editorId: '{{ $id }}' + '-' + Date.now() + Math.floor(Math.random() * 1000)
    })" 
    x-init="initEditor()"
    @template-selected.window="handleTemplateSelected($event.detail.content)"
    @ai-content-generated.window="handleTemplateSelected($event.detail.content)"
    wire:ignore 
    class="relative w-full max-w-full flex flex-col min-w-0 rich-editor-container"
>
    <!-- Loading Overlay -->
    <div x-show="editorLoading" x-transition.opacity.duration.200ms class="absolute inset-0 z-[10] flex flex-col items-center justify-center bg-white/90 dark:bg-zinc-900/90 backdrop-blur-sm rounded-lg" style="display: none;">
        <svg class="w-8 h-8 text-indigo-500 animate-spin mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Menyiapkan Editor...</span>
    </div>

    <!-- Wrapper Editor -->
    <div class="w-full max-w-full flex-1 bg-white dark:bg-zinc-900 relative z-0">
        <textarea :id="editorId" class="w-full flex-1 border-0"></textarea>
    </div>

    <!-- Media Library Modal (Teleported to body) -->
    <template x-teleport="body">
        <div x-show="showMedia" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" style="display: none;" class="fixed inset-0 z-[200] flex flex-col bg-white dark:bg-zinc-900">
            
            <!-- Header Media Library -->
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between gap-4 shrink-0">
                <div>
                    <h3 class="font-bold text-zinc-800 dark:text-zinc-100 text-base">📁 Media Library</h3>
                    <p class="text-[12px] text-zinc-400 mt-0.5">Kelola dan pilih gambar untuk disisipkan ke catatan.</p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Gunakan cropper global jika ada, atau upload sederhana -->
                    <label class="cursor-pointer bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                        Upload Baru
                        <input type="file" class="hidden" accept="image/*" multiple @change="uploadMediaFiles($event.target.files)">
                    </label>
                    <button @click="closeMediaLibrary()" type="button" class="text-zinc-400 hover:text-rose-500 transition-colors p-1.5 rounded-full hover:bg-rose-50 dark:hover:bg-rose-900/20">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="px-4 py-2 border-b border-zinc-100 dark:border-zinc-800 shrink-0">
                <div class="relative">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="mediaSearch" @input.debounce.400ms="loadMedia()" placeholder="Cari gambar berdasarkan nama..." class="w-full pl-8 pr-4 py-1.5 text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
            </div>

            <!-- Main Content: Grid + Detail Sidebar -->
            <div class="flex flex-1 overflow-hidden">
                <!-- Grid & Uploader -->
                <div class="flex-1 overflow-y-auto p-4 custom-scrollbar" @dragover.prevent="mediaDragover = true" @dragleave.prevent="mediaDragover = false" @drop.prevent="mediaDragover = false; uploadMediaFiles($event.dataTransfer.files)">
                    
                    <!-- Uploading Indicator -->
                    <div x-show="mediaUploading" class="mb-4 w-full max-w-sm mx-auto flex items-center justify-center gap-2 text-sm text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 p-3 rounded-lg shadow-sm">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Menyimpan gambar...
                    </div>

                    <!-- Loading State -->
                    <div x-show="mediaLoading" class="flex justify-center items-center h-40">
                        <svg class="w-8 h-8 text-zinc-300 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    </div>

                    <!-- Image Grid -->
                    <div x-show="!mediaLoading && mediaImages.length > 0" class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 gap-2">
                        <template x-for="img in mediaImages" :key="img.path">
                            <div @click="mediaSelected = img"
                                 :class="mediaSelected?.path === img.path ? 'ring-2 ring-indigo-500 ring-offset-2' : 'ring-1 ring-zinc-200 dark:ring-zinc-700 hover:ring-indigo-300'"
                                 class="relative cursor-pointer rounded-lg overflow-hidden aspect-square bg-zinc-100 dark:bg-zinc-800 group transition-all">
                                <img :src="img.url" :alt="img.name" class="w-full h-full object-cover">
                                <div x-show="mediaSelected?.path === img.path" class="absolute top-1 right-1 w-5 h-5 bg-indigo-500 rounded-full flex items-center justify-center">
                                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Detail Sidebar -->
                <div x-show="mediaSelected" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" class="w-64 border-l border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-4 flex flex-col shrink-0">
                    <h4 class="font-bold text-sm text-zinc-800 dark:text-zinc-200 mb-4 uppercase tracking-wider">Detail Gambar</h4>
                    <template x-if="mediaSelected">
                        <div class="space-y-4">
                            <div class="rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 aspect-video flex items-center justify-center">
                                <img :src="mediaSelected.url" class="max-w-full max-h-full object-contain">
                            </div>
                            <div class="space-y-2 text-sm">
                                <div>
                                    <span class="block text-xs text-zinc-400">Nama File</span>
                                    <span class="block text-zinc-800 dark:text-zinc-200 truncate" x-text="mediaSelected.name"></span>
                                </div>
                                <div>
                                    <span class="block text-xs text-zinc-400">Ukuran</span>
                                    <span class="block text-zinc-800 dark:text-zinc-200" x-text="formatSize(mediaSelected.size)"></span>
                                </div>
                            </div>
                            <div class="pt-4 mt-4 border-t border-zinc-200 dark:border-zinc-700 flex flex-col gap-2">
                                <flux:button variant="primary" size="sm" class="w-full" @click="insertSelectedMedia()">Sisipkan ke Editor</flux:button>
                                <flux:button variant="ghost" size="sm" class="w-full text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20" @click="deleteMedia(mediaSelected)">Hapus Permanen</flux:button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

@once
<style>
    /* Paksa toolbar TinyMCE untuk wrap ke bawah jika layar terlalu sempit */
    /* Paksa toolbar TinyMCE untuk wrap ke bawah jika layar terlalu sempit */
    .tox-toolbar__primary { flex-wrap: wrap !important; }
    /* Pastikan popup/menu TinyMCE berada di atas elemen lain, dan tidak menutupi klik (pointer-events) */
    .tox-tinymce-aux { z-index: 999999 !important; pointer-events: none; }
    .tox-tinymce-aux > * { pointer-events: auto; }
</style>
<script>
    const registerRichEditor = () => {
        Alpine.data('richEditorComponent', ({ value, editorId }) => ({
            value: value,
            editorId: editorId,
            showMedia: false,
            _tinyLoaded: false,
            _mediaCallback: null,
            _activeEditorInstance: null,
            mediaImages: [],
            mediaLoading: false,
            mediaSearch: '',
            mediaUploading: false,
            mediaDragover: false,
            mediaSelected: null,
            isUpdating: false, // flag to prevent circular updates
            _isEditorInitialized: false,
            editorLoading: false,

            init() {
                // Dengarkan perubahan dari luar (Livewire)
                this.$watch('value', val => {
                    if (!this.isUpdating && window.tinymce && tinymce.get(this.editorId)) {
                        const current = tinymce.get(this.editorId).getContent();
                        if (current !== val) {
                            tinymce.get(this.editorId).setContent(val || '');
                        }
                    }
                });
            },
            
            handleTemplateSelected(content) {
                // Karena tombol Template sekarang ada di header (di luar editor),
                // kita asumsikan editor ini yang menerima konten jika ia sedang tampil.
                let editor = this._activeEditorInstance;
                if (!editor && window.tinymce && tinymce.get(this.editorId)) {
                    editor = tinymce.get(this.editorId);
                }
                
                if (editor) {
                    editor.insertContent(content);
                }
            },
            
            destroy() {
                if (window.tinymce && tinymce.get(this.editorId)) {
                    tinymce.remove('#' + this.editorId);
                }
                this._activeEditorInstance = null;
                
                // Hapus container aux yang diinjeksikan ke body agar tidak menggeser layout
                const auxContainer = document.getElementById('tinymce-aux-container');
                if (auxContainer) auxContainer.remove();

                // Fail-safe: pastikan body scroll tidak terkunci saat dihancurkan
                document.body.style.overflow = '';
                document.body.classList.remove('tox-dialog__disable-scroll');
            },

            loadTinyMCE(callback) {
                if (window.tinymce) return callback();
                if (this._tinyLoaded) {
                    const wait = setInterval(() => {
                        if (window.tinymce) { clearInterval(wait); callback(); }
                    }, 50);
                    return;
                }
                this._tinyLoaded = true;
                const script = document.createElement('script');
                script.src = '{{ asset('vendor/tinymce/tinymce.min.js') }}';
                script.onload = callback;
                document.head.appendChild(script);
            },

            initEditor() {
                // Gunakan ResizeObserver bukan IntersectionObserver.
                // ResizeObserver HANYA fire setelah browser selesai menghitung layout (lebar nyata).
                // Ini mencegah TinyMCE inisialisasi saat lebar container masih 0 di mobile.
                const self = this;
                const observer = new ResizeObserver((entries) => {
                    for (const entry of entries) {
                        const w = entry.contentRect.width;
                        if (w > 50 && !self._isEditorInitialized) {
                            // Elemen visible dan punya lebar nyata - inisialisasi TinyMCE
                            self._isEditorInitialized = true;
                            self._doInit();
                        } else if (w === 0 && self._isEditorInitialized) {
                            // Modal ditutup → hancurkan TinyMCE agar elemen aux-nya
                            // (.tox-silver-sink, .tox-tinymce-aux) tidak menggeser layout halaman.
                            // _isEditorInitialized di-reset supaya bisa re-init saat modal dibuka kembali.
                            self._isEditorInitialized = false;
                            self.destroy();
                        } else if (w > 50 && self._isEditorInitialized) {
                            // Elemen di-resize saat editor aktif - recalculate toolbar
                            const editor = window.tinymce?.get(self.editorId);
                            if (editor) editor.fire('ResizeWindow');
                        }
                    }
                });
                observer.observe(this.$el);
            },

            _doInit() {
                this.editorLoading = true;

                // Buat container khusus untuk popup/menu TinyMCE.
                // CSS di app.css akan memastikannya tidak memperlebar halaman.
                let aux = document.getElementById('tinymce-aux-container');
                if (!aux) {
                    aux = document.createElement('div');
                    aux.id = 'tinymce-aux-container';
                    document.body.appendChild(aux);
                }

                this.loadTinyMCE(() => {
                        if (window.tinymce && tinymce.get(this.editorId)) {
                            tinymce.remove('#' + this.editorId);
                        }
                        
                        const isDark = document.documentElement.classList.contains('dark');
                        const self = this;
                        
                        tinymce.init({
                            selector: '#' + this.editorId,
                            ui_container: aux,
                            license_key: 'gpl',
                            height: '{{ $height }}',
                            menubar: false,
                            promotion: false,
                            branding: false,
                            skin: isDark ? 'oxide-dark' : 'oxide',
                            content_css: isDark ? 'dark' : 'default',
                            plugins: 'lists link table code image',
                            toolbar_mode: 'floating',
                            toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | table | link image medialibrary template | code',
                            table_toolbar: 'tableprops tabledelete | tableinsertrowbefore tableinsertrowafter tabledeleterow | tableinsertcolbefore tableinsertcolafter tabledeletecol',
                            automatic_uploads: true,
                            images_upload_handler: (blobInfo) => new Promise((resolve, reject) => {
                                const formData = new FormData();
                                formData.append('image', blobInfo.blob(), blobInfo.filename());
                                formData.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '');
                                fetch('{{ route('maklon.media.upload') }}', { method: 'POST', body: formData })
                                    .then(r => r.json())
                                    .then(data => resolve(data.location))
                                    .catch(() => reject('Upload gagal.'));
                            }),
                            setup: (editor) => {
                                // Tombol Media Library
                                editor.ui.registry.addButton('medialibrary', {
                                    icon: 'gallery',
                                    tooltip: 'Buka Media Library',
                                    onAction: () => {
                                        self._activeEditorInstance = editor;
                                        self._mediaCallback = null;
                                        self.openMediaLibrary();
                                    }
                                });
                                
                                // Tombol Template
                                editor.ui.registry.addButton('template', {
                                    icon: 'duplicate',
                                    text: 'Template',
                                    tooltip: 'Sisipkan Template Catatan',
                                    onAction: () => {
                                        self._activeEditorInstance = editor;
                                        if (window.Livewire) {
                                            window.Livewire.dispatch('open-template-modal');
                                        } else {
                                            window.dispatchEvent(new CustomEvent('open-template-modal'));
                                        }
                                    }
                                });

                                editor.on('init', () => {
                                    editor.setContent(self.value || '');
                                    self._activeEditorInstance = editor;
                                    self.editorLoading = false;
                                    // Mobile fix: force toolbar overflow recalculation
                                    // after browser settles layout post-animation
                                    requestAnimationFrame(() => {
                                        requestAnimationFrame(() => {
                                            editor.fire('ResizeWindow');
                                        });
                                    });
                                });

                                // Sinkronisasi ke Livewire pada setiap perubahan
                                editor.on('change keyup paste', () => {
                                    self.isUpdating = true;
                                    self.value = editor.getContent();
                                    // Beri jeda sejenak sebelum menerima update luar agar kursor tidak melompat
                                    setTimeout(() => self.isUpdating = false, 100);
                                });
                                
                                editor.on('focus', () => {
                                    self._activeEditorInstance = editor;
                                });
                            }
                        });
                });
            },

            // ===================== TEMPLATE =====================
            handleTemplateSelected(content) {
                if (this._activeEditorInstance && content) {
                    this._activeEditorInstance.insertContent(content);
                }
            },

            // ===================== MEDIA LIBRARY =====================
            openMediaLibrary() {
                this.showMedia = true;
                this.mediaSelected = null;
                this.mediaSearch = '';
                this.loadMedia();
            },

            closeMediaLibrary() {
                this.showMedia = false;
                this._mediaCallback = null;
            },

            async loadMedia() {
                this.mediaLoading = true;
                try {
                    const url = new URL('{{ route('maklon.media.list') }}', window.location.origin);
                    if (this.mediaSearch) url.searchParams.set('search', this.mediaSearch);
                    const res = await fetch(url);
                    this.mediaImages = await res.json();
                } catch(e) { this.mediaImages = []; }
                this.mediaLoading = false;
            },

            async uploadMediaFiles(files) {
                if (!files || files.length === 0) return;
                this.mediaUploading = true;
                const token = document.querySelector('meta[name=csrf-token]')?.content || '';
                for (const file of files) {
                    if (!file.type.startsWith('image/')) continue;
                    const formData = new FormData();
                    formData.append('image', file);
                    formData.append('_token', token);
                    await fetch('{{ route('maklon.media.upload') }}', { method: 'POST', body: formData });
                }
                this.mediaUploading = false;
                this.loadMedia();
            },

            insertSelectedMedia() {
                if (!this.mediaSelected) return;
                if (this._mediaCallback) {
                    this._mediaCallback(this.mediaSelected.url, { title: this.mediaSelected.name });
                    this._mediaCallback = null;
                } else if (this._activeEditorInstance) {
                    this._activeEditorInstance.insertContent(
                        '<img src=\'' + this.mediaSelected.url + '\' alt=\'' + this.mediaSelected.name + '\' style=\'max-width:100%;\' />'
                    );
                }
                this.showMedia = false;
                this.mediaSelected = null;
            },

            async deleteMedia(img) {
                if (!confirm('Hapus gambar ini permanen?')) return;
                const token = document.querySelector('meta[name=csrf-token]')?.content || '';
                await fetch('{{ route('maklon.media.delete') }}', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ path: img.path })
                });
                if (this.mediaSelected?.path === img.path) this.mediaSelected = null;
                this.loadMedia();
            },

            formatSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1048576).toFixed(1) + ' MB';
            }
        }));
    };

    if (window.Alpine) {
        registerRichEditor();
    } else {
        document.addEventListener('alpine:init', registerRichEditor);
    }
</script>
@endonce
