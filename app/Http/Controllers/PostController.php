<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentRequest;
use App\Http\Requests\PostRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /**
     * `{post}` arrives as a string rather than a bound model: a number with no post behind it is
     * this page's own subject, and implicit binding would throw before it could be answered. The
     * cast keeps an id past bigint range a missing post too — PHP saturates it to the largest value
     * the column holds, so the lookup misses instead of the driver raising.
     */
    public function show(Request $request, string $post): View|Response
    {
        $found = Post::find((int) $post);

        if ($found === null) {
            return response()->view('posts.missing', [], 404);
        }

        $found->load('author');

        return view('posts.show', [
            'post' => $found,
            'comments' => Comment::paginateWithin($found->comments()->with('author'), $request->integer('page')),
            'canManage' => $found->canBeManagedBy($request->user()),
        ]);
    }

    public function storeComment(CommentRequest $request, Post $post): RedirectResponse
    {
        $comment = new Comment($request->validated());
        $comment->post()->associate($post);
        $comment->author()->associate($request->user());
        $comment->save();

        // A reply is the newest row in the thread, so it lives on the last page. Sending the writer
        // back to the first would leave them reading replies they had already read. `page` stays out
        // of the URL until there is a second page to ask for.
        $lastPage = Comment::lastPage($post->comments()->count());

        return redirect()->route('post_detail', $lastPage > 1 ? ['post' => $post, 'page' => $lastPage] : $post);
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
