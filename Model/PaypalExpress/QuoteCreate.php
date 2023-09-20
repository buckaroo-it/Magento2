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

use Buckaroo\Magento2\Model\Service\QuoteService;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Buckaroo\Magento2\Api\PaypalExpressQuoteCreateInterface;
use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Api\Data\PaypalExpress\QuoteCreateResponseInterfaceFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class QuoteCreate implements PaypalExpressQuoteCreateInterface
{
    /**
     * @var QuoteCreateResponseInterfaceFactory
     */
    protected $responseFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var QuoteService
     */
    private $quoteService;

    /**
     * @var Quote|CartInterface
     */
    private $quote;

    /**
     * @param QuoteCreateResponseInterfaceFactory $responseFactory
     * @param QuoteService $quoteService
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        QuoteCreateResponseInterfaceFactory $responseFactory,
        QuoteService $quoteService,
        BuckarooLoggerInterface $logger
    ) {
        $this->responseFactory = $responseFactory;
        $this->quoteService = $quoteService;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute(
        ShippingAddressRequestInterface $shippingAddress,
        string $page,
        $orderData = null
    ) {

        if ($page === 'product') {
            $this->validateData($orderData);
            $this->quote = $this->quoteService->createQuote($orderData);
        } else {
            $this->quote = $this->quoteService->getQuote();
        }

        try {
            $this->quoteService->addAddressToQuote($shippingAddress);
            $this->quoteService->addFirstShippingMethod();
            $this->quoteService->setPaymentMethod(Paypal::CODE);
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[CREATE_QUOTE - PayPal Express] | [Model] | [%s:%s] - Create Quote | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
            throw new PaypalExpressException(__("Failed to add address quote"), 1, $th);
        }

        $this->quoteService->calculateQuoteTotals();

        return $this->responseFactory->create(["quote" => $this->quoteService->getQuoteObject()]);
    }

    /**
     * Validate form data
     *
     * @param mixed $formData
     * @return void
     * @throws PaypalExpressException
     */
    protected function validateData($formData)
    {
        if (!is_array($formData)) {
            throw new PaypalExpressException(__("Invalid order data"), 1);
        }
    }
}
