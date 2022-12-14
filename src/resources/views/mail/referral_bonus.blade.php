@component('mail::message')
# Referral bonus info

You received a bonus from your referral's order.

Bonus amount: ${{ $transaction->change }}

Bonus balance: ${{ $transaction->balance }}

@component('mail::button', ['url' => url('/')])
Back to site
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
