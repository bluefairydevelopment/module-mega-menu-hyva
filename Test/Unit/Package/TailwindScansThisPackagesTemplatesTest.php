<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Package;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * M2MAP-612 (ported from the canonical module-jira-hyva / packagist-provisioner-hyva
 * test, M2MAP-609) + M2MAP-230.
 *
 * Hyvä's Tailwind build scans the THEME, not module directories. A module's
 * utility classes reach the compiled stylesheet only if its own
 * view/frontend/tailwind/module.css declares an `@source` glob over its
 * templates. module-jira-hyva shipped that file with NO `@source` at all - a
 * comment explaining what `@source` does, and `npx hyva-sources` emitted an
 * `@import` that imported a comment. Nothing else in a package's gates can see
 * this: phpcs and phpstan do not read CSS, phpunit is green across it, and the
 * storefront returns 200 with the right markup either way.
 *
 * The predecessor test here (TailwindSourcesReachTheThemeBuildTest) asserted a
 * single weak substring match for `@source` and used the NAIVE comment stripper (a general block-comment regex), which eats a globstar
     * path: a globstar templates path contains an opening and closing comment marker. This port brings the canonical stripper and its three
 * controls, and adds a coverage check that models Tailwind's `**` correctly:
 * this package's templates sit four directories deep
 * (view/frontend/templates/html/header/menu/), which PHP glob() cannot reach
 * through `**`, so the check translates the glob to a regex instead.
 *
 * The M2MAP-230 assertions (the registration observer, its PSR-4 autoload, and
 * the literal Alpine class tokens) are kept - they guard the OTHER half of "the
 * classes reach the build" and have no equivalent in the canonical test.
 */
#[CoversNothing]
class TailwindScansThisPackagesTemplatesTest extends TestCase
{
    private const SOURCE_FILE = '/view/frontend/tailwind/module.css';

    private static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function moduleCss(): string
    {
        $path = self::packageRoot() . self::SOURCE_FILE;
        self::assertFileExists($path, 'the Tailwind registration file is missing entirely');
        $css = file_get_contents($path);
        self::assertIsString($css, 'the Tailwind registration file is unreadable');

        return $css;
    }

    /**
     * Strip block comments. The defect class is a file whose ONLY mention of
     * `@source` is inside a comment, so a plain substring search would pass on
     * the broken state. The obvious a general block-comment regex EATS THE GLOB: a globstar
     * path contains an opening and closing block-comment marker, so a general strip rewrites
     * a globstar templates path to `../templates*.phtml`. This form only treats
     * `/*` or `/**` as a comment opener when it is FOLLOWED BY WHITESPACE - the
     * `/**` inside a globstar path is followed by `/`, and survives.
     * testTheStripperDoesNotEatAGlobstarPath is the control.
     */
    private static function directives(string $css): string
    {
        return (string) preg_replace('!/\*\*?(?=\s)[\s\S]*?\*/!', '', $css);
    }

    /**
     * Translate a shell/Tailwind glob to an anchored regex that honours `**`
     * (zero or more directory segments) - which PHP glob() does not. This is how
     * the Tailwind 4 scanner reads the `@source` pattern; the coverage test
     * below depends on modelling it, not on glob().
     */
    private static function globToRegex(string $glob): string
    {
        $out = '';
        $len = strlen($glob);
        for ($i = 0; $i < $len; $i++) {
            $c = $glob[$i];
            if ($c === '*') {
                if (($glob[$i + 1] ?? '') === '*') {
                    // `**/` -> zero or more path segments; `**` alone -> anything.
                    if (($glob[$i + 2] ?? '') === '/') {
                        $out .= '(?:[^/]+/)*';
                        $i += 2;
                    } else {
                        $out .= '.*';
                        $i += 1;
                    }
                } else {
                    $out .= '[^/]*';
                }
            } elseif ($c === '?') {
                $out .= '[^/]';
            } else {
                $out .= preg_quote($c, '#');
            }
        }

        return '#^' . $out . '$#';
    }

