<x-slot:title>{{ $athlete->name }} - Formulaire quotidien</x-slot>
<div class="mx-auto max-w-2xl">

    <flux:heading size="xl" level="1">{{ $athlete->name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Rentrer les métriques pour le jour choisi.</flux:text>

    <div class="mb-4 flex items-center justify-center">
        <!-- Previous Button -->
        <a class="flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white" href="?d={{ $prevDate->format('Y-m-d') }}">
            <svg class="h-3.5 w-3.5 rtl:rotate-180"
                aria-hidden="true"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 14 10">
                <path stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 5H1m0 0 4 4M1 5l4-4" />
            </svg>
        </a>
        <div>
            <div class="relative mx-3 max-w-sm">
                <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3.5">
                    <svg class="h-4 w-4 text-gray-500 dark:text-gray-400"
                        aria-hidden="true"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="currentColor"
                        viewBox="0 0 20 20">
                        <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z" />
                    </svg>
                </div>
                <input class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 ps-10 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-blue-500 dark:focus:ring-blue-500"
                    id="datepicker"
                    data-date="{{ $date->format('Y-m-d') }}"
                    type="text"
                    value="{{ $date->format('Y-m-d') }}"
                    datepicker
                    datepicker-format="yyyy-mm-dd"
                    inline-datepicker
                    datepicker-buttons
                    datepicker-autoselect-today
                    placeholder="Choisir une date">
                <button class="absolute bottom-2.5 end-2.5 rounded-lg bg-blue-700 px-3 py-1 text-xs font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" onclick="window.location.href='?d='+document.getElementById('datepicker').value">Go</button>
            </div>
        </div>
        <a class="flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white" @if (!$canGetNextDay) href="javascript:void(0)"
            style="cursor: not-allowed;" role="link" aria-disabled="true"
        @else
        href="?d={{ $nextDate->format('Y-m-d') }}" @endif>
            <svg class="h-3.5 w-3.5 rtl:rotate-180"
                aria-hidden="true"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 14 10">
                <path stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M1 5h12m0 0L9 1m4 4L9 9" />
            </svg>
        </a>
    </div>

    <form wire:submit="save">
        {{ $this->form }}

        <flux:button class="mt-4"
            type="submit"
            variant="primary">Sauvegarder</flux:button>
    </form>

    <x-filament-actions::modals />
</div>
