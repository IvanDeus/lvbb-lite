@extends('layouts.app')

@section('title', 'Profile')

@section('content')
  <h2>Profile</h2>

  <p class="meta">
    <img class="avatar" src="{{ $user->avatarUrl() }}" alt="" width="24" height="24">
    {{ $user->username }} &middot; {{ $user->email }}
  </p>

  <form method="post" action="{{ route('profile_store') }}" enctype="multipart/form-data">
    @csrf
    @include('partials.avatar-picker', [
      'current' => in_array($user->avatar, \App\Models\User::AVATAR_PRESETS, true) ? $user->avatar : null,
    ])
    @if ($user->hasUploadedAvatar())
      <p class="meta">Your uploaded picture is kept unless you pick one of the four above.</p>
    @endif
    <button type="submit">Save</button>
  </form>
@endsection
