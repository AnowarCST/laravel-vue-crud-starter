<?php

namespace App\Http\Requests\Products;

use App\Actions\Products\StoreProductImage;
use App\Enums\ProductStatus;
use App\Models\Product;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create (POST /api/product) and update (PUT/PATCH /api/product/{product})
 * of a product, authorized by ProductPolicy.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->product();

        return $product !== null
            ? $this->user()?->can('update', $product) === true
            : $this->user()?->can('create', Product::class) === true;
    }

    /**
     * The product being updated (bound from the route), or null when creating.
     */
    public function product(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * SKUs are stored in upper case ("ab-12" is saved as "AB-12").
     */
    protected function prepareForValidation(): void
    {
        $sku = $this->input('sku');

        if (is_string($sku)) {
            $this->merge(['sku' => strtoupper(trim($sku))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.regex' => 'The SKU may only contain letters, digits and hyphens.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['sku' => 'SKU'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Products created before SKUs existed have none, so on update the
        // SKU may be left out; when it is sent it must be valid.
        $creating = $this->product() === null;

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:191'],
            'sku' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:64', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('products', 'sku')->ignore($this->product())],
            'description' => ['required', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'status' => ['sometimes', 'required', 'string', Rule::enum(ProductStatus::class)],
            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*.text' => ['required', 'string', 'max:191'],
            'photo' => ['sometimes', 'nullable', 'string', 'max:191', function (string $attribute, mixed $value, Closure $fail): void {
                // Non-string values are already rejected by the "string" rule.
                if (is_string($value) && ! preg_match(StoreProductImage::PATH_PATTERN, $value) && ! $this->isUnchangedPhoto($value)) {
                    $fail('The :attribute must be an image uploaded through /api/product/upload.');
                }
            }],
        ];
    }

    /**
     * The validated product fields, without "tags".
     *
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        return $this->safe()->except('tags');
    }

    /**
     * Names from the validated "tags" payload, e.g. [{"text": "Bestseller"}],
     * or null when "tags" was not sent.
     *
     * @return list<string>|null
     */
    public function tagNames(): ?array
    {
        if (! $this->safe()->has('tags')) {
            return null;
        }

        $tags = $this->validated('tags');
        $names = [];

        foreach (is_array($tags) ? $tags : [] as $tag) {
            if (is_array($tag) && isset($tag['text']) && is_string($tag['text'])) {
                $names[] = $tag['text'];
            }
        }

        return $names;
    }

    /**
     * Seeded products use a bundled image (images/products/<name>.webp) and
     * older installs may still have other seeded paths. Such a value cannot
     * be set through the API, but resubmitting the product's current value
     * unchanged on update is allowed.
     */
    private function isUnchangedPhoto(string $value): bool
    {
        return $this->product()?->photo === $value;
    }
}
