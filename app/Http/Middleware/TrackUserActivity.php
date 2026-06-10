<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $expiresAt = now()->addMinutes(5);
            $user = Auth::user();
            $cacheKey = 'user-is-online-' . $user->id;
            
            $route = $request->path();
            
            // Jangan timpa rute asli jika request ini dari background sistem (Livewire/Pusher)
            if (str_starts_with($route, 'livewire') || str_starts_with($route, 'broadcasting') || str_starts_with($route, '_debugbar')) {
                $existing = Cache::get($cacheKey);
                
                // Membaca sinyal Focus Tracking dari Javascript
                if ($request->hasHeader('X-Current-Path')) {
                    $isFocused = $request->header('X-Tab-Focused') === '1';
                    $clientRoute = ltrim(parse_url($request->header('X-Current-Path'), PHP_URL_PATH), '/');
                    
                    // JIKA tab ini sedang di-klik/aktif oleh user, timpa rutenya!
                    // JIKA tidak, biarkan rute lama dari tab lain.
                    $route = $isFocused ? $clientRoute : ($existing ? $existing['route'] : 'Dashboard');
                } else {
                    $route = $existing ? $existing['route'] : 'Dashboard';
                }
            }

            Cache::put($cacheKey, [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'route' => $route,
                'last_seen' => now()->toIso8601String(),
            ], $expiresAt);
        }

        return $next($request);
    }
}
