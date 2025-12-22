<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalQuestions = 10;
        $correctAnswers = fake()->numberBetween(4, 10);
        $percentage = ($correctAnswers / $totalQuestions) * 100;

        return [
            'quiz_id' => Quiz::factory(),
            'user_id' => User::factory(),
            'status' => 'completed',
            'score' => $percentage,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'incorrect_answers' => $totalQuestions - $correctAnswers,
            'unanswered' => 0,
            'time_spent_seconds' => fake()->numberBetween(120, 600),
            'percentage' => $percentage,
            'passed' => $percentage >= 60,
            'answers' => [],
            'started_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'submitted_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'expires_at' => fake()->dateTimeBetween('now', '+1 hour'),
        ];
    }

    /**
     * Indicate that the attempt is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'score' => null,
            'correct_answers' => null,
            'incorrect_answers' => null,
            'percentage' => null,
            'passed' => null,
            'submitted_at' => null,
        ]);
    }

    /**
     * Indicate that the attempt has passed.
     */
    public function passed(): static
    {
        $totalQuestions = 10;
        $correctAnswers = fake()->numberBetween(7, 10);
        $percentage = ($correctAnswers / $totalQuestions) * 100;

        return $this->state(fn (array $attributes) => [
            'correct_answers' => $correctAnswers,
            'incorrect_answers' => $totalQuestions - $correctAnswers,
            'percentage' => $percentage,
            'passed' => true,
        ]);
    }

    /**
     * Indicate that the attempt has failed.
     */
    public function failed(): static
    {
        $totalQuestions = 10;
        $correctAnswers = fake()->numberBetween(0, 5);
        $percentage = ($correctAnswers / $totalQuestions) * 100;

        return $this->state(fn (array $attributes) => [
            'correct_answers' => $correctAnswers,
            'incorrect_answers' => $totalQuestions - $correctAnswers,
            'percentage' => $percentage,
            'passed' => false,
        ]);
    }
}
