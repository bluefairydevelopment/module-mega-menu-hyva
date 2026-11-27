<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Integration\Controller;

use BlueFairy\MegaMenu\Api\MegaMenuRepositoryInterface;
use BlueFairy\MegaMenu\Model\CacheTag;
use BlueFairy\MegaMenu\Model\Config;
use BlueFairy\MegaMenu\Model\MegaMenuItemFactory;
use BlueFairy\MegaMenu\Model\Navigation\LinkItems;
use BlueFairy\MegaMenu\Test\Integration\Helper\LinkMenuItems;
use BlueFairy\MegaMenu\Test\Integration\Helper\StorefrontMenu;
use BlueFairy\MegaMenu\ViewModel\Navigation;
use Hyva\Theme\ViewModel\Navigation as HyvaNavigation;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * M2MAP-690 on Hyvä: CMS Page and Custom Link menu items render in both header templates
 * (desktop.phtml and mobile.phtml) as plain top-level links after the categories, and a new
 * one shows in the next header even when the header comes from the block cache.
 *
 * The header (Hyvä's topmenu_generic, block-cached for an hour) is tagged with the Navigation
 * view model's identities. A new row's save purges the sentinel and the row's own tag - and
 * until build round 6 the identities carried neither the sentinel nor any link item (there were
 * none), so a new item would have waited for the hour.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
#[CoversClass(LinkItems::class)]
#[CoversClass(Navigation::class)]
class HyvaLinkItemsTest extends AbstractController
{
    private const HYVA_THEME = 'frontend/Hyva/default-csp';

    /** @var string[] the block-cache tags of the last header rendered */
    private array $lastHeaderTags = [];

    private string $lastHeaderCacheKey = '';

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
     * @magentoConfigFixture current_store bluefairy_megamenu/general/enabled 1
     */
    public function testBothHeaderTemplatesRenderTheLinkItemsAfterTheCategories(): void
    {
        StorefrontMenu::create();
        $links = LinkMenuItems::create();

        $this->dispatch('/');
        $body = html_entity_decode((string) $this->getResponse()->getBody(), ENT_QUOTES | ENT_HTML5);
        $pageUrl = '#href="(https?://[^"]+/' . preg_quote($links['page_key'], '#') . ')"#';

        $desktopStart = strpos($body, 'x-data="megaMenuDesktop"');
        $mobileStart = strpos($body, 'x-data="megaMenuMobile"');
        self::assertNotFalse($desktopStart, 'not the Hyvä desktop mega menu');
        self::assertNotFalse($mobileStart, 'not the Hyvä mobile mega menu');
        $desktop = substr($body, $desktopStart, (int) strpos($body, '</nav>', $desktopStart) - $desktopStart);
        $mobile = substr($body, $mobileStart, (int) strpos($body, '</nav>', $mobileStart) - $mobileStart);

        foreach (['desktop' => $desktop, 'mobile' => $mobile] as $where => $html) {
            self::assertMatchesRegularExpression($pageUrl, $html, 'the CMS Page item is not in the ' . $where . ' menu');
            self::assertStringContainsString(LinkMenuItems::PAGE_TITLE, $html, $where . ': no page title');
            self::assertStringContainsString('href="' . LinkMenuItems::CUSTOM_URL . '"', $html, $where . ': no Custom Link');
            self::assertStringContainsString(LinkMenuItems::CUSTOM_TEXT, $html, $where . ': no Link Text');
            self::assertLessThan(
                strpos($html, LinkMenuItems::PAGE_TITLE),
                strpos($html, StorefrontMenu::PARENT_NAME),
                $where . ': the link items come after the categories'
            );
        }
        self::assertMatchesRegularExpression(
            '#<a\s+href="https?://[^"]+/' . preg_quote($links['page_key'], '#') . '"\s+class="level-0 [^"]*"\s*>\s*'
            . preg_quote(LinkMenuItems::PAGE_TITLE, '#') . '\s*</a>\s*<span[^>]*style="background-color: \#e63946;#',
            $desktop,
            'the desktop CMS Page item is not a top-level link with its label'
        );
    }

    /**
     * A new Custom Link row shows in the next header rendered from the block cache.
     *
     * @magentoConfigFixture current_store bluefairy_megamenu/general/enabled 1
     * @magentoCache block_html enabled
     */
    public function testANewLinkItemShowsInTheNextCachedHeader(): void
    {
        $links = LinkMenuItems::create();
        self::assertStringContainsString(LinkMenuItems::CUSTOM_TEXT, $this->headerHtml(), 'precondition: not rendered');
        self::assertContains(
            CacheTag::SENTINEL,
            $this->lastHeaderTags,
            'the cached header is not tagged with the sentinel a new row\'s save purges; tags: ' . implode(',', $this->lastHeaderTags)
        );

        // Control: an edit no save event sees stays invisible - the next header is the cache's.
        $resource = $this->_objectManager->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('cms_page'),
            ['title' => 'M690 behind the cache'],
            ['page_id = ?' => (int) $links['page']->getId()]
        );
        $cached = $this->headerHtml();
        self::assertStringContainsString(LinkMenuItems::PAGE_TITLE, $cached, 'control: the header is not from the block cache');

        $row = $this->_objectManager->get(MegaMenuItemFactory::class)->create();
        $row->setStoreId(1)->setIsEnabled(true)->setMenuType('custom_link')->setCustomUrl('/m690-blog')
            ->setTitle('M690 Blog')->setSortOrder(3);
        $key = $this->lastHeaderCacheKey;
        $cache = $this->_objectManager->get(CacheInterface::class);
        self::assertNotFalse($cache->load($key), 'control: the header is not in the block cache under ' . $key);
        $this->_objectManager->get(MegaMenuRepositoryInterface::class)->save($row);
        self::assertFalse($cache->load($key), 'the new row\'s save did not clean the cached header ' . $key);

        self::assertStringContainsString('M690 Blog', $this->headerHtml(), 'the new Custom Link item is not in the next header');
    }

    /**
     * Hyvä's cached topmenu_generic (both header menus) from a fresh page layout, as a new request builds it.
     */
    private function headerHtml(): string
    {
        // What one request memoizes: Hyvä's own navigation tree (by depth) is rebuilt by a new
        // instance; the mega menu's view model forgets its merged tree and link nodes.
        foreach ([HyvaNavigation::class, \BlueFairy\MegaMenuHyva\Model\HyvaNavigationSource::class] as $class) {
            $this->_objectManager->removeSharedInstance($class);
        }
        foreach ([Navigation::class, \BlueFairy\MegaMenu\Model\Panel\PanelDataBuilder::class] as $class) {
            $instance = $this->_objectManager->get($class);
            if ($instance instanceof ResetAfterRequestInterface) {
                $instance->_resetState();
            }
        }
        // An ISOLATED layout: the shared one keeps each child block's rendered HTML (Layout's render
        // cache), so the header's menus would be printed from the first render however the block
        // cache was cleaned.
        $page = $this->_objectManager->get(PageFactory::class)->create(false, ['isIsolated' => true]);
        $page->getConfig()->setPageLayout('1column');
        $page->addHandle(['default', 'hyva_default', 'cms_index_index']);
        $block = $page->getLayout()->getBlock('topmenu_generic');
        self::assertNotFalse($block, 'the Hyvä page has no topmenu_generic');
        $html = (string) $block->toHtml();
        $method = new \ReflectionMethod($block, 'getCacheTags');
        $this->lastHeaderTags = array_values(array_filter((array) $method->invoke($block), 'is_string'));
        $this->lastHeaderCacheKey = (string) $block->getCacheKey();

        return $html;
    }
}
