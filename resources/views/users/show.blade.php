@extends('layouts.app')

@section('title', $person->username)

@section('content')
  <h2>{{ $person->username }}</h2>
  <p><img class="avatar-full" src="{{ $person->avatarUrl() }}" alt="{{ $person->username }}&#39;s avatar" width="100" height="100"></p>
  @if ($showEmail)
    <p class="meta">{{ $person->email }}</p>
  @endif
@endsection
