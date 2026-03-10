@php
    $localeOptions = collect((array) config('app.supported_locales', []))
        ->mapWithKeys(fn ($locale) => [strtolower((string) $locale) => strtoupper((string) $locale)])
        ->all();

    $normalizeCountryCode = static function (?string $code): ?string {
        $code = strtoupper(trim((string) $code));

        return match ($code) {
            'UK' => 'UA',
            default => $code !== '' ? $code : null,
        };
    };

    $countryOptions = collect((array) \Backpack\Store\app\Services\Store::countries())
        ->mapWithKeys(function ($item, $code) use ($normalizeCountryCode) {
            $normalized = $normalizeCountryCode($item['code'] ?? $code);

            return $normalized ? [$normalized => (($item['country'] ?? $normalized) . ' (' . $normalized . ')')] : [];
        })
        ->merge([
            'UA' => 'Ukraine (UA)',
            'CZ' => 'Czech Republic (CZ)',
            'DE' => 'Germany (DE)',
            'ES' => 'Spain (ES)',
        ])
        ->sortKeys()
        ->all();

    $defaultPassword = config(
        'backpack.profile.bot_generation.default_password',
        config('profile.bot_generation.default_password', 'bot228vivadzen')
    );
@endphp

<button type="button" class="btn btn-primary" id="open-bot-generation-modal">
    <i class="la la-robot"></i> Генерация ботов
</button>

