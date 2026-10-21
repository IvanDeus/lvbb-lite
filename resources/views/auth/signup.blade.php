@extends('layouts.app')

@section('title', 'Sign up')

@section('content')
  <h2>Sign up</h2>

  <form method="post" action="{{ route('signup_store') }}" enctype="multipart/form-data">
    @csrf
    <p>
      <label for="username">Username</label>
      @error('username')<p class="errorlist">{{ $message }}</p>@enderror
      <input class="field" type="text" name="username" id="username" placeholder="Username"
             value="{{ old('username') }}" autocomplete="username" maxlength="150" required>
    </p>
    <p>
      <label for="email">E-mail</label>
      @error('email')<p class="errorlist">{{ $message }}</p>@enderror
      <input class="field" type="email" name="email" id="email" placeholder="E-mail"
             value="{{ old('email') }}" autocomplete="email" required>
    </p>
    <p>
      <label for="password">Password</label>
      @error('password')<p class="errorlist">{{ $message }}</p>@enderror
      <input class="field" type="password" name="password" id="password"
             placeholder="Password (min 6 chars)" autocomplete="new-password" minlength="6" required>
    </p>
    @include('partials.avatar-picker', ['current' => null])
    <button type="submit">Sign up</button>
  </form>
@endsection
