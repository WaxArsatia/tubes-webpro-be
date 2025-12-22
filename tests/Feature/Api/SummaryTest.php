<?php

use App\Contracts\AIServiceInterface;
use App\Models\Document;
use App\Models\Summary;
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
        ->shouldReceive('generateSummary')
        ->andReturn('This is a generated summary of the document content. It covers the main points and key concepts presented in the document.')
        ->shouldReceive('deleteFile')
        ->andReturn(true);
});

describe('Summary Generation', function () {
    it('can generate a summary', function () {
        $response = actingAs($this->user)->postJson('/api/summaries/generate', [
            'document_id' => $this->document->id,
            'summary_type' => 'concise',
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'summary' => [
                        'id',
                        'document_id',
                        'summary_type',
                        'content',
                        'created_at',
                    ],
                ],
            ]);

        assertDatabaseHas('summaries', [
            'document_id' => $this->document->id,
            'user_id' => $this->user->id,
            'summary_type' => 'concise',
        ]);
    });

    it('validates document_id is required', function () {
        $response = actingAs($this->user)->postJson('/api/summaries/generate', [
            'summary_type' => 'concise',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_id']);
    });

    it('validates type is required', function () {
        $response = actingAs($this->user)->postJson('/api/summaries/generate', [
            'document_id' => $this->document->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['summary_type']);
    });

    it('validates type is valid', function () {
        $response = actingAs($this->user)->postJson('/api/summaries/generate', [
            'document_id' => $this->document->id,
            'summary_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['summary_type']);
    });

    it('cannot generate summary for other user document', function () {
        $otherUser = User::factory()->create();
        $otherDocument = Document::factory()->for($otherUser)->create(['status' => 'completed']);

        $response = actingAs($this->user)->postJson('/api/summaries/generate', [
            'document_id' => $otherDocument->id,
            'summary_type' => 'concise',
        ]);

        $response->assertNotFound(); // Security pattern - don't reveal resource existence
    });

    it('cannot generate summary for incomplete document', function () {
        $pendingDoc = Document::factory()->for($this->user)->create(['status' => 'pending']);

        $response = actingAs($this->user)->postJson('/api/summaries/generate', [
            'document_id' => $pendingDoc->id,
            'summary_type' => 'concise',
        ]);

        $response->assertUnprocessable();
    });

    it('requires authentication', function () {
        $response = postJson('/api/summaries/generate', [
            'document_id' => $this->document->id,
            'summary_type' => 'concise',
        ]);

        $response->assertUnauthorized();
    });
});

describe('Summary Show', function () {
    it('can show a summary', function () {
        $summary = Summary::factory()->for($this->document)->for($this->user)->create();

        $response = actingAs($this->user)->getJson("/api/summaries/{$summary->id}");

        $response->assertSuccessful()
            ->assertJson([
                'data' => [
                    'summary' => [
                        'id' => $summary->id,
                        'summary_type' => $summary->summary_type,
                    ],
                ],
            ]);
    });

    it('increments view count on show', function () {
        $summary = Summary::factory()->for($this->document)->for($this->user)->create(['views_count' => 0]);

        actingAs($this->user)->getJson("/api/summaries/{$summary->id}");

        expect($summary->fresh()->views_count)->toBe(1);
    });

    it('cannot view other user summaries', function () {
        $otherUser = User::factory()->create();
        $otherDoc = Document::factory()->for($otherUser)->create();
        $summary = Summary::factory()->for($otherDoc)->for($otherUser)->create();

        $response = actingAs($this->user)->getJson("/api/summaries/{$summary->id}");

        $response->assertNotFound(); // Security pattern - don't reveal resource existence
    });
});

describe('Summary List', function () {
    it('can list user summaries', function () {
        Summary::factory()->count(3)->for($this->document)->for($this->user)->create();
        Summary::factory()->count(2)->create(); // Other user's summaries

        $response = actingAs($this->user)->getJson('/api/summaries');

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data.summaries')
            ->assertJsonPath('data.pagination.total', 3);
    });

    it('can filter by type', function () {
        Summary::factory()->count(2)->for($this->document)->for($this->user)->create(['summary_type' => 'concise']);
        Summary::factory()->count(3)->for($this->document)->for($this->user)->create(['summary_type' => 'detailed']);

        $response = actingAs($this->user)->getJson('/api/summaries?summary_type=concise');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data.summaries');
    });

    it('supports pagination', function () {
        Summary::factory()->count(15)->for($this->document)->for($this->user)->create();

        $response = actingAs($this->user)->getJson('/api/summaries?per_page=5');

        $response->assertSuccessful()
            ->assertJsonCount(5, 'data.summaries')
            ->assertJsonStructure([
                'data' => [
                    'summaries',
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);
    });
});

describe('Document Summaries', function () {
    it('can list summaries for a document', function () {
        Summary::factory()->count(3)->for($this->document)->for($this->user)->create();

        $otherDoc = Document::factory()->for($this->user)->create();
        Summary::factory()->count(2)->for($otherDoc)->for($this->user)->create();

        $response = actingAs($this->user)->getJson("/api/documents/{$this->document->id}/summaries");

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data.summaries');
    });

    it('cannot view summaries for other user documents', function () {
        $otherUser = User::factory()->create();
        $otherDoc = Document::factory()->for($otherUser)->create();

        $response = actingAs($this->user)->getJson("/api/documents/{$otherDoc->id}/summaries");

        $response->assertNotFound(); // Security pattern - don't reveal resource existence
    });
});

describe('Summary Delete', function () {
    it('can delete own summary', function () {
        $summary = Summary::factory()->for($this->document)->for($this->user)->create();

        $response = actingAs($this->user)->deleteJson("/api/summaries/{$summary->id}");

        $response->assertSuccessful();
        expect(Summary::find($summary->id))->toBeNull();
    });

    it('cannot delete other user summaries', function () {
        $otherUser = User::factory()->create();
        $otherDoc = Document::factory()->for($otherUser)->create();
        $summary = Summary::factory()->for($otherDoc)->for($otherUser)->create();

        $response = actingAs($this->user)->deleteJson("/api/summaries/{$summary->id}");

        $response->assertNotFound(); // Security pattern - don't reveal resource existence
    });
});
