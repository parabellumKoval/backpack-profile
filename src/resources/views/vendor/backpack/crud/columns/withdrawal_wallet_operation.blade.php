@include('crud::columns.price', ['price' => $price, 'currency' => $currency, 'small' => $small, 'rate' => $rate, 'rate_from' => $rate_from, 'rate_to' => $rate_to])


@if($ledger && $price)
  @if($ledger->type === 'hold' || $ledger->type === 'release')
    @include('crud::columns.ledger_type_status', ['ledger' => $ledger])
  @endif
@endif