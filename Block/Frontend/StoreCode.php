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
