<flux:modal name="camera-scanner-modal" class="w-full max-w-md p-0" x-on:close="$dispatch('stop-camera-scanner')">
    <div x-data="cameraScannerComponent()" x-on:stop-camera-scanner.window="stopScanner" class="p-4 sm:p-6 bg-white dark:bg-zinc-800 flex flex-col relative rounded-xl">
        <h2 class="text-xl font-bold mb-4 text-center text-zinc-900 dark:text-white">Pindai Barcode</h2>
        
        <!-- Pilih Kamera -->
        <div class="mb-4">
            <select x-model="selectedCamera" @change="switchCamera" class="w-full bg-zinc-50 border border-zinc-300 text-zinc-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 dark:bg-zinc-700 dark:border-zinc-600 dark:placeholder-zinc-400 dark:text-white dark:focus:ring-indigo-500 dark:focus:border-indigo-500">
                <option value="" x-show="cameras.length === 0">Kamera Utama (Otomatis)</option>
                <template x-for="camera in cameras" :key="camera.id">
                    <option :value="camera.id" x-text="camera.label || 'Kamera ' + camera.id"></option>
                </template>
            </select>
        </div>

        <div id="reader" class="w-full bg-black rounded-lg overflow-hidden min-h-[250px] flex items-center justify-center relative shadow-inner">
            <!-- Loader -->
            <div x-show="isLoading" class="absolute inset-0 flex items-center justify-center bg-zinc-900 z-10 text-white">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>Memproses...</span>
            </div>
        </div>
        
        <!-- Wadah tersembunyi untuk proses scan file/gambar tanpa mengganggu video stream -->
        <div id="file-reader" class="hidden"></div>
        
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <div class="flex flex-col sm:flex-row gap-3 w-full max-w-md mx-auto">
  
  <!-- TOMBOL 1: BUKA KAMERA -->
  <label class="px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 cursor-pointer text-center w-full sm:flex-1 flex justify-center items-center gap-2 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 dark:hover:bg-indigo-500/20 transition-colors"> 
    <!-- Ikon Kamera -->
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
    </svg>
    Ambil Foto
    <input type="file" class="hidden" accept="image/*" capture="environment" @change="scanImage"> 
  </label>

  <!-- TOMBOL 2: PILIH GALERI -->
  <label class="px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 cursor-pointer text-center w-full sm:flex-1 flex justify-center items-center gap-2 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 dark:hover:bg-indigo-500/20 transition-colors"> 
    <!-- Ikon Galeri -->
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
    </svg>
    Pilih Galeri 
    <input type="file" class="hidden" accept="image/*" @change="scanImage"> 
  </label>

