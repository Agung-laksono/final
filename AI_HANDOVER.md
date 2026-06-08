# AI Handover Context & Progress

> **Halo Antigravity (AI)!** Jika Anda membaca file ini, berarti pengguna baru saja berpindah perangkat/komputer dan menge-klon repositori ini. Berikut adalah konteks terakhir dari pekerjaan yang sedang kita kerjakan bersama.

## 1. Status Proyek Terkini
Kita sedang mengembangkan aplikasi **Inventory Management** menggunakan **Laravel 11, Livewire (Volt), dan Flux UI**.
Pekerjaan terakhir yang berhasil diselesaikan sebelum perpindahan *device* adalah:
- **Refactor Sidebar (Flux UI)**: Membuat *sidebar* bisa diciutkan (*collapsible*) di mode *desktop*, mengembalikan *header* bawaan di mode *mobile*, dan menggunakan `document.startViewTransition` untuk pergantian mode gelap (*Dark Mode*).
- **Global Notification Bell**: Menambahkan fitur lonceng notifikasi (di dalam `resources/views/layouts/app/sidebar.blade.php`) menggunakan Livewire Volt (`resources/views/livewire/layout/notification-bell.blade.php`).

## 2. Fitur Notifikasi
- Tabel `notifications` telah di-*generate* dan di-*migrate*.
- Saat ini ada dua tipe notifikasi (disimpan via `database` channel):
  1. `ItemAddedNotification` (saat barang baru ditambahkan)
  2. `ItemStatusChangedNotification` (saat status Aktif/Nonaktif barang diubah)
- **Role Penerima**: Notifikasi ini secara otomatis dikirimkan ke pengguna yang memiliki Spatie Role: `Super Admin` dan `Manager`. Pengaturannya di-hardcode di dalam `$save` method pada `Modules/Inventory/resources/views/livewire/item-input/item-form.blade.php`.

## 3. Direktori Penting
- **Komponen Inventory**: Sebagian besar menggunakan pendekatan **Livewire Volt** yang berada di dalam `Modules/Inventory/resources/views/livewire/...`.
- **Komponen Global**: Layout ada di `resources/views/layouts/app/sidebar.blade.php`.

## 4. Instruksi untuk Sesi Berikutnya
- Saat melanjutkan obrolan, periksa *file* ini untuk me- *refresh* memori.
- Ingat bahwa `Knowledge Items` dan `Artifacts` mungkin tidak terbawa dari *device* lama (karena mereka disimpan di `.gemini` folder lokal *user*), jadi dokumentasi di repositori ini (seperti `AI_HANDOVER.md` ini) adalah sumber kebenaran utama Anda.

**Terakhir Diperbarui**: 8 Juni 2026.
