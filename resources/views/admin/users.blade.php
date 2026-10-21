@extends('layouts.app')

@section('title', 'Admin · Users')

@section('content')
  <h2>Users</h2>
  <p><a href="{{ route('admin_posts') }}">Posts</a> &middot; <a href="{{ route('admin_comments') }}">Comments</a></p>

  <table>
    <tr><th>Avatar</th><th>Name</th><th>E-mail</th></tr>
    @forelse ($users as $person)
      <tr>
        <td><img class="avatar-full" src="{{ $person->avatarUrl() }}" alt="" width="48" height="48"></td>
        <td>{{ $person->username }}</td>
        <td>{{ $person->email }}</td>
      </tr>
    @empty
      <tr><td colspan="3">No users.</td></tr>
    @endforelse
  </table>

  <p class="meta">Nothing here is editable. Accounts stay where the board keeps them.</p>
@endsection
