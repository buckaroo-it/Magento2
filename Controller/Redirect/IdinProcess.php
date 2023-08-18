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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
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
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\Quote;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class IdinProcess extends Process
{
    /**
     * @var CustomerFactory
     */
    private CustomerFactory $customerResourceFactory;

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
     * @param CustomerFactory $customerFactory
     *
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
        CustomerFactory $customerFactory
    ) {
        parent::__construct($context, $logger, $quote, $accountConfig, $orderRequestService,
            $orderStatusFactory, $checkoutSession, $customerSession, $customerRepository,
            $orderService, $eventManager, $quoteRecreate, $requestPushFactory);

        $this->customerResourceFactory = $customerFactory;
    }

    /**
     * @return ResponseInterface|void
     * @throws \Buckaroo\Magento2\Exception
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
    }

    /**
     * Set consumer bin IDIN on customer
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
                    $this->logger->addError(__METHOD__ . ' | ' . $e->getMessage());
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
        $this->logger->addDebug('start redirectToCheckout');
        try {
            $this->checkoutSession->restoreQuote();

        } catch (\Exception $e) {
            $this->logger->addError('Could not restore the quote.');
        }

        return $this->handleProcessedResponse('checkout', ['_query' => ['bk_e' => 1]]);
    }
}