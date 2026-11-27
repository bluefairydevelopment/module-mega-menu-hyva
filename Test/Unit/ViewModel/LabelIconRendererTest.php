<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\ViewModel;

use BlueFairy\MegaMenu\Model\LabelIcon;
use BlueFairy\MegaMenuHyva\ViewModel\LabelIconRenderer;
use Hyva\Theme\ViewModel\LucideIcons;
use Magento\Framework\View\Asset\File\NotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use BlueFairy\MegaMenu\Test\Unit\Support\DoublesFollowExpectations;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * M2MAP-688: Hyvä label icons - every admin value reaches Lucide under a name Lucide
 * has, and a name it cannot render shows no icon instead of breaking the menu.
 */
#[CoversClass(LabelIconRenderer::class)]
class LabelIconRendererTest extends TestCase
{
    use DoublesFollowExpectations;

    private LucideIcons&Stub $lucide;

    private LoggerInterface&Stub $logger;

    private LabelIconRenderer $renderer;

    protected function setUp(): void
    {
        $this->lucide = $this->double(LucideIcons::class, 'lucide');
        $this->logger = $this->double(LoggerInterface::class, 'logger');
        $this->renderer = new LabelIconRenderer(new LabelIcon(), $this->lucide, $this->logger);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function offered(): array
    {
        return [
            'star' => ['star', 'star'],
            'flame' => ['flame', 'flame'],
            'new' => ['new', 'sparkles'],
            'sale' => ['sale', 'badge-percent'],
            'hot' => ['hot', 'flame-kindling'],
        ];
    }

    #[DataProvider('offered')]
    public function testEachOfferedValueRendersItsLucideIconAtTheTemplatesSize(string $value, string $lucide): void
    {
        // The exact call desktop.phtml made before, so star/flame keep their markup.
        $this->lucide->expects($this->once())->method('renderHtml')
            ->with($lucide, 'inline-block', 12, 12, ['aria-hidden' => 'true'])
            ->willReturn('<svg data-icon="' . $lucide . '"></svg>');
        $this->logger->expects($this->never())->method('warning');

        self::assertSame('<svg data-icon="' . $lucide . '"></svg>', $this->renderer->render($value));
    }

    public function testNoIconAsksLucideForNothing(): void
    {
        $this->lucide->expects($this->never())->method('renderHtml');
        $this->logger->expects($this->never())->method('warning');

        self::assertSame('', $this->renderer->render(''));
        self::assertSame('', $this->renderer->render('none'));
    }

    public function testAnotherLucideNameStillRendersAsItDidBefore(): void
    {
        $this->lucide->expects($this->once())->method('renderHtml')
            ->with('heart', 'inline-block', 12, 12, ['aria-hidden' => 'true'])
            ->willReturn('<svg data-icon="heart"></svg>');

        self::assertSame('<svg data-icon="heart"></svg>', $this->renderer->render('heart'));
    }

    public function testAnIconLucideCannotFindRendersNothingAndIsLogged(): void
    {
        $this->lucide->method('renderHtml')->willThrowException(
            new NotFoundException('Unable to find the SVG icon "nope"')
        );
        $this->logger->expects($this->once())->method('warning')->with(
            $this->stringContains('cannot be rendered'),
            $this->callback(static fn (array $c): bool => $c['label_icon'] === 'nope'
                && $c['lucide_icon'] === 'nope'
                && ($c['failure'] ?? null) === NotFoundException::class
                && !str_contains((string) json_encode($c), 'Unable to find'))
        );

        self::assertSame('', $this->renderer->render('nope'));
    }

    public function testAnyOtherFailureIsCaughtToo(): void
    {
        $this->lucide->method('renderHtml')->willThrowException(new \TypeError('boom'));
        $this->logger->expects($this->once())->method('warning');

        self::assertSame('', $this->renderer->render('new'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notIconNames(): array
    {
        return ['markup' => ['<svg onload=alert(1)>'], 'path' => ['../x'], 'upper case' => ['Star'],
            'trailing newline' => ["star\n"], 'too long' => [str_repeat('a', 65)]];
    }

    /**
     * M2MAP-688 review r3 LOW-15: any stored value was passed to Lucide.
     */
    #[DataProvider('notIconNames')]
    public function testAValueThatIsNotAnIconNameNeverReachesLucide(string $value): void
    {
        $this->lucide->expects($this->never())->method('renderHtml');
        $this->logger->expects($this->once())->method('warning')->with(
            $this->stringContains('not an icon name'),
            // Cut to 64 characters for the log since review r4 S-11 (the "too long" case).
            ['label_icon' => (new LabelIcon())->forLog($value)]
        );

        self::assertSame('', $this->renderer->render($value));
    }

    /**
     * Review r4 security S-11: the stored value was logged whole, whatever its length.
     */
    public function testAVeryLongValueIsLoggedCutTo64Characters(): void
    {
        $value = str_repeat('<x>', 4000);
        $this->lucide->expects($this->never())->method('renderHtml');
        $this->logger->expects($this->once())->method('warning')->with(
            $this->stringContains('not an icon name'),
            ['label_icon' => substr($value, 0, 64) . '...']
        );

        self::assertSame('', $this->renderer->render($value));
    }
}
