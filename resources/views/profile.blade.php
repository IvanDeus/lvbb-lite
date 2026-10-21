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

  <h3>Change password</h3>
  <form method="post" action="{{ route('password_store') }}">
    @csrf
    <p class="meta">At least 8 characters, and not this account's own username or address, or a row of digits.</p>
    <p>
      <label for="old_password">Current password</label>
      @error('old_password')<p class="errorlist">{{ $message }}</p>@enderror
      <input class="field" type="password" name="old_password" id="old_password"
             placeholder="Current password" autocomplete="current-password" required>
    </p>
    <p>
      <label for="new_password1">New password</label>
      @error('new_password1')<p class="errorlist">{{ $message }}</p>@enderror
      <input class="field" type="password" name="new_password1" id="new_password1"
             placeholder="New password" autocomplete="new-password" minlength="8" required>
    </p>
    <p>
      <label for="new_password2">Type the new password again</label>
      @error('new_password2')<p class="errorlist">{{ $message }}</p>@enderror
      <input class="field" type="password" name="new_password2" id="new_password2"
             placeholder="Type the new password again" autocomplete="new-password" minlength="8" required>
    </p>
    <button type="submit">Change password</button>
  </form>
@endsection
