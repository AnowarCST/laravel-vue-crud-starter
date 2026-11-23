<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\AssertsApiResponses;
use Tests\Concerns\CreatesUsers;

abstract class TestCase extends BaseTestCase
{
    use AssertsApiResponses, CreatesUsers;

    /**
     * Ephemeral Passport key pair generated once per test process, so the
     * suite never depends on (or creates) storage/oauth-*.key files.
     *
     * @var array{private: string, public: string}|null
     */
    private static ?array $passportKeys = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Views must render without a compiled Vite manifest (public/build).
        $this->withoutVite();

        config([
            'passport.private_key' => self::passportKeys()['private'],
            'passport.public_key' => self::passportKeys()['public'],
        ]);
    }

    /**
     * A string value from a JSON response, failing the test otherwise.
     *
     * @param  TestResponse<Response>  $response
     */
    protected function jsonString(TestResponse $response, string $key): string
    {
        $value = $response->json($key);
        $this->assertIsString($value, "JSON value [{$key}] is not a string.");

        return $value;
    }

    /**
     * An integer value (such as an id) from a JSON response, failing the
     * test otherwise.
     *
     * @param  TestResponse<Response>  $response
     */
    protected function jsonInt(TestResponse $response, string $key): int
    {
        $value = $response->json($key);
        $this->assertIsInt($value, "JSON value [{$key}] is not an integer.");

        return $value;
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function passportKeys(): array
    {
        if (self::$passportKeys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $details = $key === false ? false : openssl_pkey_get_details($key);

            if ($key === false || ! openssl_pkey_export($key, $private) || ! is_string($private)
                || $details === false || ! is_string($details['key'] ?? null)) {
                throw new RuntimeException('Could not generate a Passport key pair for the tests.');
            }

            self::$passportKeys = ['private' => $private, 'public' => $details['key']];
        }

        return self::$passportKeys;
    }
}
