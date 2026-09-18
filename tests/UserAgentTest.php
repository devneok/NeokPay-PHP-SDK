<?php

declare(strict_types=1);

namespace Neok\Pay\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAgentTest extends TestCase
{
    #[DataProvider('versions')]
    public function test_version_comes_from_metadata_or_an_honest_fallback(
        string $mode,
        string $version,
        string $suffix,
        string $expected,
    ): void {
        $process = proc_open(
            [PHP_BINARY, __DIR__.'/user-agent-fixture.php', $mode, $version, $suffix],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame('neokpay-php/'.$expected, $output);
        self::assertNotSame('neokpay-php/1.0.0', $output);
    }

    public static function versions(): array
    {
        return [
            'beta' => ['installed', '1.0.0-beta.2', '', '1.0.0-beta.2'],
            'tag prefix' => ['installed', 'v1.0.0-beta.2', '', 'v1.0.0-beta.2'],
            'dev branch' => ['installed', 'dev-main', '', 'dev-main'],
            'dev alias' => ['installed', '1.0.x-dev', '', '1.0.x-dev'],
            'future stable metadata' => ['installed', '1.1.0', '', '1.1.0'],
            'suffix' => ['installed', '1.0.0-beta.2', 'my-app/2.3', '1.0.0-beta.2 my-app/2.3'],
            'no Composer' => ['no-composer', '', '', 'development'],
            'missing package' => ['missing-package', '', '', 'development'],
            'null metadata' => ['null-version', '', '', 'development'],
            'root placeholder' => ['installed', '1.0.0+no-version-set', '', 'development'],
            'empty metadata' => ['installed', '', '', 'development'],
            'invalid metadata' => ['installed', "dev-main\r\nInjected: value", '', 'development'],
        ];
    }
}
