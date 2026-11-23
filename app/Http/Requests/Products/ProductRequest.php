<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Paths produced by ProductController::upload(): Str::random(40) plus the
     * extension of the detected image format (see ImageReencoder::EXTENSIONS).
     */
    public const UPLOADED_PHOTO_PATTERN = '~^uploads/[A-Za-z0-9]{40}\.(jpg|jpeg|png|webp)$~';

    public function authorize(): bool
    {
        // Resource-level checks happen in the controller via ProductPolicy.
        return $this->user() !== null;
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
        $creating = $this->route('product') === null;

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:191'],
            'sku' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:64', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'description' => ['required', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'status' => ['sometimes', 'required', 'string', Rule::in(Product::STATUSES)],
            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*.text' => ['required', 'string', 'max:191'],
            'photo' => ['sometimes', 'nullable', 'string', 'max:191', function (string $attribute, mixed $value, Closure $fail): void {
                // Non-string values are already rejected by the "string" rule.
                if (is_string($value) && ! preg_match(self::UPLOADED_PHOTO_PATTERN, $value) && ! $this->isUnchangedPhoto($value)) {
                    $fail('The :attribute must be an image uploaded through /api/product/upload.');
                }
            }],
        ];
    }

    /**
     * Names from the validated "tags" payload, e.g. [{"text": "Bestseller"}].
     *
     * @return list<string>
     */
    public function tagNames(): array
    {
        $tags = $this->validated('tags', []);
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
        $id = $this->route('product');

        return $id !== null && Product::whereKey($id)->value('photo') === $value;
    }
}
