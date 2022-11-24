<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Buckaroo\Magento2\Logging\Log;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class DPDPickupAddressHandler extends AbstractAddressHandler
{
    protected $quoteRepository;

    public function __construct(Log $buckarooLogger, \Magento\Quote\Api\CartRepositoryInterface $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
        parent::__construct($buckarooLogger);
    }

    /**
     * @param Order $order
     * @param OrderAddressInterface $shippingAddress
     * @return Order
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order
    {
        if ($order->getShippingMethod() == 'dpdpickup_dpdpickup') {
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $this->updateShippingAddressByDpdParcel($quote, $requestData);
        }

        return $order;
    }

    /**
     * @param $quote
     * @param $requestData
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function updateShippingAddressByDpdParcel($quote, &$requestData)
    {
        $fullStreet = $quote->getDpdStreet();
        $postalCode = $quote->getDpdZipcode();
        $city = $quote->getDpdCity();
        $country = $quote->getDpdCountry();

        if (!$fullStreet && $quote->getDpdParcelshopId()) {
            $this->buckarooLogger->addDebug(__METHOD__ . '|2|');
            $this->buckarooLogger->addDebug(var_export($_COOKIE, true));
            $fullStreet = $_COOKIE['dpd-selected-parcelshop-street'] ?? '';
            $postalCode = $_COOKIE['dpd-selected-parcelshop-zipcode'] ?? '';
            $city = $_COOKIE['dpd-selected-parcelshop-city'] ?? '';
            $country = $_COOKIE['dpd-selected-parcelshop-country'] ?? '';
        }

        $matches = false;
        if ($fullStreet && preg_match('/(.*)\s(.+)$/', $fullStreet, $matches)) {
            $this->buckarooLogger->addDebug(__METHOD__ . '|3|');

            $street = $matches[1];
            $streetHouseNumber = $matches[2];

            $mapping = [
                ['Street', $street],
                ['PostalCode', $postalCode],
                ['City', $city],
                ['Country', $country],
                ['StreetNumber', $streetHouseNumber],
            ];

            $this->buckarooLogger->addDebug(var_export($mapping, true));

            $this->updateShippingAddressCommonMapping($mapping, $requestData);

            foreach ($requestData as $key => $value) {
                if ($requestData[$key]['Group'] == 'ShippingCustomer'
                    && $requestData[$key]['Name'] == 'StreetNumberAdditional'
                ) {
                    unset($requestData[$key]);
                }
            }
        }
    }
}
