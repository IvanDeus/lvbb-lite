@extends('layouts.app')

@section('title', 'No such post')

@section('head')
  <meta http-equiv="refresh" content="2;url={{ route('post_list') }}">
@endsection

@section('content')
  <h2>No such post</h2>
  <p>That topic does not exist, or it has been deleted. Back to the board in a moment…</p>
  <p><a href="{{ route('post_list') }}">Go to the board now</a></p>
@endsection
