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
use Buckaroo\Magento2\Model\Ideal\QuoteBuilderInterfaceFactory;
use Buckaroo\Magento2\Api\Data\QuoteCreateResponseInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

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

    public function __construct(
        QuoteCreateResponseInterfaceFactory $responseFactory,
        QuoteBuilderInterfaceFactory $quoteBuilderInterfaceFactory,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository,
        CheckoutSession $checkoutSession,
        QuoteRepository $quoteRepository,
        ShipmentEstimationInterface $shipmentEstimation,
        Log $logger
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
    }

    /**
     * Execute quote creation and address handling
     *
     * @param string $page
     * @param string|null $form_data
     * @return QuoteCreateResponseInterface
     * @throws IdealException
     */
    public function execute(string $page, string $form_data = null)
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
            throw new IdealException("Failed to create quote");
        }

        $this->calculateQuoteTotals();
        return $this->responseFactory->create(["quote" => $this->quote]);
    }

    /**
     * Get a customer's address by address ID
     *
     * @param int|null $addressId
     * @return \Magento\Customer\Api\Data\AddressInterface|null
     * @throws LocalizedException
     */
    protected function getAddress($addressId)
    {
        if (!$addressId) {
            return null;
        }

        try {
            return $this->addressRepository->getById($addressId);
        } catch (NoSuchEntityException $e) {
            $this->logger->error('Address not found', ['address_id' => $addressId]);
            return null;
        }
    }

    /**
     * Set the shipping and billing addresses for the quote
     *
     * @param \Magento\Customer\Api\Data\AddressInterface|null $shippingAddressData
     * @param \Magento\Customer\Api\Data\AddressInterface|null $billingAddressData
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @return void
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
     * @return void
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
     * @param \Magento\Quote\Model\Quote\Address $address
     * @return void
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
     *
     * @return void
     */
    protected function calculateQuoteTotals()
    {
        $this->quote->setStoreId($this->quote->getStore()->getId());

        if ($this->quote->getCustomerEmail() === null) {
            $this->quote->setCustomerEmail('no-reply@example.com');
        }

        $this->quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->quoteRepository->save($this->quote);
    }

    /**
     * Add the first found shipping method to the shipping address
     *
     * @param Address $address
     * @return void
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
     * @param string $form_data
     * @return Quote
     * @throws IdealException
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

    /**
     * Clear the quote by removing all items and deactivating it
     *
     * @throws IdealException
     */
    public function clearQuote()
    {
        try {
            // Retrieve the current quote from the session
            $quote = $this->checkoutSession->getQuote();

            // Check if the quote exists and has an ID
            if ($quote && $quote->getId()) {
                // Remove all items from the quote
                $quote->removeAllItems();
                // Deactivate the quote
                $quote->setIsActive(false);
                // Save the modified quote
                $this->quoteRepository->save($quote);
            }
        } catch (\Exception $e) {
            // Log the error and rethrow it as a LocalizedException
            $this->logger->addError('Error clearing quote: ' . $e->getMessage());
            throw new IdealException('Unable to clear the quote.');
        }
    }
}
