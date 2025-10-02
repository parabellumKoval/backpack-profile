@if(in_array($entry->status, ['pending','failed']))
  <a href="{{ url($crud->route.'/'.$entry->id.'/process') }}"
     class="btn btn-sm btn-success"
     onclick="event.preventDefault(); document.getElementById('process-{{ $entry->id }}').submit();">
     <i class="la la-play"></i> Process
  </a>
  <form id="process-{{ $entry->id }}" action="{{ url($crud->route.'/'.$entry->id.'/process') }}" method="POST" style="display:none;">
    @csrf
  </form>
@endif
