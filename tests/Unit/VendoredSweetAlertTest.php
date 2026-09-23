<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class VendoredSweetAlertTest extends TestCase
{
    private const BUNDLES = [
        'sweetalert2.js',
        'sweetalert2.min.js',
        'sweetalert2.all.js',
        'sweetalert2.all.min.js',
    ];

    private const FORBIDDEN = [
        'swal-initiation',
        'flag-gimn.ru',
        'xn--p1ai',
        'pointerEvents',
    ];

    /**
     * @dataProvider bundleProvider
     */
    public function test_bundle_is_free_of_protestware(string $bundle)
    {
        $path = dirname(__DIR__, 2) . '/public/plugins/sweetalert2/' . $bundle;
        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        foreach (self::FORBIDDEN as $needle) {
            $this->assertFalse(
                str_contains($contents, $needle),
                "{$bundle} still contains locale-gated protestware marker '{$needle}'."
            );
        }
    }

    public function bundleProvider(): array
    {
        return array_map(static fn ($bundle) => [$bundle], self::BUNDLES);
    }
}
