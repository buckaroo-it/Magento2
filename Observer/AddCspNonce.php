<?php declare(strict_types=1);

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\Template;
use Buckaroo\Magento2\Factory\CspNonceProviderFactory;

class AddCspNonce implements ObserverInterface
{
    /**
     * @var \Magento\Csp\Helper\CspNonceProvider|\Buckaroo\Magento2\Helper\CustomCspNonceProvider|null
     */
    private $cspNonceProvider;

    public function __construct(
        CspNonceProviderFactory $cspNonceProviderFactory
    ) {
        $this->cspNonceProvider = $cspNonceProviderFactory->create();
    }

    public function execute(Observer $observer)
    {
        /** @var Template $block */
        $block = $observer->getEvent()->getBlock();
        if (false === $block instanceof Template) {
            return;
        }

        // Retrieve the block name
        $nameInLayout = $block->getNameInLayout();
        // Check if $nameInLayout is a non-empty string
        if (!is_string($nameInLayout) || strpos($nameInLayout, 'buckaroo_magento2') === false) {
            return;
        }

        if ($this->cspNonceProvider) {
            try {
                $nonce = $this->cspNonceProvider->generateNonce();
                $block->assign('cspNonce', $nonce);
            } catch (\Exception $e) {
            }
        }
    }
}
