@extends(backpack_view('blank'))

@php
    $profileWidgetData = $widgetData ?? app(\Backpack\Profile\app\Services\DashboardWidgetData::class)->get();
    $widget = ['data' => $profileWidgetData];
@endphp

@section('header')
    <div class="container-fluid mb-4">
        <h2 class="mb-0">User & Referral Dashboard</h2>
        <small class="text-muted">Все метрики из профиля теперь собраны здесь</small>
    </div>
@endsection

@section('content')
    <div class="profile-dashboard-widgets">
        @include('profile-backpack::widgets.profile_overview', compact('widget'))
    </div>
@endsection
