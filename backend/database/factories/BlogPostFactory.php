<?php

namespace Database\Factories;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BlogPost> */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->paragraph(),
            'author_name' => fake()->name(),
            'image_url' => null,
            'body' => fake()->paragraph()."\n\n".fake()->paragraph(),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()->subDay()]);
    }
}
