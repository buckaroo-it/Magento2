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
namespace Buckaroo\Magento2\Observer;

class ProcessHandleFailed implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Buckaroo\Magento2\Model\SecondChanceRepository
     */
    protected $secondChanceRepository;

    protected $logging;

    protected $configProviderAccount;

    protected $quoteRecreate;

    protected $customerSession;

    /**
     * @param \Magento\Checkout\Model\Cart          $cart
     */
    public function __construct(
        \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository,
        \Buckaroo\Magento2\Logging\Log $logging,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $configProviderAccount,
        \Buckaroo\Magento2\Service\Sales\Quote\Recreate $quoteRecreate,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->secondChanceRepository = $secondChanceRepository;
        $this->logging                = $logging;
        $this->configProviderAccount  = $configProviderAccount;
        $this->quoteRecreate          = $quoteRecreate;
        $this->customerSession        = $customerSession;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

        /* @var $order \Magento\Sales\Model\Order */
        $order = $observer->getEvent()->getOrder();

        if ($order && $this->configProviderAccount->getSecondChance($order->getStore())) {
            $this->quoteRecreate->duplicate($order);
            $this->customerSession->setSkipHandleFailedRecreate(1);
        }

    }
}
