<div class="px-0 sm:px-4 lg:px-6 pb-24">
    <div {{ $attributes->merge(['class' => 'bg-white dark:bg-zinc-900 rounded-xl sm:rounded-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm mb-6 [&_th:first-child]:!pl-4 [&_td:first-child]:!pl-4 [&_th:last-child]:!pr-4 [&_td:last-child]:!pr-4 sm:[&_th:first-child]:!pl-6 sm:[&_td:first-child]:!pl-6 sm:[&_th:last-child]:!pr-6 sm:[&_td:last-child]:!pr-6']) }}>
        <div class="overflow-x-auto min-h-[50vh]">
            {{ $slot }}
        </div>
    </div>
</div>
