<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tag inside a product. The name is returned as `text`, the format the
 * dashboard's tag input uses.
 *
 * @mixin Tag
 *
 * @property-read Pivot $pivot Set by the product's tags() relation.
 */
class ProductTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'text' => $this->name,
            'id' => $this->id,
            'pivot' => $this->whenPivotLoaded('product_tag', fn (): array => [
                'product_id' => $this->pivot->getAttribute('product_id'),
                'tag_id' => $this->pivot->getAttribute('tag_id'),
            ]),
        ];
    }
}
