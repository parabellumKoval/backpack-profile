@extends(backpack_view('blank'))

@php
  $defaultBreadcrumbs = [
    trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
    __('translator::settings.settings_title') => false
  ];

  $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
  <div class="container-fluid">
    <h2>
      <span class="text-capitalize">{{ __('translator::settings.settings_title') }}</span>
      <small id="datatable_info_stack">{{ __('translator::settings.settings_desc') }}</small>
    </h2>
  </div>
@endsection

@section('content')
  <form action="/admin/translator/settings" method="POST">
    @csrf

    <div class="row">
      <div class="col-md-8">
        <!-- Tabs navigation -->
        <ul class="nav nav-tabs mb-0" id="modelTabs" role="tablist">
          @foreach($models as $index => $model)
            <li class="nav-item">
              <a class="nav-link {{ $loop->index === 0 ? 'active' : '' }}" 
                 id="tab-{{ $model['settings']['key'] }}" 
                 data-toggle="tab" 
                 href="#content-{{ $model['settings']['key'] }}" 
                 role="tab" 
                 aria-controls="content-{{ $model['settings']['key'] }}" 
                 aria-selected="{{ $loop->index === 0 ? 'true' : 'false' }}">
                {{ __($model['settings']['title']) }}
              </a>
            </li>
          @endforeach
        </ul>

        <!-- Tabs content -->
        <div class="tab-content" id="modelTabsContent">
          @foreach($models as $index => $model)
            <div class="tab-pane fade {{ $loop->index === 0 ? 'show active' : '' }}" 
                 id="content-{{ $model['settings']['key'] }}" 
                 role="tabpanel" 
                 aria-labelledby="tab-{{ $model['settings']['key'] }}">
              
              <div class="card">
                <div class="card-header bg-gray-100">
                  <h4>{{ __($model['settings']['title']) }}</h4>
                  <p>{{ __('translator::settings.models_description') }}</p>
                      
                  @php
                    $name = "{$model['settings']['key']}_enabled";
                  @endphp
                  <div class="custom-control custom-switch mb-4">
                    <input type="hidden" name="{{ $name }}" value="0">
                    <input name="{{ $name }}" type="checkbox" class="custom-control-input" id="{{ $name }}" value="1"
                          @if(isset($settings[$name]) && $settings[$name]) checked @endif>
                    <label class="custom-control-label" for="{{ $name }}">{{ __('translator::settings.translate', ['model' => $model['settings']['title']]) }}</label>
                  </div>

                  <h5 class="mb-3">{{ __('translator::settings.common_model_settings', ['name' => $model['settings']['title']]) }}</h5>

                  @php
                    $name = "{$model['settings']['key']}_setup_languages";
                  @endphp
                  <div class="custom-control custom-switch mb-4">
                    <input type="hidden" name="{{ $name }}" value="0">
                    <input name="{{ $name }}" type="checkbox" class="custom-control-input" id="{{ $name }}" value="1"
                          @if(isset($settings[$name]) && $settings[$name]) checked @endif>
                    <label class="custom-control-label" for="{{ $name }}">{{ __('translator::settings.setup_model_languages') }}</label>
                  </div>

                  @php
                    $name = "{$model['settings']['key']}_from_languages";
                  @endphp
                  <div class="form-group">
                    <label for="{{ $name }}" class="form-label">{{ __('translator::settings.chose_from_languages') }}</label>
                    <select name="{{ $name }}[]" multiple class="form-control" id="{{ $name }}">
                      @foreach($translator_config['languages'] as $key => $language)
                        <option value="{{ $key }}" @if(isset($settings[$name]) && in_array($key, $settings[$name])) selected @endif>
                          {{ $language }}
                        </option>
                      @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('translator::settings.chose_from_languages_hint') }}</small>
                  </div>

                  @php
                    $name = "{$model['settings']['key']}_to_languages";
                  @endphp
                  <div class="form-group">
                    <label for="{{ $name }}" class="form-label">{{ __('translator::settings.chose_to_languages') }}</label>
                    <select name="{{ $name }}[]" multiple class="form-control" id="{{ $name }}">
                      @foreach($translator_config['languages'] as $key => $language)
                        <option value="{{ $key }}" @if(isset($settings[$name]) && in_array($key, $settings[$name])) selected @endif>
                          {{ $language }}
                        </option>
                      @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('translator::settings.chose_to_languages_hint') }}</small>
                  </div>

                  @foreach($model['settings']['backpack_settings'] as $setting)
                    @include('translator-backpack::fields.' . $setting['type'], ['field' => $setting, 'key' => $model['settings']['key']])
                  @endforeach
                </div>

                <!-- Cases settings -->
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-12">
                      @foreach($model['cases'] as $index => $case)
                        <div class="row">
                          <div class="col-md-12">
                            <h5 class="mb-3">{{ __('translator::settings.translate_group') }}</h5>
                          </div>    
                          @foreach($case['fields'] as $key => $field)
                            @php
                              $name = "{$model['settings']['key']}_group_{$index}_field_{$key}";
                            @endphp
                            <div class="col-md-3">
                              <div class="form-check mb-3">
                                <input type="hidden" name="{{ $name }}" value="0">
                                <input name="{{ $name }}" class="form-check-input" type="checkbox" value="1" id="{{ $name }}"
                                      @if(isset($settings[$name]) && $settings[$name]) checked @endif>
                                <label class="form-check-label" for="{{ $name }}">{{ $field }}</label>
                                <small class="form-text text-muted">{{ __('translator::settings.translate_field', ['name' => $key]) }}</small>
                              </div>
                            </div>
                          @endforeach

                          @php 
                            $name = "{$model['settings']['key']}_group_{$index}_driver";
                          @endphp
                          <div class="form-group col-md-12">
                            <label for="{{ $name }}" class="form-label">{{ __('translator::settings.chose_model_driver') }}</label>
                            <select name="{{ $name }}" class="form-control" id="{{ $name }}">
                              <option value="">{{ __('translator::settings.default') }}</option>
                              @foreach($translator_config['drivers'] as $key => $driver)
                                <option value="{{ $key }}" @if(isset($settings[$name]) && $settings[$name] === $key) selected @endif>
                                  {{ $driver['name'] ?? $key }}
                                </option>
                              @endforeach
                            </select>
                            <small class="form-text text-muted">{{ __('translator::settings.chose_model_driver_hint') }}</small>
                          </div>
                        </div>

                        @if(count($model['cases']) > $index + 1)
                          <hr class="mb-3">
                        @endif
                      @endforeach
                    </div>
                  </div>
                </div>

              </div>
            </div>
          @endforeach
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-md-4">
        <div class="card">
          <div class="card-header  bg-gray-100">
            <h4>{{ __('translator::settings.settings_common_title') }}</h4>
            <p>{{ __('translator::settings.settings_description') }}</p>

            <div>
              <div class="custom-control custom-switch">
                <input type="hidden" name="enabled" value="0">
                <input name="common_enabled" type="checkbox" class="custom-control-input" id="enabledSwitcher" value="1"
                      @if(isset($settings['common_enabled']) && $settings['common_enabled']) checked @endif>
                <label class="custom-control-label" for="enabledSwitcher">{{ __('translator::settings.auto_translate_enabled') }}</label>
              </div>
            </div>
          </div>

          <div class="card-body">
            @include('translator-backpack::common', ['translator_config' => $translator_config, 'settings' => $settings])
          </div>

          <div class="card-footer">
            <div class="row">
              <div class="col-12 mb-3 mb-xl-0">
                <button class="btn btn-primary" type="submit">{{ __('translator::settings.save_changes') }}</button>
                <a href="{{ route('translator.reset_settings') }}" class="btn btn-outline-danger">{{ __('translator::settings.reset_settings') }}</a>
              </div>
            </div>
          </div>

        </div>

        @include('translator-backpack::providers', ['providers' => $providers])

      </div>  <!-- col-md-4 -->
  </div> <!-- row -->
