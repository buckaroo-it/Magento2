<?php declare(strict_types=1);

namespace Buckaroo\Magento2\Observer;

use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\Template;

class AddCspNonce implements ObserverInterface
{
    private CspNonceProvider $cspNonceProvider;

    public function __construct(
        CspNonceProvider $cspNonceProvider
    ) {
        $this->cspNonceProvider = $cspNonceProvider;
    }

    public function execute(Observer $observer)
    {
        /** @var Template $block */
        $block = $observer->getEvent()->getBlock();
        if (false === $block instanceof Template) {
            return;
        }

        if (false === strstr($block->getNameInLayout(), 'buckaroo_magento2')) {
            return;
        }

        $block->assign('cspNonce', $this->cspNonceProvider->generateNonce());
    }
}
