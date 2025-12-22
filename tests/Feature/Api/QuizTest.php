<?php

use App\Contracts\AIServiceInterface;
use App\Models\Document;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\mock;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->document = Document::factory()->for($this->user)->create(['status' => 'completed']);

    // Mock AIServiceInterface to avoid real API calls
    mock(AIServiceInterface::class)
        ->shouldReceive('uploadFile')
        ->andReturn('files/mock-file-id')
        ->shouldReceive('generateQuiz')
        ->andReturn([
            [
                'id' => 1,
                'question' => 'What is the main topic of this document?',
                'options' => ['Option A', 'Option B', 'Option C', 'Option D'],
                'correct_answer' => 0,
                'explanation' => 'Option A is correct because it represents the main topic.',
            ],
        ])
        ->shouldReceive('deleteFile')
        ->andReturn(true);
});

describe('Quiz Generation', function () {
    it('can generate a quiz', function () {
        $response = actingAs($this->user)->postJson('/api/quizzes/generate', [
            'document_id' => $this->document->id,
            'difficulty' => 'medium',
            'question_count' => 5,
            'question_type' => 'multiple_choice',
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'quiz' => [
                        'id',
                        'document_id',
                        'difficulty',
                        'questions',
                        'question_count',
                        'created_at',
                    ],
                ],
            ]);

        assertDatabaseHas('quizzes', [
            'document_id' => $this->document->id,
            'user_id' => $this->user->id,
            'difficulty' => 'medium',
            'question_count' => 5,
        ]);
    });

    it('validates document_id is required', function () {
        $response = actingAs($this->user)->postJson('/api/quizzes/generate', [
            'difficulty' => 'medium',
            'question_count' => 5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_id']);
    });

    it('validates difficulty is valid', function () {
        $response = actingAs($this->user)->postJson('/api/quizzes/generate', [
            'document_id' => $this->document->id,
            'difficulty' => 'invalid',
            'question_count' => 5,
            'question_type' => 'multiple_choice',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['difficulty']);
    });

    it('validates question_count range', function () {
        $response = actingAs($this->user)->postJson('/api/quizzes/generate', [
            'document_id' => $this->document->id,
            'difficulty' => 'medium',
            'question_count' => 0,
            'question_type' => 'multiple_choice',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['question_count']);
    });

    it('cannot generate quiz for incomplete document', function () {
        $pendingDoc = Document::factory()->for($this->user)->create(['status' => 'pending']);

        $response = actingAs($this->user)->postJson('/api/quizzes/generate', [
            'document_id' => $pendingDoc->id,
            'difficulty' => 'medium',
            'question_count' => 5,
            'question_type' => 'multiple_choice',
        ]);

        $response->assertUnprocessable();
    });

    it('requires authentication', function () {
        $response = postJson('/api/quizzes/generate', [
            'document_id' => $this->document->id,
            'difficulty' => 'medium',
            'question_count' => 5,
            'question_type' => 'multiple_choice',
        ]);

        $response->assertUnauthorized();
    });
});

describe('Quiz Show', function () {
    it('can show a quiz without answers', function () {
        $quiz = Quiz::factory()->for($this->document)->for($this->user)->create();

        $response = actingAs($this->user)->getJson("/api/quizzes/{$quiz->id}");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'quiz' => [
                        'id',
                        'questions' => [
                            '*' => [
                                'question',
                                'options',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertJsonMissing(['correct_answer']);
    });

    it('cannot view other user quizzes', function () {
        $otherUser = User::factory()->create();
        $otherDoc = Document::factory()->for($otherUser)->create();
        $quiz = Quiz::factory()->for($otherDoc)->for($otherUser)->create();

        $response = actingAs($this->user)->getJson("/api/quizzes/{$quiz->id}");

        $response->assertNotFound(); // Security pattern - don't reveal resource existence
    });
});

describe('Quiz List', function () {
    it('can list user quizzes', function () {
        Quiz::factory()->count(3)->for($this->document)->for($this->user)->create();
        Quiz::factory()->count(2)->create(); // Other user's quizzes

        $response = actingAs($this->user)->getJson('/api/quizzes');

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data.quizzes')
            ->assertJsonPath('data.pagination.total', 3);
    });

    it('can filter by difficulty', function () {
        Quiz::factory()->count(2)->for($this->document)->for($this->user)->create(['difficulty' => 'easy']);
        Quiz::factory()->count(3)->for($this->document)->for($this->user)->create(['difficulty' => 'hard']);

        $response = actingAs($this->user)->getJson('/api/quizzes?difficulty=easy');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data.quizzes');
    });
});

describe('Quiz Attempt Start', function () {
    it('can start a quiz attempt', function () {
        $quiz = Quiz::factory()->for($this->document)->for($this->user)->create();

        $response = actingAs($this->user)->postJson("/api/quizzes/{$quiz->id}/start");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'attempt_id',
                    'quiz_id',
                    'started_at',
                    'expires_at',
                ],
            ]);

        assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $this->user->id,
            'status' => 'in_progress',
        ]);
    });

    it('cannot start other user quiz', function () {
        $otherUser = User::factory()->create();
        $otherDoc = Document::factory()->for($otherUser)->create();
        $quiz = Quiz::factory()->for($otherDoc)->for($otherUser)->create();

        $response = actingAs($this->user)->postJson("/api/quizzes/{$quiz->id}/start");

        $response->assertNotFound(); // Security pattern - don't reveal resource existence
    });
});

describe('Quiz Attempt Submit', function () {
    it('can submit quiz answers', function () {
        $quiz = Quiz::factory()->for($this->document)->for($this->user)->create();
        $attempt = QuizAttempt::factory()->for($quiz)->for($this->user)->create([
            'status' => 'in_progress',
        ]);

        $answers = [];
        foreach ($quiz->questions as $question) {
            $answers[] = [
                'question_id' => $question['id'],
                'answer_index' => $question['correct_answer'],
            ];
        }

        $response = actingAs($this->user)->postJson("/api/quizzes/{$quiz->id}/submit", [
            'attempt_id' => $attempt->id,
            'answers' => $answers,
            'time_spent_seconds' => 120,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'quiz_attempt' => [
                        'id',
                        'score',
                        'percentage',
                        'passed',
                    ],
                    'answers',
                    'quiz',
                ],
            ]);

        expect($attempt->fresh()->status)->toBe('completed');
    });

    it('validates answers are required', function () {
        $quiz = Quiz::factory()->for($this->document)->for($this->user)->create();
        $attempt = QuizAttempt::factory()->for($quiz)->for($this->user)->create(['status' => 'in_progress']);

        $response = actingAs($this->user)->postJson("/api/quizzes/{$quiz->id}/submit", [
            'attempt_id' => $attempt->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['answers']);
    });

    it('cannot submit already completed attempt', function () {
        $quiz = Quiz::factory()->for($this->document)->for($this->user)->create();
        $attempt = QuizAttempt::factory()->for($quiz)->for($this->user)->create([
            'status' => 'completed',
        ]);

        $response = actingAs($this->user)->postJson("/api/quizzes/{$quiz->id}/submit", [
            'attempt_id' => $attempt->id,
            'answers' => [['question_id' => 1, 'answer_index' => 0]],
            'time_spent_seconds' => 60,
        ]);

        $response->assertStatus(400); // Bad request for already completed
    });

    it('cannot submit other user attempt', function () {
        $otherUser = User::factory()->create();
        $otherDoc = Document::factory()->for($otherUser)->create();
        $quiz = Quiz::factory()->for($otherDoc)->for($otherUser)->create();
        $attempt = QuizAttempt::factory()->for($quiz)->for($otherUser)->create(['status' => 'in_progress']);

        $response = actingAs($this->user)->postJson("/api/quizzes/{$quiz->id}/submit", [
            'attempt_id' => $attempt->id,
            'answers' => [['question_id' => 1, 'answer_index' => 0]],
            'time_spent_seconds' => 60,
        ]);

        $response->assertNotFound(); // Security pattern - don't reveal resource existence
    });
});

describe('Quiz Attempt Results', function () {
    it('can view completed attempt results', function () {
        $quiz = Quiz::factory()->for($this->document)->for($this->user)->create();
        $attempt = QuizAttempt::factory()->for($quiz)->for($this->user)->create([
            'status' => 'completed',
            'score' => 4,
        ]);

        $response = actingAs($this->user)->getJson("/api/quizzes/{$quiz->id}/attempts/{$attempt->id}");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'quiz_attempt' => [
                        'id',
                        'score',
                        'status',
                    ],
                    'answers',
                    'quiz',
                ],
            ]);
    });

    it('cannot view other user attempts', function () {
        $otherUser = User::factory()->create();
        $otherDoc = Document::factory()->for($otherUser)->create();
        $quiz = Quiz::factory()->for($otherDoc)->for($otherUser)->create();
        $attempt = QuizAttempt::factory()->for($quiz)->for($otherUser)->create();

        $response = actingAs($this->user)->getJson("/api/quizzes/{$quiz->id}/attempts/{$attempt->id}");

        $response->assertNotFound(); // Security pattern - don't reveal resource existence
    });
});

