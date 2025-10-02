<?php
namespace Backpack\Profile\app\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;


class SettingsCrudController extends Controller
{

    public function index()
    {
      return view('profile-backpack::settings', []);
    }
    
    
    /**
     * Method store
     *
     * @param Request $request [explicite description]
     *
     * @return void
     */
    public function store(Request $request) {

      $data = $request->all();

      $settingsData = [];
      
      foreach($data as $key => $value) {
        if($key === '_token')
          continue;

        $extras = !empty($value)? json_encode($value) : null;

        $settingsData[] = [
          'key' => $key,
          'extras' => $extras,
        ];
      }

      try {
        TranslatorSettings::upsert(
            $settingsData,
            ['key'],
            ['extras']
        );
      }catch(\Exception $e) {
        \Alert::add('error', __('translator::settings.saving_error', ['message' => $e->getMessage()]))->flash();
      }
  
      \Alert::add('success', __('translator::settings.saving_success') )->flash();
      return redirect()->back();
  }
  
  /**
   * Method resetSettings
   *
   * @param Request $request [explicite description]
   *
   * @return void
   */
  public function resetSettings(Request $request) {
    TranslatorSettings::query()->delete();

    \Alert::add('success', __('translator::settings.settings_reset_success') )->flash();
    return redirect()->back();
  }
  
  /**
   * Method resetProvidersStatus
   *
   * @return void
   */
  public function resetProvidersStatus() {

    $providers = config('translator.drivers');
    
    foreach ($providers as $key => $provider) {
      Cache::forget("{$key}_status");
    }

    \Alert::add('success', __('translator::settings.providers_status_dropped') )->flash();
    return redirect()->back();
  }
}