<?php

namespace Backpack\Profile\app\Http\Controllers\Admin;

use Backpack\Profile\app\Services\DashboardWidgetData;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProfileDashboardController extends Controller
{
    public function index(DashboardWidgetData $widgetData)
    {
        return view('crud::profile_dashboard', [
            'widgetData' => $widgetData->get(),
        ]);
    }

    public function topUsers(Request $request, DashboardWidgetData $widgetData)
    {
        $sort = $widgetData->resolveTopUsersSort($request->query('sort'));
        $topUsers = $widgetData->topUsers($sort);
        $maxOrders = max($topUsers->max('order_total_orders') ?? 0, 1);

        $html = view('profile-backpack::widgets.profile.partials.top_users_rows', [
            'topUsers' => $topUsers,
            'storeCurrency' => config('backpack.store.base_currency', 'USD'),
            'maxOrders' => $maxOrders,
            'columnCount' => 8,
        ])->render();

        return response()->json([
            'html' => $html,
            'sort' => $sort,
        ]);
    }
}
