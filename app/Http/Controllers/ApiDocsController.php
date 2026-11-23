<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The OpenAPI document and its viewer. Access is checked by
 * App\Http\Middleware\AuthorizeApiDocs on both routes.
 */
class ApiDocsController extends Controller
{
    /**
     * The document is kept outside public/ so the web server never serves it
     * without the access check.
     */
    public const SPEC_PATH = 'docs/api/openapi.yaml';

    /**
     * Viewer page. The script (resources/js/api-docs.js) is a separate Vite
     * entry and reads the document URL from data-spec-url.
     */
    public function show(): View
    {
        return view('api-docs', ['specUrl' => route('api-docs.spec', absolute: false)]);
    }

    public function spec(): BinaryFileResponse
    {
        $path = base_path(self::SPEC_PATH);

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
