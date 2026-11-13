<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Block\Catalog\Product\View\Applepay;
use Buckaroo\Magento2\Block\Catalog\Product\View\Googlepay;
use Buckaroo\Magento2\Block\Catalog\Product\View\IdealFastCheckout;
use Buckaroo\Magento2\Block\Catalog\Product\View\PaypalExpress;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Checkout\Block\QuoteShortcutButtons;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Adds the Buckaroo express payment buttons to the cart page shortcut container.
 *
 * Uses the shortcut_buttons_container event (same mechanism as Magento_Paypal) instead
 * of layout XML, so the buttons render wherever the active theme places the shortcut
 * block and cannot affect the position of sibling elements such as the
 * "Proceed to Checkout" button (see GitHub issue #1707).
 */
class AddExpressShortcuts implements ObserverInterface
{
    /**
     * Express button blocks and their cart page templates, in render order.
     */
    private const EXPRESS_SHORTCUTS = [
        Applepay::class => 'Buckaroo_Magento2::checkout/cart/applepay.phtml',
        Googlepay::class => 'Buckaroo_Magento2::checkout/cart/googlepay.phtml',
        IdealFastCheckout::class => 'Buckaroo_Magento2::checkout/cart/ideal-fast-checkout.phtml',
        PaypalExpress::class => 'Buckaroo_Magento2::checkout/cart/paypal-express.phtml',
    ];

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(BuckarooLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Add the Buckaroo express shortcut buttons to the cart page container.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if ($observer->getData('is_catalog_product')) {
            return;
        }

        $shortcutButtons = $observer->getEvent()->getContainer();
        if (!$shortcutButtons instanceof QuoteShortcutButtons) {
            return;
        }

        foreach (self::EXPRESS_SHORTCUTS as $blockClass => $template) {
            try {
                $shortcut = $shortcutButtons->getLayout()->createBlock($blockClass);
                $shortcut->setTemplate($template);
                $shortcutButtons->addShortcut($shortcut);
            } catch (\Throwable $exception) {
                $this->logger->addError(sprintf(
                    '[AddExpressShortcuts] | [Observer] | [%s:%s] - Could not add express shortcut %s | [ERROR]: %s',
                    __METHOD__,
                    __LINE__,
                    $blockClass,
                    $exception->getMessage()
                ));
            }
        }
    }
}
