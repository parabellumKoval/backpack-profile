<?php

namespace Backpack\Profile\app\Http\Controllers\Admin;

use Backpack\Profile\app\Services\DashboardWidgetData;
use Illuminate\Routing\Controller;

class ProfileDashboardController extends Controller
{
    public function index(DashboardWidgetData $widgetData)
    {
        return view('crud::profile_dashboard', [
            'widgetData' => $widgetData->get(),
        ]);
    }
}
