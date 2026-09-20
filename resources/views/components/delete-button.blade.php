@props([
    'action',
    'label' => 'Usuń',
    'confirm' => 'Na pewno usunąć? Tej operacji nie można cofnąć.',
])

<form method="POST" action="{{ $action }}" class="inline" onsubmit="return confirm(@js($confirm));">
    @csrf
    @method('DELETE')
    <flux:button type="submit" size="sm" variant="ghost" icon="trash" :tooltip="$label" :aria-label="$label" />
</form>
