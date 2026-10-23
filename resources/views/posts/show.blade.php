@extends('layouts.app')

@section('title', $post->title)

@section('content')
  <article class="post">
    <h2>{{ $post->title }}</h2>
    <p class="meta"><a href="{{ route('user_detail', $post->author->username) }}"><img class="avatar" src="{{ $post->author->avatarUrl() }}" alt="" width="24" height="24">{{ $post->author->username }}</a> &middot; {{ $post->created_at->format('Y-m-d H:i') }}</p>
    <p class="body">{{ $post->body }}</p>
    @if ($canManage)
      <div class="actions">
        <a href="{{ route('post_update', $post) }}">edit</a>
        <a href="{{ route('post_delete', $post) }}">delete</a>
      </div>
    @endif
  </article>

  <h3>Comments</h3>
  @forelse ($comments as $comment)
    <div class="comment">
      <p class="meta"><a href="{{ route('user_detail', $comment->author->username) }}"><img class="avatar" src="{{ $comment->author->avatarUrl() }}" alt="" width="24" height="24">{{ $comment->author->username }}</a> &middot; {{ $comment->created_at->format('Y-m-d H:i') }}</p>
      <p class="body">{{ $comment->body }}</p>
    </div>
  @empty
    <p>No comments yet.</p>
  @endforelse

  @include('partials.pager', ['comments' => $comments, 'link' => fn (int $page) => route('post_detail', ['post' => $post, 'page' => $page])])

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