    /** Every `@source "<glob>"` in the registration file, comments stripped. */
    private static function sourceGlobs(): array
    {
        $directives = self::directives(self::moduleCss());
        preg_match_all('/@source\s+"([^"]+)"/', $directives, $m);

        return $m[1];
    }

    /** Absolute path a glob resolves against: the tailwind/ directory it lives in. */
    private static function base(): string
    {
        return self::packageRoot() . '/view/frontend/tailwind/';
    }

    /**
     * True if $absoluteFile is reached by $glob (relative to the tailwind dir),
     * with `**` treated as recursive. Resolves the non-glob prefix on disk so
     * the `../` is normalised the same way for pattern and file.
     */
    private static function globReaches(string $glob, string $absoluteFile): bool
    {
        $firstStar = strcspn($glob, '*?');
        $prefix = substr($glob, 0, $firstStar);
        $suffix = substr($glob, $firstStar);

        $prefixDir = realpath(self::base() . $prefix);
        if ($prefixDir === false) {
            return false;
        }
        $prefixDir = rtrim($prefixDir, '/') . '/';

        $file = realpath($absoluteFile);
        if ($file === false || !str_starts_with($file, $prefixDir)) {
            return false;
        }

        $remainder = substr($file, strlen($prefixDir));

        return (bool) preg_match(self::globToRegex($suffix), $remainder);
    }

