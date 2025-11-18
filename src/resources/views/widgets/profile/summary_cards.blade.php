@php
    $cards = collect($cards ?? [])->values();
    $chartPayload = [];
    $chartModes = [
        ['type' => 'line', 'fill' => true, 'tension' => 0.45],
        ['type' => 'bar'],
        ['type' => 'line', 'fill' => false, 'tension' => 0.2],
        ['type' => 'bar', 'rounded' => false],
        ['type' => 'line', 'fill' => true, 'tension' => 0.1, 'dashed' => [5, 5]],
    ];
@endphp

@if($cards->isNotEmpty())
    <div class="profile-summary-card-row d-flex flex-wrap">
        @foreach($cards as $index => $card)
            @php
                $chartId = 'profile-summary-card-'.$index;
                $mode = $chartModes[$index % count($chartModes)];
                $chartPayload[] = [
                    'id'         => $chartId,
                    'labels'     => $card['chart']['labels'] ?? [],
                    'data'       => $card['chart']['data'] ?? [],
                    'color'      => $card['chartColor'] ?? 'rgba(99,102,241,1)',
                    'background' => $card['chartBackground'] ?? 'rgba(99,102,241,0.15)',
                    'mode'       => $mode,
                    'label'      => $card['label'] ?? 'Value',
                ];
                $accent = $card['accent'] ?? 'primary';
            @endphp
            <div class="profile-summary-card px-2 mb-3">
                <div class="card text-white bg-{{ $accent }} shadow-sm h-100">
                    <div class="card-body pb-0 position-relative">
                        @if($index === 0)
                            <div class="btn-group float-right">
                                <button class="btn btn-transparent dropdown-toggle p-0 text-white" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="la la-gear la-lg"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="{{ backpack_url('profile/create') }}">Добавить нового пользователя</a>
                                    <a class="dropdown-item" href="{{ backpack_url('profile') }}">Все пользователи</a>
                                    <a class="dropdown-item" href="{{ backpack_url('settings/profile') }}">Настройки</a>
                                </div>
                            </div>
                        @else
                            <div class="btn-group float-right">
                                @if(!empty($card['route']))
                                    <a class="btn btn-transparent p-0 text-white" href="{{ backpack_url($card['route']) }}">
                                        <i class="la la-external-link-alt"></i>
                                    </a>
                                @endif
                            </div>
                        @endif
                        <div class="d-flex align-items-center mb-1">
                            <div class="summary-icon mr-3 d-flex align-items-center justify-content-center">
                                <i class="{{ $card['icon'] ?? 'las la-chart-bar' }} la-2x"></i>
                            </div>
                            <div>
                                <div class="text-value h2 mb-0">{{ number_format($card['value'] ?? 0) }}</div>
                                <div>{{ $card['label'] ?? '' }}</div>
                            </div>
                        </div>
                        <div class="small text-uppercase mt-1">{{ $card['muted'] ?? '' }}</div>
                    </div>
                    <div class="chart-wrapper mt-3 mx-3" style="height:70px;">
                        <canvas id="{{ $chartId }}" height="70"></canvas>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @push('after_styles')
        <style>
            .profile-summary-card-row {
                margin-left: -0.5rem;
                margin-right: -0.5rem;
            }
            .profile-summary-card {
                flex: 0 0 20%;
                max-width: 20%;
            }
            @media (max-width: 1400px) {
                .profile-summary-card {
                    flex: 0 0 25%;
                    max-width: 25%;
                }
            }
            @media (max-width: 1200px) {
                .profile-summary-card {
                    flex: 0 0 33.3333%;
                    max-width: 33.3333%;
                }
            }
            @media (max-width: 992px) {
                .profile-summary-card {
                    flex: 0 0 50%;
                    max-width: 50%;
                }
            }
            @media (max-width: 576px) {
                .profile-summary-card {
                    flex: 0 0 100%;
                    max-width: 100%;
                }
            }
            .summary-icon {
                width: 48px;
                height: 48px;
                background: rgba(0,0,0,0.15);
                border-radius: 50%;
            }
        </style>
    @endpush

    @push('after_scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Chart === 'undefined') {
                    return;
                }
                const cards = @json($chartPayload);

                const parseRgba = (color) => {
                    const match = (color || '').match(/rgba?\(([^)]+)\)/);
                    if (!match) return null;
                    const parts = match[1].split(',').map(p => parseFloat(p.trim()));
                    if (parts.length < 3) return null;
                    const [r,g,b,a = 1] = parts;
                    return { r, g, b, a };
                };

                const alpha = (color, opacity) => {
                    const parsed = parseRgba(color);
                    if (!parsed) return `rgba(99,102,241,${opacity})`;
                    return `rgba(${parsed.r}, ${parsed.g}, ${parsed.b}, ${opacity})`;
                };

                const darken = (color, factor = 0.75) => {
                    const parsed = parseRgba(color);
                    if (!parsed) return color;
                    const clamp = (v) => Math.max(0, Math.min(255, Math.round(v)));
                    return `rgba(${clamp(parsed.r * factor)}, ${clamp(parsed.g * factor)}, ${clamp(parsed.b * factor)}, ${parsed.a})`;
                };

                cards.forEach(function (card) {
                    const canvas = document.getElementById(card.id);
                    if (!canvas) {
                        return;
                    }
                    const ctx = canvas.getContext('2d');
                    const baseColor = card.color || 'rgba(99,102,241,1)';
                    const strokeColor = darken(baseColor, 0.7);
                    const bg = card.background || alpha(baseColor, 0.25);
                    const mode = card.mode || {};
                    const chartType = mode.type || 'line';
                    const dataset = {
                        data: card.data,
                        borderColor: strokeColor,
                        backgroundColor: chartType === 'bar' ? alpha(strokeColor, 0.6) :  alpha(strokeColor, 0.2),
                        fill: mode.fill ?? chartType === 'line',
                        tension: mode.tension ?? (chartType === 'line' ? 0.4 : 0),
                        borderWidth: chartType === 'bar' ? 0 : 2,
                        pointRadius: chartType === 'line' ? 5 : 0,
                        pointHoverRadius: chartType === 'line' ? 5 : 0,
                        pointBackgroundColor: chartType === 'line' ? strokeColor : undefined,
                        pointBorderColor: chartType === 'line' ? 'rgba(255,255,255,0.4)' : undefined,
                        pointBorderWidth: chartType === 'line' ? 1 : 0,
                        borderDash: mode.dashed || [],
                        barThickness: 10,
                        label: card.label || 'Значение',
                        pointHoverBackgroundColor: chartType === 'line' ? strokeColor : undefined,
                        pointHoverBorderColor: chartType === 'line' ? '#ffffff' : undefined,
                    };
                    if (chartType === 'bar' && mode.rounded) {
                        dataset.borderRadius = 4;
                    }
                    new Chart(ctx, {
                        type: chartType,
                        data: {
                            labels: card.labels,
                            datasets: [dataset]
                        },
                        options: {
                            animation: false,
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    enabled: true,
                                    intersect: false,
                                    mode: 'index',
                                    callbacks: {
                                        title: (items) => items?.[0]?.label ?? '',
                                        label: (ctx) => {
                                            const value = typeof ctx.parsed === 'object' ? ctx.parsed.y : ctx.parsed;
                                            return `${ctx.dataset?.label || 'Значение'}: ${value ?? 0}`;
                                        },
                                    },
                                    displayColors: false,
                                },
                            },
                            scales: {
                                x: { display: false, stacked: false },
                                y: { display: false, stacked: false },
                            }
                        }
                    });
                });
            });
        </script>
    @endpush
@endif
