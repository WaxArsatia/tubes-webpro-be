# Quiz API Specification

**Base URL**: `http://localhost:8000/api`  
**Auth**: Required - `Authorization: Bearer {token}`  
**Related Frontend**: `src/routes/quiz/$id.tsx`, `src/routes/quiz/$id_.result.tsx`, `src/routes/dashboard/$id.summarize.tsx`

## Overview
Quiz generation and management endpoints. Generate AI-powered quizzes from documents, take quizzes, submit answers, and view results with detailed analytics.

## Response Format
**Success**: `{"message": "...", "data": {...}}`  
**Error**: `{"message": "...", "errors": {"field": ["error"]}}`  
**Status Codes**: 200 (OK), 201 (Created), 400 (Bad Request), 401 (Unauthorized), 404 (Not Found), 422 (Validation)

## Quiz Object Schema
```json
{
  "id": 12,
  "document_id": 5,
  "user_id": 1,
  "difficulty": "medium",
  "question_count": 10,
  "question_type": "multiple_choice",
  "questions": [
    {
      "id": 45,
      "question": "What is artificial intelligence?",
      "options": [
        "Simulation of human intelligence",
        "A type of computer hardware",
        "Programming language",
        "Database system"
      ],
      "correct_answer": 0,
      "explanation": "AI is the simulation of human intelligence processes by machines."
    }
  ],
  "created_at": "2025-12-22T10:00:00.000000Z",
  "updated_at": "2025-12-22T10:00:00.000000Z"
}
```

**Difficulty Levels**: `easy`, `medium`, `hard`  
**Question Types**: `multiple_choice`, `true_false`, `mixed`

---

## API Endpoints

### 1. Generate Quiz
`POST /api/quizzes/generate` - **Auth required**

Generate a new quiz from a document using AI.

**Request**:
```json
{
  "document_id": 5,
  "question_count": 10,
  "difficulty": "medium",
  "question_type": "multiple_choice"
}
```

**Validation**:
- `document_id`: required, exists in user's documents, status must be "completed"
- `question_count`: required, integer, min:5, max:50
- `difficulty`: required, in:easy,medium,hard
- `question_type`: required, in:multiple_choice,true_false,mixed

**Response (201)**:
```json
{

  "message": "Quiz generated successfully",
  "data": {
    "quiz": {
      "id": 12,
      "document_id": 5,
      "user_id": 1,
      "difficulty": "medium",
      "question_count": 10,
      "question_type": "multiple_choice",
      "questions": [
        {
          "id": 45,
          "question": "What is artificial intelligence?",
          "options": [
            "Simulation of human intelligence",
            "A type of computer hardware",
            "Programming language",
            "Database system"
          ],
          "correct_answer": 0,
          "explanation": "AI is the simulation of human intelligence processes by machines."
        }
      ],
      "created_at": "2025-12-22T10:00:00.000000Z",
      "updated_at": "2025-12-22T10:00:00.000000Z"
    }
  }
}
```

**Error Response (422)**:
```json
{
  "message": "Validation failed",
  "errors": {
    "document_id": ["The document must be fully processed before generating quizzes."],
    "question_count": ["The question count must be between 5 and 50."]
  }
}
```

**Error Response (500)** - AI Generation Failed:
```json
{
  "message": "Failed to generate quiz: AI service temporarily unavailable"
}
```

**Error Response (500)** - Invalid Quiz Structure:
```json
{
  "message": "Failed to generate quiz questions"
}
```

**Implementation Notes**:
- **AI Engine**: Google Gemini AI (`gemini-2.0-flash` model) for quiz generation
- **Document Processing**: PDFs uploaded directly to Gemini File API
- **Service Layer**: `App\Services\GeminiService` handles all AI operations
- **Quiz Generation Flow**:
  1. Upload document to Gemini File API
  2. Send document reference + generation parameters to Gemini AI
  3. Receive AI-generated questions in structured JSON format
  4. Clean up uploaded file from Gemini
  5. Validate and store quiz in database
- **Structured Output**: Uses Gemini's JSON schema validation for consistent format
- **Error Handling**: Returns 500 error if upload fails, generation fails, or questions are invalid
- **System Instructions**: AI instructed to act as "expert educational content creator"
- Store questions with correct answers (hidden from GET endpoints)
- Log activity as `quiz_generate`

