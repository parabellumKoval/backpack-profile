@if(in_array($entry->status, ['approved','pending']))
    <a href="{{ url($crud->route.'/'.$entry->id.'/paid') }}" class="btn btn-sm btn-primary" onclick="event.preventDefault(); document.getElementById('paid-{{ $entry->id }}').submit();">
        <i class="la la-money"></i> Mark paid
    </a>
    <form id="paid-{{ $entry->id }}" method="POST" action="{{ url($crud->route.'/'.$entry->id.'/paid') }}" style="display:none;">
        @csrf
    </form>
@endif
