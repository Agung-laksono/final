# AI Handover Notes

## Status Terakhir (9 Juni 2026)
Aplikasi saat ini stabil dan fitur Notifikasi Global (Bell) sudah berfungsi penuh. User baru saja selesai dan akan istirahat memancing 🎣.

### Pencapaian Fitur Notifikasi:
1. **Penyelesaian Bug Kompiler Volt/Livewire**:
   - Sempat terjadi `ParseError: syntax error, unexpected token "protected"` secara berulang.
   - **Penyebab Utama**: Compiler Livewire (V3) menggunakan Regex `/}(\s*);/` untuk mencari akhir dari sebuah Anonymous Class dan menyisipkan `protected function view()`. Penggunaan sintaks fungsional Volt (`on()`, `state()`, `with()`) tanpa diakhiri closure membuat regex tersebut meleset dan menyisipkan fungsi di tengah-tengah script (biasanya memotong closure lain secara tidak sengaja).
   - **Solusi**: Merubah sintaks fungsional Volt menjadi format *Anonymous Class* (`new class extends Component { ... }`) di file `notification-bell.blade.php`. Ini menyelesaikan bug kompiler secara permanen.

2. **Real-Time dengan Pusher**:
   - `unreadCount` akan diinisialisasi melalui `mount()`.
   - Menggunakan Laravel Echo & Pusher untuk mendengarkan event notifikasi bawaan Laravel (`Illuminate\Notifications\Events\BroadcastNotificationCreated`) di *private channel* `App.Models.User.{id}`.
   - Penggunaan placeholder `{authId}` pada kunci listener Livewire `#[On(...)]` sangat penting (hindari *string concatenation* di dalam attribute PHP8).
   - Ketiga class notifikasi (`ItemAddedNotification`, `ItemStatusChangedNotification`, `ItemUpdatedNotification`) sudah memiliki array `'broadcast'` pada method `via()`, jadi notifikasi terkirim sukses ke Pusher tanpa perlu mengimplementasikan interface `ShouldBroadcast`.

3. **Penyesuaian UI/UX Bel**:
   - Menghindari penggunaan *wrapper* tambahan di dalam `flux:sidebar.item` agar layout *collapse* Flux UI tidak berantakan.
   - Memasukkan ikon bel ke dalam `<x-slot:icon>` milik `flux:sidebar.item`.
   - Membuat *badge* angka merah bulat diletakkan menggunakan posisi absolut relatif terhadap wadah ikon (top-left).
   - Menyediakan class CSS khusus `.bell-has-unread` dan keyframes animasi `@keyframes bell-ring` (di `app.css`) yang membuat bel berayun lembut berwarna merah saat ada notifikasi belum dibaca.

4. **Transisi ke Queue Synchronous**:
   - User mengatur aplikasi untuk skala maksimal ~20 orang dan akan menggunakan *shared hosting/cPanel*.
   - Keputusan Arsitektur: Mengubah `QUEUE_CONNECTION=database` menjadi `QUEUE_CONNECTION=sync` di `.env` untuk menghindari kebutuhan *background worker/daemon* yang tidak didukung cPanel biasa.
   - Karena sinkron, pengiriman notifikasi sedikit menambah jeda (delay) saat user melakukan simpan data, namun infrastruktur menjadi sangat sederhana.

### Langkah Selanjutnya Saat User Kembali:
- Melanjutkan perbaikan tampilan (UI) sesuai instruksi user sebelumnya ("kita perbaiki tampilan dulu").
- Periksa jika ada bagian UI yang terdampak oleh perbaikan struktur *sidebar* terbaru.
- Menjalankan perintah `npm run dev` dan `php artisan serve` seperti biasa (tidak perlu menjalankan `queue:work` lagi karena antrean sudah sinkron).
