@php
    /** @var \Illuminate\Database\Eloquent\Model $entry */
    /** @var array $column */
    use Illuminate\Support\Str;
    use Carbon\Carbon;
    use Backpack\Profile\app\Support\AdminUrl;

    $external = (string) data_get($entry, $column['name']);
    $subjectType = data_get($entry, 'subject_type');
    $subjectId   = data_get($entry, 'subject_id');

    // Парсим версию и признак reversal из внешнего идентификатора.
    // Примеры:
    //   "Review:41:review_published:v1"
    //   "Review:45:review_published:v1#reversal:1757609315"
    $version = null;
    $revTs   = null;

    if (preg_match('/:v(\d+)(?:#reversal:(\d+))?$/', $external, $m)) {
        $version = $m[1] ?? null;
        $revTs   = isset($m[2]) ? (int) $m[2] : null;
    }

    $revAt = $revTs ? Carbon::createFromTimestamp($revTs) : null;

    $subjectUrl = AdminUrl::forModel($subjectType, $subjectId);
   
    $subjectLabel = $entry->subject->displayLabelHtml ?? null;

    if(!$subjectLabel) {
        $subjectLabel = $subjectType && $subjectId
            ? (class_basename($subjectType).' #'.$subjectId)
            : null;
    }

    // Если захочешь брать версию из ak_event_counters — добавь модель со статик-методом:
    // $version = $version ?: \Backpack\Profile\app\Models\EventCounter::versionForExternalId($external);
@endphp

<div class="d-flex flex-column">
    {{-- ссылка на связанную запись --}}
    @if($subjectLabel)
        @if($subjectUrl)
            <a href="{{ $subjectUrl }}" target="_blank" rel="noopener" class="dark-link">{!! $subjectLabel !!}</a>
        @else
            <span>{{ $subjectLabel }}</span>
        @endif
    @endif

    {{-- сам external_id (моноширинно, усечённо) --}}
    <!-- <code title="{{ $external }}">{{ \Illuminate\Support\Str::limit($external, 96) }}</code> -->

    {{-- мета: версия + reversal --}}
    <div class="mt-1">
        @if($version)
            <span class="badge badge-light">v{{ $version }}</span>
        @endif
        @if($revAt)
            <span class="badge badge-danger" title="{{ $revAt->toDateTimeString() }}">reversal {{ $revAt->diffForHumans() }}</span>
        @elseif(Str::contains($external, '#reversal'))
            <span class="badge badge-danger">reversal</span>
        @endif
    </div>
</div>
