@extends(backpack_view('blank'))

@php
    $defaultBreadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        $crud->entity_name_plural => url($crud->route),
        trans('backpack::crud.preview') => false,
    ];

    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;

    $avatarUrl = $profile->avatarUrl();
    $emailVerified = optional($user)->email_verified_at !== null;
    $createdAt = optional($user)->created_at ?? $profile->created_at;

    $addressFormatter = function (array $address): \Illuminate\Support\Collection {
        return collect([
            'Имя' => $address['first_name'] ?? '',
            'Фамилия' => $address['last_name'] ?? '',
            'Компания' => $address['company'] ?? '',
            'Email' => $address['email'] ?? '',
            'Телефон' => $address['phone'] ?? '',
            'Адрес 1' => $address['address_1'] ?? '',
            'Адрес 2' => $address['address_2'] ?? '',
            'Город' => $address['city'] ?? '',
            'Регион' => $address['state'] ?? '',
            'Индекс' => $address['postcode'] ?? '',
            'Страна' => $address['country'] ?? '',
        ])->filter(fn ($value) => $value !== null && $value !== '');
    };
@endphp

@section('header')
    <section class="container-fluid d-print-none">
        <a href="javascript: window.print();" class="btn float-right"><i class="la la-print"></i></a>
        <h2>
            <span class="text-capitalize">{!! $crud->getHeading() ?? $crud->entity_name_plural !!}</span>
            <small>{!! $crud->getSubheading() ?? mb_ucfirst(trans('backpack::crud.preview')).' '.$crud->entity_name !!}.</small>
            @if ($crud->hasAccess('list'))
                <small class=""><a href="{{ url($crud->route) }}" class="font-sm"><i class="la la-angle-double-left"></i> {{ trans('backpack::crud.back_to_all') }} <span>{{ $crud->entity_name_plural }}</span></a></small>
            @endif
        </h2>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-4 mb-4">
            <div class="card shadow-sm mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $profile->fullname }}" class="img-fluid rounded-circle" style="max-width: 160px;">
                        @else
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 160px; height: 160px; font-size: 48px;">
                                {{ strtoupper(mb_substr($profile->fullname ?? $user?->name ?? 'U', 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <h4 class="mb-1">{{ $profile->fullname ?? $user?->name ?? '—' }}</h4>
                    <p class="text-muted mb-2">{{ $user?->email ?? '—' }}</p>
                    <div class="d-flex flex-column gap-1 small text-start mx-auto" style="max-width: 260px;">
                        <div><span class="text-muted">Телефон:</span> {{ $profile->phone ?? '—' }}</div>
                        <div><span class="text-muted">Код страны:</span> {{ $profile->country_code ?? '—' }}</div>
                        <div><span class="text-muted">Язык:</span> {{ $profile->locale ?? '—' }}</div>
                        <div><span class="text-muted">Часовой пояс:</span> {{ $profile->timezone ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Контакты</h5>
                </div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-5">ID профиля</dt>
                        <dd class="col-7">{{ $profile->id }}</dd>

                        <dt class="col-5">ID пользователя</dt>
                        <dd class="col-7">{{ $profile->user_id ?? '—' }}</dd>

                        <dt class="col-5">Email подтверждён</dt>
                        <dd class="col-7">
                            @if($emailVerified)
                                <span class="badge badge-success">Да</span>
                            @else
                                <span class="badge badge-secondary">Нет</span>
                            @endif
                        </dd>

                        <dt class="col-5">Создан</dt>
                        <dd class="col-7">{{ optional($createdAt)->format('d.m.Y H:i') ?? '—' }}</dd>

                        <dt class="col-5">Реферальный код</dt>
                        <dd class="col-7">{{ $profile->referral_code ?? '—' }}</dd>

                        <dt class="col-5">Скидка</dt>
                        <dd class="col-7">{{ number_format($profile->discount_percent ?? 0, 2) }} %</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Состояние счёта</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Валюта</th>
                                    <th class="text-right">Баланс</th>
                                    <th class="text-muted text-right">Последнее обновление</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($walletBalances as $balance)
                                    <tr>
                                        <td>{{ strtoupper($balance->currency) }}</td>
                                        <td class="text-right">{{ number_format((float) $balance->balance, 2, '.', ' ') }}</td>
                                        <td class="text-right text-muted">{{ optional($balance->updated_at)->format('d.m.Y H:i') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Балансов не найдено</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Платёжные данные</h6>
                        </div>
                        <div class="card-body small">
                            @php($formattedBilling = $addressFormatter($billing))
                            @if($formattedBilling->isEmpty())
                                <p class="text-muted mb-0">Не заполнено</p>
                            @else
                                <dl class="mb-0">
                                    @foreach($formattedBilling as $label => $value)
                                        <dt>{{ $label }}</dt>
                                        <dd>{{ $value }}</dd>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Адрес доставки</h6>
                        </div>
                        <div class="card-body small">
                            @php($formattedShipping = $addressFormatter($shipping))
                            @if($formattedShipping->isEmpty())
                                <p class="text-muted mb-0">Не заполнено</p>
                            @else
                                <dl class="mb-0">
                                    @foreach($formattedShipping as $label => $value)
                                        <dt>{{ $label }}</dt>
                                        <dd>{{ $value }}</dd>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">История вознаграждений</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Сумма</th>
                                    <th>Валюта</th>
                                    <th>Уровень</th>
                                    <th>Дата</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rewards as $reward)
                                    <tr>
                                        <td>{{ $reward->id }}</td>
                                        <td>{{ number_format((float) $reward->amount, 2, '.', ' ') }}</td>
                                        <td>{{ strtoupper($reward->currency) }}</td>
                                        <td>{{ $reward->level }}</td>
                                        <td>{{ optional($reward->created_at)->format('d.m.Y H:i') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Вознаграждений нет</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Операции кошелька</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Тип</th>
                                    <th>Сумма</th>
                                    <th>Валюта</th>
                                    <th>Дата</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ledger as $item)
                                    <tr>
                                        <td>{{ $item->id }}</td>
                                        <td>{{ ucfirst($item->type) }}</td>
                                        <td>{{ number_format((float) $item->amount, 2, '.', ' ') }}</td>
                                        <td>{{ strtoupper($item->currency) }}</td>
                                        <td>{{ optional($item->created_at)->format('d.m.Y H:i') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Операций не найдено</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Заявки на вывод средств</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Статус</th>
                                    <th>Сумма</th>
                                    <th>Валюта</th>
                                    <th>Создано</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($withdrawals as $withdrawal)
                                    <tr>
                                        <td>{{ $withdrawal->id }}</td>
                                        <td>{{ ucfirst($withdrawal->status) }}</td>
                                        <td>{{ number_format((float) $withdrawal->amount, 2, '.', ' ') }}</td>
                                        <td>{{ strtoupper($withdrawal->currency) }}</td>
                                        <td>{{ optional($withdrawal->created_at)->format('d.m.Y H:i') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Заявок нет</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
