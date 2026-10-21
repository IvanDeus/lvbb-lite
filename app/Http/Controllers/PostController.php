<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentRequest;
use App\Http\Requests\PostRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PostController extends Controller
{
    public function index(): View
    {
        return view('posts.index', [
            'posts' => Post::with('author')->withCount('comments')->latest('created_at')->get(),
        ]);
    }

    public function show(Request $request, Post $post): View
    {
        $post->load(['author', 'comments.author']);

        return view('posts.show', [
            'post' => $post,
            'canManage' => $post->canBeManagedBy($request->user()),
        ]);
    }

    public function storeComment(CommentRequest $request, Post $post): RedirectResponse
    {
        $comment = new Comment($request->validated());
        $comment->post()->associate($post);
        $comment->author()->associate($request->user());
        $comment->save();

        return redirect()->route('post_detail', $post);
    }

    public function create(): View
    {
        return view('posts.form', ['post' => new Post, 'editing' => false]);
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $post = new Post($request->validated());
        $post->author()->associate($request->user());
        $post->save();

        return redirect()->route('post_detail', $post);
    }

    public function edit(Request $request, Post $post): View|RedirectResponse
    {
        if (! $post->canBeManagedBy($request->user())) {
            return redirect()->route('post_detail', $post);
        }

        return view('posts.form', ['post' => $post, 'editing' => true]);
    }

    public function update(PostRequest $request, Post $post): View|RedirectResponse
    {
        if (! $post->canBeManagedBy($request->user())) {
            return redirect()->route('post_detail', $post);
        }

        $post->fill($request->validated())->save();

        return redirect()->route('post_detail', $post);
    }

    public function confirmDelete(Request $request, Post $post): View|RedirectResponse
    {
        if (! $post->canBeManagedBy($request->user())) {
            return redirect()->route('post_detail', $post);
        }

        return view('posts.confirm_delete', ['post' => $post]);
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        if (! $post->canBeManagedBy($request->user())) {
            return redirect()->route('post_detail', $post);
        }

        $post->delete();

        return redirect()->route('post_list');
    }
}
