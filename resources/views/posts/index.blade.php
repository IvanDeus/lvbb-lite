@extends('layouts.app')

@section('content')
  @forelse ($posts as $post)
    <article class="post">
      <h2><a href="{{ route('post_detail', $post) }}">{{ $post->title }}</a></h2>
      <p class="meta">
        <a href="{{ route('user_detail', $post->author->username) }}"><img class="avatar" src="{{ $post->author->avatarUrl() }}" alt="" width="24" height="24">{{ $post->author->username }}</a>
        &middot; {{ $post->created_at->format('Y-m-d H:i') }}
        &middot; {{ $post->comments_count }} comment{{ $post->comments_count === 1 ? '' : 's' }}
      </p>
      {{-- Str::limit(239, '…') reproduces Django's truncatechars:240, which counts the ellipsis --}}
      <p class="body">{{ Str::limit($post->body, 239, '…') }}</p>
    </article>
  @empty
    <p>Nothing on the board yet. <a href="{{ route('post_create') }}">Post something</a>.</p>
  @endforelse
@endsection
