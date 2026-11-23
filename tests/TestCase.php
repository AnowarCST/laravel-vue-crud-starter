<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
     * @return array{private: string, public: string}
     */
    private static function passportKeys(): array
    {
        if (self::$passportKeys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);

            self::$passportKeys = [
                'private' => $private,
                'public' => openssl_pkey_get_details($key)['key'],
            ];
        }

        return self::$passportKeys;
    }
}
