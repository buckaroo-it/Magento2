<?php

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Buckaroo\Magento2\Api\AddressUpdaterInterface;
use Buckaroo\Magento2\Logging\Log;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote\AddressFactory;

class PostNLAddressHandler extends AbstractAddressHandler
{
    /**
     * @var Address
     */
    protected $addressFactory;

    public function __construct(Log $buckarooLogger, AddressFactory $addressFactory)
    {
        $this->addressFactory = $addressFactory;
        parent::__construct($buckarooLogger);
    }

    public function handle(Order $order): Order
    {
        $postNLPakjeGemakAddress = $this->getPostNLPakjeGemakAddressInQuote($order->getQuoteId());

        if (!empty($postNLPakjeGemakAddress) && !empty($postNLPakjeGemakAddress->getData())) {
            $shippingAddress = $postNLPakjeGemakAddress;
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
