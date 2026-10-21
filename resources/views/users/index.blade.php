@extends('layouts.app')

@section('title', 'Members')

@section('content')
  <h2>Members</h2>

  <table>
    <tr><th>Avatar</th><th>Name</th><th>Type</th></tr>
    @foreach ($people as $person)
      <tr>
        <td>
          <a href="{{ route('user_detail', $person->username) }}">
            <img class="avatar-full" src="{{ $person->avatarUrl() }}" alt="" width="48" height="48">
          </a>
        </td>
        <td><a href="{{ route('user_detail', $person->username) }}">{{ $person->username }}</a></td>
        <td>{{ $person->is_staff ? 'admin' : 'member' }}</td>
      </tr>
    @endforeach
  </table>
@endsection
