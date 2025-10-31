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

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Buckaroo\Magento2\Api\AddressUpdaterInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class PostNLAddressHandler extends AbstractAddressHandler
{
    /**
     * @var Address
     */
    protected $addressFactory;

    /**
     * @param BuckarooLoggerInterface $logger
     * @param AddressFactory          $addressFactory
     */
    public function __construct(BuckarooLoggerInterface $logger, AddressFactory $addressFactory)
    {
        $this->addressFactory = $addressFactory;
        parent::__construct($logger);
    }

    /**
     * Update shipping address by PostNL
     *
     * @param  Order                 $order
     * @param  OrderAddressInterface $shippingAddress
     * @return Order
     */
    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order
    {
        $postNLPakjeGemakAddress = $this->getPostNLPakjeGemakAddressInQuote($order->getQuoteId());

        if (!empty($postNLPakjeGemakAddress) && !empty($postNLPakjeGemakAddress->getData())) {
            foreach ($postNLPakjeGemakAddress->getData() as $key => $value) {
                $shippingAddress->setData($key, $value);
            }
        }

        return $order;
    }

    /**
     * Check if there is a "pakjegemak" address stored in the quote by this order.
     * Afterpay wants to receive the "pakjegemak" address instead of the customer shipping address.
     *
     * @param int $quoteId
     *
     * @return array|Address
     */
    protected function getPostNLPakjeGemakAddressInQuote($quoteId)
    {
        $quoteAddress = $this->addressFactory->create();

        $collection = $quoteAddress->getCollection();
        $collection->addFieldToFilter('quote_id', $quoteId);
        $collection->addFieldToFilter('address_type', 'pakjegemak');
        // @codingStandardsIgnoreLine
        return $collection->setPageSize(1)->getFirstItem();
    }
}
