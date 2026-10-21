@extends('layouts.app')

@section('title', 'Admin · Comments')

@section('content')
  <h2>Comments</h2>
  <p><a href="{{ route('admin_posts') }}">Posts</a> &middot; <a href="{{ route('admin_users') }}">Users</a></p>

  <table>
    <tr><th>Post</th><th>Author</th><th>Created</th><th></th></tr>
    @forelse ($comments as $comment)
      <tr>
        <td><a href="{{ route('admin_post', $comment->post) }}">{{ $comment->post->title }}</a></td>
        <td><img class="avatar" src="{{ $comment->author->avatarUrl() }}" alt="" width="24" height="24">{{ $comment->author->username }}</td>
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
