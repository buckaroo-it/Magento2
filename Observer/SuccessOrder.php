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

class SuccessOrder implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    protected $quoteFactory;

    protected $messageManager;

    protected $layout;

    protected $cart;

    protected $logging;

    /**
     * @var \Buckaroo\Magento2\Model\SecondChanceRepository
     */
    protected $secondChanceRepository;

    protected $configProviderAccount;

    /**
     * @param \Magento\Checkout\Model\Cart          $cart
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\View\LayoutInterface $layout,
        \Magento\Checkout\Model\Cart $cart,
        \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository,
        \Buckaroo\Magento2\Logging\Log $logging,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $configProviderAccount
    ) {
        $this->checkoutSession        = $checkoutSession;
        $this->quoteFactory           = $quoteFactory;
        $this->messageManager         = $messageManager;
        $this->layout                 = $layout;
        $this->cart                   = $cart;
        $this->secondChanceRepository = $secondChanceRepository;
        $this->logging                = $logging;
        $this->configProviderAccount  = $configProviderAccount;
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
            try {
                $this->secondChanceRepository->deleteByOrderId($order->getIncrementId());
            } catch (\Exception $e) {
                $this->logging->addError('Could not find SC by order id:' . $order->getIncrementId());
            }
        }

        if ($this->checkoutSession->getMyParcelNLBuckarooData()) {
            $this->checkoutSession->setMyParcelNLBuckarooData(null);
        }

        try {
            $this->cart->truncate()->save();
        } catch (\Exception $exception) {
            $this->messageManager->addExceptionMessage($exception, __('We can\'t empty the shopping cart.'));
        }
    }
}
