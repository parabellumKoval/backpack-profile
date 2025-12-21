@php
    $roleKey = $entry->role ?? null;
    $meta = \Backpack\Profile\app\Support\ProfileRoles::badgeMeta($roleKey);
    $label = $roleKey ? ($meta['label'] ?? $roleKey) : '—';
    $class = $meta['class'] ?? 'badge-secondary';
    $color = $meta['color'] ?? '#6c757d';
    $textColor = $meta['text_color'] ?? '#ffffff';
@endphp

@if($roleKey)
    <span class="badge badge-pill {{ $class }}" style="background-color: {{ $color }}; color: {{ $textColor }}; border: 1px solid {{ $color }};">
        {{ $label }}
    </span>
@else
    <span class="text-muted">—</span>
@endif
