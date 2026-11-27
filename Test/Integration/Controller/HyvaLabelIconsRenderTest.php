<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Integration\Controller;

use BlueFairy\MegaMenu\Api\MegaMenuRepositoryInterface;
use BlueFairy\MegaMenu\Model\CacheTag;
use BlueFairy\MegaMenu\Model\Config;
use BlueFairy\MegaMenu\Test\Integration\Helper\StorefrontMenu;
use Hyva\Theme\ViewModel\LucideIcons;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * M2MAP-688: every label icon the admin form offers renders on a Hyvä storefront, and
 * an icon name Hyvä does not have renders nothing instead of breaking the menu.
 *
 * The defect: desktop.phtml handed the stored label_icon straight to
 * LucideIcons::renderHtml(). Hyvä's Lucide set has `star` and `flame`, not `new`,
 * `sale` or `hot`, and renderHtml() throws "Unable to find the SVG icon" for a name it
 * cannot find - so a merchant who picked New, Sale or Hot broke the storefront menu.
 *
 * Each case is a real request on the Hyvä theme. The expected icon is the Lucide icon
 * this test names for the value (its own table, not the code's map), rendered by
 * Hyvä's own LucideIcons at the template's size and attributes.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
#[CoversClass(\BlueFairy\MegaMenuHyva\ViewModel\LabelIconRenderer::class)]
class HyvaLabelIconsRenderTest extends AbstractController
{
    private const HYVA_THEME = 'frontend/Hyva/default-csp';

    private const ICON_SPAN = '#<span class="megamenu-label-icon" aria-hidden="true">\s*(.*?)\s*</span>#s';

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
     * The admin form's values and the Lucide icon each must show.
     *
     * @return array<string, array{string, string}>
     */
    public static function offeredIcons(): array
    {
        return [
            'star' => ['star', 'star'],
            'flame' => ['flame', 'flame'],
            'new' => ['new', 'sparkles'],
            'sale' => ['sale', 'badge-percent'],
            'hot' => ['hot', 'flame-kindling'],
        ];
    }

    /**
     * Render the storefront home page with the menu row's label icon set to $value.
     */
    private function renderWithIcon(string $value): string
    {
        $menu = StorefrontMenu::create();
        $item = $menu['item'];
        $item->setLabelIcon($value);
        $this->_objectManager->get(MegaMenuRepositoryInterface::class)->save($item);

        $this->dispatch('/');
        $body = (string) $this->getResponse()->getBody();
        self::assertSame(200, $this->getResponse()->getHttpResponseCode(), 'the storefront did not answer 200');
        self::assertStringContainsString('x-data="megaMenuDesktop"', $body, 'the Hyvä desktop mega menu is not on the page');
        self::assertStringContainsString(StorefrontMenu::PARENT_NAME, $body, 'the menu item is not on the page');

        return $body;
    }

    /**
     * @magentoConfigFixture current_store bluefairy_megamenu/general/enabled 1
     */
    #[DataProvider('offeredIcons')]
    public function testEveryOfferedIconRendersItsLucideIcon(string $value, string $lucideName): void
    {
        $body = $this->renderWithIcon($value);

        self::assertSame(
            1,
            preg_match(self::ICON_SPAN, $body, $match),
            'no label icon was rendered for "' . $value . '"'
        );
        $expected = $this->_objectManager->get(LucideIcons::class)
            ->renderHtml($lucideName, 'inline-block', 12, 12, ['aria-hidden' => 'true']);
        self::assertSame(
            trim($expected),
            trim($match[1]),
            'label icon "' . $value . '" did not render Lucide\'s "' . $lucideName . '"'
        );
    }

    /**
     * @magentoConfigFixture current_store bluefairy_megamenu/general/enabled 1
     */
    public function testAnIconHyvaDoesNotHaveRendersNothingAndKeepsTheMenu(): void
    {
        $body = $this->renderWithIcon('no-such-icon-m688');

        self::assertSame(0, preg_match(self::ICON_SPAN, $body), 'an icon span was rendered for an unknown icon');
        self::assertMatchesRegularExpression(
            '#text-xs font-semibold rounded text-white"\s+style="[^"]*"\s*>\s*New\s*</span>#s',
            $body,
            'the label badge (text "New") is missing - the unknown icon took the label down with it'
        );
    }
}
