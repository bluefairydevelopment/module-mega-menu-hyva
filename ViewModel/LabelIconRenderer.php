<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\ViewModel;

use BlueFairy\MegaMenu\Model\Log\FailureCodes;
use BlueFairy\MegaMenu\Model\LabelIcon;
use Hyva\Theme\ViewModel\LucideIcons;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;

/**
 * Renders a menu label's icon on Hyvä, and never breaks the menu doing it (M2MAP-688).
 *
 * desktop.phtml used to hand the stored label_icon value straight to
 * LucideIcons::renderHtml(). The admin form offers star, flame, new, sale and hot;
 * Hyvä's Lucide set has no `new`, `sale` or `hot`, and renderHtml() throws "Unable
 * to find the SVG icon" for a name it cannot find - so picking one of them broke the
 * storefront menu.
 *
 * Now an offered value is mapped to the Lucide icon the base module's
 * {@see LabelIcon} names for it (the same glyph the Luma menu draws inline): star
 * and flame keep exactly the markup they had, new/sale/hot render sparkles /
 * badge-percent / flame-kindling. Any other value shaped like an icon name
 * ({@see LabelIcon::NAME_PATTERN}) is passed to Lucide as it was before - a store
 * that saved a Lucide name through the API keeps its icon - and if Lucide cannot
 * render it the label shows no icon and the failure is logged. A value of any other
 * shape is logged and never reaches Lucide.
 */
class LabelIconRenderer implements ArgumentInterface
{
    /** What the desktop template asked LucideIcons for before this class existed. */
    public const CSS_CLASS = 'inline-block';

    public const SIZE = 12;

    /**
     * @param LabelIcon $labelIcon
     * @param LucideIcons $lucideIcons
     * @param LoggerInterface $logger
     * @param FailureCodes|null $failureCodes
     */
    public function __construct(
        private readonly LabelIcon $labelIcon,
        private readonly LucideIcons $lucideIcons,
        private readonly LoggerInterface $logger,
        ?FailureCodes $failureCodes = null
    ) {
        $this->failureCodes = $failureCodes ?? new FailureCodes();
    }

    /** @var FailureCodes how a failure is logged */
    private FailureCodes $failureCodes;

    /**
     * The icon's SVG for a label_icon value, or '' for none or an icon that cannot render.
     *
     * @param string $value
     * @return string
     */
    public function render(string $value): string
    {
        if ($this->labelIcon->isNone($value)) {
            return '';
        }
        // A stored value that is not even shaped like an
        // icon name (one saved through the API) never reaches Lucide.
        if (!$this->labelIcon->isWellFormed($value)) {
            $this->logger->warning(
                'BlueFairy MegaMenuHyva: the label icon is not an icon name; the label shows without one.',
                // A value of any length can be stored through the API.
                ['label_icon' => $this->labelIcon->forLog($value)]
            );

            return '';
        }
        $name = $this->labelIcon->lucideName($value) ?? $value;

        try {
            return $this->lucideIcons->renderHtml(
                $name,
                self::CSS_CLASS,
                self::SIZE,
                self::SIZE,
                ['aria-hidden' => 'true']
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'BlueFairy MegaMenuHyva: the label icon cannot be rendered; the label shows without one.',
                ['label_icon' => $this->labelIcon->forLog($value), 'lucide_icon' => $this->labelIcon->forLog($name),
                    'failure' => $this->failureCodes->of($e)]
            );

            return '';
        }
    }
}
