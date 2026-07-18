@props(['model' => null])

@once
<style>
    /* FIX POIN 4: Mencegah pop-up TinyMCE tertutup Modal/elemen lain */
    .tox-tinymce-aux {
        z-index: 999999 !important;
    }
</style>
<script>
document.addEventListener('focusin', function (e) {
    if (e.target.closest('.tox-tinymce-aux, .moxman-window, .tam-assetmanager-root') !== null) {
        e.stopImmediatePropagation();
    }
});
</script>
@endonce

<div class="w-full" wire:ignore
    x-data="{ 
        value: @entangle($model),
        id: 'tiny-' + Math.random().toString(36).substr(2, 9),
        init() {
            this.$refs.tiny.id = this.id;
            this.loadScript().then(() => this.setupEditor());
        },
        loadScript() {
            return new Promise((resolve) => {
                if (typeof tinymce !== 'undefined') {
                    resolve();
                    return;
                }
                const script = document.createElement('script');
                script.src = '/vendor/tinymce/tinymce.min.js';
                
                script.onerror = () => {
                    console.warn('TinyMCE lokal tidak ditemukan. Memuat dari CDN Cloudflare...');
                    const fallbackScript = document.createElement('script');
                    fallbackScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.5.1/tinymce.min.js';
                    fallbackScript.onload = () => resolve();
                    document.head.appendChild(fallbackScript);
                };
                
                script.onload = () => resolve();
                document.head.appendChild(script);
            });
        },
        setupEditor() {
            this.$nextTick(() => {
                if (typeof tinymce === 'undefined') return;

                if (tinymce.get(this.id)) {
                    tinymce.remove(tinymce.get(this.id));
                }
                
                tinymce.init({
                    target: this.$refs.tiny,
                    height: 300,
                    menubar: false,
                    license_key: 'gpl',
                    plugins: 'lists link image table code',
                    toolbar: 'undo redo | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist | table image',
                    // FIX POIN 3: Dukungan Dark Mode
                    skin: document.documentElement.classList.contains('dark') ? 'oxide-dark' : 'oxide',
                    content_css: document.documentElement.classList.contains('dark') ? 'dark' : 'default',
                    branding: false,
                    promotion: false,
                    setup: (editor) => {
                        editor.on('init', () => {
                            if (this.value) {
                                editor.setContent(this.value);
                            }
                        });

                        editor.on('change input undo redo blur', () => {
                            this.value = editor.getContent();
                        });

                        this.$watch('value', (newValue) => {
                            if (newValue !== editor.getContent()) {
                                editor.setContent(newValue || '');
                            }
                        });
                    }
                });
            });
        },
        destroy() {
            if (tinymce.get(this.id)) {
                tinymce.remove(tinymce.get(this.id));
            }
        }
    }">
    <textarea x-ref="tiny"></textarea>
</div>
