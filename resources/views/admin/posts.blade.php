@extends('layouts.app')

@section('title', 'Admin · Posts')

@section('content')
  <h2>Posts</h2>
  <p><a href="{{ route('admin_comments') }}">Comments</a> &middot; <a href="{{ route('admin_users') }}">Users</a></p>

  <table>
    <tr><th>Title</th><th>Author</th><th>Created</th><th></th></tr>
    @forelse ($posts as $post)
      <tr>
        <td><a href="{{ route('admin_post', $post) }}">{{ $post->title }}</a></td>
        <td><img class="avatar" src="{{ $post->author->avatarUrl() }}" alt="" width="24" height="24">{{ $post->author->username }}</td>
        <td>{{ $post->created_at->format('Y-m-d H:i') }}</td>
        <td>
          <form method="post" action="{{ route('admin_post_delete', $post) }}" onsubmit="return confirm('Delete this post and its comments?')">
            @csrf
            <button type="submit">delete</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="4">No posts.</td></tr>
    @endforelse
  </table>
@endsection
