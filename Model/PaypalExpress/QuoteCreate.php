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

namespace Buckaroo\Magento2\Model\PaypalExpress;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Buckaroo\Magento2\Service\ExpressPayment\ProductValidationService;
use Magento\Framework\Exception\InputException;
use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Logging\Log;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Buckaroo\Magento2\Api\PaypalExpressQuoteCreateInterface;
use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Api\Data\PaypalExpress\QuoteCreateResponseInterfaceFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class QuoteCreate implements PaypalExpressQuoteCreateInterface
{

    /**
     * @var \Buckaroo\Magento2\Api\Data\PaypalExpress\QuoteCreateResponseInterfaceFactory
     */
    protected $responseFactory;

    /**
     * @var \Buckaroo\Magento2\Model\PaypalExpress\QuoteBuilderInterfaceFactory
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

    /**
     * @var ProductValidationService
     */
    protected $productValidationService;

    public function __construct(
        QuoteCreateResponseInterfaceFactory $responseFactory,
        QuoteBuilderInterfaceFactory $quoteBuilderInterfaceFactory,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CheckoutSession $checkoutSession,
        QuoteRepository $quoteRepository,
        ShipmentEstimationInterface $shipmentEstimation,
        Log $logger,
        ProductValidationService $productValidationService
    ) {
        $this->responseFactory = $responseFactory;
        $this->quoteBuilderInterfaceFactory = $quoteBuilderInterfaceFactory;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->logger = $logger;
        $this->productValidationService = $productValidationService;
    }

    /** @inheritDoc */
    public function execute(
        ShippingAddressRequestInterface $shipping_address,
        string $page,
        ?string $form_data = null
    ) {
        if ($page === 'product' && is_string($form_data)) {
            $this->quote = $this->createQuote($form_data);
        } else {
            $this->quote = $this->checkoutSession->getQuote();
        }

        try {
            $this->addAddressToQuote($shipping_address);
            $this->setPaymentMethod();
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__.$th->getMessage());
            throw new PaypalExpressException(__("Failed to add address quote"), 1, $th);
        }

        $this->calculateQuoteTotals();
        return $this->responseFactory->create(["quote" => $this->quote]);
    }

    /**
     * Calculate quote totals, set store id required for quote masking,
     * @return void
     */
    protected function calculateQuoteTotals()
    {
        $this->quote->setStoreId($this->quote->getStore()->getId());

        $this->quote
            ->setTotalsCollectedFlag(false)
            ->collectTotals();

        $this->quoteRepository->save($this->quote);
    }

    /**
     * Add address from PayPal express to quote
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
        $address->setRegion($shipping_address->getState());

        $this->quoteRepository->save($this->quote);
        $this->addFirstShippingMethod($address);
    }

    /**
     * Add the first found shipping method to the shipping address &
     * recalculate shipping totals
     *
     * @param Address $address
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
                $address->setShippingMethod($shippingMethod->getCarrierCode(). '_' .$shippingMethod->getMethodCode());
            }
        }
        $address->setCollectShippingRates(true);
        $address->collectShippingRates();
    }

    /**
     * Set paypal payment method on quote
     */
    protected function setPaymentMethod()
    {
        $payment = $this->quote->getPayment();
        $payment->setMethod(Paypal::CODE);
        $this->quote->setPayment($payment);
    }

    /**
     * Create quote if in product page
     *
     * @param string $form_data
     *
     * @return Quote
     * @throws PaypalExpressException
     */
    protected function createQuote(string $form_data)
    {
        try {
            // Parse form data to get product ID and options
            $data = [];
            parse_str($form_data, $data);

            $productId = $data['product'] ?? null;
            $qty = $data['qty'] ?? 1;

            if (!$productId) {
                throw new PaypalExpressException(__("Product ID is required"), 1);
            }

            // Validate product before creating quote
            $options = $data['super_attribute'] ?? [];
            $validationResult = $this->productValidationService->validateProduct($productId, $options, $qty);

            if (!$validationResult['is_valid']) {
                $errors = $validationResult['errors'];
                throw new PaypalExpressException(__(implode(', ', $errors)), 1);
            }

            $quoteBuilder = $this->quoteBuilderInterfaceFactory->create();
            $quoteBuilder->setFormData($form_data);
            return $quoteBuilder->build();
        } catch (PaypalExpressException $e) {
            $this->logger->addDebug(__METHOD__ . $e->getMessage());
            throw $e;
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__ . $th->getMessage());
            throw new PaypalExpressException(__("Failed to create quote"), 1, $th);
        }
    }
}
