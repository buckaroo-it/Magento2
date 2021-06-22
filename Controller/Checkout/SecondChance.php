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

namespace Buckaroo\Magento2\Controller\Checkout;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate as QuoteRecreate;
use Magento\Framework\App\ObjectManager;

class SecondChance extends \Magento\Framework\App\Action\Action
{
    /**
     * @var array
     */
    protected $response;

    /** @var QuoteRecreate */
    private $quoteRecreate;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curlClient;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /** @var CheckoutSession */
    protected $checkoutSession;

    protected $secondChanceFactory;

    protected $customerFactory;

    protected $sessionFactory;

    /**
     * @var \Magento\Sales\Model\OrderIncrementIdChecker
     */
    private $orderIncrementIdChecker;

    /**
     * @param \Magento\Framework\App\Action\Context               $context
     * @param \Buckaroo\Magento2\Helper\Data                      $helper
     * @param \Magento\Checkout\Model\Cart                        $cart
     * @param \Magento\Sales\Model\Order                          $order
     * @param \Magento\Quote\Model\Quote                          $quote
     * @param Log                                                 $logger
     * @param Account                                             $configProviderAccount
     * @param \Magento\Store\Model\StoreManagerInterface         $storeManager
     * @param CheckoutSession                                    $checkoutSession
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        QuoteRecreate $quoteRecreate,
        Log $logger,
        Account $configProviderAccount,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Buckaroo\Magento2\Model\SecondChanceFactory $secondChanceFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\SessionFactory $sessionFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Magento\Sales\Model\OrderIncrementIdChecker $orderIncrementIdChecker = null
    ) {
        parent::__construct($context);

        $this->logger                  = $logger;
        $this->quoteRecreate           = $quoteRecreate;
        $this->configProviderAccount   = $configProviderAccount;
        $this->storeManager            = $storeManager;
        $this->checkoutSession         = $checkoutSession;
        $this->orderFactory            = $orderFactory;
        $this->customerSession         = $customerSession;
        $this->sessionFactory          = $sessionFactory;
        $this->customerFactory         = $customerFactory;
        $this->secondChanceFactory     = $secondChanceFactory;
        $this->orderIncrementIdChecker = $orderIncrementIdChecker ?: ObjectManager::getInstance()
            ->get(\Magento\Sales\Model\OrderIncrementIdChecker::class);
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $this->response = $this->getRequest()->getParams();
        $mode           = $this->configProviderAccount->getActive();
        $storeId        = $this->storeManager->getStore()->getId();
        $data           = $this->response;
        if ($buckaroo_second_chance = $data['token']) {
            $secondChance = $this->secondChanceFactory->create();
            $collection   = $secondChance->getCollection()
                ->addFieldToFilter(
                    'token',
                    ['eq' => $buckaroo_second_chance]
                );
            foreach ($collection as $item) {
                $order = $this->orderFactory->create()->loadByIncrementId($item->getOrderId());
                if ($this->customerSession->isLoggedIn()) {
                    $this->customerSession->logout();
                }

                if ($customerId = $order->getCustomerId()) {
                    $customer       = $this->customerFactory->create()->load($customerId);
                    $sessionManager = $this->sessionFactory->create();
                    $sessionManager->setCustomerAsLoggedIn($customer);
                }

                $this->quoteRecreate->recreate($order);
                $this->setAvailableIncrementId($item->getOrderId(), $item);

            }
        }
        return $this->_redirect('checkout');
    }

    private function setAvailableIncrementId($orderId, $item)
    {
        for ($i = 1; $i < 100; $i++) {
            $newOrderId = $orderId . '-' . $i;
            if (!$this->orderIncrementIdChecker->isIncrementIdUsed($newOrderId)) {
                $this->checkoutSession->getQuote()->setReservedOrderId($newOrderId);
                $this->checkoutSession->getQuote()->save();
                $item->setLastOrderId($newOrderId);
                $item->save();
                return true;
            }
        }
    }
}
