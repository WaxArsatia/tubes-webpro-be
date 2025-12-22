<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AIServiceInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use Smalot\PdfParser\Parser;

class OpenAIService implements AIServiceInterface
{
    /**
     * Store uploaded file content for processing.
     * OpenAI doesn't have a file upload API for chat completions,
     * so we read and store the content locally.
     *
     * @var array<string, string>
     */
    private array $fileContents = [];

    /**
     * Upload a file to process - extracts text content from PDF.
     */
    public function uploadFile(string $storagePath): ?string
    {
        try {
            if (! Storage::exists($storagePath)) {
                throw new Exception("File not found: {$storagePath}");
            }

            $absolutePath = Storage::path($storagePath);

            // Parse PDF and extract text content
            $parser = new Parser;
            $pdf = $parser->parseFile($absolutePath);
            $text = $pdf->getText();

            if (empty(trim($text))) {
                throw new Exception('Failed to extract text from PDF');
            }

            // Generate a unique ID for this file
            $fileId = 'openai_'.uniqid();

            // Store the extracted text
            $this->fileContents[$fileId] = $text;

            Log::info('File processed for OpenAI', [
                'storage_path' => $storagePath,
                'file_id' => $fileId,
                'text_length' => strlen($text),
            ]);

            return $fileId;
        } catch (Exception $e) {
            Log::error('OpenAI file processing failed', [
                'error' => $e->getMessage(),
                'storage_path' => $storagePath,
            ]);

            return null;
        }
    }

