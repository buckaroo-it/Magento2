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

namespace Buckaroo\Magento2\Plugin\CatalogInventory\Model;

use Buckaroo\Magento2\Model\Session as BuckarooSession;

class Configuration
{
    /**
     * @var BuckarooSession
     */
    protected BuckarooSession $buckarooSession;

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
     * @param mixed $result
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
