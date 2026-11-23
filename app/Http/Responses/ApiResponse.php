<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The envelope of every successful /api response (except GET /api/version):
 * {"success": true, "data": ..., "message": "..."} with status 200.
 *
 * `data` is serialized as it is given: a JsonResource, a paginator whose
 * items are JsonResources (the Laravel paginator fields are kept), a
 * collection or a plain array.
 */
final readonly class ApiResponse implements Responsable
{
    public function __construct(
        public mixed $data,
        public string $message,
    ) {}

    public static function success(mixed $data, string $message): self
    {
        return new self($data, $message);
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => $this->data,
            'message' => $this->message,
        ]);
    }
}
