import 'cropperjs/dist/cropper.css';
import { Html5QrcodeScanner, Html5Qrcode } from 'html5-qrcode';

window.Html5QrcodeScanner = Html5QrcodeScanner;
window.Html5Qrcode = Html5Qrcode;

// Cropper-style Interactive Crop — Pure Alpine JS, Zero Library
document.addEventListener('alpine:init', () => {
    Alpine.data('imageCropper', (wireModel = 'image') => {
        return {
            isProcessing: false,
            isCropping: false,
            originalFile: null,
            originalSize: 0,
            newSize: 0,
            previewSize: 0,
            hasCropped: false,
            maxSize: 800,
            quality: 0.8,

            // Image state
            imgSrc: null,          // Raw original Base64
            workingImgSrc: null,   // Rotated & Flipped Base64
            croppedImgSrc: null,   // Final Cropped Base64
            imgNatW: 0,
            imgNatH: 0,
            imgDispW: 0, // ukuran gambar yang ditampilkan
            imgDispH: 0,

            // Crop box state (dalam piksel relatif terhadap container gambar)
            cropX: 0,
            cropY: 0,
            cropW: 0,
            cropH: 0,

            // Drag state
            dragMode: null, // 'move', 'nw', 'ne', 'sw', 'se'
            dragStartX: 0,
            dragStartY: 0,
            dragStartCropX: 0,
            dragStartCropY: 0,
            dragStartCropW: 0,
            dragStartCropH: 0,

            // Aspect ratio
            ratioLabel: '1:1',
            aspectRatio: 1, // w/h, null = free

            // Transformations
            rotation: 0,
            flipH: false,
            flipV: false,

            containerWidth: 400,

            handleFile(event) {
                this.originalFile = event.target.files[0];
                if (this.originalFile) {
                    this.hasCropped = false;
                    this.rotation = 0;
                    this.flipH = false;
                    this.flipV = false;
                    this.originalSize = this.originalFile.size;
                    this.startCrop();
                }
            },

            setRatio(label) {
                this.ratioLabel = label;
                switch (label) {
                    case '1:1': this.aspectRatio = 1; break;
                    case '4:3': this.aspectRatio = 4 / 3; break;
                    case '16:9': this.aspectRatio = 16 / 9; break;
                    case 'Bebas': this.aspectRatio = null; break;
                }
                // Reset crop box dengan rasio baru
                this.initCropBox();
                this.$nextTick(() => this.updatePreviewSize());
            },

            startCrop() {
                this.isCropping = true;
                this.isProcessing = false;

                const reader = new FileReader();
                reader.onload = (e) => {
                    this.imgSrc = e.target.result;
                    this.applyTransformations();
                };
                reader.readAsDataURL(this.originalFile);
            },

            applyTransformations() {
                this.isProcessing = true;
                const img = new Image();
                img.onload = () => {
                    // Buat canvas sementara untuk merotasi/membalik foto asli
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    const isRotated = this.rotation % 180 !== 0;
                    canvas.width = isRotated ? img.height : img.width;
                    canvas.height = isRotated ? img.width : img.height;
                    
                    ctx.translate(canvas.width / 2, canvas.height / 2);
                    ctx.rotate((this.rotation * Math.PI) / 180);
                    ctx.scale(this.flipH ? -1 : 1, this.flipV ? -1 : 1);
                    ctx.drawImage(img, -img.width / 2, -img.height / 2);
                    
                    // Simpan hasil transformasi sebagai gambar kerja
                    this.workingImgSrc = canvas.toDataURL('image/png');
                    this.imgNatW = canvas.width;
                    this.imgNatH = canvas.height;

                    // Hitung ukuran tampilan (fit ke container max 400x350px, sesuaikan dengan lebar layar HP)
                    const vw = typeof window !== 'undefined' && window.innerWidth ? window.innerWidth : 400;
                    const maxW = Math.min(400, Math.max(200, vw - 64)); // Minimal 200px
                    const maxH = 350;
                    const ratio = Math.min(maxW / this.imgNatW, maxH / this.imgNatH);
                    
                    this.imgDispW = Math.round(this.imgNatW * ratio);
                    this.imgDispH = Math.round(this.imgNatH * ratio);
                    this.containerWidth = this.imgDispW;

                    this.$nextTick(() => {
                        this.initCropBox();
                        this.updatePreviewSize();
                        this.isProcessing = false;
                    });
                };
                img.src = this.imgSrc;
            },

            rotateLeft() {
                this.rotation = (this.rotation - 90 + 360) % 360;
                this.applyTransformations();
            },

            rotateRight() {
                this.rotation = (this.rotation + 90) % 360;
                this.applyTransformations();
            },

            toggleFlipH() {
                this.flipH = !this.flipH;
                this.applyTransformations();
            },

            toggleFlipV() {
                this.flipV = !this.flipV;
                this.applyTransformations();
            },

            initCropBox() {
                const ar = this.aspectRatio;
                const w = this.imgDispW;
                const h = this.imgDispH;

                if (ar) {
                    // Fit crop box sebesar mungkin di dalam gambar dengan rasio tertentu
                    if (w / h > ar) {
                        this.cropH = Math.round(h * 0.8);
                        this.cropW = Math.round(this.cropH * ar);
                    } else {
                        this.cropW = Math.round(w * 0.8);
                        this.cropH = Math.round(this.cropW / ar);
                    }
                } else {
                    this.cropW = Math.round(w * 0.8);
                    this.cropH = Math.round(h * 0.8);
                }

                // Pusatkan
                this.cropX = Math.round((w - this.cropW) / 2);
                this.cropY = Math.round((h - this.cropH) / 2);
            },

            // --- Pointer Events ---
            onPointerDown(e, mode) {
                e.preventDefault();
                this.dragMode = mode;
                const point = e.touches ? e.touches[0] : e;
                this.dragStartX = point.clientX;
                this.dragStartY = point.clientY;
                this.dragStartCropX = this.cropX;
                this.dragStartCropY = this.cropY;
                this.dragStartCropW = this.cropW;
                this.dragStartCropH = this.cropH;

                const onMove = (e) => this.onPointerMove(e);
                const onUp = () => {
                    if (this.dragMode) {
                        this.updatePreviewSize();
                    }
                    this.dragMode = null;
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    document.removeEventListener('touchmove', onMove);
                    document.removeEventListener('touchend', onUp);
                };
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
                document.addEventListener('touchmove', onMove, { passive: false });
                document.addEventListener('touchend', onUp);
            },

            onPointerMove(e) {
                if (!this.dragMode) return;
                e.preventDefault();
                const point = e.touches ? e.touches[0] : e;
                const dx = point.clientX - this.dragStartX;
                const dy = point.clientY - this.dragStartY;
                const ar = this.aspectRatio;
                const minSize = 40;

                if (this.dragMode === 'move') {
                    let nx = this.dragStartCropX + dx;
                    let ny = this.dragStartCropY + dy;
                    // Clamp ke batas gambar
                    nx = Math.max(0, Math.min(nx, this.imgDispW - this.cropW));
                    ny = Math.max(0, Math.min(ny, this.imgDispH - this.cropH));
                    this.cropX = nx;
                    this.cropY = ny;
                } else {
                    // Resize dari handle sudut
                    let nw = this.dragStartCropW;
                    let nh = this.dragStartCropH;
                    let nx = this.dragStartCropX;
                    let ny = this.dragStartCropY;

                    if (this.dragMode.includes('e')) nw = this.dragStartCropW + dx;
                    if (this.dragMode.includes('w')) { nw = this.dragStartCropW - dx; nx = this.dragStartCropX + dx; }
                    if (this.dragMode.includes('s')) nh = this.dragStartCropH + dy;
                    if (this.dragMode.includes('n')) { nh = this.dragStartCropH - dy; ny = this.dragStartCropY + dy; }

                    // Enforce minimum size
                    nw = Math.max(minSize, nw);
                    nh = Math.max(minSize, nh);

                    // Enforce aspect ratio
                    if (ar) {
                        if (this.dragMode.includes('e') || this.dragMode.includes('w')) {
                            nh = Math.round(nw / ar);
                        } else {
                            nw = Math.round(nh * ar);
                        }
                    }

                    // Clamp ke batas gambar
                    if (nx < 0) { nw += nx; nx = 0; }
                    if (ny < 0) { nh += ny; ny = 0; }
                    if (nx + nw > this.imgDispW) nw = this.imgDispW - nx;
                    if (ny + nh > this.imgDispH) nh = this.imgDispH - ny;

                    if (ar) nh = Math.round(nw / ar);

                    this.cropX = nx;
                    this.cropY = ny;
                    this.cropW = nw;
                    this.cropH = nh;
                }
            },

            generateCanvas() {
                return new Promise((resolve) => {
                    const img = new Image();
                    img.onload = () => {
                        // Konversi posisi crop dari display ke natural (Gunakan this.imgNatW bukan img.width)
                        const scaleX = this.imgNatW / this.imgDispW;
                        const scaleY = this.imgNatH / this.imgDispH;
                        const sx = this.cropX * scaleX;
                        const sy = this.cropY * scaleY;
                        const sw = this.cropW * scaleX;
                        const sh = this.cropH * scaleY;

                        // Hitung ukuran output
                        let destW = Math.min(this.maxSize, Math.round(sw));
                        let destH = Math.round(destW * (sh / sw));
                        if (destH > this.maxSize) {
                            destH = this.maxSize;
                            destW = Math.round(destH * (sw / sh));
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = destW;
                        canvas.height = destH;
                        const ctx = canvas.getContext('2d');

                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, destW, destH);
                        ctx.drawImage(img, sx, sy, sw, sh, 0, 0, destW, destH);
                        
                        resolve(canvas);
                    };
                    img.src = this.workingImgSrc;
                });
            },

            async updatePreviewSize() {
                if (!this.workingImgSrc) return;
                const canvas = await this.generateCanvas();
                const dataUrl = canvas.toDataURL('image/webp', this.quality);
                const base64Length = dataUrl.length - (dataUrl.indexOf(',') + 1);
                this.previewSize = Math.floor(base64Length * 0.75);
            },

            async applyCrop() {
                if (!this.workingImgSrc) return;
                this.isProcessing = true;

                const canvas = await this.generateCanvas();
                const dataUrl = canvas.toDataURL('image/webp', this.quality);
                
                const base64Length = dataUrl.length - (dataUrl.indexOf(',') + 1);
                this.newSize = Math.floor(base64Length * 0.75);

                this.croppedImgSrc = dataUrl; // Simpan hasil crop untuk preview
                this.$wire.set(wireModel, dataUrl);
                this.hasCropped = true;
                this.isCropping = false;
                this.isProcessing = false;
            },

            cancelCrop() {
                this.isCropping = false;
                if (!this.hasCropped) {
                    this.resetCropperState();
                }
            },

            resetCropper() {
                this.isCropping = false;
                this.hasCropped = false;
                this.resetCropperState();
            },

            resetCropperState() {
                this.originalFile = null;
                this.imgSrc = null;
                this.workingImgSrc = null;
                this.croppedImgSrc = null;
                this.rotation = 0;
                this.flipH = false;
                this.flipV = false;
                
                if (this.$refs.fileInputMain) {
                    this.$refs.fileInputMain.value = '';
                }
                if (this.$refs.fileInputAlt) {
                    this.$refs.fileInputAlt.value = '';
                }

                // Reset semua input file native yang ada di dalam komponen
                const fileInputs = this.$el.querySelectorAll('input[type="file"]');
                fileInputs.forEach(input => {
                    input.value = '';
                });
            },

            formatSize(bytes) {
                if (bytes === 0) return '0 KB';
                return (bytes / 1024).toFixed(1) + ' KB';
            }
        };
    });
});

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

// Mendengarkan pembaruan inventaris melalui Pusher dan memicu Livewire
window.Echo.channel('inventory')
    .listen('InventoryUpdated', (event) => {
        console.log('Pusher received InventoryUpdated:', event);
        if (window.Livewire) {
            window.Livewire.dispatch('item-updated');
            window.Livewire.dispatch('addLog', { msg: event.message });
        }
    });
