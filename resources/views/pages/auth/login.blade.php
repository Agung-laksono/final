<x-layouts::auth :title="__('Log in')">
    <style>
        .page-enter {
            animation: fadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        .page-exit {
            animation: fadeOut 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            pointer-events: none;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px) scale(0.99); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes fadeOut {
            to { opacity: 0; transform: translateY(-10px) scale(0.99); }
        }
    </style>
    <div class="flex flex-col gap-6 page-enter" x-data="{ isSubmitting: false }" :class="{ 'page-exit': isSubmitting }">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6" @submit="isSubmitting = true">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email atau Username')"
                :value="old('email')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="email@contoh.com atau joko123"
                x-on:input="$el.value = $el.value.toLowerCase()"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full relative" data-test="login-button" x-bind:disabled="isSubmitting" x-bind:class="{ 'opacity-80 pointer-events-none': isSubmitting }">
                    <span x-show="!isSubmitting">{{ __('Log in') }}</span>
                    <span x-show="isSubmitting" class="flex items-center justify-center gap-2" style="display: none;">
                        <svg class="animate-spin h-5 w-5 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Memproses...') }}
                    </span>
                </flux:button>
            </div>
        </form>

        @if (Route::has('register'))
        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
        @endif
    </div>
</x-layouts::auth>
