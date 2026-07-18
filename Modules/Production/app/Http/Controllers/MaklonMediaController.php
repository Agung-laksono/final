<?php

namespace Modules\Production\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaklonMediaController extends Controller
{
    public function upload(Request $request)
    {
        abort_unless(auth()->check(), 403);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:8192',
        ], [
            'image.max' => 'Ukuran gambar maksimal 8MB.',
            'image.mimes' => 'Format gambar tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.',
        ]);

        $path = $request->file('image')->store(
            'maklon-media/' . now()->format('Y/m'),
            'public'
        );

        return response()->json([
            'location' => Storage::url($path),
            'name'     => $request->file('image')->getClientOriginalName(),
            'size'     => Storage::disk('public')->size($path),
            'path'     => $path,
        ]);
    }

    public function list(Request $request)
    {
        abort_unless(auth()->check(), 403);

        $search = $request->query('search', '');

        $files = collect(Storage::disk('public')->allFiles('maklon-media'))
            ->filter(fn($f) => preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f))
            ->map(fn($f) => [
                'url'      => Storage::url($f),
                'name'     => basename($f),
                'size'     => Storage::disk('public')->size($f),
                'modified' => Storage::disk('public')->lastModified($f),
                'path'     => $f,
            ])
            ->when($search, fn($c) => $c->filter(
                fn($item) => str_contains(strtolower($item['name']), strtolower($search))
            ))
            ->sortByDesc('modified')
            ->values();

        return response()->json($files);
    }

    public function delete(Request $request)
    {
        abort_unless(auth()->check(), 403);

        $path = $request->input('path');

        // Security: only allow deletion within maklon-media folder
        if (!str_starts_with($path, 'maklon-media/')) {
            abort(403, 'Tidak diizinkan.');
        }

        Storage::disk('public')->delete($path);

        return response()->json(['success' => true]);
    }
}
