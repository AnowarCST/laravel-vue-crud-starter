<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    /**
     * Paths produced by ProductController::upload(): UploadedFile::store()
     * uses Str::random(40) plus the MIME-derived extension.
     */
    public const UPLOADED_PHOTO_PATTERN = '~^uploads/[A-Za-z0-9]{40}\.(jpg|jpeg|png|webp)$~';

    public function authorize(): bool
    {
        // Resource-level checks happen in the controller via ProductPolicy.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*.text' => ['required', 'string', 'max:191'],
            'photo' => ['sometimes', 'nullable', 'string', 'max:191', function (string $attribute, mixed $value, Closure $fail): void {
                if (! preg_match(self::UPLOADED_PHOTO_PATTERN, $value) && ! $this->isUnchangedPhoto($value)) {
                    $fail('The :attribute must be an image uploaded through /api/product/upload.');
                }
            }],
        ];
    }

    /**
     * Products seeded before uploads existed keep their legacy photo path;
     * resubmitting that same value on update is allowed.
     */
    private function isUnchangedPhoto(string $value): bool
    {
        $id = $this->route('product');

        return $id !== null && Product::whereKey($id)->value('photo') === $value;
    }
}
