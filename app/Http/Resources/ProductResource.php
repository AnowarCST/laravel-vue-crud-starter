<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * Fields are in the order of the MySQL table columns, followed by
     * `photo_url` and the relations. The create response contains only the saved fields: no `deleted_at`,
     * and no `photo` unless one was sent. `category` and `tags` are only
     * included when they were loaded (the list and show endpoints);
     * `category` is null when the category no longer exists.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'status' => $this->status,
            'category_id' => $this->category_id,
            'photo' => $this->whenHas('photo'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->whenHas('deleted_at'),
            'photo_url' => $this->photo_url,
            'category' => $this->whenLoaded('category', fn (?Category $category): ?CategoryResource => $category ? new CategoryResource($category) : null),
            'tags' => ProductTagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
