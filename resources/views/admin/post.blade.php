@extends('layouts.app')

@section('title', 'Admin · '.$post->title)

@section('content')
  <h2>{{ $post->title }}</h2>
  <p class="meta">{{ $post->author->username }} &middot; {{ $post->created_at->format('Y-m-d H:i') }} &middot; #{{ $post->id }}</p>
  <p class="body">{{ $post->body }}</p>

  <div class="actions">
    <a href="{{ route('post_update', $post) }}">edit</a>
    <a href="{{ route('post_detail', $post) }}">view on board</a>
    <a href="{{ route('admin_posts') }}">back to posts</a>
  </div>

  <h3>Comments ({{ $post->comments->count() }})</h3>
  <table>
    <tr><th>Author</th><th>Body</th><th>Created</th><th></th></tr>
    @forelse ($post->comments as $comment)
      <tr>
        <td>{{ $comment->author->username }}</td>
        <td>{{ Str::limit($comment->body, 60, '…') }}</td>
        <td>{{ $comment->created_at->format('Y-m-d H:i') }}</td>
        <td>
          <form method="post" action="{{ route('admin_comment_delete', $comment) }}">
            @csrf
            <button type="submit">delete</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="4">No comments.</td></tr>
    @endforelse
  </table>
@endsection