**AI Technical Details**:
- **Model**: `gemini-2.0-flash` (Google Gemini 2.0 Flash)
- **Multimodal Input**: Supports PDF documents with text + images
- **Output Format**: Structured JSON with schema validation
- **Question Schema**: Each question includes:
  - `id`: Integer (sequential numbering from 1)
  - `question`: String (clear, unambiguous question text)
  - `options`: Array of 4 strings (answer choices)
  - `correct_answer`: Integer 0-3 (index of correct option)
  - `explanation`: String (why the answer is correct)
- **Prompt Engineering**: Custom prompts based on difficulty and type:
  - `easy`: Tests basic concepts and fundamental understanding
  - `medium`: Tests application of concepts and analytical thinking
  - `hard`: Tests complex analysis, synthesis, and critical evaluation
- **Question Distribution**: AI mixes correct answer positions (not always option A)
- **Content Coverage**: Questions cover different parts/topics of the document

---

### 2. Get Quiz by ID
`GET /api/quizzes/{id}` - **Auth required**

Retrieve a quiz (without showing correct answers).

**Response (200)**:
```json
{

  "data": {
    "quiz": {
      "id": 12,
      "document_id": 5,
      "document_name": "Introduction to AI.pdf",
      "user_id": 1,
      "difficulty": "medium",
      "question_count": 10,
      "question_type": "multiple_choice",
      "questions": [
        {
          "id": 45,
          "question": "What is artificial intelligence?",
          "options": [
            "Simulation of human intelligence",
            "A type of computer hardware",
            "Programming language",
            "Database system"
          ]
        }
      ],
      "attempts_count": 3,
      "best_score": 85,
      "average_score": 78.3,
      "created_at": "2025-12-22T10:00:00.000000Z"
    }
  }
}
```

**Notes**:
- Never return `correct_answer` or `explanation` fields on GET
- Include quiz statistics (attempts, best score)
- Returns 404 if quiz doesn't exist or belongs to another user

---

### 3. Get All Quizzes
`GET /api/quizzes` - **Auth required**

Get all quizzes created by the authenticated user.

**Query Parameters**:
- `document_id` (optional): Filter by document
- `difficulty` (optional): Filter by difficulty
- `sort` (optional): Sort order - `newest` (default), `oldest`, `difficulty`
- `per_page` (optional): Items per page (default: 15, max: 50)

**Response (200)**:
```json
{

  "data": {
    "quizzes": [
      {
        "id": 12,
        "document_id": 5,
        "document_name": "Introduction to AI.pdf",
        "difficulty": "medium",
        "question_count": 10,
        "question_type": "multiple_choice",
        "attempts_count": 3,
        "best_score": 85,
        "last_attempt_at": "2025-12-22T15:30:00.000000Z",
        "created_at": "2025-12-22T10:00:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 8,
      "last_page": 1
    }
  }
}
```

---

### 4. Start Quiz Attempt
`POST /api/quizzes/{id}/start` - **Auth required**

Start a new quiz attempt (creates a session, logs activity).

**Response (200)**:
```json
{

  "message": "Quiz attempt started",
  "data": {
    "attempt_id": 34,
    "quiz_id": 12,
    "started_at": "2025-12-22T15:25:00.000000Z",
    "expires_at": "2025-12-22T16:25:00.000000Z"
  }
}
```

**Notes**:
- Creates a quiz_attempts record with status "in_progress"
- Optional: Set expiration time (e.g., 60 minutes)
- Log activity as `quiz_start`

---

### 5. Submit Quiz Answers
`POST /api/quizzes/{id}/submit` - **Auth required**

Submit answers and get results.

**Request**:
```json
{
  "attempt_id": 34,
  "answers": [
    {
      "question_id": 45,
      "answer_index": 0
    },
    {
      "question_id": 46,
      "answer_index": 2
    }
  ],
  "time_spent_seconds": 320
}
```

**Validation**:
- `attempt_id`: required, exists, belongs to user, status is "in_progress"
- `answers`: required, array, must include all questions
- `answers.*.question_id`: required, exists in quiz
- `answers.*.answer_index`: required, integer, min:0, max:(options_count-1)
- `time_spent_seconds`: required, integer, min:0

