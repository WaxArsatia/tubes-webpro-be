<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'document_name' => $this->whenLoaded('document', fn () => $this->document->original_filename),
            'user_id' => $this->user_id,
            'content' => $this->content,
            'summary_type' => $this->summary_type,
            'word_count' => $this->word_count,
            'language' => $this->language,
            'status' => $this->status,
            'processing_time_seconds' => $this->processing_time_seconds,
            'views_count' => $this->views_count,
            'last_viewed_at' => $this->last_viewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
