@php
    $leaders = $leaders ?? collect();
@endphp

<div class="card shadow-sm h-100">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Топ спонсоров</strong>
        <a href="{{ backpack_url('profile') }}" class="btn btn-sm btn-outline-secondary">Все пользователи</a>
    </div>
    <div class="card-body">
        @forelse($leaders as $leader)
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                    <div><strong>{{ trim(($leader->first_name ?? '').' '.($leader->last_name ?? '')) ?: 'ID #'.$leader->id }}</strong></div>
                    <div class="text-muted small">{{ $leader->email ?? '—' }}</div>
                </div>
                <span class="badge badge-success">{{ $leader->total }}</span>
            </div>
        @empty
            <div class="text-muted text-center py-4">Нет данных рефералов</div>
        @endforelse
    </div>
</div>
