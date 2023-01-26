<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\ErrorMapper;

use Magento\Framework\Config\DataInterface;
use Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapperInterface;

class ErrorMessageMapper implements ErrorMessageMapperInterface
{
    /**
     * @var DataInterface
     */
    private $messageMapping;

    /**
     * @param DataInterface $messageMapping
     */
    public function __construct(DataInterface $messageMapping)
    {
        $this->messageMapping = $messageMapping;
    }

    /**
     * @inheritdoc
     */
    public function getMessage(string $code)
    {
        if (strlen($code) > 4) {
            $message = $code;
        } else {
            $message = $this->messageMapping->get($code);
        }
        return $message ? __($message) : null;
    }
}
