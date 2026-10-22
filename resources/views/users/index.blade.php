@extends('layouts.app')

@section('title', 'Members')

@section('content')
  <h2>Members</h2>

  <table>
    <tr><th>Avatar</th><th>Name</th><th>Type</th><th>Last action</th><th></th></tr>
    @foreach ($people as $person)
      <tr>
        <td>
          <a href="{{ route('user_detail', $person->username) }}">
            <img class="avatar-full" src="{{ $person->avatarUrl() }}" alt="" width="48" height="48">
          </a>
        </td>
        <td><a href="{{ route('user_detail', $person->username) }}">{{ $person->username }}</a></td>
        <td>{{ $person->is_staff ? 'admin' : 'member' }}</td>
        <td>{{ $person->last_activity->format('Y-m-d H:i') }}</td>
        <td>
          {{-- The acting admin's own row never offers a button: switching it off would end the
               session's right to this page, and the board designs that out instead of warning. --}}
          @if ((int) $person->id !== (int) auth()->id())
            <form method="post" action="{{ route('member_role_store', $person) }}">
              @csrf
              <button type="submit">{{ $person->is_staff ? 'make member' : 'make admin' }}</button>
            </form>
          @endif
        </td>
      </tr>
    @endforeach
  </table>
@endsection
