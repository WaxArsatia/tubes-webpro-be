<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $questions = $this->questions;

        // Remove correct answers if not showing results
        if (! $this->shouldShowAnswers()) {
            $questions = collect($questions)->map(function ($question) {
                return [
                    'id' => $question['id'],
                    'question' => $question['question'],
                    'options' => $question['options'],
                ];
            })->toArray();
        }

        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'document_name' => $this->whenLoaded('document', fn () => $this->document->original_filename),
            'user_id' => $this->user_id,
            'difficulty' => $this->difficulty,
            'question_count' => $this->question_count,
            'question_type' => $this->question_type,
            'questions' => $questions,
            'attempts_count' => $this->whenCounted('attempts'),
            'best_score' => $this->when(
                $this->relationLoaded('completedAttempts'),
                fn () => $this->completedAttempts->max('score')
            ),
            'average_score' => $this->when(
                $this->relationLoaded('completedAttempts'),
                fn () => $this->completedAttempts->avg('score')
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Determine if answers should be shown.
     */
    protected function shouldShowAnswers(): bool
    {
        return request()->routeIs('quizzes.submit') || request()->routeIs('quizzes.attempt');
    }
}
