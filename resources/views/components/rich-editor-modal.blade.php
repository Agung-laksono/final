@props(['name' => 'rich-editor', 'title' => 'EDITOR CATATAN'])

{{--
    ═══════════════════════════════════════════════════════════════════
    RICH EDITOR MODAL — dengan Lazy Load TinyMCE
    ═══════════════════════════════════════════════════════════════════
    TinyMCE TIDAK dimuat saat halaman pertama kali dibuka.
    Script-nya baru di-inject secara dinamis saat editor benar-benar 
    dibuka untuk pertama kali. Ini mencegah event listener global 
    TinyMCE mengganggu komponen lain (seperti Image Cropper).
    ═══════════════════════════════════════════════════════════════════
--}}

<div x-data="richEditorModal('{{ $name }}')" @keydown.escape.window="destroyEditor()">
    <template x-teleport="body">
        <dialog id="native-{{ $name }}-modal"
                class="bg-transparent m-auto p-0 backdrop:bg-zinc-900/60 backdrop:backdrop-blur-sm w-full max-w-4xl"
                style="position: fixed; inset: 0; z-index: 9999;">

            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl md:w-[780px] w-full mx-auto p-6 flex flex-col max-h-[90vh]">

                {{-- Header --}}
                <div class="flex justify-between items-start mb-4 shrink-0">
                    <div>
                        <h3 class="text-sm font-bold text-[#1a2b4c] dark:text-white tracking-widest uppercase">{{ $title }}</h3>
                        <p class="text-[10px] text-slate-400 tracking-wider uppercase mt-0.5">RICH TEXT MODE</p>
                    </div>
                    <button type="button" x-on:click="destroyEditor()"
                            class="w-8 h-8 rounded-full border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-zinc-400 hover:text-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                        <flux:icon.x-mark class="w-4 h-4" />
                    </button>
                </div>

                {{-- Editor area: wire:ignore agar Livewire tidak menyentuh DOM ini --}}
                <div class="flex-1 overflow-y-auto" wire:ignore>
                    <textarea id="{{ $name }}-textarea" class="w-full"></textarea>
                </div>

                {{-- Footer --}}
                <div class="mt-5 flex justify-between items-center shrink-0 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button type="button" x-on:click="destroyEditor()"
                            class="text-[11px] font-bold text-slate-400 hover:text-slate-600 tracking-widest uppercase">
                        BATAL
                    </button>
                    <button type="button" x-on:click="saveEditor()"
                            class="bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase px-8 py-2.5 rounded-xl shadow-sm transition-colors flex items-center justify-center min-w-[110px]">
                        SIMPAN
                    </button>
                </div>
            </div>
        </dialog>
    </template>
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('richEditorModal', (name) => ({

        // ── Lazy Load: apakah TinyMCE sudah pernah di-load? ──
        _tinyLoaded: false,

        init() {
            window.addEventListener('open-' + name, (e) => {
                this.initEditor(e.detail?.content ?? '');
            });
        },

        // ── Load TinyMCE secara dinamis (hanya sekali) ────────────────
        loadTinyMCE(callback) {
            if (window.tinymce) {
                // Sudah ada dari sesi sebelumnya
                callback();
                return;
            }
            if (this._tinyLoaded) {
                // Script sedang dimuat, tunggu
                const wait = setInterval(() => {
                    if (window.tinymce) {
                        clearInterval(wait);
                        callback();
                    }
                }, 50);
                return;
            }
            // Inject script untuk pertama kali
            this._tinyLoaded = true;
            const script = document.createElement('script');
            script.src = '{{ asset("vendor/tinymce/tinymce.min.js") }}';
            script.onload = callback;
            document.head.appendChild(script);
        },

        // ── Buka dialog dan inisialisasi TinyMCE ─────────────────────
        initEditor(content) {
            const dialog = document.getElementById('native-' + name + '-modal');
            dialog.showModal();

            this.loadTinyMCE(() => {
                setTimeout(() => {
                    // Hapus instance lama jika ada
                    if (tinymce.get(name + '-textarea')) {
                        tinymce.get(name + '-textarea').remove();
                    }

                    tinymce.init({
                        selector: '#' + name + '-textarea',
                        license_key: 'gpl',
                        height: 380,
                        menubar: false,
                        promotion: false,
                        branding: false,
                        plugins: 'lists link table code',
                        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | table | code',
                        setup(editor) {
                            editor.on('init', function () {
                                editor.setContent(content || '');
                                editor.focus();
                            });
                        }
                    });
                }, 100);
            });
        },

        // ── Tutup dan bersihkan ───────────────────────────────────────
        destroyEditor() {
            if (window.tinymce && tinymce.get(name + '-textarea')) {
                tinymce.get(name + '-textarea').remove();
            }
            const dialog = document.getElementById('native-' + name + '-modal');
            if (dialog) dialog.close();
        },

        // ── Simpan konten lalu tutup ──────────────────────────────────
        saveEditor() {
            if (window.tinymce && tinymce.get(name + '-textarea')) {
                const content = tinymce.get(name + '-textarea').getContent();
                this.$dispatch('cart-note-saved', { content });
            }
            this.destroyEditor();
        }
    }));
});
</script>
@endonce
