{{-- resources/views/filament/components/lang-switch.blade.php --}}
<x-dropdown align="right" width="32">
    <x-slot name="trigger">
        <button
            class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 transition">
            {{ strtoupper(app()->getLocale()) }}
            <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                      d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 
                      1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                      clip-rule="evenodd" />
            </svg>
        </button>
    </x-slot>

    <x-slot name="content">
        <x-dropdown-link href="{{ route('lang.switch', 'en') }}">English</x-dropdown-link>
        <x-dropdown-link href="{{ route('lang.switch', 'ar') }}">العربية</x-dropdown-link>
    </x-slot>
</x-dropdown>
