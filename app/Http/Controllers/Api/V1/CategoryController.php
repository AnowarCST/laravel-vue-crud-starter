<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;

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

    public function show(Category $category): ApiResponse
    {
        return ApiResponse::success(new CategoryResource($category), 'Category Details');
    }

    public function store(CategoryRequest $request): ApiResponse
    {
        $category = Category::create($request->validated());

        return ApiResponse::success(new CategoryResource($category), 'Category Created Successfully');
    }

    public function update(CategoryRequest $request, Category $category): ApiResponse
    {
        $category->update($request->validated());

        return ApiResponse::success(new CategoryResource($category), 'Category Information has been updated');
    }

    public function destroy(Category $category): ApiResponse
    {
        $this->authorize('delete', $category);
        $category->delete();

        return ApiResponse::success(new CategoryResource($category), 'Category has been Deleted');
    }
}