    /**
     * Generate a summary from a document file.
     */
    public function generateSummary(string $fileUri, string $fileName, string $summaryType, string $language = 'id'): string
    {
        try {
            $content = $this->getFileContent($fileUri);
            $prompt = $this->buildSummaryPrompt($summaryType, $fileName, $language);
            $model = config('ai.openai.model', 'gpt-4o-mini');

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional document summarizer. Provide accurate, well-structured summaries based on the document content.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt."\n\nDocument Content:\n".$content,
                    ],
                ],
                'temperature' => 0.7,
            ]);

            return $response->choices[0]->message->content;
        } catch (Exception $e) {
            Log::error('OpenAI summary generation failed', [
                'error' => $e->getMessage(),
                'file_uri' => $fileUri,
            ]);

            throw new Exception('Failed to generate summary: '.$e->getMessage());
        }
    }

    /**
     * Generate quiz questions from a document file.
     */
    public function generateQuiz(
        string $fileUri,
        string $fileName,
        int $questionCount,
        string $difficulty,
        string $questionType,
        string $language = 'id'
    ): array {
        try {
            $content = $this->getFileContent($fileUri);
            $prompt = $this->buildQuizPrompt($questionCount, $difficulty, $questionType, $fileName, $language);
            $model = config('ai.openai.model', 'gpt-4o-mini');

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert educational content creator. Generate high-quality quiz questions based on document content. Always respond with valid JSON only, no additional text.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt."\n\nDocument Content:\n".$content,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
            ]);

            $result = json_decode($response->choices[0]->message->content, true);

            return $result['questions'] ?? [];
        } catch (Exception $e) {
            Log::error('OpenAI quiz generation failed', [
                'error' => $e->getMessage(),
                'file_uri' => $fileUri,
            ]);

            throw new Exception('Failed to generate quiz: '.$e->getMessage());
        }
    }

    /**
     * Delete a file from the local storage.
     */
    public function deleteFile(string $fileUri): bool
    {
        try {
            unset($this->fileContents[$fileUri]);

            Log::info('File content deleted from OpenAI service', ['file_uri' => $fileUri]);

            return true;
        } catch (Exception $e) {
            Log::warning('OpenAI file content deletion failed', [
                'error' => $e->getMessage(),
                'file_uri' => $fileUri,
            ]);

            return false;
        }
    }

    /**
     * Get file content by URI.
     */
    private function getFileContent(string $fileUri): string
    {
        if (! isset($this->fileContents[$fileUri])) {
            throw new Exception("File content not found for URI: {$fileUri}");
        }

        return $this->fileContents[$fileUri];
    }

    /**
     * Build the summary prompt based on type.
     */
    private function buildSummaryPrompt(string $type, string $fileName, string $language = 'id'): string
    {
        $languageNames = [
            'en' => 'English',
            'id' => 'Indonesian (Bahasa Indonesia)',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'ar' => 'Arabic',
            'pt' => 'Portuguese',
        ];

        $languageName = $languageNames[$language] ?? 'English';
        $languageInstruction = "IMPORTANT: Write the entire summary in {$languageName}. All text must be in {$languageName}.";

        $prompts = [
            'concise' => "Analyze the document '{$fileName}' and provide a concise summary (2-3 paragraphs) that captures the main points, key concepts, and essential information. Focus on the most important ideas presented in the document.\n\n{$languageInstruction}",

            'detailed' => "Analyze the document '{$fileName}' and create a comprehensive, detailed summary that covers all major sections, key arguments, supporting evidence, and important details. Organize the summary logically with clear sections. The summary should be thorough enough that someone could understand the document's full scope without reading it.\n\n{$languageInstruction}",

            'bullet_points' => "Analyze the document '{$fileName}' and create a structured bullet-point summary. Use clear bullet points (•) to list:\n• Main topics and themes\n• Key concepts and definitions\n• Important findings and conclusions\n• Practical applications\n• Critical insights and takeaways\nKeep each bullet point concise but informative.\n\n{$languageInstruction}",

            'abstract' => "Analyze the document '{$fileName}' and write a formal academic-style abstract (150-250 words) that includes: the document's purpose, methodology or approach, key findings, and conclusions. Use formal academic language appropriate for a research paper abstract.\n\n{$languageInstruction}",
        ];

        return $prompts[$type] ?? $prompts['concise'];
    }

    /**
     * Build the quiz generation prompt.
     */
    private function buildQuizPrompt(int $count, string $difficulty, string $type, string $fileName, string $language = 'id'): string
    {
        $languageNames = [
            'en' => 'English',
            'id' => 'Indonesian (Bahasa Indonesia)',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'ar' => 'Arabic',
            'pt' => 'Portuguese',
        ];

        $languageName = $languageNames[$language] ?? 'English';
        $languageInstruction = "IMPORTANT: Write ALL questions, options, and explanations in {$languageName}. Every piece of text must be in {$languageName}.";

        $difficultyInstructions = [
            'easy' => 'basic concepts and fundamental understanding',
            'medium' => 'application of concepts and analytical thinking',
            'hard' => 'complex analysis, synthesis, and critical evaluation',
        ];

        $typeInstructions = [
            'multiple_choice' => 'multiple-choice questions. Each question must have EXACTLY 4 answer options.',
            'true_false' => "true/false questions. Each question must have EXACTLY 2 answer options: ['Benar', 'Salah'] for Indonesian or ['True', 'False'] for other languages.",
            'mixed' => "a mix of multiple-choice and true/false questions. Multiple-choice questions must have EXACTLY 4 options. True/false questions must have EXACTLY 2 options: ['Benar', 'Salah'] for Indonesian or ['True', 'False'] for other languages. Mix them roughly equally.",
        ];

        $difficultyDesc = $difficultyInstructions[$difficulty] ?? $difficultyInstructions['medium'];
        $typeDesc = $typeInstructions[$type] ?? $typeInstructions['multiple_choice'];

        // Build JSON example based on question type
        if ($type === 'true_false') {
            $jsonExample = '
{
    "questions": [
        {
            "id": 1,
            "question": "Question text here",
            "options": ["Benar", "Salah"],
            "correct_answer": 0,
            "explanation": "Brief explanation of the correct answer"
        }
    ]
}';
            $requirements = "- Each question MUST have exactly 2 options\n- Options must be ['Benar', 'Salah'] if language is Indonesian, or ['True', 'False'] for other languages\n- correct_answer must be 0 (for True/Benar) or 1 (for False/Salah)";
        } elseif ($type === 'mixed') {
            $jsonExample = '
{
    "questions": [
        {
            "id": 1,
            "question": "Multiple choice question text",
            "type": "multiple_choice",
            "options": ["Option A", "Option B", "Option C", "Option D"],
            "correct_answer": 0,
            "explanation": "Explanation for multiple choice"
        },
        {
            "id": 2,
            "question": "True/false question text",
            "type": "true_false",
            "options": ["Benar", "Salah"],
            "correct_answer": 1,
            "explanation": "Explanation for true/false"
        }
    ]
}';
            $requirements = "- For multiple-choice questions: provide exactly 4 options, correct_answer index 0-3\n- For true/false questions: provide exactly 2 options ['Benar', 'Salah'] for Indonesian or ['True', 'False'] for other languages, correct_answer 0 or 1\n- Mix roughly equal numbers of both types\n- Add a 'type' field to each question: 'multiple_choice' or 'true_false'";
        } else {
            $jsonExample = '
{
    "questions": [
        {
            "id": 1,
            "question": "Question text here",
            "options": ["Option A", "Option B", "Option C", "Option D"],
            "correct_answer": 0,
            "explanation": "Brief explanation of the correct answer"
        }
    ]
}';
            $requirements = "- Each question must have exactly 4 options\n- correct_answer index must be 0-3\n- Mix the position of correct answers (don't always make it option A/0)";
        }

        return "Analyze the document '{$fileName}' and generate exactly {$count} {$typeDesc}

Difficulty Level: {$difficulty}
Questions should test {$difficultyDesc}

Requirements:
- All questions must be based on the actual content of the document
- Each question should have a clear, unambiguous answer
{$requirements}
- Provide a brief explanation for each correct answer
- Ensure questions cover different parts/topics of the document
- Use clear, professional language
- Number questions starting from 1

{$languageInstruction}

Return the response as JSON with this exact structure:
{$jsonExample}

Generate questions that would genuinely test someone's understanding of the document content.";
    }
}
