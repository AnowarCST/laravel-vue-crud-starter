<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tags\TagRequest;
use App\Http\Resources\TagResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tag;

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

    public function show(Tag $tag): ApiResponse
    {
        return ApiResponse::success(new TagResource($tag), 'Tag Details');
    }

    public function store(TagRequest $request): ApiResponse
    {
        $tag = Tag::create($request->validated());

        return ApiResponse::success(new TagResource($tag), 'Tag Created Successfully');
    }

    public function update(TagRequest $request, Tag $tag): ApiResponse
    {
        $tag->update($request->validated());

        return ApiResponse::success(new TagResource($tag), 'Tag Information has been updated');
    }

    public function destroy(Tag $tag): ApiResponse
    {
        $this->authorize('delete', $tag);
        $tag->delete();

        return ApiResponse::success(new TagResource($tag), 'Tag has been Deleted');
    }
}
