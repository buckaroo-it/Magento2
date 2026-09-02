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
     * @param Order                 $order
     * @param OrderAddressInterface $shippingAddress
     *
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
     *
     * Afterpay wants to receive the "pakjegemak" address instead of the customer shipping address.
     *
     * @param int $quoteId
     *
     * @return array|Address
     * @throws \Magento\Framework\Exception\LocalizedException
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
