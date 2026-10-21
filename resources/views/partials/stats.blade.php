<p class="stats">
  {{-- The member roll-call is staff's to read, so only their footer carries the link. --}}
  @if (auth()->user()?->is_staff)
    <a href="{{ route('member_list') }}">{{ $stats['users'] }} {{ Str::plural('user', $stats['users']) }}</a>
  @else
    {{ $stats['users'] }} {{ Str::plural('user', $stats['users']) }}
  @endif &middot;
  {{ $stats['comments'] }} {{ Str::plural('comment', $stats['comments']) }} &middot;
  {{ $stats['topics'] }} {{ Str::plural('topic', $stats['topics']) }}
</p>
