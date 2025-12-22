<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AIServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateQuizRequest;
use App\Http\Requests\SubmitQuizRequest;
use App\Http\Resources\QuizAttemptResource;
use App\Http\Resources\QuizResource;
use App\Models\Activity;
use App\Models\Document;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    use ApiResponse;

    public function __construct(private AIServiceInterface $aiService) {}

    /**
     * Generate a new quiz from a document.
     */
    public function generate(GenerateQuizRequest $request): JsonResponse
    {
        $document = Document::where('user_id', auth()->id())
            ->findOrFail($request->document_id);

        if (! $document->isCompleted()) {
            return $this->errorResponse(
                'The document must be fully processed before generating quizzes',
                422
            );
        }

        try {
            // Upload document to Gemini
            $fileUri = $this->aiService->uploadFile($document->file_path);

            if (! $fileUri) {
                return $this->errorResponse(
                    'Failed to upload document for processing',
                    500
                );
            }

            // Generate quiz using AI
            $questions = $this->aiService->generateQuiz(
                $fileUri,
                $document->original_filename,
                $request->question_count,
                $request->difficulty,
                $request->question_type,
                $request->get('language', 'id')
            );

            // Clean up uploaded file
            $this->aiService->deleteFile($fileUri);

            if (empty($questions)) {
                return $this->errorResponse(
                    'Failed to generate quiz questions',
                    500
                );
            }
        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to generate quiz: '.$e->getMessage(),
                500
            );
        }

        $quiz = Quiz::create([
            'document_id' => $document->id,
            'user_id' => auth()->id(),
            'difficulty' => $request->difficulty,
            'question_count' => $request->question_count,
            'question_type' => $request->question_type,
            'questions' => $questions,
        ]);

        // Log activity
        Activity::log(
            auth()->id(),
            'quiz_generate',
            "Generated {$request->difficulty} quiz for '{$document->original_filename}'",
            [
                'document_id' => $document->id,
                'document_name' => $document->original_filename,
                'quiz_id' => $quiz->id,
                'difficulty' => $request->difficulty,
                'question_count' => $request->question_count,
            ],
            $document->id
        );

        return $this->successResponse(
            data: ['quiz' => new QuizResource($quiz->load('document'))],
            message: 'Quiz generated successfully',
            status: 201
        );
    }

    /**
     * Get a specific quiz (without answers).
     */
    public function show(int $id): JsonResponse
    {
        $quiz = Quiz::with(['document', 'completedAttempts'])
            ->withCount('attempts')
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return $this->successResponse(
            data: ['quiz' => new QuizResource($quiz)]
        );
    }

    /**
     * Get all quizzes for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Quiz::with('document')
            ->where('user_id', auth()->id());

        // Filters
        if ($request->has('document_id')) {
            $query->where('document_id', $request->document_id);
        }

        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        // Sort
        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'difficulty' => $query->orderBy('difficulty', 'asc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min($request->get('per_page', 15), 50);
        $quizzes = $query->paginate($perPage);

        return $this->successResponse(
            data: [
                'quizzes' => QuizResource::collection($quizzes->items()),
                'pagination' => [
                    'current_page' => $quizzes->currentPage(),
                    'per_page' => $quizzes->perPage(),
                    'total' => $quizzes->total(),
                    'last_page' => $quizzes->lastPage(),
                ],
            ]
        );
    }

    /**
     * Start a new quiz attempt.
     */
    public function startAttempt(int $id): JsonResponse
    {
        $quiz = Quiz::where('user_id', auth()->id())
            ->findOrFail($id);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => auth()->id(),
            'status' => 'in_progress',
            'started_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        // Log activity
        Activity::log(
            auth()->id(),
            'quiz_start',
            'Started quiz attempt',
            [
                'quiz_id' => $quiz->id,
                'attempt_id' => $attempt->id,
                'difficulty' => $quiz->difficulty,
            ]
        );

        return $this->successResponse(
            data: [
                'attempt_id' => $attempt->id,
                'quiz_id' => $quiz->id,
                'started_at' => $attempt->started_at,
                'expires_at' => $attempt->expires_at,
            ],
            message: 'Quiz attempt started'
        );
    }

    /**
     * Submit quiz answers and get results.
     */
    public function submitAnswers(int $id, SubmitQuizRequest $request): JsonResponse
    {
        $quiz = Quiz::where('user_id', auth()->id())
            ->findOrFail($id);

        $attempt = QuizAttempt::where('user_id', auth()->id())
            ->where('quiz_id', $quiz->id)
            ->findOrFail($request->attempt_id);

        if (! $attempt->isInProgress()) {
            return $this->errorResponse('This attempt has already been completed', 400);
        }

        // Grade the quiz
        $result = $this->gradeQuiz($quiz, $request->answers);

        // Update attempt
        $attempt->update([
            'status' => 'completed',
            'score' => $result['score'],
            'total_questions' => $result['total_questions'],
            'correct_answers' => $result['correct_answers'],
            'incorrect_answers' => $result['incorrect_answers'],
            'unanswered' => $result['unanswered'],
            'time_spent_seconds' => $request->time_spent_seconds,
            'percentage' => $result['percentage'],
            'passed' => $result['percentage'] >= 60,
            'answers' => $result['detailed_answers'],
            'submitted_at' => now(),
        ]);

        // Log activity
        Activity::log(
            auth()->id(),
            'quiz_complete',
            "Completed quiz with {$result['percentage']}% score",
            [
                'quiz_id' => $quiz->id,
                'attempt_id' => $attempt->id,
                'score' => $result['score'],
                'percentage' => $result['percentage'],
            ]
        );

        return $this->successResponse(
            data: [
                'quiz_attempt' => new QuizAttemptResource($attempt),
                'answers' => $result['detailed_answers'],
                'quiz' => [
                    'id' => $quiz->id,
                    'document_id' => $quiz->document_id,
                    'difficulty' => $quiz->difficulty,
                    'question_count' => $quiz->question_count,
                ],
            ],
            message: 'Quiz submitted successfully'
        );
    }

    /**
     * Get a specific quiz attempt result.
     */
    public function getAttempt(int $quizId, int $attemptId): JsonResponse
    {
        $attempt = QuizAttempt::with('quiz.document')
            ->where('user_id', auth()->id())
            ->where('quiz_id', $quizId)
            ->findOrFail($attemptId);

        return $this->successResponse(
            data: [
                'quiz_attempt' => new QuizAttemptResource($attempt),
                'answers' => $attempt->answers,
                'quiz' => [
                    'id' => $attempt->quiz->id,
                    'document_name' => $attempt->quiz->document->original_filename,
                    'difficulty' => $attempt->quiz->difficulty,
                ],
            ]
        );
    }

    /**
     * Get all attempts for a quiz.
     */
    public function getAttempts(int $id): JsonResponse
    {
        $quiz = Quiz::with('document')
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $attempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(
            data: [
                'quiz' => [
                    'id' => $quiz->id,
                    'document_name' => $quiz->document->original_filename,
                    'difficulty' => $quiz->difficulty,
                    'question_count' => $quiz->question_count,
                ],
                'attempts' => QuizAttemptResource::collection($attempts),
            ]
        );
    }

    /**
     * Delete a quiz.
     */
    public function destroy(int $id): JsonResponse
    {
        $quiz = Quiz::where('user_id', auth()->id())
            ->findOrFail($id);

        $quiz->delete();

        return $this->successResponse(
            message: 'Quiz deleted successfully'
        );
    }

    /**
     * Grade the quiz and return results.
     */
    private function gradeQuiz(Quiz $quiz, array $userAnswers): array
    {
        $questions = $quiz->questions;
        $correctAnswers = 0;
        $incorrectAnswers = 0;
        $unanswered = 0;
        $detailedAnswers = [];

        $answerMap = collect($userAnswers)->keyBy('question_id');

        foreach ($questions as $question) {
            $userAnswer = $answerMap->get($question['id']);

            if ($userAnswer === null) {
                $unanswered++;
                $isCorrect = false;
            } else {
                $isCorrect = $userAnswer['answer_index'] == $question['correct_answer'];
                if ($isCorrect) {
                    $correctAnswers++;
                } else {
                    $incorrectAnswers++;
                }
            }

            $detailedAnswers[] = [
                'question_id' => $question['id'],
                'question' => $question['question'],
                'options' => $question['options'],
                'user_answer' => $userAnswer['answer_index'] ?? null,
                'correct_answer' => $question['correct_answer'],
                'is_correct' => $isCorrect,
                'explanation' => $question['explanation'] ?? '',
            ];
        }

        $totalQuestions = count($questions);
        $score = ($correctAnswers / $totalQuestions) * 100;
        $percentage = round($score, 2);

        return [
            'score' => $score,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'incorrect_answers' => $incorrectAnswers,
            'unanswered' => $unanswered,
            'percentage' => $percentage,
            'detailed_answers' => $detailedAnswers,
        ];
    }
}
