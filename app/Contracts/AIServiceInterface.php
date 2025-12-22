<?php

declare(strict_types=1);

namespace App\Contracts;

interface AIServiceInterface
{
    /**
     * Upload a file to the AI provider for processing.
     *
     * @param  string  $storagePath  The storage path of the file to upload
     * @return string|null The file URI/ID or null on failure
     */
    public function uploadFile(string $storagePath): ?string;

    /**
     * Generate a summary from a document file.
     *
     * @param  string  $fileUri  The file URI/ID from uploadFile
     * @param  string  $fileName  The original filename
     * @param  string  $summaryType  The type of summary (concise, detailed, bullet_points, abstract)
     * @param  string  $language  The language code for the summary (e.g., 'en', 'id')
     * @return string The generated summary content
     *
     * @throws \Exception When generation fails
     */
    public function generateSummary(string $fileUri, string $fileName, string $summaryType, string $language = 'id'): string;

    /**
     * Generate quiz questions from a document file.
     *
     * @param  string  $fileUri  The file URI/ID from uploadFile
     * @param  string  $fileName  The original filename
     * @param  int  $questionCount  Number of questions to generate
     * @param  string  $difficulty  Difficulty level (easy, medium, hard)
     * @param  string  $questionType  Type of questions (multiple_choice, true_false, mixed)
     * @param  string  $language  The language code for the quiz (e.g., 'en', 'id')
     * @return array Array of question objects
     *
     * @throws \Exception When generation fails
     */
    public function generateQuiz(
        string $fileUri,
        string $fileName,
        int $questionCount,
        string $difficulty,
        string $questionType,
        string $language = 'id'
    ): array;

    /**
     * Delete a file from the AI provider.
     *
     * @param  string  $fileUri  The file URI/ID to delete
     * @return bool True on success, false on failure
     */
    public function deleteFile(string $fileUri): bool;
}
