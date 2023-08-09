<?php

namespace Buckaroo\Magento2\Controller\Redirect;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Model\Service\Order as OrderService;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\ResourceModel\CustomerFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as Http;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;

class IdinProcess extends Process
{
    /**
     * @var Customer
     */
    protected $customerModel;

    /**
     * @param Context $context
     * @param Order $order
     * @param Quote $quote
     * @param TransactionInterface $transaction
     * @param Log $logger
     * @param AccountConfig $accountConfig
     * @param OrderRequestService $orderRequestService
     * @param OrderStatusFactory $orderStatusFactory
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param Customer $customerModel
     * @param CustomerFactory $customerFactory
     * @param OrderService $orderService
     * @param ManagerInterface $eventManager
     * @param Recreate $quoteRecreate
     * @param RequestPushFactory $requestPushFactory
     * @throws Exception
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Order $order,
        Quote $quote,
        Log $logger,
        AccountConfig $accountConfig,
        OrderRequestService $orderRequestService,
        OrderStatusFactory $orderStatusFactory,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        Customer $customerModel,
        CustomerFactory $customerFactory,
        OrderService $orderService,
        ManagerInterface $eventManager,
        Recreate $quoteRecreate,
        RequestPushFactory $requestPushFactory
    ) {
        parent::__construct($context);
        $this->order = $order;
        $this->quote = $quote;
        $this->logger = $logger;
        $this->orderRequestService = $orderRequestService;
        $this->orderStatusFactory = $orderStatusFactory;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->customerModel = $customerModel;
        $this->customerResourceFactory = $customerFactory;
        $this->accountConfig = $accountConfig;
        $this->orderService = $orderService;
        $this->eventManager = $eventManager;
        $this->quoteRecreate = $quoteRecreate;

        // @codingStandardsIgnoreStart
        if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $request = $this->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
        $this->redirectRequest = $requestPushFactory->create();
        // @codingStandardsIgnoreEnd
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     * @throws \Buckaroo\Magento2\Exception
     */
    public function execute()
    {
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
    private function setCustomerIDIN()
    {
        if (!empty($this->redirectRequest->getServiceIdinConsumerbin())
            && !empty($this->redirectRequest->getServiceIdinIseighteenorolder())
            && $this->redirectRequest->getServiceIdinIseighteenorolder() == 'True'
        ) {
            $this->checkoutSession->setCustomerIDIN($this->redirectRequest->getServiceIdinConsumerbin());
            $this->checkoutSession->setCustomerIDINIsEighteenOrOlder(true);
            if (!empty($this->redirectRequest->getAdditionalInformation('idin_cid'))) {
                $customerNew = $this->customerModel->load((int)$this->redirectRequest->getAdditionalInformation('idin_cid'));
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
}