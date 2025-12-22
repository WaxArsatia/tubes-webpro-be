<?php

use App\Models\Activity;
use App\Models\Document;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Activity History', function () {
    it('can list user activities', function () {
        Activity::factory()->count(5)->for($this->user)->create();
        Activity::factory()->count(3)->create(); // Other user's activities

        $response = actingAs($this->user)->getJson('/api/history');

        $response->assertSuccessful()
            ->assertJsonCount(5, 'data.activities')
            ->assertJsonPath('data.pagination.total', 5);
    });

    it('supports pagination', function () {
        Activity::factory()->count(25)->for($this->user)->create();

        $response = actingAs($this->user)->getJson('/api/history?per_page=10');

        $response->assertSuccessful()
            ->assertJsonCount(10, 'data.activities')
            ->assertJsonStructure([
                'data' => [
                    'activities',
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);
    });

    it('can filter by activity type', function () {
        Activity::factory()->count(3)->for($this->user)->create(['activity_type' => 'document_upload']);
        Activity::factory()->count(2)->for($this->user)->create(['activity_type' => 'summary_generate']);

        $response = actingAs($this->user)->getJson('/api/history?type=document_upload');

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data.activities');
    });

    it('returns activities in descending order by date', function () {
        $old = Activity::factory()->for($this->user)->create(['created_at' => now()->subDays(2)]);
        $recent = Activity::factory()->for($this->user)->create(['created_at' => now()]);

        $response = actingAs($this->user)->getJson('/api/history');

        $activities = $response->json('data.activities');
        expect($activities[0]['id'])->toBe($recent->id);
        expect($activities[1]['id'])->toBe($old->id);
    });

    it('requires authentication', function () {
        $response = getJson('/api/history');

        $response->assertUnauthorized();
    });
});

describe('Document Activity History', function () {
    it('can list activities for a specific document', function () {
        $document = Document::factory()->for($this->user)->create();
        Activity::factory()->count(3)->for($this->user)->create(['document_id' => $document->id]);
        Activity::factory()->count(2)->for($this->user)->create(); // Activities without document

        $response = actingAs($this->user)->getJson("/api/history/documents/{$document->id}");

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data.activities');
    });

    it('cannot view activities for other user documents', function () {
        $otherUser = User::factory()->create();
        $document = Document::factory()->for($otherUser)->create();
        Activity::factory()->count(3)->for($otherUser)->create(['document_id' => $document->id]);

        $response = actingAs($this->user)->getJson("/api/history/documents/{$document->id}");

        $response->assertSuccessful()
            ->assertJsonCount(0, 'data.activities'); // Returns empty for other user's documents
    });

    it('returns empty for non-existent document', function () {
        $response = actingAs($this->user)->getJson('/api/history/documents/99999');

        $response->assertSuccessful()
            ->assertJsonCount(0, 'data.activities');
    });
});

describe('Activity Statistics', function () {
    it('can retrieve activity statistics', function () {
        Activity::factory()->count(5)->for($this->user)->create(['activity_type' => 'document_upload']);
        Activity::factory()->count(3)->for($this->user)->create(['activity_type' => 'summary_generate']);
        Activity::factory()->count(2)->for($this->user)->create(['activity_type' => 'quiz_complete']);

        $response = actingAs($this->user)->getJson('/api/history/stats');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'stats' => [
                        'total_activities',
                        'documents_uploaded',
                        'summaries_generated',
                    ],
                    'activity_breakdown',
                    'daily_activity',
                ],
            ]);

        expect($response->json('data.stats.total_activities'))->toBe(10);
        expect($response->json('data.stats.documents_uploaded'))->toBe(5);
    });

    it('can filter stats by period', function () {
        Activity::factory()->for($this->user)->create(['created_at' => now()->subDays(8)]);
        Activity::factory()->count(3)->for($this->user)->create(['created_at' => now()]);

        $response = actingAs($this->user)->getJson('/api/history/stats?period=week');

        $response->assertSuccessful();

        $total = $response->json('data.stats.total_activities');
        expect($total)->toBe(3); // Only last week
    });

});

describe('Recent Activities', function () {
    it('can retrieve recent activities', function () {
        Activity::factory()->count(10)->for($this->user)->create();

        $response = actingAs($this->user)->getJson('/api/history/recent');

        $response->assertSuccessful()
            ->assertJsonCount(10, 'data.activities'); // Default limit is 10
    });

    it('respects custom limit', function () {
        Activity::factory()->count(15)->for($this->user)->create();

        $response = actingAs($this->user)->getJson('/api/history/recent?limit=10');

        $response->assertSuccessful()
            ->assertJsonCount(10, 'data.activities');
    });

    it('validates maximum limit', function () {
        Activity::factory()->count(60)->for($this->user)->create();

        $response = actingAs($this->user)->getJson('/api/history/recent?limit=100');

        $response->assertSuccessful()
            ->assertJsonCount(50, 'data.activities'); // Max limit is 50
    });
});

describe('Clear Activity History', function () {
    it('can clear all activities', function () {
        Activity::factory()->count(5)->for($this->user)->create();

        $response = actingAs($this->user)->deleteJson('/api/history');

        $response->assertSuccessful();
        expect(Activity::where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('can clear activities by type', function () {
        Activity::factory()->count(3)->for($this->user)->create(['activity_type' => 'document_upload']);
        Activity::factory()->count(2)->for($this->user)->create(['activity_type' => 'summary_generate']);

        $response = actingAs($this->user)->deleteJson('/api/history?type=document_upload');

        $response->assertSuccessful();
        expect(Activity::where('user_id', $this->user->id)->count())->toBe(2);
        expect(Activity::where('user_id', $this->user->id)->where('activity_type', 'summary_generate')->count())->toBe(2);
    });

    it('can clear activities by date range', function () {
        Activity::factory()->for($this->user)->create(['created_at' => now()->subDays(10)]);
        Activity::factory()->for($this->user)->create(['created_at' => now()->subDays(5)]);
        Activity::factory()->for($this->user)->create(['created_at' => now()]);

        $response = actingAs($this->user)->deleteJson('/api/history?before_date='.now()->subDays(6)->toDateString());

        $response->assertSuccessful();
        expect(Activity::where('user_id', $this->user->id)->count())->toBe(2);
    });

    it('only clears own activities', function () {
        Activity::factory()->count(3)->for($this->user)->create();
        $otherUser = User::factory()->create();
        Activity::factory()->count(2)->for($otherUser)->create();

        $response = actingAs($this->user)->deleteJson('/api/history');

        $response->assertSuccessful();
        expect(Activity::where('user_id', $otherUser->id)->count())->toBe(2);
    });
});
