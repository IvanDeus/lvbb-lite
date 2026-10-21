@extends('layouts.app')

@section('title', $editing ? 'Edit post' : 'New post')

@section('content')
  <h2>{{ $editing ? 'Edit post' : 'New post' }}</h2>

  <form method="post" action="{{ $editing ? route('post_update_store', $post) : route('post_store') }}">
    @csrf
    <p>
      <label for="title">Title</label>
      @error('title')<p class="errorlist">{{ $message }}</p>@enderror
      <input class="field" type="text" name="title" id="title" placeholder="Title"
             value="{{ old('title', $post->title) }}" maxlength="200" required>
    </p>
    <p>
      <label for="body">Body</label>
      @error('body')<p class="errorlist">{{ $message }}</p>@enderror
      <textarea class="field" name="body" id="body" rows="8" placeholder="What is on the board?" required>{{ old('body', $post->body) }}</textarea>
    </p>
    <button type="submit">{{ $editing ? 'Save' : 'Publish' }}</button>
  </form>
@endsection
