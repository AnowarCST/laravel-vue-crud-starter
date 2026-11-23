<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): ApiResponse
    {
        $categories = Category::latest()->paginate(10)
            ->through(fn (Category $category) => new CategoryResource($category));

        return ApiResponse::success($categories, 'Category list');
    }

    /**
     * Every category as an {id: name} map, for select inputs.
     */
    public function list(): ApiResponse
    {
        return ApiResponse::success(Category::pluck('name', 'id'), 'Category list');
    }

    public function show(string $id): ApiResponse
    {
        return ApiResponse::success(new CategoryResource(Category::findOrFail($id)), 'Category Details');
    }

    public function store(Request $request): ApiResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::create($this->validated($request));

        return ApiResponse::success(new CategoryResource($category), 'Category Created Successfully');
    }

    public function update(Request $request, string $id): ApiResponse
    {
        $record = Category::findOrFail($id);
        $this->authorize('update', $record);
        $record->update($this->validated($request));

        return ApiResponse::success(new CategoryResource($record), 'Category Information has been updated');
    }

    public function destroy(string $id): ApiResponse
    {
        $record = Category::findOrFail($id);
        $this->authorize('delete', $record);
        $record->delete();

        return ApiResponse::success(new CategoryResource($record), 'Category has been Deleted');
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
