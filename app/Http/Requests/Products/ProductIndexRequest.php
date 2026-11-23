<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of GET /api/product. Every parameter is optional; unknown
 * values are rejected with 422 so nothing from the request reaches SQL
 * except as a bound value.
 */
class ProductIndexRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 10;

    public const MAX_PER_PAGE = 50;

    /**
     * Allowed "sort" values: [column, direction].
     *
     * @var array<string, array{string, 'asc'|'desc'}>
     */
    public const SORTS = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'name' => ['name', 'asc'],
        '-name' => ['name', 'desc'],
        'price' => ['price', 'asc'],
        '-price' => ['price', 'desc'],
        'stock' => ['stock', 'asc'],
        '-stock' => ['stock', 'desc'],
    ];

    /**
     * Allowed "stock" values: "in" (1 or more), "low" (1 to
     * Product::LOW_STOCK_THRESHOLD) and "out" (0).
     */
    public const STOCK_LEVELS = ['in', 'low', 'out'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'tag' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(Product::STATUSES)],
            'stock' => ['nullable', 'string', Rule::in(self::STOCK_LEVELS)],
            'sort' => ['nullable', 'string', Rule::in(array_keys(self::SORTS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * A validated, non-empty string parameter, or null when it was not sent.
     */
    public function filterText(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A validated integer parameter, or null when it was not sent.
     */
    public function filterInt(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array{string, 'asc'|'desc'}
     */
    public function sort(): array
    {
        return self::SORTS[$this->filterText('sort') ?? 'newest'] ?? self::SORTS['newest'];
    }

    public function perPage(): int
    {
        return $this->filterInt('per_page') ?? self::DEFAULT_PER_PAGE;
    }
}
