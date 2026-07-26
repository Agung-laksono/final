<div class="px-0 sm:px-4 lg:px-6 pb-24 max-sm:w-screen max-sm:relative max-sm:left-1/2 max-sm:-translate-x-1/2">
    <div {{ $attributes->merge(['class' => 'max-sm:bg-transparent sm:bg-white sm:dark:bg-zinc-900 max-sm:rounded-none sm:rounded-2xl max-sm:border-none sm:border sm:border-zinc-200 sm:dark:border-zinc-700 overflow-hidden max-sm:shadow-none sm:shadow-sm mb-6 [&_th:first-child]:!pl-4 [&_td:first-child]:!pl-4 [&_th:last-child]:!pr-4 [&_td:last-child]:!pr-4 sm:[&_th:first-child]:!pl-6 sm:[&_td:first-child]:!pl-6 sm:[&_th:last-child]:!pr-6 sm:[&_td:last-child]:!pr-6']) }}>
        <div class="overflow-x-auto min-h-[50vh] px-2">
            {{ $slot }}
        </div>
    </div>
</div>
