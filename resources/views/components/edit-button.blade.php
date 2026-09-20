@props(['href', 'label' => 'Edytuj'])

<flux:button size="sm" variant="ghost" icon="pencil-square" :href="$href" :tooltip="$label" :aria-label="$label" />
