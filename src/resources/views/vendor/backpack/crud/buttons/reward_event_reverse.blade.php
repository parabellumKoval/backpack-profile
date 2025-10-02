@if(!$entry->is_reversal && $entry->status === 'processed')
  <a href="{{ url($crud->route.'/'.$entry->id.'/reverse') }}"
     class="btn btn-sm btn-warning"
     onclick="event.preventDefault(); if(confirm('Create reversal?')) document.getElementById('reverse-{{ $entry->id }}').submit();">
     <i class="la la-undo"></i> Reverse
  </a>
  <form id="reverse-{{ $entry->id }}" action="{{ url($crud->route.'/'.$entry->id.'/reverse') }}" method="POST" style="display:none;">
    @csrf
  </form>
@endif
