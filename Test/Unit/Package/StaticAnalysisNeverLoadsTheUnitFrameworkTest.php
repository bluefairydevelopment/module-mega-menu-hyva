<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Package;

use PHPUnit\Framework\TestCase;

/**
 * Magento's unit-test framework (dev/tests/unit/framework/bootstrap.php) writes a
 * stand-in for every class whose name ends in "Factory", with an empty create().
 * Loaded under phpstan, it replaces the real factories: phpstan then reports nothing
 * about anything a factory returned, and shadows the real ResultFactory.
 *
 * This package's bootstrap must therefore load that framework for the unit run only,
 * and under analysis autoload the compiled generated/code - refusing when there is
 * none. The bootstrap is run here as a separate PHP process against a fixture Magento
 * root whose unit framework announces itself.
 *
 * PORTED from module-mega-menu, where this ran as a data set named "MegaMenuHyva"
 * over the monorepo's three package bootstraps. This package left that repository on
 * 2026-11-27, which took its three cases with it - and nothing here replaced them,
 * so for a few hours this package shipped a phpstan.neon.dist that nothing checked.
 * The check reads only this package's own files, so it ports without any dependency
 * on the core.
 */
class StaticAnalysisNeverLoadsTheUnitFrameworkTest extends TestCase
{
    private const MARKER = 'UNIT-FRAMEWORK-LOADED';

    private string $dir = '';

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            $walk = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($walk as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($this->dir);
        }
    }

    /** This package's own bootstrap. The repository IS the package. */
    private static function bootstrap(): string
    {
        return dirname(__DIR__, 3) . '/Test/bootstrap.php';
    }

    /**
     * Build a fixture root and a copy of the bootstrap; run it through a wrapper
     * whose file name decides whether the run looks like phpstan.
     *
     * @return array{int, string}
     */
    private function runBootstrap(bool $analysis, bool $compiled): array
    {
        $bootstrap = self::bootstrap();
        self::assertFileExists($bootstrap, 'this package ships no Test/bootstrap.php to examine');
        $this->dir = sys_get_temp_dir() . '/mmh-sa-' . bin2hex(random_bytes(6));
        $root = $this->dir . '/magento';
        $pkg = $this->dir . '/pkg';
        foreach ([$root . '/vendor', $root . '/dev/tests/unit/framework', $pkg . '/Test',
            $root . '/generated/code/Magento/Catalog/Api/Data'] as $d) {
            mkdir($d, 0777, true);
        }
        file_put_contents($root . '/vendor/autoload.php', "<?php\n");
        file_put_contents(
            $root . '/dev/tests/unit/framework/bootstrap.php',
            "<?php\nfwrite(STDERR, '" . self::MARKER . "');\n"
        );
        if ($compiled) {
            file_put_contents(
                $root . '/generated/code/Magento/Catalog/Api/Data/ProductExtensionInterface.php',
                "<?php\n"
            );
        }
        copy($bootstrap, $pkg . '/Test/bootstrap.php');

        $wrapper = $this->dir . '/' . ($analysis ? 'phpstan-run.php' : 'unit-run.php');
        file_put_contents($wrapper, "<?php\nrequire " . var_export($pkg . '/Test/bootstrap.php', true) . ";\n");

        $proc = proc_open(
            [PHP_BINARY, $wrapper],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['MAGENTO_ROOT' => $root, 'PATH' => (string) getenv('PATH')]
        );
        self::assertIsResource($proc);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proc), $stdout . $stderr];
    }

    public function testUnderAnalysisTheUnitFrameworkIsNotLoaded(): void
    {
        [$rc, $out] = $this->runBootstrap(true, true);

        self::assertSame(0, $rc, $out);
        self::assertStringNotContainsString(
            self::MARKER,
            $out,
            'under phpstan the bootstrap loaded the unit-test framework, whose stand-in factories hide every type behind them'
        );
    }

    /** Control: the unit run still gets the framework, so the check above is about analysis. */
    public function testTheUnitRunStillLoadsTheUnitFramework(): void
    {
        [$rc, $out] = $this->runBootstrap(false, true);

        self::assertSame(0, $rc, $out);
        self::assertStringContainsString(self::MARKER, $out, 'the unit run no longer loads the unit-test framework');
    }

    public function testAnalysisRefusesARootWithNoCompiledCode(): void
    {
        [$rc, $out] = $this->runBootstrap(true, false);

        self::assertSame(1, $rc, $out);
        self::assertStringContainsString('REFUSING to analyse', $out);
        self::assertStringContainsString('setup:di:compile', $out);
    }
}