**Response (200)**:
```json
{

  "message": "Quiz submitted successfully",
  "data": {
    "quiz_attempt": {
      "id": 34,
      "quiz_id": 12,
      "user_id": 1,
      "score": 85,
      "total_questions": 10,
      "correct_answers": 8,
      "incorrect_answers": 2,
      "unanswered": 0,
      "time_spent_seconds": 320,
      "percentage": 80.0,
      "passed": true,
      "submitted_at": "2025-12-22T15:30:00.000000Z"
    },
    "answers": [
      {
        "question_id": 45,
        "question": "What is artificial intelligence?",
        "options": ["Option A", "Option B", "Option C", "Option D"],
        "user_answer": 0,
        "correct_answer": 0,
        "is_correct": true,
        "explanation": "AI is the simulation of human intelligence processes."
      },
      {
        "question_id": 46,
        "question": "Which year was AI coined?",
        "options": ["1950", "1956", "1960", "1970"],
        "user_answer": 2,
        "correct_answer": 1,
        "is_correct": false,
        "explanation": "The term 'artificial intelligence' was coined in 1956."
      }
    ],
    "quiz": {
      "id": 12,
      "document_id": 5,
      "document_name": "Introduction to AI.pdf",
      "difficulty": "medium",
      "question_count": 10
    }
  }
}
```

**Implementation Notes**:
- Calculate score: (correct_answers / total_questions) * 100
- `passed` threshold: typically 60% or configurable per difficulty
- Update attempt status to "completed"
- Log activity as `quiz_complete`
- Store individual answer results for review

---

### 6. Get Quiz Result
`GET /api/quizzes/{quiz_id}/attempts/{attempt_id}` - **Auth required**

Retrieve detailed results of a specific quiz attempt.

**Response (200)**:
```json
{

  "data": {
    "quiz_attempt": {
      "id": 34,
      "quiz_id": 12,
      "user_id": 1,
      "score": 85,
      "total_questions": 10,
      "correct_answers": 8,
      "incorrect_answers": 2,
      "time_spent_seconds": 320,
      "percentage": 80.0,
      "passed": true,
      "submitted_at": "2025-12-22T15:30:00.000000Z"
    },
    "answers": [
      {
        "question_id": 45,
        "question": "What is artificial intelligence?",
        "options": ["Option A", "Option B", "Option C", "Option D"],
        "user_answer": 0,
        "correct_answer": 0,
        "is_correct": true,
        "explanation": "Detailed explanation here."
      }
    ],
    "quiz": {
      "id": 12,
      "document_name": "Introduction to AI.pdf",
      "difficulty": "medium"
    }
  }
}
```

---

### 7. Get Quiz Attempts History
`GET /api/quizzes/{id}/attempts` - **Auth required**

Get all attempts for a specific quiz.

**Response (200)**:
```json
{

  "data": {
    "quiz": {
      "id": 12,
      "document_name": "Introduction to AI.pdf",
      "difficulty": "medium",
      "question_count": 10
    },
    "attempts": [
      {
        "id": 34,
        "score": 85,
        "percentage": 80.0,
        "passed": true,
        "time_spent_seconds": 320,
        "submitted_at": "2025-12-22T15:30:00.000000Z"
      },
      {
        "id": 28,
        "score": 75,
        "percentage": 70.0,
        "passed": true,
        "time_spent_seconds": 420,
        "submitted_at": "2025-12-20T10:15:00.000000Z"
      }
    ],
    "stats": {
      "total_attempts": 3,
      "best_score": 85,
      "average_score": 78.3,
      "average_time_seconds": 360,
      "pass_rate": 100.0
    }
  }
}
```

---

### 8. Delete Quiz
`DELETE /api/quizzes/{id}` - **Auth required**

Delete a quiz and all associated attempts/results.

**Response (200)**:
```json
{

  "message": "Quiz deleted successfully"
}
```

**Notes**:
- Cascade delete all quiz attempts and answers
- Only owner can delete their quizzes
- Consider soft deletes for data retention

---

## TypeScript Implementation

