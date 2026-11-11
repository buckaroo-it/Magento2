<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Block\Frontend;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;

/**
 * Exposes the current store code to JavaScript.
 *
 * Magento only publishes the store code to the frontend through window.checkoutConfig, which
 * exists on the checkout page and nowhere else. The express-checkout components run on catalog and
 * cart pages and still have to address the store-scoped REST route, so they need it there too.
 */
class StoreCode extends Template
{
    /**
     * Get the code of the store view the page is being rendered for
     *
     * @return string
     */
    public function getStoreCode(): string
    {
        try {
            return (string)$this->_storeManager->getStore()->getCode();
        } catch (NoSuchEntityException $exception) {
            return '';
        }
    }
}
