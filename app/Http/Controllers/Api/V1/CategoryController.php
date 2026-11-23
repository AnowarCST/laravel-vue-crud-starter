<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
    public function index(): JsonResponse
    {
        return $this->sendResponse(Category::latest()->paginate(10), 'Category list');
    }

    public function list(): JsonResponse
    {
        return $this->sendResponse(Category::pluck('name', 'id'), 'Category list');
    }

    public function show(string $id): JsonResponse
    {
        return $this->sendResponse(Category::findOrFail($id), 'Category Details');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        return $this->sendResponse(Category::create($this->validated($request)), 'Category Created Successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $record = Category::findOrFail($id);
        $this->authorize('update', $record);
        $record->update($this->validated($request));

        return $this->sendResponse($record, 'Category Information has been updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $record = Category::findOrFail($id);
        $this->authorize('delete', $record);
        $record->delete();

        return $this->sendResponse($record, 'Category has been Deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $this->validate($request, [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
