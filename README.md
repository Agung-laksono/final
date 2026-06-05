# Nama Proyek

> Aplikasi web modern yang dibangun dengan Laravel + Livewire.

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4.x-4E56A6?style=for-the-badge&logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Flux](https://img.shields.io/badge/Flux-2.x-000000?style=for-the-badge&logo=laravel&logoColor=white)](https://fluxui.dev/)
[![Tailwind CSS](https://img.shields.io/badge/TailwindCSS-4.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)

## 📖 Tentang Proyek Ini

Proyek ini adalah aplikasi web modern yang dibangun menggunakan **Laravel Livewire Starter Kit** resmi. Aplikasi ini memanfaatkan keandalan Laravel untuk operasi backend, Livewire untuk antarmuka dinamis tanpa membuat API terpisah, Flux untuk komponen UI yang indah dan aksesibel, serta Tailwind CSS 4 untuk gaya desainnya.

## 📦 Dependensi Utama

Berikut adalah teknologi dan package utama yang digunakan dalam proyek ini:

### Backend (PHP / Composer)
- **[Laravel Framework (v13.7+)](https://laravel.com):** Fondasi utama aplikasi (Routing, ORM, dll).
- **[Livewire (v4.1+)](https://livewire.laravel.com):** Framework full-stack untuk Laravel yang membuat antarmuka dinamis semudah menulis komponen Blade.
- **[Flux UI (v2.13+)](https://fluxui.dev/):** Pustaka komponen UI resmi dan premium untuk ekosistem Livewire.
- **[Laravel Fortify (v1.37+)](https://laravel.com/docs/fortify):** Sistem backend autentikasi tanpa antarmuka bawaan (headless authentication).
- **[PestPHP (v4.7+)](https://pestphp.com):** Framework testing yang elegan.

### Frontend (NPM)
- **[Tailwind CSS (v4.0+)](https://tailwindcss.com):** Framework CSS berbasis utilitas (utility-first) untuk desain tampilan.
- **[Vite (v8.0+)](https://vitejs.dev/):** Build tool frontend modern yang sangat cepat.
- **[@laravel/passkeys (v0.2+)](https://laravel.com):** Pustaka untuk mendukung autentikasi menggunakan Passkey secara native.

## ✨ Fitur Utama

- **Tumpukan Teknologi Modern:** Berjalan di atas Laravel 13 dan PHP 8.3+.
- **UI Dinamis SPA-like:** Ditenagai oleh Livewire v4 untuk pengalaman pengguna yang mulus tanpa perlu menulis JavaScript kustom.
- **Komponen UI Siap Pakai:** Menggunakan Flux UI untuk komponen yang didesain secara indah dan responsif.
- **Autentikasi Aman:** Terintegrasi dengan Laravel Fortify lengkap dengan dukungan otentikasi biometrik/Passkey.
- **Siap Uji:** Sudah dikonfigurasi dengan PestPHP untuk penulisan pengujian (testing) yang cepat.

## 🚀 Panduan Memulai

Ikuti langkah-langkah berikut untuk menjalankan proyek ini di komputer lokal Anda.

### Persyaratan Sistem

- PHP >= 8.3
- Composer
- Node.js & NPM
- SQLite (atau database pilihan Anda)

### Instalasi

1. **Clone repositori ini** ke direktori komputer Anda.

2. **Instal dependensi PHP**
   ```bash
   composer install
   ```

3. **Instal dependensi NPM**
   ```bash
   npm install
   ```

4. **Pengaturan Environment**
   Salin file `.env.example` menjadi `.env` dan konfigurasikan database serta variabel environment lainnya.
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Jalankan Migrasi Database**
   ```bash
   php artisan migrate
   ```

6. **Mulai Development Server**
   Perintah tunggal ini akan menjalankan `php artisan serve`, queue worker, dan `npm run dev` untuk Vite secara bersamaan.
   ```bash
   composer dev
   ```

   Atau, Anda bisa menjalankannya secara manual di terminal yang terpisah:
   ```bash
   php artisan serve
   ```
   ```bash
   npm run dev
   ```

## 🧪 Pengujian (Testing)

Proyek ini menggunakan [Pest](https://pestphp.com/) untuk pengujian otomatis. Untuk menjalankan test suite:

```bash
composer test
```
Atau untuk mengecek format kode menggunakan Pint dan menjalankan test sekaligus:
```bash
composer ci:check
```

## 📄 Lisensi

Framework Laravel adalah perangkat lunak sumber terbuka yang dilisensikan di bawah [Lisensi MIT](https://opensource.org/licenses/MIT). Proyek ini juga menggunakan lisensi MIT kecuali dinyatakan lain.
