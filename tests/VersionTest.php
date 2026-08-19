<?php

namespace Sendivent\Tests;

use PHPUnit\Framework\TestCase;
use Sendivent\Sendivent;

class VersionTest extends TestCase
{
    /**
     * composer.json's version field drives the auto-tag release workflow, and
     * Sendivent::VERSION drives the User-Agent. Keep them from drifting apart —
     * the User-Agent sat at 0.1.0 through five releases before this test existed.
     */
    public function testVersionConstantMatchesComposerJson(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

        $this->assertSame($composer['version'], Sendivent::VERSION);
    }
}
