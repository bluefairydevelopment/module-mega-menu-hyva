<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Integration\Controller;

use BlueFairy\MegaMenu\Api\ChildOverrideRepositoryInterface;
use BlueFairy\MegaMenu\Api\Data\ChildOverrideInterfaceFactory;
use BlueFairy\MegaMenu\Api\MegaMenuRepositoryInterface;
use BlueFairy\MegaMenu\Model\CacheTag;
use BlueFairy\MegaMenu\Model\Config;
use BlueFairy\MegaMenu\Model\SafeUrl;
use BlueFairy\MegaMenu\Test\Integration\Helper\StorefrontMenu;
use BlueFairy\MegaMenu\ViewModel\LabelStyle;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * M2MAP-688 review r4 security S-5 / S-5b, undirected-a 5: the Hyvä header templates
 * print admin-entered label colours and URLs through the base module's rules.
 *
 * Before: desktop.phtml and mobile.phtml validated label colours with
 * /^#[0-9a-fA-F]{3,6}$/ (four or five digits passed, a trailing newline too), and
 * mobile.phtml printed a child override's custom URL raw, so "//host" (another host
 * to a browser) became a menu link. Each case is a real request on the Hyvä theme; the
 * positive case shows the same page DOES print a valid colour and a safe override, so
 * an absence below cannot come from a page that prints neither.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
#[CoversClass(LabelStyle::class)]
#[CoversClass(SafeUrl::class)]
class HyvaLabelStyleAndUrlRulesTest extends AbstractController
{
    private const HYVA_THEME = 'frontend/Hyva/default-csp';

    protected function setUp(): void
    {
        parent::setUp();
        $this->_objectManager->removeSharedInstance(Config::class);
        $this->_objectManager->get(CacheInterface::class)->clean([CacheTag::SENTINEL]);
        $types = $this->_objectManager->get(TypeListInterface::class);
        foreach (['block_html', 'full_page', 'layout'] as $type) {
            $types->cleanType($type);
        }

        $theme = $this->_objectManager->get(ThemeCollectionFactory::class)->create()
            ->getThemeByFullPath(self::HYVA_THEME);
        self::assertNotNull($theme->getId(), self::HYVA_THEME . ' is not registered on this install');
        $this->_objectManager->get(MutableScopeConfigInterface::class)->setValue(
            'design/theme/theme_id',
            (string) $theme->getId(),
            ScopeInterface::SCOPE_STORE,
            'default'
        );
        $this->_objectManager->get(DesignInterface::class)->setDesignTheme($theme, 'frontend');
    }

    /**
     * The home page with the menu row's label colour and the child's override set.
     */
    private function render(string $labelColor, string $childLabelColor, string $customUrl): string
    {
        $menu = StorefrontMenu::create();
        $item = $menu['item'];
        $item->setLabelColor($labelColor);
        $this->_objectManager->get(MegaMenuRepositoryInterface::class)->save($item);
        $override = $this->_objectManager->get(ChildOverrideInterfaceFactory::class)->create();
        $override->setParentMegamenuItemId((int) $item->getEntityId())->setCategoryId($menu['child_id'])
            ->setStoreId(1)->setLabelText('Child M688')->setLabelColor($childLabelColor)->setCustomUrl($customUrl);
        $this->_objectManager->get(ChildOverrideRepositoryInterface::class)->save($override);

        $this->dispatch('/');
        $body = (string) $this->getResponse()->getBody();
        self::assertSame(200, $this->getResponse()->getHttpResponseCode(), 'the storefront did not answer 200');
        self::assertStringContainsString('x-data="megaMenuDesktop"', $body, 'the Hyvä desktop menu is not on the page');
        self::assertStringContainsString('x-data="megaMenuMobile"', $body, 'the Hyvä mobile menu is not on the page');
        self::assertStringContainsString(StorefrontMenu::CHILD_NAME, $body, 'the child is not on the page');

        // Attribute values are printed escaped (escapeHtmlAttr turns ":" "#" " " into &#x..;): compare the
        // decoded page, or an absence below could only mean "not printed in that spelling".
        return html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @magentoConfigFixture current_store bluefairy_megamenu/general/enabled 1
     */
    public function testValidColoursAndASafeOverrideArePrinted(): void
    {
        $body = $this->render('#e63946', '#00aa00', '/m688-safe-override');

        self::assertStringContainsString('background-color: #e63946', $body, 'a valid label colour was not printed');
        self::assertStringContainsString('background-color: #00aa00', $body, 'a valid child label colour was not printed');
        self::assertStringContainsString('href="/m688-safe-override"', $body, 'a safe override URL was not printed');
    }

    /**
     * @magentoConfigFixture current_store bluefairy_megamenu/general/enabled 1
     */
    public function testFourOrFiveDigitColoursAndAProtocolRelativeOverrideAreNot(): void
    {
        $body = $this->render('#abcd', '#abcde', '//evil.example/m688');

        self::assertStringNotContainsString('#abcd', $body, 'a four-digit label colour reached a style attribute');
        self::assertStringNotContainsString('#abcde', $body, 'a five-digit child label colour reached a style attribute');
        self::assertStringContainsString('background-color: #2563eb', $body, 'the desktop label lost its default colour');
        self::assertStringNotContainsString('evil.example', $body, 'a protocol-relative override URL became a link');
    }
}
