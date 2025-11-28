<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Ideal;

use Buckaroo\Magento2\Api\Data\QuoteCreateResponseInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Logging\Log;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteRepository;
use Buckaroo\Magento2\Model\Method\Ideal;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Buckaroo\Magento2\Api\IdealQuoteCreateInterface;
use Buckaroo\Magento2\Api\Data\QuoteCreateResponseInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class QuoteCreate implements IdealQuoteCreateInterface
{
    protected $responseFactory;
    protected $quoteBuilderInterfaceFactory;
    protected $customerSession;
    protected $checkoutSession;
    protected $quoteRepository;
    protected $customerRepository;
    protected $addressRepository;
    protected $shipmentEstimation;
    protected $logger;
    protected $quote;
    protected $storeManager;

    public function __construct(
        QuoteCreateResponseInterfaceFactory $responseFactory,
        QuoteBuilderInterfaceFactory $quoteBuilderInterfaceFactory,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository,
        CheckoutSession $checkoutSession,
        QuoteRepository $quoteRepository,
        ShipmentEstimationInterface $shipmentEstimation,
        Log $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->responseFactory = $responseFactory;
        $this->quoteBuilderInterfaceFactory = $quoteBuilderInterfaceFactory;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute quote creation and address handling
     *
     * @param  string                       $page
     * @param  string|null                  $form_data
     * @throws IdealException
     * @return QuoteCreateResponseInterface
     */
    public function execute(string $page, ?string $form_data = null)
    {
        try {
            if ($page === 'product' && is_string($form_data)) {
                $this->quote = $this->createQuote($form_data);
            } else {
                $this->quote = $this->checkoutSession->getQuote();
            }

            if ($this->customerSession->isLoggedIn()) {
                $customer = $this->customerRepository->getById($this->customerSession->getCustomerId());
                $defaultBillingAddress = $this->getAddress($customer->getDefaultBilling());
                $defaultShippingAddress = $this->getAddress($customer->getDefaultShipping());

                $this->setAddresses($defaultShippingAddress, $defaultBillingAddress, $customer);
            } else {
                $this->setDefaultShippingAddress();
            }

            $this->setPaymentMethod();
        } catch (\Throwable $th) {
            $this->logger->debug('Error during quote creation: ' . $th->getMessage());
            throw new IdealException("Failed to create quote");
        }

        $this->restoreStoreContext();
        $this->calculateQuoteTotals();
        return $this->responseFactory->create(["quote" => $this->quote]);
    }

    /**
     * Restore store context for the quote
     */
    protected function restoreStoreContext()
    {
        if ($this->quote && $this->quote->getStoreId()) {
            $this->storeManager->setCurrentStore($this->quote->getStoreId());
        } else {
            $this->logger->debug('Store ID not set on quote.');
        }
    }

    /**
     * Get a customer's address by address ID
     *
     * @param  int|null              $addressId
     * @throws LocalizedException
     * @return AddressInterface|null
     */
    protected function getAddress($addressId)
    {
        if (!$addressId) {
            return null;
        }

        try {
            return $this->addressRepository->getById($addressId);
        } catch (NoSuchEntityException $e) {
            $this->logger->debug('Address not found', ['address_id' => $addressId]);
            return null;
        }
    }

    /**
     * Set the shipping and billing addresses for the quote
     *
     * @param  AddressInterface|null $shippingAddressData
     * @param  AddressInterface|null $billingAddressData
     * @param  CustomerInterface     $customer
     * @throws InputException
     */
    protected function setAddresses($shippingAddressData, $billingAddressData, $customer)
    {
        $shippingAddress = $this->quote->getShippingAddress();
        $billingAddress = $this->quote->getBillingAddress();

        if ($shippingAddressData) {
            $shippingAddress->setFirstname($customer->getFirstname());
            $shippingAddress->setLastname($customer->getLastname());
            $shippingAddress->setEmail($customer->getEmail());
            $shippingAddress->setStreet($shippingAddressData->getStreet());
            $shippingAddress->setCity($shippingAddressData->getCity());
            $shippingAddress->setPostcode($shippingAddressData->getPostcode());
            $shippingAddress->setCountryId($shippingAddressData->getCountryId());
            $shippingAddress->setTelephone($shippingAddressData->getTelephone());
        } else {
            $this->setPlaceholderAddress($shippingAddress);
        }

        if ($billingAddressData) {
            $billingAddress->setFirstname($customer->getFirstname());
            $billingAddress->setLastname($customer->getLastname());
            $billingAddress->setEmail($customer->getEmail());
            $billingAddress->setStreet($billingAddressData->getStreet());
            $billingAddress->setCity($billingAddressData->getCity());
            $billingAddress->setPostcode($billingAddressData->getPostcode());
            $billingAddress->setCountryId($billingAddressData->getCountryId());
            $billingAddress->setTelephone($billingAddressData->getTelephone());
        } else {
            $this->setPlaceholderAddress($billingAddress);
        }

        $this->quote->setShippingAddress($shippingAddress);
        $this->quote->setBillingAddress($billingAddress);
        $this->quoteRepository->save($this->quote);

        $this->addFirstShippingMethod($shippingAddress);
    }

    /**
     * Set default shipping and billing addresses for a guest
     *
     * @throws InputException
     */
    protected function setDefaultShippingAddress()
    {
        $shippingAddress = $this->quote->getShippingAddress();
        $billingAddress = $this->quote->getBillingAddress();

        $this->setPlaceholderAddress($shippingAddress);
        $this->setPlaceholderAddress($billingAddress);

        $this->quote->setShippingAddress($shippingAddress);
        $this->quote->setBillingAddress($billingAddress);
        $this->quoteRepository->save($this->quote);

        $this->addFirstShippingMethod($shippingAddress);
    }

    /**
     * Set placeholder values for the address if no customer information is available
     *
     * @param Address $address
     */
    protected function setPlaceholderAddress(Address $address)
    {
        $address->setFirstname('Guest');
        $address->setLastname('User');
        $address->setEmail('guest@example.com');
        $address->setStreet(['123 Placeholder St']);
        $address->setCity('Placeholder City');
        $address->setPostcode('00000');
        $address->setCountryId('NL');
        $address->setTelephone('0000000000');
    }

    /**
     * Calculate quote totals and set store id and email if needed
     */
    protected function calculateQuoteTotals()
    {
        $this->restoreStoreContext();

        if ($this->quote->getCustomerEmail() === null) {
            $this->quote->setCustomerEmail('no-reply@example.com');
        }

        $this->quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->quoteRepository->save($this->quote);
    }

    /**
     * Add the first found shipping method to the shipping address
     *
     * @param  Address        $address
     * @throws InputException
     */
    protected function addFirstShippingMethod(Address $address)
    {
        if (empty($address->getShippingMethod())) {
            $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress(
                $this->quote->getId(),
                $this->quote->getShippingAddress()
            );

            if (count($shippingMethods)) {
                $shippingMethod = array_shift($shippingMethods);
                $address->setShippingMethod($shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode());
            }
        }
        $address->setCollectShippingRates(true);
        $address->collectShippingRates();
    }

    /**
     * Set the payment method on the quote
     *
     * @return Quote
     */
    protected function setPaymentMethod()
    {
        $payment = $this->quote->getPayment();
        $payment->setMethod(Ideal::PAYMENT_METHOD_CODE);
        $this->quote->setPayment($payment);
    }

    /**
     * Create a new quote if on the product page
     *
     * @param  string         $form_data
     * @throws IdealException
     * @return Quote
     */
    protected function createQuote(string $form_data)
    {
        try {
            $quoteBuilder = $this->quoteBuilderInterfaceFactory->create();
            $quoteBuilder->setFormData($form_data);
            return $quoteBuilder->build();
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__ . ' ' . $th->getMessage());
            throw new IdealException("Failed to create quote");
        }
    }
}
