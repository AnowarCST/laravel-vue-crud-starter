<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(): ApiResponse
    {
        $tags = Tag::latest()->paginate(10)
            ->through(fn (Tag $tag) => new TagResource($tag));

        return ApiResponse::success($tags, 'Tag list');
    }

    /**
     * Every tag as {name, id}, for the tag input's suggestions.
     */
    public function list(): ApiResponse
    {
        $tags = Tag::get(['name', 'id'])
            ->map(fn (Tag $tag): array => ['name' => $tag->name, 'id' => $tag->id]);

        return ApiResponse::success($tags, 'Tag list');
    }

    public function show(string $id): ApiResponse
    {
        return ApiResponse::success(new TagResource(Tag::findOrFail($id)), 'Tag Details');
    }

    public function store(Request $request): ApiResponse
    {
        $this->authorize('create', Tag::class);

        return ApiResponse::success(new TagResource(Tag::create($this->validated($request))), 'Tag Created Successfully');
    }

    public function update(Request $request, string $id): ApiResponse
    {
        $record = Tag::findOrFail($id);
        $this->authorize('update', $record);
        $record->update($this->validated($request));

        return ApiResponse::success(new TagResource($record), 'Tag Information has been updated');
    }

    public function destroy(string $id): ApiResponse
    {
        $record = Tag::findOrFail($id);
        $this->authorize('delete', $record);
        $record->delete();

        return ApiResponse::success(new TagResource($record), 'Tag has been Deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $this->validate($request, ['name' => ['required', 'string', 'max:191']]);
    }
}
