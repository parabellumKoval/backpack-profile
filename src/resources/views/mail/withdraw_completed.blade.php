@component('mail::message')
# Withdrawal request processed

{!! nl2br($transaction->description) !!}

Withdraw amount: ${{ - $transaction->change }}

Bonus balance: ${{ abs(round($transaction->balance, 2)) }}

@component('mail::button', ['url' => url('/')])
Back to site
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
