<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends BaseController
{
    public function index(): JsonResponse
    {
        return $this->sendResponse(Tag::latest()->paginate(10), 'Tag list');
    }

    public function list(): JsonResponse
    {
        return $this->sendResponse(Tag::get(['name', 'id']), 'Tag list');
    }

    public function show(string $id): JsonResponse
    {
        return $this->sendResponse(Tag::findOrFail($id), 'Tag Details');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Tag::class);

        return $this->sendResponse(Tag::create($this->validated($request)), 'Tag Created Successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $record = Tag::findOrFail($id);
        $this->authorize('update', $record);
        $record->update($this->validated($request));

        return $this->sendResponse($record, 'Tag Information has been updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $record = Tag::findOrFail($id);
        $this->authorize('delete', $record);
        $record->delete();

        return $this->sendResponse($record, 'Tag has been Deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $this->validate($request, ['name' => ['required', 'string', 'max:191']]);
    }
}
