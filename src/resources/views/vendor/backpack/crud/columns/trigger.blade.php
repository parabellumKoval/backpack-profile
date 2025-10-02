@php
    /** @var \Illuminate\Database\Eloquent\Model $entry */
    /** @var array $column */
    $key = data_get($entry, $column['name']);
    $resolved = \Backpack\Profile\app\Support\TriggerLabels::resolve((string) $key);
@endphp

<div>
    <span>{{ $resolved['label'] }}</span>
    @if($resolved['reversal'])
        <span class="badge badge-warning ml-1" title="Обратная операция">reversal</span>
    @endif
    <div class="text-muted small">{{ $resolved['base'] }}</div>
</div>
