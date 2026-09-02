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

namespace Buckaroo\Magento2\Controller\Redirect;

use Buckaroo\Magento2\Service\SpamLimitService;
use Buckaroo\Magento2\Model\Method\LimitReachException;
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Model\Service\Order as OrderService;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class IdinProcess extends Process implements HttpPostActionInterface
{
    /**
     * @var CustomerFactory
     */
    private $customerResourceFactory;

    /**
     * @var SpamLimitService
     */
    protected $spamLimitService;

    /**
     * @param Context $context
     * @param BuckarooLoggerInterface $logger
     * @param Quote $quote
     * @param AccountConfig $accountConfig
     * @param OrderRequestService $orderRequestService
     * @param OrderStatusFactory $orderStatusFactory
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param OrderService $orderService
     * @param ManagerInterface $eventManager
     * @param Recreate $quoteRecreate
     * @param RequestPushFactory $requestPushFactory
     * @param LockManagerWrapper $lockManager
     * @param SpamLimitService $spamLimitService
     * @param CustomerFactory $customerFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CartRepositoryInterface $cartRepository
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        BuckarooLoggerInterface $logger,
        Quote $quote,
        AccountConfig $accountConfig,
        OrderRequestService $orderRequestService,
        OrderStatusFactory $orderStatusFactory,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        OrderService $orderService,
        ManagerInterface $eventManager,
        Recreate $quoteRecreate,
        RequestPushFactory $requestPushFactory,
        LockManagerWrapper $lockManager,
        SpamLimitService $spamLimitService,
        CustomerFactory $customerFactory,
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $cartRepository,
        OrderPaymentRepositoryInterface $paymentRepository
    ) {
        parent::__construct(
            $context,
            $logger,
            $quote,
            $accountConfig,
            $orderRequestService,
            $orderStatusFactory,
            $checkoutSession,
            $customerSession,
            $customerRepository,
            $orderService,
            $eventManager,
            $quoteRecreate,
            $requestPushFactory,
            $lockManager,
            $spamLimitService,
            $orderRepository,
            $cartRepository,
            $paymentRepository
        );

        $this->customerResourceFactory = $customerFactory;
    }

    /**
     * Process the iDIN redirect request and verify the customer.
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    public function execute(): ResponseInterface
    {
        // Initialize the order, quote, payment
        if ($this->redirectRequest->hasPostData('primary_service', 'IDIN')) {
            if ($this->setCustomerIDIN()) {
                $this->addSuccessMessage(__('Your iDIN verified succesfully!'));
            } else {
                $this->addErrorMessage(
                    __(
                        'Unfortunately iDIN not verified!'
                    )
                );
            }

            return $this->redirectToCheckout();
        }

        return $this->handleProcessedResponse('checkout');
    }

    /**
     * Set consumer bin IDIN on customer
     *
     * @throws \Exception
     *
     * @return bool
     */
    private function setCustomerIDIN(): bool
    {
        if (!empty($this->redirectRequest->getServiceIdinConsumerbin())
            && !empty($this->redirectRequest->getServiceIdinIseighteenorolder())
            && $this->redirectRequest->getServiceIdinIseighteenorolder() == 'True'
        ) {
            $this->checkoutSession->setCustomerIDIN($this->redirectRequest->getServiceIdinConsumerbin());
            $this->checkoutSession->setCustomerIDINIsEighteenOrOlder(true);
            $idinCid = $this->redirectRequest->getAdditionalInformation('idin_cid');
            if (!empty($idinCid)) {
                try {
                    /** @var Customer $customerNew */
                    $customerNew = $this->customerRepository->getById((int)$idinCid);
                } catch (\Exception $e) {
                    $this->addErrorMessage(__('Unfortunately customer was not find by IDIN id: "%1"!', $idinCid));
                    $this->logger->addError(sprintf(
                        '[REDIRECT - iDIN] | [Controller] | [%s:%s] - Customer was not find by IDIN id | [ERROR]: %s',
                        __METHOD__,
                        __LINE__,
                        $e->getMessage()
                    ));
                    return false;
                }
                $customerData = $customerNew->getDataModel();
                $customerData->setCustomAttribute('buckaroo_idin', $this->redirectRequest->getServiceIdinConsumerbin());
                $customerData->setCustomAttribute('buckaroo_idin_iseighteenorolder', 1);
                $customerNew->updateData($customerData);

                $customerResource = $this->customerResourceFactory->create();
                $customerResource->saveAttribute($customerNew, 'buckaroo_idin');
                $customerResource->saveAttribute($customerNew, 'buckaroo_idin_iseighteenorolder');
            }
            return true;
        }
        return false;
    }

    /**
     * Create redirect response
     *
     * @return ResponseInterface
     */
    protected function redirectToCheckout(): ResponseInterface
    {
        $this->logger->addDebug('[REDIRECT - iDIN] | [Controller] | ['.__METHOD__.'] - start redirectToCheckout');

        try {
            $this->checkoutSession->restoreQuote();
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[REDIRECT - iDIN] | [Controller] | [%s:%s] - Could not restore the quote | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
        }

        return $this->handleProcessedResponse('checkout', ['_query' => ['bk_e' => 1]]);
    }
}
