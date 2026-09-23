<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NoClientSuppliedUploadNamesTest extends TestCase
{
    public function test_no_controller_stores_an_upload_under_its_client_supplied_name(): void
    {
        $offenders = [];
        $controllers = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__ . '/../../app/Http/Controllers')
        );

        foreach ($controllers as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (str_contains($contents, 'getClientOriginalName')) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'Uploads must be stored under a server generated name.');
    }
}
