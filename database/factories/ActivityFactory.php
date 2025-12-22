<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $activityTypes = [
            'document_upload',
            'document_view',
            'document_delete',
            'summary_generate',
            'summary_view',
            'quiz_generate',
            'quiz_start',
            'quiz_complete',
            'profile_update',
        ];

        $activityType = fake()->randomElement($activityTypes);

        return [
            'user_id' => User::factory(),
            'activity_type' => $activityType,
            'description' => $this->generateDescription($activityType),
            'metadata' => $this->generateMetadata($activityType),
            'document_id' => fake()->optional(0.7)->randomElement([null, Document::factory()]),
        ];
    }

    /**
     * Generate activity description based on type.
     */
    private function generateDescription(string $type): string
    {
        return match ($type) {
            'document_upload' => 'Uploaded document '.fake()->word().'.pdf',
            'document_view' => 'Viewed document '.fake()->word().'.pdf',
            'document_delete' => 'Deleted document '.fake()->word().'.pdf',
            'summary_generate' => 'Generated summary for document',
            'summary_view' => 'Viewed summary',
            'quiz_generate' => 'Generated quiz from document',
            'quiz_start' => 'Started quiz attempt',
            'quiz_complete' => 'Completed quiz with '.fake()->numberBetween(50, 100).'% score',
            'profile_update' => 'Updated profile information',
            default => 'Performed '.$type,
        };
    }

    /**
     * Generate metadata based on activity type.
     */
    private function generateMetadata(string $type): array
    {
        return match ($type) {
            'quiz_complete' => [
                'score' => fake()->numberBetween(50, 100),
                'total_questions' => 10,
            ],
            'summary_generate' => [
                'word_count' => fake()->numberBetween(200, 500),
                'summary_type' => fake()->randomElement(['concise', 'detailed']),
            ],
            default => [],
        };
    }
}
