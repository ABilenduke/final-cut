<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\BlogPostResource;
use App\Models\BlogPost;
use App\Observers\BlogPostObserver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class BlogPostController extends Controller
{
    /**
     * GET /api/blog-posts
     *
     * Published posts, newest first, without bodies. Versioned-cache pattern
     * identical to the featured-slides/ticker endpoints.
     */
    public function index(): JsonResponse
    {
        $version = (int) Cache::get(BlogPostObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "blog_posts_public:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            return BlogPost::active()
                ->orderByDesc('published_at')
                ->orderBy('id')
                ->get()
                ->map(fn (BlogPost $post) => (new BlogPostResource($post))->resolve())
                ->all();
        });

        return $this->successResponse($data);
    }

    /**
     * GET /api/blog-posts/{slug}
     *
     * One published post with its body. Drafts and unknown slugs 404 —
     * scheduling is enforced here, not just hidden in the list.
     */
    public function show(string $slug): JsonResponse
    {
        $version = (int) Cache::get(BlogPostObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = 'blog_post_public:'.sha1($slug).":v{$version}";

        $data = Cache::remember($cacheKey, 300, function () use ($slug) {
            $post = BlogPost::active()->where('slug', $slug)->first();

            return $post === null
                ? null
                : (new BlogPostResource($post, withBody: true))->resolve();
        });

        if ($data === null) {
            return $this->errorResponse([['message' => 'Post not found']], 404);
        }

        return $this->successResponse($data);
    }
}
