@extends('layouts.app')

@section('title', 'Log in')

@section('content')
  <h2>Log in</h2>

  <form method="post" action="{{ route('login_store') }}">
    @csrf
    <p>
      <label for="username">Username</label>
      @error('username')<p class="errorlist">{{ $message }}</p>@enderror
      <input class="field" type="text" name="username" id="username"
             value="{{ old('username') }}" autocomplete="username" autofocus required>
    </p>
    <p>
      <label for="password">Password</label>
      @error('password')<p class="errorlist">{{ $message }}</p>@enderror
      <input class="field" type="password" name="password" id="password" autocomplete="current-password" required>
    </p>
    <button type="submit">Log in</button>
  </form>

  <p><a href="{{ route('signup') }}">Sign up</a></p>
@endsection