    private static function shippedTemplates(): array
    {
        $dir = self::packageRoot() . '/view/frontend/templates';
        if (!is_dir($dir)) {
            return [];
        }
        $found = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'phtml') {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    public function testTheRegistrationFileDeclaresAtLeastOneSourceDirective(): void
    {
        self::assertMatchesRegularExpression(
            '/@source\b/',
            self::directives(self::moduleCss()),
            'view/frontend/tailwind/module.css declares no @source outside its comments, so Hyvä '
            . 'imports the file and scans none of this package\'s templates'
        );
    }

    public function testTheSourceGlobReachesEveryTemplateThisPackageShips(): void
    {
        $globs = self::sourceGlobs();
        self::assertNotEmpty($globs, 'no quoted @source glob to check');

        $templates = self::shippedTemplates();
        // A floor, not an exact count: an empty search space must not pass. Two
        // templates ship today (desktop.phtml, mobile.phtml); if that drops to
        // zero the coverage loop below would be trivially satisfied.
        self::assertGreaterThanOrEqual(
            2,
            count($templates),
            'fewer templates found than this package ships - the search space is wrong'
        );

        foreach ($templates as $template) {
            $covered = false;
            foreach ($globs as $glob) {
                if (self::globReaches($glob, $template)) {
                    $covered = true;
                    break;
                }
            }
            self::assertTrue(
                $covered,
                sprintf(
                    '%s is not reached by any @source glob (%s), so every utility used only there is '
                    . 'absent from the compiled stylesheet',
                    str_replace(self::packageRoot() . '/', '', $template),
                    implode(', ', $globs)
                )
            );
        }
    }

    /**
     * The control. A glob is a claim about a search space, worth nothing until it
     * has been seen to EXCLUDE something. hyva_default.xml sits under
     * view/frontend/layout - outside templates/ and not a .phtml - so the glob
     * must not reach it. If it did, this test could not tell a covered template
     * from an uncovered one.
     */
    public function testTheGlobCheckRejectsAFileOutsideIt(): void
    {
        $outside = self::packageRoot() . '/view/frontend/layout/hyva_default.xml';
        self::assertFileExists($outside, 'the specimen this control needs does not exist');

        $reached = false;
        foreach (self::sourceGlobs() as $glob) {
            if (self::globReaches($glob, $outside)) {
                $reached = true;
            }
        }

        self::assertFalse(
            $reached,
            'the @source glob matches a layout XML file, so it is broader than intended and this '
            . 'test cannot distinguish a covered template from an uncovered one'
        );
    }

    /**
     * The comment-stripping control: prove the stripper removes a planted
     * `@source` inside a comment, so the first test cannot pass on prose.
     */
    public function testACommentedOutSourceDoesNotCountAsADeclaration(): void
    {
        $specimen = "/*\n * See the @source directive.\n */\n";
        self::assertDoesNotMatchRegularExpression(
            '/@source\b/',
            self::directives($specimen),
            'the comment stripper leaves @source mentions inside comments, which is exactly how the '
            . 'original defect read as cured'
        );

        $specimen .= '@source "../templates/**/*.phtml";' . "\n";
        self::assertMatchesRegularExpression(
            '/@source\b/',
            self::directives($specimen),
            'the comment stripper removed a real directive'
        );
    }

    /**
     * The control for the control. A globstar path contains `/*` and `*​/`, so a
     * naive block-comment strip silently truncates it - and the truncated glob
     * still matches SOMETHING, so the damage reads as a finding about the package
     * rather than about the stripper. This is what the predecessor test did.
     */
    public function testTheStripperDoesNotEatAGlobstarPath(): void
    {
        $specimen = '@source "../templates/**/*.phtml";';

        self::assertStringContainsString(
            '../templates/**/*.phtml',
            self::directives($specimen),
            'the comment stripper mangled a globstar path'
        );

        self::assertSame(
            '@source "../templates*.phtml";',
            (string) preg_replace('!/\*.*?\*/!s', '', $specimen),
            'the naive stripper no longer truncates the glob, so this control has stopped '
            . 'demonstrating anything and should be re-aimed or removed'
        );
    }

    public function testTheObserverIsRegisteredAndExists(): void
    {
        $events = self::packageRoot() . '/etc/frontend/events.xml';
        self::assertFileExists($events, 'nothing puts this module into the generator input');
        $xml = (string) file_get_contents($events);
        self::assertStringContainsString('hyva_config_generate_before', $xml, 'not observing Hyva\'s generator event');
        self::assertStringContainsString(
            'BlueFairy\\MegaMenuHyva\\Observer\\RegisterModuleForHyvaConfig',
            $xml,
            'events.xml does not name the registration observer'
        );
        self::assertFileExists(
            self::packageRoot() . '/Observer/RegisterModuleForHyvaConfig.php',
            'events.xml names an observer class that does not exist - which fails at runtime, not here'
        );
    }

    public function testComposerDeclaresPsr4SoTheObserverCanLoad(): void
    {
        $composer = json_decode((string) file_get_contents(self::packageRoot() . '/composer.json'), true);
        $psr4 = $composer['autoload']['psr-4'] ?? [];
        self::assertArrayHasKey(
            'BlueFairy\\MegaMenuHyva\\',
            $psr4,
            'composer.json declares no PSR-4 mapping, so the registration observer class cannot autoload'
        );
    }

    /**
     * desktop.phtml binds `:class="panelAlign"`; panelAlign only ever holds
     * left-0 or right-0. The @source glob covers them ONLY because both appear
     * as literals in the same scanned .phtml. If someone moves that Alpine data
     * out of the template, the glob stops covering the binding and the panel
     * mis-aligns silently. This photographs that safety.
     */
    public function testTheAlpineBoundClassTokensAreLiteralInAScannedTemplate(): void
    {
        $desktop = self::packageRoot() . '/view/frontend/templates/html/header/menu/desktop.phtml';
        self::assertFileExists($desktop, 'desktop.phtml moved - retarget this test');
        $html = (string) file_get_contents($desktop);

        self::assertStringContainsString(':class="panelAlign"', $html, 'the panelAlign binding moved - retarget this test');
        foreach (["'left-0'", "'right-0'"] as $token) {
            self::assertStringContainsString(
                $token,
                $html,
                'panelAlign can hold ' . $token . ' but that class token is not literal in the scanned '
                . 'template, so the Tailwind build purges it. Keep it literal here or add an '
                . '@source inline(...) safelist to view/frontend/tailwind/module.css'
            );
        }
    }
}
