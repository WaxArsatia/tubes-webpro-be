<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ExampleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Welcome to your Laravel API!',
            'data' => [
                'examples' => [
                    'This is where your data would go',
                    'You can return arrays, objects, or models',
                ],
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'message' => 'Showing item',
            'data' => [
                'id' => $id,
                'name' => 'Example Item '.$id,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): JsonResponse
    {
        // Add validation here using Form Requests
        return response()->json([
            'message' => 'Item created successfully',
            'data' => [
                'id' => 1,
            ],
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(string $id): JsonResponse
    {
        // Add validation here using Form Requests
        return response()->json([
            'message' => 'Item updated successfully',
            'data' => [
                'id' => $id,
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        return response()->json([
            'message' => 'Item deleted successfully',
        ]);
    }
}
