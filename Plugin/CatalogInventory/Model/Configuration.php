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

namespace Buckaroo\Magento2\Plugin\CatalogInventory\Model;

use Buckaroo\Magento2\Model\Session as BuckarooSession;

class Configuration
{
    /**
     * @var BuckarooSession
     */
    protected $buckarooSession;

    /**
     * @param BuckarooSession $buckarooSession
     */
    public function __construct(
        BuckarooSession $buckarooSession
    ) {
        $this->buckarooSession = $buckarooSession;
    }

    /**
     * Check if is possible subtract value from item qty based on buckaroo session flag
     *
     * @param \Magento\CatalogInventory\Model\Configuration $subject
     * @param mixed                                         $result
     *
     * @return false|mixed
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCanSubtractQty($subject, $result)
    {
        $flag = $this->buckarooSession->getData('flagHandleFailedQuote');
        if ($flag) {
            return false;
        } else {
            return $result;
        }
    }
}
