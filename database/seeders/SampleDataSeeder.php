<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Document;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    /**
     * Seed the application's database with sample data.
     */
    public function run(): void
    {
        // Create 5 regular users
        $users = User::factory()->count(5)->create();

        // Create documents for each user
        foreach ($users as $user) {
            // Create 2-4 documents per user
            $documents = Document::factory()
                ->count(rand(2, 4))
                ->for($user)
                ->create();

            foreach ($documents as $document) {
                // Generate summaries for completed documents
                if ($document->status === 'completed') {
                    Summary::factory()
                        ->count(rand(1, 2))
                        ->for($document)
                        ->for($user)
                        ->create();

                    // Generate 1-2 quizzes per document
                    $quizzes = Quiz::factory()
                        ->count(rand(1, 2))
                        ->for($document)
                        ->for($user)
                        ->create();

                    // Create quiz attempts
                    foreach ($quizzes as $quiz) {
                        QuizAttempt::factory()
                            ->count(rand(1, 3))
                            ->for($quiz)
                            ->for($user)
                            ->create();
                    }
                }

                // Log activities for this document
                Activity::log(
                    $user->id,
                    'document_uploaded',
                    "Uploaded document: {$document->title}",
                    [],
                    $document->id
                );
            }
        }

        $this->command->info('Sample data seeded successfully!');
    }
}
