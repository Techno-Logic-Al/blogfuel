<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Post>
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(5);

        return [
            'topic' => fake()->sentence(3),
            'keywords' => 'AI, Laravel, automation',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'excerpt' => fake()->paragraph(),
            'intro' => fake()->paragraph(),
            'sections' => [
                [
                    'heading' => fake()->sentence(3),
                    'body' => fake()->paragraph(3)."\n\n".fake()->paragraph(3),
                ],
                [
                    'heading' => fake()->sentence(3),
                    'body' => fake()->paragraph(3)."\n\n".fake()->paragraph(3),
                ],
            ],
            'takeaway' => fake()->sentence(14),
            'tags' => ['AI', 'Laravel', 'Content'],
            'reading_time' => fake()->numberBetween(4, 9),
            'model' => 'gpt-5-mini',
        ];
    }
}
