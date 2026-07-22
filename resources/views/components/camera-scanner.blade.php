<flux:modal name="camera-scanner-modal" class="w-full max-w-md p-0" x-on:close="$dispatch('stop-camera-scanner')">
    <div x-data="cameraScannerComponent()" x-on:stop-camera-scanner.window="stopScanner" class="p-4 sm:p-6 bg-white dark:bg-zinc-800 flex flex-col relative rounded-xl">
        <!-- Judul dinamis berdasarkan mode -->
        <h2 class="text-xl font-bold mb-1 text-center text-zinc-900 dark:text-white">Pindai Barcode</h2>
        <p x-show="scanMode === 'continuous'" class="text-xs text-center text-emerald-600 dark:text-emerald-400 mb-3 font-medium">Mode Beruntun — Tekan "Selesai" jika sudah</p>
        <div x-show="scanMode === 'single'" class="mb-3"></div>
        
        <!-- Wrapper utama (Tanpa Live Video) -->
        <div class="relative w-full rounded-lg flex flex-col items-center justify-center p-6 text-center shadow-sm border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 min-h-[200px]">
            <!-- Overlay Loader -->
            <div x-show="isLoading" x-transition
                 class="absolute inset-0 flex flex-col items-center justify-center bg-zinc-900/95 z-20 text-white rounded-lg">
                <svg class="animate-spin mb-3 h-8 w-8 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="loadingMessage" class="font-medium tracking-wide">Memproses gambar...</span>
            </div>

            <!-- Overlay Sukses -->
            <div x-show="successMsg" x-transition
                 class="absolute inset-0 flex flex-col items-center justify-center bg-emerald-600/95 z-20 text-white rounded-lg px-4 text-center gap-3">
                <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="font-bold text-lg">Berhasil Diinput!</p>
                <p class="text-sm font-mono bg-emerald-700/60 px-3 py-1 rounded-lg tracking-widest" x-text="successMsg"></p>
            </div>

            <div x-show="!isLoading && !successMsg" class="flex flex-col items-center gap-2">
                <flux:icon.qr-code class="w-16 h-16 text-zinc-300 dark:text-zinc-600 mb-2" />
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Ambil foto barcode dengan kamera HP Anda, sistem akan mendeteksinya secara otomatis.</p>
            </div>
        </div>
        
        <!-- Wadah tersembunyi untuk proses scan file -->
        <div id="file-reader" class="hidden"></div>
        
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <div class="flex flex-col sm:flex-row gap-3 w-full max-w-md mx-auto">
                <!-- TOMBOL 1: AMBIL FOTO -->
                <label class="px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 cursor-pointer text-center w-full sm:flex-1 flex justify-center items-center gap-2 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 dark:hover:bg-indigo-500/20 transition-colors"> 
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Ambil Foto
                    <input type="file" class="hidden" accept="image/*" capture="environment" @change="scanImage">
                </label>
                <!-- TOMBOL 2: PILIH GALERI -->
                <label class="px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 cursor-pointer text-center w-full sm:flex-1 flex justify-center items-center gap-2 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 dark:hover:bg-indigo-500/20 transition-colors"> 
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Pilih Galeri 
                    <input type="file" class="hidden" accept="image/*" @change="scanImage">
                </label>
            </div>
            <!-- Tombol label berubah: "Selesai" untuk batch mode, "Batal" untuk single -->
            <button type="button" @click="Flux.modal('camera-scanner-modal').close()"
                    class="px-4 py-2 text-sm font-medium rounded-lg w-full sm:flex-1 transition-colors"
                    :class="scanMode === 'continuous'
                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-none'
                        : 'text-zinc-700 bg-white border border-zinc-300 hover:bg-zinc-50 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-600 dark:hover:bg-zinc-700'">
                <span x-text="scanMode === 'continuous' ? 'Selesai Scan' : 'Batal'"></span>
            </button>
        </div>
    </div>

    <script>
        const initCameraScanner = () => {
            Alpine.data('cameraScannerComponent', () => ({
                isLoading: false,
                isScanning: false,
                isStopping: false,
                errorMsg: '',
                successMsg: '',
                scanMode: 'single',
                loadingMessage: 'Memuat...',

                init() {
                    window._cameraOpenHandler = async (event) => {

                        // Baca mode dari event (default: 'single')
                        this.scanMode = event?.detail?.mode === 'continuous' ? 'continuous' : 'single';
                        this.errorMsg = '';
                        this.successMsg = '';
                        this.isLoading = true;
                        this.loadingMessage = 'Memuat kamera...';

                        if (!this._camerasLoaded) {
                            await this.getCameras();
                            this._camerasLoaded = true;
                        }

                        await this.createAndStart();
                    };

                    window.addEventListener('camera-scanner-modal-opened', window._cameraOpenHandler);

                    // MutationObserver: deteksi modal ditutup (ESC/klik backdrop)
                    const watchModal = () => {
                        const modalEl = document.querySelector('[name="camera-scanner-modal"]') ||
                                        document.querySelector('dialog[id*="camera-scanner"]');
                        if (!modalEl) { setTimeout(watchModal, 300); return; }

                        const observer = new MutationObserver(() => {
                            const isClosed = !modalEl.hasAttribute('open') &&
                                             (modalEl.style.display === 'none' ||
                                              modalEl.getAttribute('aria-hidden') === 'true' ||
                                              !modalEl.classList.contains('open'));
                            if (this.isScanning && isClosed) {
                                this.stopScanner();
                            }
                        });
                        observer.observe(modalEl, {
                            attributes: true,
                            attributeFilter: ['open', 'style', 'class', 'aria-hidden']
                        });
                    };
                    setTimeout(watchModal, 200);

                    // Jaring pengaman berkala
                    setInterval(() => {
                        if (this.isScanning && !this.isStopping) {
                            const wrapper = document.getElementById('reader-wrapper');
                            if (wrapper && wrapper.offsetParent === null) {
                                this.stopScanner();
                            }
                        }
                    }, 800);
                },

                // ============================================================
                // INTI FIX: createAndStart()
                // Selalu: stop lama → buat reader baru dengan ID unik → start baru
                // ID unik memastikan Html5Qrcode lama TIDAK BISA menemukan reader
                // baru via getElementById, sehingga mustahil terjadi injeksi video ganda.
                // ============================================================
                async createAndStart() {
                    // Tunggu scanner sebelumnya benar-benar berhenti
                    await this.stopScanner();

                    // Buat reader baru dengan ID unik (timestamp)
                    this._currentReaderId = 'qr-' + Date.now();
                    const wrapper = document.getElementById('reader-wrapper');
                    if (!wrapper) return;

                    // Bersihkan wrapper: matikan semua video track yang mungkin tersisa
                    wrapper.querySelectorAll('video').forEach(v => {
                        if (v.srcObject) {
                            v.srcObject.getTracks().forEach(t => t.stop());
                            v.srcObject = null;
                        }
                    });
                    wrapper.innerHTML = ''; // Hapus semua child lama

                    // Tambahkan div baru dengan ID unik baru
                    const el = document.createElement('div');
                    el.id = this._currentReaderId;
                    el.className = 'qr-reader-el w-full';
                    wrapper.appendChild(el);

                    // Mulai scanner baru
                    this.startScanner();
                },

                async getCameras() {
                    try {
                        const devices = await window.Html5Qrcode.getCameras();
                        if (!devices || devices.length === 0) return;
                        this.cameras = devices;

                        const savedId = localStorage.getItem(this._STORAGE_KEY);
                        if (savedId && devices.find(d => d.id === savedId)) {
                            this.selectedCamera = savedId;
                            return;
                        }

                        const backCameras = devices.filter(d => {
                            const lbl = (d.label || '').toLowerCase();
                            return lbl.includes('back') || lbl.includes('rear') ||
                                   lbl.includes('belakang') || lbl.includes('environment');
                        });
                        const mainBack = backCameras.length > 0
                            ? (backCameras.find(d => {
                                const lbl = (d.label || '').toLowerCase();
                                return !lbl.includes('wide') && !lbl.includes('macro') &&
                                       !lbl.includes('depth') && !lbl.includes('zoom') &&
                                       !lbl.includes('tele') && !lbl.includes('virtual') &&
                                       !lbl.includes('ultra');
                              }) || backCameras[0])
                            : null;

                        this.selectedCamera = mainBack ? mainBack.id : devices[0].id;
                    } catch (err) {
                        console.warn('Tidak bisa mendapatkan daftar kamera:', err);
                    }
                },

                switchCamera() {
                    if (this.selectedCamera) {
                        localStorage.setItem(this._STORAGE_KEY, this.selectedCamera);
                    }
                    this.createAndStart();
                },

                startScanner() {
                    if (this.isScanning || this.isStopping) return;
                    if (!this._currentReaderId) return;

                    const readerEl = document.getElementById(this._currentReaderId);
                    if (!readerEl) {
                        console.error('Reader element tidak ditemukan:', this._currentReaderId);
                        return;
                    }

                    this.scanner = new window.Html5Qrcode(this._currentReaderId, { verbose: false });

                    const cameraConfig = this.selectedCamera
                        ? { deviceId: { exact: this.selectedCamera } }
                        : { facingMode: 'environment' };

                    this.isLoading = true;
                    this.loadingMessage = 'Mengaktifkan kamera...';
                    this.errorMsg = '';

                    this.scanner.start(
                        cameraConfig,
                        { fps: 12, qrbox: { width: 250, height: 200 }, aspectRatio: 1.5, disableFlip: false },
                        (decodedText) => { this.onScanSuccess(decodedText); },
                        (_err) => { /* Abaikan error per-frame */ }
                    ).then(() => {
                        this.isLoading = false;
                        this.isScanning = true;
                    }).catch(err => {
                        console.error('Gagal memulai kamera:', err);
                        this.isLoading = false;
                        this.isScanning = false;
                        this.scanner = null;
                        this.errorMsg = 'Gagal mengakses kamera. Pastikan izin sudah diberikan di browser.';
                    });
                },

                stopScanner() {
                    return new Promise(resolve => {
                        if (!this.scanner) {
                            this._killAllVideoTracks();
                            this.isScanning = false;
                            this.isStopping = false;
                            resolve();
                            return;
                        }
                        if (!this.isScanning) {
                            try { this.scanner.clear(); } catch(e) {}
                            this._killAllVideoTracks();
                            this.scanner = null;
                            this.isStopping = false;
                            resolve();
                            return;
                        }

                        this.isStopping = true;

                        const forceKill = setTimeout(() => {
                            console.warn('Force-kill kamera (timeout)');
                            this._killAllVideoTracks();
                            this.scanner = null;
                            this.isScanning = false;
                            this.isStopping = false;
                            resolve();
                        }, 3000);

                        this.scanner.stop()
                            .then(() => {
                                clearTimeout(forceKill);
                                try { this.scanner.clear(); } catch(e) {}
                                this._killAllVideoTracks();
                                this.scanner = null;
                                this.isScanning = false;
                                this.isStopping = false;
                                resolve();
                            })
                            .catch(err => {
                                clearTimeout(forceKill);
                                console.warn('stop() error:', err);
                                this._killAllVideoTracks();
                                this.scanner = null;
                                this.isScanning = false;
                                this.isStopping = false;
                                resolve();
                            });
                    });
                },

                _killAllVideoTracks() {
                    try {
                        // Cari di SELURUH dokumen, jangan cuma di wrapper
                        // karena kadang DOM library terlepas dari wrapper saat re-render
                        document.querySelectorAll('video').forEach(v => {
                            if (v.srcObject) {
                                v.srcObject.getTracks().forEach(t => t.stop());
                                v.srcObject = null;
                            }
                        });
                        if (window._activeScannerStream) {
                            window._activeScannerStream.getTracks().forEach(t => t.stop());
                            window._activeScannerStream = null;
                        }
                    } catch(e) { console.warn('Kill tracks error:', e); }
                },

                async scanImage(event) {
                    if (!event.target.files.length) return;
                    const imageFile = event.target.files[0];
                    this.isLoading = true;
                    this.loadingMessage = 'Memindai gambar...';
                    await new Promise(r => setTimeout(r, 50));
                    try {
                        const fileReaderEl = document.getElementById('file-reader');
                        if (fileReaderEl) fileReaderEl.innerHTML = '';
                        const fileScanner = new window.Html5Qrcode('file-reader', { verbose: false });
                        try {
                            const decodedText = await fileScanner.scanFile(imageFile, false);
                            this.isLoading = false;
                            this.onScanSuccess(decodedText);
                        } catch(err) {
                            this.isLoading = false;
                            alert('Tidak ditemukan barcode di gambar. Pastikan gambar jelas dan terfokus.');
                        } finally {
                            event.target.value = '';
                            try { fileScanner.clear(); } catch(e) {}
                        }
                    } catch(e) {
                        this.isLoading = false;
                        event.target.value = '';
                        alert('Gagal memproses gambar. Format mungkin tidak didukung.');
                    }
                },

                async onScanSuccess(decodedText) {
                    const code = decodedText.trim();

                    if (this.scanMode === 'continuous') {
                        // Mode beruntun: jangan tutup modal
                        // 1. Tampilkan overlay sukses hijau
                        this.successMsg = code;

                        // 2. Kirim event ke Livewire
                        window.dispatchEvent(new CustomEvent('barcode-scanned', {
                            detail: { code }
                        }));

                        // 3. Setelah 1.5 detik, sembunyikan overlay & scanner siap lagi
                        //    (scanner sudah aktif — Html5Qrcode terus berjalan, hanya di-pause overlay)
                        setTimeout(() => {
                            this.successMsg = '';
                        }, 1500);

                    } else {
                        // Mode single (default): tutup modal seperti sebelumnya
                        try { Flux.modal('camera-scanner-modal').close(); } catch(e) {}
                        this.stopScanner();
                        window.dispatchEvent(new CustomEvent('barcode-scanned', {
                            detail: { code }
                        }));
                    }
                },
            }));
        };

        if (window.Alpine) {
            initCameraScanner();
        } else {
            document.addEventListener('alpine:init', initCameraScanner);
        }
    </script>
    
    <style>
        #reader-wrapper { min-height: 250px; }
        /* Gunakan class-based selector karena ID reader berubah setiap sesi */
        .qr-reader-el { border: none !important; }
        .qr-reader-el video {
            object-fit: cover !important;
            border-radius: 0.5rem;
            width: 100% !important;
            max-height: 50vh !important;
        }
        /* Sembunyikan toolbar bawaan Html5Qrcode yang tidak kita perlukan */
        [id^="qr-"] [id$="__dashboard_section_swaplink"],
        [id^="qr-"] [id$="__dashboard_section_csr"],
        [id^="qr-"] [id$="__header_message"] { display: none !important; }
    </style>
</flux:modal>
