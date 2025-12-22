<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

describe('Document Upload', function () {
    it('can upload a document', function () {
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');

        $response = actingAs($this->user)->postJson('/api/documents', [
            'file' => $file,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'document' => [
                        'id',
                        'filename',
                        'original_filename',
                        'file_path',
                        'file_size',
                        'mime_type',
                        'status',
                    ],
                ],
            ]);

        assertDatabaseHas('documents', [
            'user_id' => $this->user->id,
            'status' => 'completed',
            'original_filename' => 'test.pdf',
        ]);

        $filename = $response->json('data.document.filename');
        Storage::disk('local')->assertExists('documents/user-'.$this->user->id.'/'.$filename);
    });

    it('requires authentication to upload', function () {
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');

        $response = postJson('/api/documents', [
            'file' => $file,
            'title' => 'Test Document',
        ]);

        $response->assertUnauthorized();
    });

    it('validates file is required', function () {
        $response = actingAs($this->user)->postJson('/api/documents', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    });

    it('validates file type', function () {
        $file = UploadedFile::fake()->create('test.exe', 1024, 'application/x-msdownload');

        $response = actingAs($this->user)->postJson('/api/documents', [
            'file' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    });

});

describe('Document List', function () {
    it('can list user documents', function () {
        Document::factory()->count(3)->for($this->user)->create();
        Document::factory()->count(2)->create(); // Other user's documents

        $response = actingAs($this->user)->getJson('/api/documents');

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data.documents');
    });

    it('supports pagination', function () {
        Document::factory()->count(15)->for($this->user)->create();

        $response = actingAs($this->user)->getJson('/api/documents?per_page=5');

        $response->assertSuccessful()
            ->assertJsonCount(5, 'data.documents')
            ->assertJsonStructure([
                'data' => [
                    'documents',
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);
    });

    it('can filter by status', function () {
        Document::factory()->count(2)->for($this->user)->create(['status' => 'completed']);
        Document::factory()->count(3)->for($this->user)->create(['status' => 'pending']);

        $response = actingAs($this->user)->getJson('/api/documents?status=completed');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data.documents');
    });

    it('requires authentication', function () {
        $response = getJson('/api/documents');

        $response->assertUnauthorized();
    });
});

describe('Document Show', function () {
    it('can show a document', function () {
        $document = Document::factory()->for($this->user)->create();

        $response = actingAs($this->user)->getJson("/api/documents/{$document->id}");

        $response->assertSuccessful()
            ->assertJson([
                'data' => [
                    'document' => [
                        'id' => $document->id,
                        'filename' => $document->filename,
                    ],
                ],
            ]);
    });

    it('cannot view other user documents', function () {
        $otherUser = User::factory()->create();
        $document = Document::factory()->for($otherUser)->create();

        $response = actingAs($this->user)->getJson("/api/documents/{$document->id}");

        $response->assertNotFound();
    });

    it('returns 404 for non-existent document', function () {
        $response = actingAs($this->user)->getJson('/api/documents/99999');

        $response->assertNotFound();
    });
});

describe('Document Download', function () {
    it('can download a document', function () {
        Storage::fake('local');
        $document = Document::factory()->for($this->user)->create([
            'filename' => 'test.pdf',
            'file_path' => 'documents/test.pdf',
        ]);

        Storage::disk('local')->put('documents/test.pdf', 'fake content');

        $response = actingAs($this->user)->getJson("/api/documents/{$document->id}/download");

        $response->assertSuccessful();
    });

    it('cannot download other user documents', function () {
        $otherUser = User::factory()->create();
        $document = Document::factory()->for($otherUser)->create();

        $response = actingAs($this->user)->getJson("/api/documents/{$document->id}/download");

        $response->assertNotFound();
    });
});

describe('Document Delete', function () {
    it('can delete own document', function () {
        Storage::fake('local');
        $document = Document::factory()->for($this->user)->create([
            'file_path' => 'documents/test.pdf',
        ]);

        Storage::disk('local')->put('documents/test.pdf', 'fake content');

        $response = actingAs($this->user)->deleteJson("/api/documents/{$document->id}");

        $response->assertSuccessful();
        expect(Document::find($document->id))->toBeNull();
        Storage::disk('local')->assertMissing('documents/test.pdf');
    });

    it('cannot delete other user documents', function () {
        $otherUser = User::factory()->create();
        $document = Document::factory()->for($otherUser)->create();

        $response = actingAs($this->user)->deleteJson("/api/documents/{$document->id}");

        $response->assertNotFound();
        expect(Document::find($document->id))->not->toBeNull();
    });
});
