{{-- Two arguments: $comments, the page itself, and $link, a closure turning a page number into this
     list's own URL. The hrefs come from route() rather than the paginator's url(), which builds an
     absolute address from the request's host and renders through a Tailwind view this board has no
     CSS for. An end that does not exist is a plain word, not a link to the page already on screen, and
     the outer pair follows the same rule: standing on page one makes the jump to page one no
     destination. --}}
@if ($comments->hasPages())
  <nav class="pager">
    @if ($comments->onFirstPage())
      <span class="pager-off" title="first page">&laquo;</span>
      <span class="pager-off">&lsaquo; previous</span>
    @else
      <a href="{{ $link(1) }}" title="first page">&laquo;</a>
      <a href="{{ $link($comments->currentPage() - 1) }}">&lsaquo; previous</a>
    @endif

    <span>page {{ $comments->currentPage() }} of {{ $comments->lastPage() }}</span>

    @if ($comments->hasMorePages())
      <a href="{{ $link($comments->currentPage() + 1) }}">next &rsaquo;</a>
      <a href="{{ $link($comments->lastPage()) }}" title="last page">&raquo;</a>
    @else
      <span class="pager-off">next &rsaquo;</span>
      <span class="pager-off" title="last page">&raquo;</span>
    @endif
  </nav>
@endif