</div>

            <button type="button" @click="Flux.modal('camera-scanner-modal').close()" class="px-4 py-2 text-sm font-medium text-zinc-700 bg-white border border-zinc-300 rounded-lg hover:bg-zinc-50 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-600 dark:hover:bg-zinc-700 w-full sm:flex-1 transition-colors">
                Batal
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('cameraScannerComponent', () => ({
                isOpen: false,
                isLoading: false,
                isScanning: false,
                scanner: null,
                cameras: [],
                selectedCamera: null,
                
                init() {
                    window.addEventListener('camera-scanner-modal-opened', () => {
                        if (typeof window.Html5Qrcode === 'undefined') {
                            alert("Library kamera belum siap. Mohon muat ulang (refresh) halaman.");
                            return;
                        }

                        this.isOpen = true;
                        this.isLoading = true;
                        
                        this.getCameras().then(() => {
                            setTimeout(() => {
                                this.startScanner();
                            }, 100);
                        });
                    });
                },

                getCameras() {
                    return window.Html5Qrcode.getCameras().then(devices => {
                        if (devices && devices.length) {
                            this.cameras = devices;
                            // Default ke kamera terakhir (biasanya kamera belakang di HP)
                            this.selectedCamera = devices[devices.length - 1].id;
                        }
                    }).catch(err => {
                        console.error("Error getting cameras", err);
                        alert("Tidak dapat membaca daftar kamera dari sistem. " + err);
                    });
                },

                switchCamera() {
                    this.stopScanner().then(() => {
                        this.startScanner();
                    });
                },

                startScanner() {
                    if (this.isScanning) {
                        this.isLoading = false;
                        return;
                    }

                    // Pastikan div reader bersih sebelum membuat scanner baru
                    if (this.scanner) {
                        try { this.scanner.clear(); } catch(e) {}
                    }

                    this.scanner = new window.Html5Qrcode("reader");
                    
                    let cameraIdOrConfig = this.selectedCamera 
                        ? { deviceId: { exact: this.selectedCamera } } 
                        : { facingMode: "environment" };

                    this.isLoading = true;
                    this.scanner.start(
                        cameraIdOrConfig,
                        { fps: 10, qrbox: { width: 250, height: 250 } },
                        (decodedText) => {
                            this.onScanSuccess(decodedText);
                        },
                        (errorMessage) => {
                            // Abaikan error fokus
                        }
                    ).then(() => {
                        this.isLoading = false;
                        this.isScanning = true;
                    }).catch((err) => {
                        console.error("Gagal memulai kamera", err);
                        this.isLoading = false;
                        alert("Gagal mengakses kamera. Pastikan Anda memberikan izin (Permission).");
                    });
                },

                stopScanner() {
                    return new Promise((resolve) => {
                        if (this.scanner && this.isScanning) {
                            this.scanner.stop().then(() => {
                                this.scanner.clear();
                                this.scanner = null;
                                this.isScanning = false;
                                resolve();
                            }).catch(error => {
                                console.error("Gagal mematikan kamera", error);
                                this.scanner = null;
                                this.isScanning = false;
                                resolve();
                            });
                        } else {
                            if (this.scanner) this.scanner.clear();
                            this.scanner = null;
                            resolve();
                        }
                    });
                },

                scanImage(event) {
                    if (event.target.files.length == 0) return;
                    const imageFile = event.target.files[0];
                    
                    this.isLoading = true;

                    // Beri jeda sedikit agar UI sempat merender status "Memproses..." 
                    // sebelum thread utama diblokir oleh pemrosesan gambar
                    setTimeout(() => {
                        try {
                            let fileScanner = new window.Html5Qrcode("file-reader");
                            
                            fileScanner.scanFile(imageFile, false)
                                .then(decodedText => {
                                    this.isLoading = false;
                                    this.onScanSuccess(decodedText);
                                    event.target.value = ''; // reset file input
                                    try { fileScanner.clear(); } catch(e) {}
                                })
                                .catch(err => {
                                    this.isLoading = false;
                                    alert("Tidak ditemukan barcode di dalam gambar tersebut. Pastikan foto jelas dan terfokus.");
                                    event.target.value = ''; // reset file input
                                    try { fileScanner.clear(); } catch(e) {}
                                });
                        } catch (e) {
                            this.isLoading = false;
                            alert("Gagal memproses gambar. Format mungkin tidak didukung.");
                            console.error(e);
                            event.target.value = ''; // reset file input
                        }
                    }, 50);
                },

                onScanSuccess(decodedText) {
                    // 1. Langsung tutup modal Flux secepat mungkin
                    try {
                        Flux.modal('camera-scanner-modal').close();
                    } catch (e) {
                        console.error('Gagal menutup modal', e);
                    }
                    
                    // 2. Matikan hardware kamera di background
                    if (this.scanner) {
                        this.stopScanner();
                    }
                    
                    // 3. Kirim data barcode ke komponen Livewire
                    window.dispatchEvent(new CustomEvent('barcode-scanned', { detail: { code: decodedText } }));
                },
                
                closeModal() {
                    this.stopScanner().then(() => {
                        this.isOpen = false;
                    });
                }
            }));
        });
    </script>
    
    <style>
        #reader { border: none !important; }
        #reader video { 
            object-fit: cover !important; 
            border-radius: 0.5rem;
            width: 100% !important;
            max-height: 50vh !important;
        }
    </style>
</flux:modal>
