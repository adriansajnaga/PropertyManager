@props(['name', 'label', 'checked' => false, 'description' => null])

{{-- Natywny checkbox — wysyła się ze zwykłym formularzem POST bez Livewire. --}}
<label class="flex items-start gap-3 text-sm">
    <input type="checkbox" name="{{ $name }}" value="1" @checked($checked)
           class="mt-0.5 size-4 rounded border-zinc-300 accent-zinc-800 dark:border-zinc-600 dark:accent-white">
    <span>
        <span class="font-medium text-zinc-800 dark:text-white">{{ $label }}</span>
        @if ($description)
            <span class="block text-zinc-500 dark:text-zinc-400">{{ $description }}</span>
        @endif
    </span>
</label>