<div class="modal fade" id="botGenerationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Пакетная генерация ботов</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="bot-generation-form">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="bot_generation_count">Количество ботов</label>
                            <input type="number" min="1" max="5000" class="form-control" id="bot_generation_count" value="100" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="bot_generation_batch">Размер батча AI</label>
                            <input type="number" min="1" max="500" class="form-control" id="bot_generation_batch" value="50">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="bot_generation_email_domain">Email domain</label>
                            <input type="text" class="form-control" id="bot_generation_email_domain" value="bot.local">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="bot_generation_languages">Языки ботов</label>
                            <select id="bot_generation_languages" class="form-control" multiple>
                                @foreach($localeOptions as $localeCode => $localeLabel)
                                    <option value="{{ $localeCode }}">{{ $localeLabel }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Если не выбрать, команда возьмёт все `supported_locales`.</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="bot_generation_countries">Страны</label>
                            <select id="bot_generation_countries" class="form-control" multiple>
                                @foreach($countryOptions as $countryCode => $countryLabel)
                                    <option value="{{ $countryCode }}">{{ $countryLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6 mb-2">
                            <label for="bot_generation_password">Пароль для новых ботов</label>
                            <input type="text" class="form-control" id="bot_generation_password" placeholder="Оставьте пустым для значения по умолчанию">
                            <small class="form-text text-muted">Стандартный пароль: {{ $defaultPassword }}</small>
                        </div>
                    </div>

                    <div class="form-row align-items-center">
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="bot_generation_verified" checked>
                                <label class="form-check-label" for="bot_generation_verified">Подтверждать email</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="bot_generation_dry_run">
                                <label class="form-check-label" for="bot_generation_dry_run">Dry run</label>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="alert alert-danger d-none mt-3" id="bot-generation-error"></div>

                <div class="generation-panel mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Последние запуски</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="bot-generation-refresh">Обновить</button>
                    </div>
                    <div id="bot-generation-runs" class="generation-runs-empty text-muted">Запусков пока нет.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" id="submit-bot-generation">Запустить генерацию</button>
            </div>
        </div>
    </div>
</div>

@push('after_styles')
<style>
    #botGenerationModal .modal-dialog {
        max-width: 900px;
    }

    .generation-run-card {
        border: 1px solid #d9e2ef;
        border-radius: 8px;
        padding: 12px 14px;
        background: #fff;
        margin-bottom: 10px;
    }

    .generation-run-card:last-child {
        margin-bottom: 0;
    }

    .generation-run-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 8px;
    }

    .generation-run-status {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .generation-run-status.is-queued { color: #9a6700; }
    .generation-run-status.is-running { color: #0c63e7; }
    .generation-run-status.is-completed { color: #137333; }
    .generation-run-status.is-failed { color: #b42318; }

    .generation-run-progress {
        height: 8px;
        background: #edf2f7;
        border-radius: 999px;
        overflow: hidden;
        margin-bottom: 8px;
    }

    .generation-run-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #0d6efd, #6ea8fe);
        border-radius: 999px;
    }

    .generation-runs-empty {
        padding: 12px 0;
    }
</style>
@endpush

@push('after_scripts')
<script>
(function() {
    const modal = $('#botGenerationModal');
    const form = $('#bot-generation-form');
    const runsContainer = $('#bot-generation-runs');
    const errorBox = $('#bot-generation-error');
    const routes = {
        index: @json(route('bp.profile.generations.index')),
        store: @json(route('bp.profile.generations.store')),
        showTemplate: @json(route('bp.profile.generations.show', ['run' => '__RUN__'])),
    };
    let pollTimer = null;

    function notify(type, text) {
        if (window.Noty) {
            new Noty({type, text}).show();
            return;
        }

        if (type === 'error') {
            window.alert(text);
        }
    }

    function statusLabel(status) {
        switch (status) {
            case 'queued': return 'В очереди';
            case 'running': return 'Выполняется';
            case 'completed': return 'Завершено';
            case 'failed': return 'Ошибка';
            default: return status;
        }
    }

    function renderRun(run) {
        const percent = run.progress ? run.progress.percent : 0;
        const current = run.progress ? run.progress.current : 0;
        const total = run.progress ? run.progress.total : 0;
        const errorHtml = run.error_message ? `<div class="text-danger small mt-1">${escapeHtml(run.error_message)}</div>` : '';

        return `
            <div class="generation-run-card" data-run-id="${run.id}">
                <div class="generation-run-head">
                    <div>
                        <strong>#${run.id}</strong>
                        <div class="text-muted small">${escapeHtml(run.summary || 'Генерация ботов')}</div>
                    </div>
                    <div class="generation-run-status is-${run.status}">${escapeHtml(statusLabel(run.status))}</div>
                </div>
                <div class="generation-run-progress">
                    <div class="generation-run-progress-bar" style="width:${percent}%"></div>
                </div>
                <div class="d-flex justify-content-between text-muted small">
                    <span>${current}/${total || '?'}</span>
                    <span>${percent}%</span>
                </div>
                ${errorHtml}
            </div>
        `;
    }

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function renderRuns(runs) {
        if (!runs.length) {
            runsContainer.html('<div class="generation-runs-empty text-muted">Запусков пока нет.</div>');
            return;
        }

        runsContainer.html(runs.map(renderRun).join(''));
    }

    function clearPoll() {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    function fetchRuns() {
        $.get(routes.index, {limit: 10})
            .done(function(response) {
                const runs = response.data || [];
                renderRuns(runs);

                const active = runs.find(run => run.status === 'queued' || run.status === 'running');
                if (active) {
                    schedulePoll(active.id);
                }
            });
    }

    function schedulePoll(runId) {
        clearPoll();
        pollTimer = window.setTimeout(function() {
            $.get(routes.showTemplate.replace('__RUN__', String(runId)))
                .done(function(response) {
                    const currentRun = response.data;
                    fetchRuns();

                    if (currentRun && (currentRun.status === 'queued' || currentRun.status === 'running')) {
                        schedulePoll(runId);
                    }
                })
                .fail(function() {
                    schedulePoll(runId);
                });
        }, 4000);
    }

    function resetError() {
        errorBox.addClass('d-none').text('');
    }

    function showError(message) {
        errorBox.removeClass('d-none').text(message);
    }

    function collectPayload() {
        return {
            count: Number($('#bot_generation_count').val() || 0),
            batch: Number($('#bot_generation_batch').val() || 0),
            languages: $('#bot_generation_languages').val() || [],
            countries: $('#bot_generation_countries').val() || [],
            password: $('#bot_generation_password').val() || null,
            email_domain: $('#bot_generation_email_domain').val() || null,
            verified: $('#bot_generation_verified').is(':checked') ? 1 : 0,
            dry_run: $('#bot_generation_dry_run').is(':checked') ? 1 : 0,
        };
    }

    $('#bot_generation_languages, #bot_generation_countries').select2({
        dropdownParent: modal,
        width: '100%'
    });

    $('#open-bot-generation-modal').on('click', function() {
        modal.appendTo('body').modal('show');
        resetError();
        fetchRuns();
    });

    $('#bot-generation-refresh').on('click', fetchRuns);

    $('#submit-bot-generation').on('click', function() {
        resetError();

        $.ajax({
            url: routes.store,
            type: 'POST',
            data: {
                ...collectPayload(),
                _token: @json(csrf_token()),
            }
        }).done(function(response) {
            const run = response.data;
            notify('success', `Запуск #${run.id} поставлен в очередь.`);
            fetchRuns();
            schedulePoll(run.id);
        }).fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Не удалось запустить генерацию ботов.';
            showError(message);
            notify('error', message);
        });
    });

    modal.on('hidden.bs.modal', clearPoll);
})();
</script>
@endpush