describe('Quiz Attempts List', function () {
    it('can list attempts for a quiz', function () {
        $quiz = Quiz::factory()->for($this->document)->for($this->user)->create();
        QuizAttempt::factory()->count(3)->for($quiz)->for($this->user)->create();

        $response = actingAs($this->user)->getJson("/api/quizzes/{$quiz->id}/attempts");

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data.attempts');
    });

    it('cannot view attempts for other user quiz', function () {
        $otherUser = User::factory()->create();
        $otherDoc = Document::factory()->for($otherUser)->create();
        $quiz = Quiz::factory()->for($otherDoc)->for($otherUser)->create();

        $response = actingAs($this->user)->getJson("/api/quizzes/{$quiz->id}/attempts");

        $response->assertNotFound(); // Security pattern - don't reveal resource existence
    });
});

describe('Quiz Delete', function () {
    it('can delete own quiz', function () {
        $quiz = Quiz::factory()->for($this->document)->for($this->user)->create();

        $response = actingAs($this->user)->deleteJson("/api/quizzes/{$quiz->id}");

        $response->assertSuccessful();
        expect(Quiz::find($quiz->id))->toBeNull();
    });

    it('cannot delete other user quiz', function () {
        $otherUser = User::factory()->create();
        $otherDoc = Document::factory()->for($otherUser)->create();
        $quiz = Quiz::factory()->for($otherDoc)->for($otherUser)->create();

        $response = actingAs($this->user)->deleteJson("/api/quizzes/{$quiz->id}");

        $response->assertNotFound(); // Security pattern - don't reveal resource existence
    });
});
