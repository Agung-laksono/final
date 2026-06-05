
<div>
    <div class="max-w-2xl mx-auto p-10 bg-white dark:bg-zinc-900 rounded-3xl shadow-xl border border-zinc-200 dark:border-zinc-800">
        <h2 class="text-2xl font-black text-zinc-800 dark:text-zinc-100 mb-6">Test Area: Image Cropper</h2>
        
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-8">
            Halaman ini digunakan untuk membuktikan bahwa komponen CropperJS bekerja sangat sempurna saat tidak diblokir oleh <code>&lt;flux:modal&gt;</code>. Silakan klik tombol di bawah ini untuk memilih gambar.
        </p>

        <x-image-cropper wire:model.live="photoBase64" label="Foto Pengujian" ratio="1" />

        <div class="mt-8 pt-8 border-t border-zinc-200 dark:border-zinc-800">
            <h3 class="text-sm font-bold text-zinc-700 dark:text-zinc-300 mb-4">Hasil Base64:</h3>
            
            <div class="w-full bg-zinc-100 dark:bg-zinc-950 p-4 rounded-xl text-xs text-zinc-500 font-mono break-all h-32 overflow-y-auto">
                @if($photoBase64)
                    {{ substr($photoBase64, 0, 500) }}... (terpotong)
                @else
                    Belum ada gambar yang dipilih atau di-crop.
                @endif
            </div>
            
            @if($photoBase64)
                <div class="mt-4">
                    <p class="text-xs font-bold text-emerald-600 mb-2">Pratinjau Hasil Akhir dari Base64:</p>
                    <img src="{{ $photoBase64 }}" class="w-32 h-32 rounded-2xl shadow-lg border border-zinc-200">
                </div>
            @endif
        </div>
    </div>
</div>
