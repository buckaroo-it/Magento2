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


    /**
     * @param \Magento\Checkout\Model\Cart          $cart
     */
    public function __construct(
        \Magento\Checkout\Model\Session\Proxy $checkoutSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\View\LayoutInterface $layout,
        \Magento\Checkout\Model\Cart $cart
    ) {
        $this->checkoutSession     = $checkoutSession;
        $this->quoteFactory        = $quoteFactory;
        $this->messageManager      = $messageManager;
        $this->layout              = $layout;
        $this->cart                = $cart;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $this->cart->truncate()->save();
        } catch (\Exception $exception) {
            $this->messageManager->addExceptionMessage($exception, __('We can\'t empty the shopping cart.'));
        }

        echo "<script>window.onload = function(){require(['Magento_Customer/js/customer-data'], function (customerData) {var sections = ['cart']; customerData.reload(sections, true); customerData.invalidate(sections); console.log('Reload shopping cart');});}</script>";
    }
}
