@php
    $withdrawals = $withdrawals ?? collect();
    $statusColor = $statusColor ?? function ($status) {
        return [
            'pending'   => 'warning',
            'approved'  => 'success',
            'completed' => 'success',
            'rejected'  => 'danger',
            'failed'    => 'danger',
        ][$status] ?? 'secondary';
    };
@endphp

<div class="card shadow-sm h-100">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Заявки на вывод</strong>
        <a href="{{ backpack_url('withdrawals') }}" class="btn btn-sm btn-outline-info text-info">Перейти к списку</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead class="thead-light">
                <tr>
                    <th>ID</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                    <th>Создано</th>
                </tr>
            </thead>
            <tbody>
                @forelse($withdrawals as $item)
                    <tr>
                        <td>#{{ $item->id }}</td>
                        <td>{{ number_format($item->amount, 2, '.', ' ') }} {{ $item->currency }}</td>
                        <td>
                            <span class="badge badge-{{ $statusColor($item->status) }}">{{ ucfirst($item->status) }}</span>
                        </td>
                        <td class="text-muted small">{{ \Carbon\Carbon::parse($item->created_at)->format('d.m H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Пока нет заявок</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
