@if($entry->status === 'pending')
    <a href="{{ url($crud->route.'/'.$entry->id.'/approve') }}" class="btn btn-sm btn-success" onclick="event.preventDefault(); document.getElementById('approve-{{ $entry->id }}').submit();">
        <i class="la la-check"></i> Approve
    </a>
    <form id="approve-{{ $entry->id }}" method="POST" action="{{ url($crud->route.'/'.$entry->id.'/approve') }}" style="display:none;">
        @csrf
    </form>
@endif
