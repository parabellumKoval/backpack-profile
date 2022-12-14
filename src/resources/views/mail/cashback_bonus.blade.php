@component('mail::message')
# Cashback info

You received a cashback bonus from your order.

Bonus amount: ${{ $transaction->change }}

Bonus balance: ${{ $transaction->balance }}

@component('mail::button', ['url' => url('/')])
Back to site
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
