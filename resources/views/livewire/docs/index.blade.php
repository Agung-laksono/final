<?php
use function Livewire\Volt\{state, layout, title, mount};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

layout('layouts.docs');
title('Dokumentasi Sistem');

state([
    'slug' => 'alur-kerja-sistem',
    'htmlContent' => '',
    'title' => 'Dokumentasi',
    'docs' => [],
]);

mount(function ($slug = 'alur-kerja-sistem') {
    $this->slug = $slug;
    
    // Scan all docs in resources/docs
    $docsPath = resource_path('docs');
    if (!File::exists($docsPath)) {
        File::makeDirectory($docsPath, 0755, true);
    }
    
    $files = File::files($docsPath);
    $this->docs = collect($files)
        ->filter(fn($file) => $file->getExtension() === 'md')
        ->map(function ($file) {
            $fileName = $file->getFilenameWithoutExtension();
            $title = Str::headline(str_replace('-', ' ', $fileName));
            return [
                'slug' => $fileName,
                'title' => $title,
            ];
        })
        ->toArray();
        
    // Load requested doc
    $filePath = $docsPath . '/' . $this->slug . '.md';
    
    if (File::exists($filePath)) {
        $markdown = File::get($filePath);
        $this->htmlContent = Str::markdown($markdown);
        $this->title = Str::headline(str_replace('-', ' ', $this->slug));
    } else {
        abort(404, 'Dokumen tidak ditemukan.');
    }
});
?>

<div>
    <x-slot:sidebar>
        <div class="mb-8">
            <h5 class="text-sm font-semibold text-zinc-900 dark:text-white mb-3 uppercase tracking-wider">Materi</h5>
            <ul class="space-y-2">
                @foreach($docs as $doc)
                    <li>
                        <a href="{{ route('docs.index', $doc['slug']) }}" class="block px-3 py-2 rounded-lg text-sm transition-colors {{ $slug === $doc['slug'] ? 'bg-indigo-50 text-indigo-700 font-medium dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-white dark:hover:bg-zinc-800' }}">
                            {{ $doc['title'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </x-slot:sidebar>

    <div class="markdown-body">
        {!! $htmlContent !!}
    </div>
</div>
