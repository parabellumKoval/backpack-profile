@php
  $class = '';
  $type = 'badge';

  
  if($ledger->type === 'debit' || $ledger->type === 'capture'){
    $class = 'badge badge-success';
  }elseif($ledger->type === 'credit') {
    $class = 'badge badge-success';
  }elseif($ledger->type === 'hold') {
    $class = 'badge badge-warning';
  }elseif($ledger->type === 'release') {
    $class = 'badge badge-secondary';
  }
@endphp

<div>
  <span class="{{ $class }}">{{ $ledger->typeLabel }}</span>
</div>