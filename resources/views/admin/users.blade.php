@extends('layouts.app')

@section('title', 'Admin · Users')

@section('content')
  <h2>Users</h2>
  <p><a href="{{ route('admin_posts') }}">Posts</a> &middot; <a href="{{ route('admin_comments') }}">Comments</a></p>

  <table>
    <tr><th>Avatar</th><th>Name</th><th>E-mail</th><th>Last action</th></tr>
    @forelse ($users as $person)
      <tr>
        <td><img class="avatar-full" src="{{ $person->avatarUrl() }}" alt="" width="48" height="48"></td>
        <td>{{ $person->username }}</td>
        <td>{{ $person->email }}</td>
        <td>{{ $person->last_activity->format('Y-m-d H:i') }}</td>
      </tr>
    @empty
      <tr><td colspan="4">No users.</td></tr>
    @endforelse
  </table>

  <p class="meta">Nothing here is editable. Types are switched on the <a href="{{ route('member_list') }}">members list</a>.</p>
@endsection
