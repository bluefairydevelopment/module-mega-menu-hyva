<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Layout;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * M2MAP-688: the Hyvä wiring the base module used to carry now lives in this
 * package's hyva_default.xml. Read as XML, not as text, so a commented-out
 * element cannot pass. The integration suite proves the same on a rendered layout.
 */
#[CoversNothing]
class HyvaDefaultLayoutTest extends TestCase
{
    private static function layout(): \SimpleXMLElement
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/view/frontend/layout/hyva_default.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, $xml, 'hyva_default.xml does not parse');

        return $xml;
    }

    public function testBothHyvaMenuBlocksGetTheirTemplateAndTheNavigationViewModel(): void
    {
        foreach ([
            'topmenu_desktop' => 'BlueFairy_MegaMenuHyva::html/header/menu/desktop.phtml',
            'topmenu_mobile' => 'BlueFairy_MegaMenuHyva::html/header/menu/mobile.phtml',
        ] as $name => $template) {
            $blocks = self::layout()->xpath("//referenceBlock[@name='{$name}']");
            self::assertCount(1, $blocks, $name . ' is not referenced exactly once');
            self::assertSame($template, (string) $blocks[0]['template'], $name . ' template');
            $argument = $blocks[0]->xpath("arguments/argument[@name='viewModel']");
            self::assertCount(1, $argument, $name . ' gets no viewModel argument');
            self::assertSame('object', (string) $argument[0]->attributes('xsi', true)['type']);
            self::assertSame('BlueFairy\MegaMenu\ViewModel\Navigation', trim((string) $argument[0]));
        }
    }

    public function testTheBasesLumaRendererIsRemovedOnHyvaPages(): void
    {
        $removed = self::layout()->xpath("//referenceBlock[@name='bluefairy.megamenu.luma'][@remove='true']");
        self::assertCount(1, $removed);

        $base = (string) file_get_contents(dirname(__DIR__, 4) . '/MegaMenu/view/frontend/layout/default.xml');
        self::assertStringContainsString(
            'name="bluefairy.megamenu.luma"',
            $base,
            'the block removed here is no longer the one the base declares - retarget'
        );
    }

    /**
     * Hyvä's topmenu_generic (parent of both menu blocks) is block-cached for an hour. A cached
     * render replays the first request's CSP nonce and adds no hash, so the browser refuses both
     * menu scripts under strict CSP. The parent's cache_lifetime is set to null: not cached.
     */
    public function testTheMenuParentIsNotBlockCached(): void
    {
        $argument = self::layout()->xpath(
            "//referenceBlock[@name='topmenu_generic']/arguments/argument[@name='cache_lifetime']"
        );
        self::assertCount(1, $argument, 'topmenu_generic keeps Hyvä\'s block cache');
        self::assertSame('null', (string) $argument[0]->attributes('xsi', true)['type']);
    }
}
