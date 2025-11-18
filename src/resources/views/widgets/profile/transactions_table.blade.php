@php
    $transactions = $transactions ?? collect();
    $typeColor = $typeColor ?? function ($type) {
        return [
            'credit'  => 'success',
            'debit'   => 'danger',
            'hold'    => 'warning',
            'release' => 'info',
            'capture' => 'primary',
        ][$type] ?? 'secondary';
    };
@endphp

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Транзакции кошелька</strong>
        <a href="{{ backpack_url('wallet-ledger') }}" class="btn btn-sm btn-outline-danger text-danger">К полному журналу</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-responsive-sm mb-0">
            <thead class="thead-light">
                <tr>
                    <th>ID</th>
                    <th>Тип</th>
                    <th>Сумма</th>
                    <th>Источник</th>
                    <th>Когда</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        <td>#{{ $tx->id }}</td>
                        <td><span class="badge badge-{{ $typeColor($tx->type) }}">{{ ucfirst($tx->type) }}</span></td>
                        <td>{{ number_format($tx->amount, 2, '.', ' ') }} {{ $tx->currency }}</td>
                        <td>
                            @php
                                $referenceLabel = $tx->reference_type ? ucfirst(str_replace('_',' ', $tx->reference_type)) : null;
                            @endphp
                            @if($referenceLabel)
                                <div>{{ $referenceLabel }}</div>
                                @if($tx->reference_id)
                                    <div class="small text-muted">#{{ $tx->reference_id }}</div>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ \Carbon\Carbon::parse($tx->created_at)->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Нет транзакций</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
