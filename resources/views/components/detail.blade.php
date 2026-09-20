@props(['label'])

<div class="flex items-baseline justify-between gap-4 py-2">
    <dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
    <dd {{ $attributes->merge(['class' => 'text-right font-medium whitespace-nowrap text-zinc-800 dark:text-white']) }}>{{ $slot }}</dd>
</div>
