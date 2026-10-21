@extends('layouts.app')

@section('title', 'Delete post')

@section('content')
  <h2>Delete "{{ $post->title }}"?</h2>
  <p>This also removes its comments.</p>
  <form method="post" action="{{ route('post_delete_store', $post) }}">
    @csrf
    <div class="actions">
      <button type="submit">Yes, delete</button>
      <a href="{{ route('post_detail', $post) }}">Cancel</a>
    </div>
  </form>
@endsection