### Types (already in `src/lib/types.ts`, but verify completeness)
```typescript
export interface QuizQuestion {
  id: number;
  question: string;
  options: string[];
  correct_answer?: number; // Only in results
  explanation?: string; // Only in results
}

export interface Quiz {
  id: number;
  document_id: number;
  document_name?: string;
  user_id: number;
  difficulty: "easy" | "medium" | "hard";
  question_count: number;
  question_type: "multiple_choice" | "true_false" | "mixed";
  questions: QuizQuestion[];
  attempts_count?: number;
  best_score?: number;
  average_score?: number;
  created_at: string;
  updated_at: string;
}

export interface QuizGenerationRequest {
  document_id: number;
  question_count: number;
  difficulty: "easy" | "medium" | "hard";
  question_type: "multiple_choice" | "true_false" | "mixed";
}

export interface QuizAttempt {
  id: number;
  quiz_id: number;
  user_id: number;
  score: number;
  total_questions: number;
  correct_answers: number;
  incorrect_answers: number;
  unanswered?: number;
  time_spent_seconds: number;
  percentage: number;
  passed: boolean;
  submitted_at: string;
}

export interface QuizAnswerResult {
  question_id: number;
  question: string;
  options: string[];
  user_answer: number;
  correct_answer: number;
  is_correct: boolean;
  explanation: string;
}

export interface QuizSubmitRequest {
  attempt_id: number;
  answers: Array<{
    question_id: number;
    answer_index: number;
  }>;
  time_spent_seconds: number;
}

export interface QuizResultResponse {
  success: boolean;
  message?: string;
  data: {
    quiz_attempt: QuizAttempt;
    answers: QuizAnswerResult[];
    quiz: Partial<Quiz>;
  };
}
```

### TanStack Query Hooks (create `src/data/quizzes.ts`)
```typescript
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import type { Quiz, QuizGenerationRequest, QuizSubmitRequest } from "@/lib/types";

export function useGenerateQuiz() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (data: QuizGenerationRequest) => {
      return await apiClient.post("/quizzes/generate", data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["quizzes"] });
    },
  });
}

export function useQuiz(id: number) {
  return useQuery({
    queryKey: ["quizzes", id],
    queryFn: async () => {
      return await apiClient.get<{ data: { quiz: Quiz } }>(`/quizzes/${id}`);
    },
    enabled: !!id,
  });
}

export function useQuizzes(filters?: { document_id?: number; difficulty?: string }) {
  return useQuery({
    queryKey: ["quizzes", filters],
    queryFn: async () => {
      const params = new URLSearchParams(filters as Record<string, string>);
      return await apiClient.get(`/quizzes?${params}`);
    },
  });
}

export function useStartQuiz() {
  return useMutation({
    mutationFn: async (quizId: number) => {
      return await apiClient.post(`/quizzes/${quizId}/start`);
    },
  });
}

export function useSubmitQuiz() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async ({ quizId, data }: { quizId: number; data: QuizSubmitRequest }) => {
      return await apiClient.post(`/quizzes/${quizId}/submit`, data);
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["quizzes", variables.quizId] });
    },
  });
}

export function useQuizAttempts(quizId: number) {
  return useQuery({
    queryKey: ["quizzes", quizId, "attempts"],
    queryFn: async () => {
      return await apiClient.get(`/quizzes/${quizId}/attempts`);
    },
    enabled: !!quizId,
  });
}

export function useQuizResult(quizId: number, attemptId: number) {
  return useQuery({
    queryKey: ["quizzes", quizId, "attempts", attemptId],
    queryFn: async () => {
      return await apiClient.get(`/quizzes/${quizId}/attempts/${attemptId}`);
    },
    enabled: !!quizId && !!attemptId,
  });
}

export function useDeleteQuiz() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (quizId: number) => {
      return await apiClient.delete(`/quizzes/${quizId}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["quizzes"] });
    },
  });
}
```

---

## AI Integration Best Practices
1. **Prompt Engineering**: Craft specific prompts for question generation based on difficulty
2. **Context Length**: Split large documents into chunks for AI processing
3. **Quality Control**: Validate generated questions (uniqueness, clarity, accuracy)
4. **Caching**: Cache generated quizzes to avoid redundant AI API calls
5. **Rate Limiting**: Limit quiz generation frequency per user
6. **Cost Management**: Track AI API usage and implement user quotas
7. **Fallback**: Have pre-generated questions as fallback if AI fails
