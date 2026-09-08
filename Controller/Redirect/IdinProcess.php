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
use Magento\Customer\Model\CustomerRegistry;
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
     * @var CustomerRegistry
     */
    private $customerRegistry;

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
     * @param CustomerRegistry $customerRegistry
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
        OrderPaymentRepositoryInterface $paymentRepository,
        CustomerRegistry $customerRegistry
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
        $this->customerRegistry = $customerRegistry;
    }

    /**
     * Process the iDIN redirect request and verify the customer.
     *
     * The iDIN outcome decides whether an age restricted checkout may continue, so the request
     * must be proven to come from Buckaroo (signature) and to belong to the iDIN verification
     * this session started. Without both checks any anonymous POST can mark itself as verified.
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    public function execute(): ResponseInterface
    {
        if (!$this->isRequestAuthentic()) {
            $this->addErrorMessage(
                __(self::GENERAL_ERROR_MESSAGE) // phpcs:ignore Magento2.Translation.ConstantUsage
            );

            return $this->handleProcessedResponse('checkout');
        }

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
     * Verify the redirect request was issued by Buckaroo for this session
     *
     * @throws \Exception
     *
     * @return bool
     */
    private function isRequestAuthentic(): bool
    {
        if (count($this->redirectRequest->getData()) === 0) {
            $this->logger->addError(sprintf(
                '[REDIRECT - iDIN] | [Controller] | [%s:%s] - Empty iDIN redirect request',
                __METHOD__,
                __LINE__
            ));

            return false;
        }

        if (!$this->redirectRequest->validate()) {
            $this->logger->addError(sprintf(
                '[REDIRECT - iDIN] | [Controller] | [%s:%s] - Signature validation failed',
                __METHOD__,
                __LINE__
            ));

            return false;
        }

        return true;
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
        $consumerBin = $this->redirectRequest->getServiceIdinConsumerbin();

        if (empty($consumerBin)
            || empty($this->redirectRequest->getServiceIdinIseighteenorolder())
            || $this->redirectRequest->getServiceIdinIseighteenorolder() != 'True'
        ) {
            return false;
        }

        $customerId = $this->getVerifiedCustomerId();

        if ($customerId === false) {
            return false;
        }

        $this->checkoutSession->setCustomerIDIN($consumerBin);
        $this->checkoutSession->setCustomerIDINIsEighteenOrOlder(true);

        if ($customerId === null) {
            return true;
        }

        return $this->persistIdinOnCustomer($customerId, (string)$consumerBin);
    }

    /**
     * Resolve the customer the verification belongs to
     *
     * Returns null for a guest verification (session only) and false when the signed customer id
     * contradicts the logged in customer.
     *
     * @return int|null|false
     */
    private function getVerifiedCustomerId()
    {
        $sessionCustomerId = (int)$this->customerSession->getCustomerId();
        $requestCustomerId = (int)$this->redirectRequest->getAdditionalInformation('idin_cid');

        if ($requestCustomerId > 0 && $sessionCustomerId > 0 && $requestCustomerId !== $sessionCustomerId) {
            $this->logger->addError(sprintf(
                '[REDIRECT - iDIN] | [Controller] | [%s:%s] - iDIN customer id %s does not match session customer %s',
                __METHOD__,
                __LINE__,
                $requestCustomerId,
                $sessionCustomerId
            ));

            return false;
        }

        if ($sessionCustomerId > 0) {
            return $sessionCustomerId;
        }

        return $requestCustomerId > 0 ? $requestCustomerId : null;
    }

    /**
     * Store the iDIN result on the customer account
     *
     * @param int    $customerId
     * @param string $consumerBin
     *
     * @return bool
     */
    private function persistIdinOnCustomer(int $customerId, string $consumerBin): bool
    {
        try {
            /** @var Customer $customer */
            $customer = $this->customerRegistry->retrieve((string)$customerId);
            $customer->setData('buckaroo_idin', $consumerBin);
            $customer->setData('buckaroo_idin_iseighteenorolder', 1);

            $customerResource = $this->customerResourceFactory->create();
            $customerResource->saveAttribute($customer, 'buckaroo_idin');
            $customerResource->saveAttribute($customer, 'buckaroo_idin_iseighteenorolder');
        } catch (\Exception $e) {
            $this->addErrorMessage(__('Unfortunately customer was not find by IDIN id: "%1"!', $customerId));
            $this->logger->addError(sprintf(
                '[REDIRECT - iDIN] | [Controller] | [%s:%s] - Customer was not find by IDIN id | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));

            return false;
        }

        return true;
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
