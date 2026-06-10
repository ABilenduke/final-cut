<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BlogPost;
use App\Support\AssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for the public blog endpoints. The list view omits `body`
 * (set `$withBody = true` for the detail endpoint). `date` carries the
 * published date as an ISO date string — the field name the frontend's
 * BlogPost contract has used since the static-data era.
 *
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
{
    public function __construct($resource, public bool $withBody = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'date' => $this->published_at?->toDateString(),
            'author' => $this->author_name,
            'imageUrl' => AssetUrl::resolve($this->image_url),
            ...($this->withBody ? ['body' => $this->body] : []),
        ];
    }
}
