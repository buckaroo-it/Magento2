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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Logging\Log;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

class SuccessOrder implements ObserverInterface
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;
    /**
     * @var Cart
     */
    protected $cart;
    /**
     * @var Log
     */
    protected $logging;
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @param Session $checkoutSession
     * @param ManagerInterface $messageManager
     * @param Cart $cart
     * @param Log $logging
     */
    public function __construct(
        Session $checkoutSession,
        ManagerInterface $messageManager,
        Cart $cart,
        Log $logging
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->cart = $cart;
        $this->logging = $logging;
    }

    /**
     * Empty the shopping cart
     *
     * @param Observer $observer
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

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
