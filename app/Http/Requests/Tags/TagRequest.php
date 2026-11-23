<?php

namespace App\Http\Requests\Tags;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create (POST /api/tag) and update (PUT/PATCH /api/tag/{tag}) of a tag,
 * authorized by TagPolicy.
 */
class TagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag
            ? $this->user()?->can('update', $tag) === true
            : $this->user()?->can('create', Tag::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:191']];
    }
}
