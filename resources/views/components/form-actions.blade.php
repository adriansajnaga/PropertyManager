@props(['cancel'])

<div class="flex items-center gap-2 pt-2">
    <flux:button type="submit" variant="primary">Zapisz</flux:button>
    <flux:button variant="ghost" :href="$cancel">Anuluj</flux:button>
</div>
