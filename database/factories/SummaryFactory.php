<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Summary>
 */
class SummaryFactory extends Factory
{
    protected $model = Summary::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = fake()->paragraphs(5, true);

        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'content' => $content,
            'summary_type' => fake()->randomElement(['concise', 'detailed', 'bullet_points', 'abstract']),
            'word_count' => str_word_count($content),
            'language' => 'en',
            'status' => 'completed',
            'processing_time_seconds' => fake()->numberBetween(5, 30),
            'views_count' => fake()->numberBetween(0, 50),
            'last_viewed_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the summary is concise type.
     */
    public function concise(): static
    {
        return $this->state(fn (array $attributes) => [
            'summary_type' => 'concise',
            'word_count' => fake()->numberBetween(150, 300),
        ]);
    }

    /**
     * Indicate that the summary is detailed type.
     */
    public function detailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'summary_type' => 'detailed',
            'word_count' => fake()->numberBetween(500, 1000),
        ]);
    }
}
