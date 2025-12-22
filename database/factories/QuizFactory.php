<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quiz>
 */
class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $questionCount = fake()->numberBetween(5, 15);
        $questions = [];

        for ($i = 1; $i <= $questionCount; $i++) {
            $questions[] = [
                'id' => $i,
                'question' => fake()->sentence().'?',
                'options' => [
                    fake()->sentence(4),
                    fake()->sentence(4),
                    fake()->sentence(4),
                    fake()->sentence(4),
                ],
                'correct_answer' => fake()->numberBetween(0, 3),
                'explanation' => fake()->sentence(),
            ];
        }

        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'question_count' => $questionCount,
            'question_type' => fake()->randomElement(['multiple_choice', 'true_false', 'mixed']),
            'questions' => $questions,
        ];
    }

    /**
     * Indicate that the quiz is easy.
     */
    public function easy(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => 'easy',
        ]);
    }

    /**
     * Indicate that the quiz is medium difficulty.
     */
    public function medium(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => 'medium',
        ]);
    }

    /**
     * Indicate that the quiz is hard.
     */
    public function hard(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => 'hard',
        ]);
    }
}
