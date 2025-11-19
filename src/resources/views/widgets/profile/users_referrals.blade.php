@php
    $topUsers = collect($topUsers ?? []);
    $storeCurrency = config('backpack.store.base_currency', 'USD');
    $pointsCurrency = strtoupper(config('profile.points.key', 'POINT'));
    $maxOrders = max($topUsers->max('order_total_orders') ?? 0, 1);
    $sortOptions = [
        'created'   => 'Новые пользователи',
        'referrals' => 'Самые активные',
        'orders'    => 'По заказам',
    ];
    $initialSort = $activeSort ?? request()->query('top_users_sort');
    $activeSort = array_key_exists($initialSort, $sortOptions) ? $initialSort : 'created';
    $ajaxEndpoint = route('bp.profile.dashboard.top-users');

    $columnCount = 8;
@endphp

<div
    class="card shadow-sm h-100"
    id="profile-top-users-widget"
    data-loading-text="{{ e('Загружаем пользователей...') }}"
    data-error-text="{{ e('Не удалось загрузить данные, попробуйте ещё раз.') }}"
>
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <div class="mb-2 mb-md-0">
            <strong>Users & Referrals</strong>
            <div class="text-muted small">Топ активных пользователей по заказам и рефералам</div>
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Sorting toggles">
            @foreach($sortOptions as $sortKey => $label)
                <button
                    type="button"
                    class="btn {{ $activeSort === $sortKey ? 'btn-primary' : 'btn-outline-secondary' }}"
                    aria-pressed="{{ $activeSort === $sortKey ? 'true' : 'false' }}"
                    data-top-users-sort="{{ $sortKey }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-responsive-sm table-striped mb-0">
            <thead class="thead-light">
                <tr>
                    <th class="text-center"><i class="la la-user"></i></th>
                    <th>Пользователь</th>
                    <th class="text-center">Страна</th>
                    <th>Заказы</th>
                    <th>Баланс ({{ $pointsCurrency }})</th>
                    <th>Рефералы</th>
                    <th>Спонсор</th>
                    <th>Регистрация</th>
                </tr>
            </thead>
            <tbody
                id="profile-top-users-body"
                data-fetch-url="{{ $ajaxEndpoint }}"
                data-active-sort="{{ $activeSort }}"
                data-columns="{{ $columnCount }}"
            >
                @include('profile-backpack::widgets.profile.partials.top_users_rows', [
                    'topUsers' => $topUsers,
                    'storeCurrency' => $storeCurrency,
                    'maxOrders' => $maxOrders,
                    'columnCount' => $columnCount,
                ])
            </tbody>
        </table>
    </div>
</div>

@push('after_styles')
    <style>
        .avatar {
            position: relative;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
        }
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-initial {
            font-weight: 600;
            color: #4b5563;
        }
        .avatar-status {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            border-radius: 999px;
            border: 2px solid #fff;
        }
        .flag-wrapper {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            min-width: 48px;
        }
        .flag-emoji {
            font-size: 1.5rem;
            line-height: 1;
        }
        .referral-levels {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .referral-levels .badge {
            font-size: 0.75rem;
        }
    </style>
@endpush

@push('after_scripts')
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            const widget = document.getElementById('profile-top-users-widget');
            if (!widget) {
                return;
            }
            const tbody = document.getElementById('profile-top-users-body');
            if (!tbody) {
                return;
            }
            const buttons = widget.querySelectorAll('[data-top-users-sort]');
            const endpoint = tbody.dataset.fetchUrl;
            let activeSort = tbody.dataset.activeSort || 'created';
            const columns = parseInt(tbody.dataset.columns || '8', 10);
            let abortController = null;
            const supportsAbort = typeof AbortController !== 'undefined';

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const placeholderRow = (text) => `<tr><td colspan="${columns}" class="text-center text-muted py-4">${escapeHtml(text)}</td></tr>`;
            const setActive = (target) => {
                buttons.forEach((button) => {
                    const isActive = button.dataset.topUsersSort === target;
                    button.classList.toggle('btn-primary', isActive);
                    button.classList.toggle('btn-outline-secondary', !isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            };

            const fetchRows = async (sort) => {
                if (!endpoint) {
                    return;
                }
                if (supportsAbort && abortController) {
                    abortController.abort();
                }
                abortController = supportsAbort ? new AbortController() : null;
                const loadingText = widget.dataset.loadingText || 'Loading...';
                const errorText = widget.dataset.errorText || 'Failed to load data.';
                setActive(sort);
                tbody.innerHTML = placeholderRow(loadingText);

                try {
                    const url = `${endpoint}?sort=${encodeURIComponent(sort)}`;
                    const fetchOptions = {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    };

                    if (supportsAbort && abortController) {
                        fetchOptions.signal = abortController.signal;
                    }

                    const response = await fetch(url, fetchOptions);

                    if (!response.ok) {
                        throw new Error('Request failed');
                    }

                    const payload = await response.json();
                    tbody.innerHTML = payload.html || placeholderRow(errorText);
                    activeSort = payload.sort || sort;
                    tbody.dataset.activeSort = activeSort;
                    setActive(activeSort);
                } catch (error) {
                    if (supportsAbort && error.name === 'AbortError') {
                        return;
                    }
                    tbody.innerHTML = placeholderRow(errorText);
                    setActive(activeSort);
                }
            };

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    const sort = button.dataset.topUsersSort;
                    if (!sort || sort === tbody.dataset.activeSort) {
                        return;
                    }
                    fetchRows(sort);
                });
            });

            setActive(activeSort);
        });
    </script>
@endpush
