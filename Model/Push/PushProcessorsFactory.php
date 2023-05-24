<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Api\PushProcessorInterface;
use Magento\Framework\ObjectManagerInterface;

class PushProcessorsFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var array
     */
    protected array $pushProcessors;

    /**
     * @var ?PushProcessorInterface
     */
    protected ?PushProcessorInterface $pushProcessor = null;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param array $pushProcessors
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        array $pushProcessors = []
    ) {
        $this->objectManager = $objectManager;
        $this->pushProcessors = $pushProcessors;
    }

    /**
     * Retrieve proper push processor for the specified transaction method.
     *
     * @param PushRequestInterface|null $pushRequest
     * @return ?PushProcessorInterface
     * @throws BuckarooException
     */
    public function get(?PushRequestInterface $pushRequest): ?PushProcessorInterface
    {
        if (!$this->pushProcessor instanceof PushProcessorInterface) {
            if (empty($this->pushProcessors)) {
                throw new \LogicException('Push processors is not set.');
            }

            $transactionMethod = $pushRequest->getTransactionMethod();

            $pushProcessorClass = $this->pushProcessors[$transactionMethod] ??
                $this->pushProcessors['default'] ?? '';

            if (empty($pushProcessorClass)) {
                throw new BuckarooException(
                    new Phrase(
                        'Unknown ConfigProvider type requested: %1.',
                        [$transactionMethod]
                    )
                );
            }
            $this->pushProcessor = $this->objectManager->get($pushProcessorClass);

        }
        return $this->pushProcessor;
    }
}