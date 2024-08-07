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

use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Logging\Log;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteRepository;
use Buckaroo\Magento2\Model\Method\Ideal;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Buckaroo\Magento2\Api\IdealQuoteCreateInterface;
use Buckaroo\Magento2\Model\Ideal\QuoteBuilderInterfaceFactory;
use Buckaroo\Magento2\Api\Data\Ideal\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Api\Data\QuoteCreateResponseInterfaceFactory;
use function Buckaroo\Magento2\Model\Ideal\__;

class QuoteCreate implements IdealQuoteCreateInterface
{

    /**
     * @var \Buckaroo\Magento2\Api\Data\QuoteCreateResponseInterfaceFactory
     */
    protected $responseFactory;

    /**
     * @var \Buckaroo\Magento2\Model\Ideal\QuoteBuilderInterfaceFactory
     */
    protected $quoteBuilderInterfaceFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Quote\Api\ShipmentEstimationInterface
     */
    protected $shipmentEstimation;

    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logger;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote;

    public function __construct(
        QuoteCreateResponseInterfaceFactory $responseFactory,
        QuoteBuilderInterfaceFactory $quoteBuilderInterfaceFactory,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CheckoutSession $checkoutSession,
        QuoteRepository $quoteRepository,
        ShipmentEstimationInterface $shipmentEstimation,
        Log $logger
    ) {
        $this->responseFactory = $responseFactory;
        $this->quoteBuilderInterfaceFactory = $quoteBuilderInterfaceFactory;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->logger = $logger;
    }
    /** @inheritDoc */
    public function execute(
        ShippingAddressRequestInterface $shipping_address,
        string $page,
        string $form_data = null
    ) {
        if ($page === 'product' && is_string($form_data)) {
            $this->quote = $this->createQuote($form_data);
        } else {
            $this->quote = $this->checkoutSession->getQuote();
        }

        try {
            $this->logger->addDebug('Adding address to quote', ['address' => $shipping_address]);
            $this->addAddressToQuote($shipping_address);
            $this->setPaymentMethod();
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__ . ' ' . $th->getMessage(), ['trace' => $th->getTraceAsString()]);
            throw new IdealException(__("Failed to add address to quote"), 1, $th);
        }

        $this->calculateQuoteTotals();
        return $this->responseFactory->create(["quote" => $this->quote]);
    }

    /**
     * Calculate quote totals, set store id required for quote masking,
     * set customer email required for order validation
     * @return void
     */
    protected function calculateQuoteTotals()
    {
        $this->quote->setStoreId($this->quote->getStore()->getId());   
        
        if ($this->quote->getCustomerEmail() === null) {
            $this->quote->setCustomerEmail('no-reply@example.com');
        }
        $this->quote
            ->setTotalsCollectedFlag(false)
            ->collectTotals();

        $this->quoteRepository->save($this->quote);
    }
    /**
     * Add address from Ideal Fast Checkout to quote
     *
     * @param ShippingAddressRequestInterface $shipping_address
     */
    protected function addAddressToQuote(ShippingAddressRequestInterface $shipping_address)
    {
        if ($this->customerSession->isLoggedIn()) {
            $this->quote->assignCustomerWithAddressChange(
                $this->customerRepository->getById($this->customerSession->getCustomerId())
            );
        }

        $address = $this->quote->getShippingAddress();

        $address->setCountryId($shipping_address->getCountryCode());
        $address->setPostcode($shipping_address->getPostalCode());
        $address->setCity($shipping_address->getCity());
        $address->setTelephone($shipping_address->getTelephone());
        $address->setFirstname($shipping_address->getFirstname());
        $address->setLastname($shipping_address->getLastname());
        $address->setEmail($shipping_address->getEmail());
        $address->setStreet($shipping_address->getStreet());

        $this->maybeFillAnyMissingAddressFields($shipping_address);

        $this->quoteRepository->save($this->quote);
        $this->addFirstShippingMethod($address);
    }
    
    /**
     * Add first found shipping method to the shipping address &
     * recalculate shipping totals
     *
     * @param Address $address
     *
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
                $address->setShippingMethod($shippingMethod->getCarrierCode(). '_' .$shippingMethod->getMethodCode());
            }
        }
        $address->setCollectShippingRates(true);
        $address->collectShippingRates();
    }

    /**
     * Fill any fields missing from the addresses
     *
     * @param ShippingAddressRequestInterface $shipping_address
     *
     * @return void
     */
    protected function maybeFillAnyMissingAddressFields(ShippingAddressRequestInterface $shipping_address)
    {
        $this->maybeFillShippingAddressFields();
        $this->maybeFillBillingAddressFields($shipping_address);
    }

    /**
     * If we didn't find any default shipping address we fill the empty fields 
     * required for quote validation
     *
     * @return void
     */
    protected function maybeFillShippingAddressFields()
    {
        $address = $this->quote->getShippingAddress();
        if ($address->getId() === null) {
            $address->setFirstname('unknown');
            $address->setLastname('unknown');
            $address->setEmail('no-reply@example.com');
            $address->setStreet('unknown');
        }
    }

    /**
     * If we didn't find any default billing address we fill the empty fields 
     * required for quote validation
     *
     * @param ShippingAddressRequestInterface $shipping_address
     *
     * @return void
     */
    protected function maybeFillBillingAddressFields(ShippingAddressRequestInterface $shipping_address)
    {
        $address = $this->quote->getBillingAddress();
        if ($address->getId() === null) {
            $address->setFirstname($shipping_address->getFirstname());
            $address->setLastname($shipping_address->getLastname());
            $address->setEmail($shipping_address->getEmail());
            $address->setStreet($shipping_address->getStreet());
            $address->setCountryId($shipping_address->getCountryCode());
            $address->setPostcode($shipping_address->getPostalCode());
            $address->setCity($shipping_address->getCity());
            $address->setTelephone($shipping_address->getTelephone());
        }
    }

    /**
     * Set Ideal Fast Checkout payment method on quote
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
     * Create quote if in product page
     *
     * @param string $form_data
     *
     * @return Quote
     */
    protected function createQuote(string $form_data)
    {
        try {
            $quoteBuilder = $this->quoteBuilderInterfaceFactory->create();
            $quoteBuilder->setFormData($form_data);
            return $quoteBuilder->build();
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__.$th->getMessage());
            throw new IdealException(__("Failed to create quote"), 1, $th);
        }
    }
}
