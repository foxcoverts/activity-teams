<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NewsFeedController extends Controller
{
    public function atom(Request $request, User $user): Response
    {
        abort_if($user->trashed() || $user->isSuspended(), 403);

        return $this->feed($request, $user, 'feeds.news-atom', 'application/atom+xml');
    }

    public function rss(Request $request, User $user): Response
    {
        abort_if($user->trashed() || $user->isSuspended(), 403);

        return $this->feed($request, $user, 'feeds.news-rss', 'application/rss+xml');
    }

    private function feed(Request $request, User $user, string $view, string $contentType): Response
    {
        $posts = NewsPost::query()
            ->with(['team', 'author'])
            ->forUser($user)
            ->live()
            ->orderByDesc('published_at')
            ->get();

        $body = view($view, [
            'user' => $user,
            'posts' => $posts,
            'selfUrl' => $request->url(),
            'title' => __(':Name News', ['name' => config('app.name')]),
            'updated' => $posts->first()?->published_at ?? now(),
        ])->render();

        return response($body, 200, [
            'Content-Type' => $contentType.'; charset=UTF-8',
        ]);
    }
}
