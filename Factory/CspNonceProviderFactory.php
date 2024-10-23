<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Factory;

use Magento\Framework\ObjectManagerInterface;
use Magento\Csp\Helper\CspNonceProvider as MagentoCspNonceProvider;
use Buckaroo\Magento2\Helper\CustomCspNonceProvider;
use Psr\Log\LoggerInterface;

/**
 * Factory to provide the appropriate CspNonceProvider
 */
class CspNonceProviderFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        ObjectManagerInterface $objectManager,
        LoggerInterface $logger
    ) {
        $this->objectManager = $objectManager;
        $this->logger = $logger;
    }

    /**
     * Create an instance of CspNonceProvider
     *
     * @return MagentoCspNonceProvider|CustomCspNonceProvider|null
     */
    public function create()
    {
        // Attempt to use Magento's CspNonceProvider if it exists
        if (class_exists(MagentoCspNonceProvider::class)) {
            try {
                return $this->objectManager->get(MagentoCspNonceProvider::class);
            } catch (\Exception $e) {
                $this->logger->error('Failed to instantiate Magento CspNonceProvider: ' . $e->getMessage());
            }
        }

        // Fallback to custom CspNonceProvider
        if (class_exists(CustomCspNonceProvider::class)) {
            try {
                return $this->objectManager->get(CustomCspNonceProvider::class);
            } catch (\Exception $e) {
                $this->logger->error('Failed to instantiate Custom CspNonceProvider: ' . $e->getMessage());
            }
        }

        // If neither class is available, log a warning
        $this->logger->warning('No CspNonceProvider available.');
        return null;
    }
}
