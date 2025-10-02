@if(in_array($entry->status, ['pending','approved']))
    <a href="{{ url($crud->route.'/'.$entry->id.'/reject') }}" class="btn btn-sm btn-warning" onclick="event.preventDefault(); document.getElementById('reject-{{ $entry->id }}').submit();">
        <i class="la la-times"></i> Reject
    </a>
    <form id="reject-{{ $entry->id }}" method="POST" action="{{ url($crud->route.'/'.$entry->id.'/reject') }}" style="display:none;">
        @csrf
    </form>
@endif
