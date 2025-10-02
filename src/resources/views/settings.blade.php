@extends(backpack_view('blank'))

@php
  $defaultBreadcrumbs = [
    trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
    __('profile::settings.settings_title') => false
  ];

  $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
  <div class="container-fluid">
    <h2>
      <span class="text-capitalize">{{ __('profile::settings.settings_title') }}</span>
      <small id="datatable_info_stack">{{ __('profile::settings.settings_desc') }}</small>
    </h2>
  </div>
@endsection

@section('content')
<form action="/admin/referrals/settings" method="POST">
  @csrf

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