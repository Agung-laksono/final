<dialog x-data="{ imageUrl: '' }"
        @open-lightbox.window="imageUrl = $event.detail.url; $el.showModal()"
        @click="if($event.target === $el) $el.close()"
        class="p-0 m-auto bg-transparent border-none shadow-none focus:outline-none backdrop:bg-black/90 backdrop:backdrop-blur-sm w-screen h-[100dvh] max-w-none max-h-none">
    
    <div class="relative w-full h-full flex flex-col items-center justify-center cursor-pointer" @click="$el.closest('dialog').close()">
        <img :src="imageUrl" class="max-w-[95vw] max-h-[95dvh] object-contain rounded-lg shadow-2xl">
    </div>
</dialog>
