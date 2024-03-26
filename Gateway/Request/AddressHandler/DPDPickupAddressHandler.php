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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class DPDPickupAddressHandler extends AbstractAddressHandler
{
    /**
     * @var CartRepositoryInterface
     */
    protected CartRepositoryInterface $quoteRepository;

    /**
     * @param BuckarooLoggerInterface $logger
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(BuckarooLoggerInterface $logger, CartRepositoryInterface $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
        parent::__construct($logger);
    }

    /**
     * Update shipping address by DPD Pickup point
     *
     * @param Order $order
     * @param OrderAddressInterface $shippingAddress
     * @return Order
     * @throws NoSuchEntityException
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order
    {
        if ($order->getShippingMethod() == 'dpdpickup_dpdpickup') {
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $requestData = [];
            $this->updateShippingAddressByDpdParcel($quote, $requestData);
        }

        return $order;
    }

    /**
     * Set shipping address fields by DPD Parcel
     *
     * @param CartInterface $quote
     * @param array $requestData
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function updateShippingAddressByDpdParcel(CartInterface $quote, array &$requestData)
    {
        $fullStreet = $quote->getDpdStreet();
        $postalCode = $quote->getDpdZipcode();
        $city = $quote->getDpdCity();
        $country = $quote->getDpdCountry();

        if (!$fullStreet && $quote->getDpdParcelshopId()) {
            $this->logger->addDebug(sprintf(
                '[CREATE_ORDER] | [Gateway] | [%s:%s] - Set shipping address fields by DPD Parcel | cookie: %s',
                __METHOD__,
                __LINE__,
                var_export($_COOKIE, true)
            ));
            $fullStreet = $_COOKIE['dpd-selected-parcelshop-street'] ?? '';
            $postalCode = $_COOKIE['dpd-selected-parcelshop-zipcode'] ?? '';
            $city = $_COOKIE['dpd-selected-parcelshop-city'] ?? '';
            $country = $_COOKIE['dpd-selected-parcelshop-country'] ?? '';
        }

        $matches = false;
        if ($fullStreet && preg_match('/(.*)\s(.+)$/', $fullStreet, $matches)) {
            $street = $matches[1];
            $streetHouseNumber = $matches[2];

            $mapping = [
                ['Street', $street],
                ['PostalCode', $postalCode],
                ['City', $city],
                ['Country', $country],
                ['StreetNumber', $streetHouseNumber],
            ];

            $this->logger->addDebug(sprintf(
                '[CREATE_ORDER] | [Gateway] | [%s:%s] - Set shipping address fields by DPD Parcel | newAddress: %s',
                __METHOD__,
                __LINE__,
                var_export($mapping, true)
            ));

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
