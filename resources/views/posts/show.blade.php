@extends('layouts.app')

@section('title', $post->title)

@section('content')
  <article class="post">
    <h2>{{ $post->title }}</h2>
    <p class="meta">{{ $post->author->username }} &middot; {{ $post->created_at->format('Y-m-d H:i') }}</p>
    <p class="body">{{ $post->body }}</p>
    @if ($canManage)
      <div class="actions">
        <a href="{{ route('post_update', $post) }}">edit</a>
        <a href="{{ route('post_delete', $post) }}">delete</a>
      </div>
    @endif
  </article>

  <h3>Comments</h3>
  @forelse ($post->comments as $comment)
    <div class="comment">
      <p class="meta">{{ $comment->author->username }} &middot; {{ $comment->created_at->format('Y-m-d H:i') }}</p>
      <p class="body">{{ $comment->body }}</p>
    </div>
  @empty
    <p>No comments yet.</p>
  @endforelse

  @auth
    <form method="post" action="{{ route('comment_store', $post) }}">
      @csrf
      <p>
        <label for="body">Body</label>
        @error('body')<p class="errorlist">{{ $message }}</p>@enderror
        <textarea class="field" name="body" id="body" rows="3" placeholder="Reply..." required>{{ old('body') }}</textarea>
      </p>
      <button type="submit">Reply</button>
    </form>
  @else
    <p><a href="{{ route('login') }}">Log in</a> to leave a comment.</p>
  @endauth
@endsection
