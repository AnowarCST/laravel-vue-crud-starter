<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assertions for the API's response envelope and its common errors.
 */
trait AssertsApiResponses
{
    /**
     * 200 with the {"success": true, "data": ..., "message": "..."} envelope,
     * and nothing else at the top level.
     *
     * @param  TestResponse<Response>  $response
     * @return TestResponse<Response>
     */
    protected function assertApiSuccess(TestResponse $response, ?string $message = null): TestResponse
    {
        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(['success', 'data', 'message'], array_keys((array) $response->json()));
        $this->assertIsString($response->json('message'));

        if ($message !== null) {
            $response->assertJsonPath('message', $message);
        }

        return $response;
    }

    /**
     * The 403 the "verified" middleware returns to accounts that have not
     * verified their email address. The SPA matches on this exact message.
     *
     * @param  TestResponse<Response>  $response
     * @return TestResponse<Response>
     */
    protected function assertEmailNotVerified(TestResponse $response): TestResponse
    {
        return $response->assertForbidden()->assertExactJson(['message' => 'Your email address is not verified.']);
    }

    /**
     * The generic JSON 404, which never names the model class.
     *
     * @param  TestResponse<Response>  $response
     * @return TestResponse<Response>
     */
    protected function assertGenericNotFound(TestResponse $response): TestResponse
    {
        return $response->assertNotFound()->assertExactJson(['message' => 'Not Found.']);
    }
}