</form>
@endsection



@push('after_styles')
<style>
  .disabled-state {
    opacity: 0.5;
    pointer-events: none;
  }
  .disabled-state input,
  .disabled-state select,
  .disabled-state .custom-switch {
    pointer-events: none;
  }
  .disabled-state .btn-primary {
    opacity: 1;
    pointer-events: auto !important;
  }
  .inactive-elements {
    opacity: 0.5;
    pointer-events: none;
  }
  .language-fields {
    display: none;
  }
  .language-fields.active {
    display: block;
  }
  .nav-tabs {
    border-bottom: 1px solid #dee2e6;
  }
  .tab-content {
    border: 0;
    margin-top: 0;
  }
  .tab-content > .tab-pane {
    padding: 0;
  }
  .card {
    margin-top: 0;
    border-top-left-radius: 0;
    border-top-right-radius: 0;
  }
</style>
@endpush

@push('after_scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const globalEnabler = document.getElementById('enabledSwitcher');
    const allCards = document.querySelectorAll('.card');
    const settingsCard = globalEnabler.closest('.card');
    
    // Setup language fields toggle handlers
    document.querySelectorAll('[id$="_setup_languages"]').forEach(setupSwitch => {
      const card = setupSwitch.closest('.card');
      const fromLanguages = card.querySelector('[id$="_from_languages"]');
      const toLanguages = card.querySelector('[id$="_to_languages"]');
      
      if (fromLanguages && toLanguages) {
        const fromGroup = fromLanguages.closest('.form-group');
        const toGroup = toLanguages.closest('.form-group');
        
        fromGroup.classList.add('language-fields');
        toGroup.classList.add('language-fields');
        
        // Set initial state
        if (setupSwitch.checked) {
          fromGroup.classList.add('active');
          toGroup.classList.add('active');
        }
        
        // Add change handler
        setupSwitch.addEventListener('change', function(e) {
          if (e.target.checked) {
            fromGroup.classList.add('active');
            toGroup.classList.add('active');
          } else {
            fromGroup.classList.remove('active');
            toGroup.classList.remove('active');
          }
        });
      }
    });
    
    function toggleCardElements(card, enabled) {
      // Исключаем hidden inputs и model-enabled переключатели из disabled состояния
      const formElements = card.querySelectorAll('.card-body input:not([type="hidden"]), .card-body select, .card-header input:not([type="hidden"]):not([id$="_enabled"]), .card-header select');
      
      formElements.forEach(element => {
        const container = element.closest('.form-group, .custom-switch');
        if (container) {
          if (!enabled) {
            container.classList.add('inactive-elements');
          } else {
            container.classList.remove('inactive-elements');
          }
        }
        element.disabled = !enabled;
      });
    }

    function toggleGlobalState(enabled) {
      // Управление всеми элементами на странице
      allCards.forEach(card => {
        if (card === settingsCard) {
          // В карточке настроек управляем только видимыми элементами
          const elements = card.querySelectorAll('select, input:not([type="hidden"]):not(#enabledSwitcher)');
          elements.forEach(element => {
            element.disabled = !enabled;
            const group = element.closest('.form-group');
            if (group) {
              if (!enabled) {
                group.classList.add('inactive-elements');
              } else {
                group.classList.remove('inactive-elements');
              }
            }
          });
        } else {
          if (!enabled) {
            // Если главный переключатель выключен
            // Исключаем hidden inputs из disabled состояния
            card.querySelectorAll('input:not([type="hidden"]), select').forEach(element => {
              element.disabled = true;
            });
            card.querySelectorAll('.card-body, .form-group:not(:first-child), .custom-switch:not(:first-child)').forEach(el => {
              el.classList.add('inactive-elements');
            });
          } else {
            // Если главный переключатель включен
            card.querySelectorAll('.inactive-elements').forEach(el => {
              el.classList.remove('inactive-elements');
            });
            
            // Всегда делаем переключатели моделей активными при включенном главном переключателе
            const modelSwitch = card.querySelector('[id$="_enabled"]');
            if (modelSwitch) {
              modelSwitch.disabled = false;
              const switchContainer = modelSwitch.closest('.custom-switch');
              if (switchContainer) {
                switchContainer.classList.remove('inactive-elements');
              }
              
              // Состояние остальных элементов зависит от положения переключателя модели
              toggleCardElements(card, modelSwitch.checked);
            }
          }
        }
      });
    }

    // Инициализация начального состояния
    toggleGlobalState(globalEnabler.checked);

    // Слушатель для главного переключателя
    globalEnabler.addEventListener('change', function(e) {
      toggleGlobalState(e.target.checked);
    });

    // Слушатели для переключателей моделей
    document.querySelectorAll('[id$="_enabled"]').forEach(modelSwitch => {
      modelSwitch.addEventListener('change', function(e) {
        if (globalEnabler.checked) {
          toggleCardElements(modelSwitch.closest('.card'), e.target.checked);
        }
      });
    });
  });
</script>
@endpush