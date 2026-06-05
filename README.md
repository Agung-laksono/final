# Project Name

> A beautifully crafted Laravel + Livewire web application.

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4.x-4E56A6?style=for-the-badge&logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Flux](https://img.shields.io/badge/Flux-2.x-000000?style=for-the-badge&logo=laravel&logoColor=white)](https://fluxui.dev/)

## 📖 About This Project

This project is a modern web application built using the official **Laravel Livewire Starter Kit**. It leverages the power of Laravel for robust backend operations, Livewire for seamless dynamic interfaces, and Flux for beautiful, accessible UI components.

## ✨ Features

- **Modern Stack:** Built on Laravel 13 and PHP 8.3+.
- **Dynamic UI:** Powered by Livewire v4 for a SPA-like experience without writing custom JavaScript.
- **UI Components:** Utilizes Flux UI for beautifully designed, accessible components out of the box.
- **Authentication:** Integrated with Laravel Fortify for secure authentication flows.
- **Testing:** Pre-configured with PestPHP for elegant and fast testing.

## 🚀 Getting Started

Follow these steps to get the project up and running on your local machine.

### Prerequisites

- PHP >= 8.3
- Composer
- Node.js & NPM
- SQLite (or your preferred database)

### Installation

1. **Clone the repository** (if applicable) or navigate to your project directory.

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install NPM dependencies**
   ```bash
   npm install
   ```

4. **Environment Setup**
   Copy the `.env.example` file to `.env` and configure your database and other environment variables.
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Run Migrations**
   ```bash
   php artisan migrate
   ```

6. **Start the Development Server**
   This single command will concurrently run `php artisan serve`, your queue worker, and `npm run dev` for Vite.
   ```bash
   composer dev
   ```

   Alternatively, you can run them manually:
   ```bash
   php artisan serve
   npm run dev
   ```

## 🧪 Testing

This project uses [Pest](https://pestphp.com/) for testing. To run the test suite:

```bash
composer test
```
Or check formatting and run tests simultaneously:
```bash
composer ci:check
```

## 🛠️ Tech Stack

- **Framework:** [Laravel 13](https://laravel.com)
- **Frontend Logic:** [Livewire 4](https://livewire.laravel.com)
- **UI Components:** [Livewire Flux](https://fluxui.dev/)
- **Testing:** [Pest](https://pestphp.com)
- **Code Formatting:** [Laravel Pint](https://laravel.com/docs/pint)

## 📄 License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT). This project is also under the MIT license unless stated otherwise.
