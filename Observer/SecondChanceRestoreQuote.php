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

class SecondChanceRestoreQuote implements \Magento\Framework\Event\ObserverInterface
{
    protected $customerSession;

    protected $quoteRecreate;

    protected $logging;
    /**
     *
     * @param \Magento\Customer\Model\Session $customerSession,
     * @param \Buckaroo\Magento2\Service\Sales\Quote\Recreate $quoteRecreate
     * @param \Buckaroo\Magento2\Logging\Log $logging,
     *
     */
    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Buckaroo\Magento2\Service\Sales\Quote\Recreate $quoteRecreate,
        \Buckaroo\Magento2\Logging\Log $logging
    ) {
        $this->customerSession        = $customerSession;
        $this->quoteRecreate          = $quoteRecreate;
        $this->logging                = $logging;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($quoteId = $this->customerSession->getSecondChanceRecreate()) {
            try {
                $this->quoteRecreate->recreateById($quoteId);
                $this->customerSession->setSecondChanceRecreate(false);
            } catch (\Exception $e) {
                $this->logging->addError('Could not recreateById SC:' . $quoteId);
            }
        }
    }
}
