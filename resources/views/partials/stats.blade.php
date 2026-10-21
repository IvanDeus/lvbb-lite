<p class="stats">
  {{ $stats['users'] }} {{ Str::plural('user', $stats['users']) }} &middot;
  {{ $stats['comments'] }} {{ Str::plural('comment', $stats['comments']) }} &middot;
  {{ $stats['topics'] }} {{ Str::plural('topic', $stats['topics']) }}
</p>
