<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AIServiceInterface;
use Exception;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Schema;
use Gemini\Data\UploadedFile;
use Gemini\Enums\DataType;
use Gemini\Enums\MimeType;
use Gemini\Enums\ResponseMimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeminiService implements AIServiceInterface
{
    /**
     * Upload a file to Gemini File API.
     */
    public function uploadFile(string $storagePath): ?string
    {
        try {
            if (! Storage::exists($storagePath)) {
                throw new Exception("File not found: {$storagePath}");
            }

            $absolutePath = Storage::path($storagePath);
            $displayName = basename($storagePath);

            $response = Gemini::files()->upload(
                filename: $absolutePath,
                mimeType: MimeType::APPLICATION_PDF,
                displayName: $displayName
            );

            Log::info('File uploaded to Gemini', [
                'display_name' => $displayName,
                'file_uri' => $response->name,
            ]);

            return $response->name;
        } catch (Exception $e) {
            Log::error('Gemini file upload failed', [
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
            $prompt = $this->buildSummaryPrompt($summaryType, $fileName, $language);

            $response = Gemini::generativeModel(model: 'gemini-2.0-flash')
                ->withSystemInstruction(
                    Content::parse('You are a professional document summarizer. Provide accurate, well-structured summaries based on the document content.')
                )
                ->generateContent([
                    $prompt,
                    new UploadedFile(
                        fileUri: $fileUri,
                        mimeType: MimeType::APPLICATION_PDF
                    ),
                ]);

            return $response->text();
        } catch (Exception $e) {
            Log::error('Gemini summary generation failed', [
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
            $prompt = $this->buildQuizPrompt($questionCount, $difficulty, $questionType, $fileName, $language);

            $schema = $this->buildQuizSchema();

            $response = Gemini::generativeModel(model: 'gemini-2.0-flash')
                ->withSystemInstruction(
                    Content::parse('You are an expert educational content creator. Generate high-quality quiz questions based on document content.')
                )
                ->withGenerationConfig(
                    generationConfig: new GenerationConfig(
                        responseMimeType: ResponseMimeType::APPLICATION_JSON,
                        responseSchema: $schema
                    )
                )
                ->generateContent([
                    $prompt,
                    new UploadedFile(
                        fileUri: $fileUri,
                        mimeType: MimeType::APPLICATION_PDF
                    ),
                ]);

            $result = $response->json();

            return $result['questions'] ?? [];
        } catch (Exception $e) {
            Log::error('Gemini quiz generation failed', [
                'error' => $e->getMessage(),
                'file_uri' => $fileUri,
            ]);

            throw new Exception('Failed to generate quiz: '.$e->getMessage());
        }
    }

    /**
     * Delete a file from Gemini File API.
     */
    public function deleteFile(string $fileUri): bool
    {
        try {
            Gemini::files()->delete($fileUri);

            Log::info('File deleted from Gemini', ['file_uri' => $fileUri]);

            return true;
        } catch (Exception $e) {
            Log::warning('Gemini file deletion failed', [
                'error' => $e->getMessage(),
                'file_uri' => $fileUri,
            ]);

            return false;
        }
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

        // Build format instructions based on question type
        $formatInstructions = match ($type) {
            'true_false' => "- Each question MUST have exactly 2 options\n- Options must be ['Benar', 'Salah'] if language is Indonesian, or ['True', 'False'] for other languages\n- correct_answer must be 0 (for True/Benar) or 1 (for False/Salah)",
            'mixed' => "- For multiple-choice questions: provide exactly 4 options, correct_answer index 0-3\n- For true/false questions: provide exactly 2 options ['Benar', 'Salah'] for Indonesian or ['True', 'False'] for other languages, correct_answer 0 or 1\n- Mix roughly equal numbers of both types\n- Add a 'type' field to each question: 'multiple_choice' or 'true_false'",
            default => "- Each question must have exactly 4 options\n- correct_answer index must be 0-3\n- Mix the position of correct answers (don't always make it option A/0)",
        };

        return "Analyze the document '{$fileName}' and generate exactly {$count} {$typeDesc}

Difficulty Level: {$difficulty}
Questions should test {$difficultyDesc}

Requirements:
- All questions must be based on the actual content of the document
- Each question should have a clear, unambiguous answer
{$formatInstructions}
- Provide a brief explanation for each correct answer
- Ensure questions cover different parts/topics of the document
- Use clear, professional language
- Number questions starting from 1

{$languageInstruction}

Generate questions that would genuinely test someone's understanding of the document content.";
    }

    /**
     * Build the JSON schema for quiz questions.
     */
    private function buildQuizSchema(): Schema
    {
        return new Schema(
            type: DataType::OBJECT,
            properties: [
                'questions' => new Schema(
                    type: DataType::ARRAY,
                    items: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'id' => new Schema(
                                type: DataType::INTEGER,
                                description: 'Question number starting from 1'
                            ),
                            'question' => new Schema(
                                type: DataType::STRING,
                                description: 'The question text'
                            ),
                            'type' => new Schema(
                                type: DataType::STRING,
                                description: 'Question type: multiple_choice or true_false (for mixed type only)'
                            ),
                            'options' => new Schema(
                                type: DataType::ARRAY,
                                items: new Schema(type: DataType::STRING),
                                description: 'Array of answer options (4 for multiple_choice, 2 for true_false)'
                            ),
                            'correct_answer' => new Schema(
                                type: DataType::INTEGER,
                                description: 'Index of correct answer (0-3 for multiple_choice, 0-1 for true_false)'
                            ),
                            'explanation' => new Schema(
                                type: DataType::STRING,
                                description: 'Brief explanation of the correct answer'
                            ),
                        ],
                        required: ['id', 'question', 'options', 'correct_answer', 'explanation']
                    )
                ),
            ],
            required: ['questions']
        );
    }
}
