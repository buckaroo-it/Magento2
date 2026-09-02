<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\ErrorMapper;

use Magento\Framework\Config\DataInterface;
use Magento\Framework\Phrase;
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
    public function getMessage(string $code): ?Phrase
    {
        if (strlen($code) > 4) {
            $message = $code;
        } else {
            $message = $this->messageMapping->get($code);
        }
        return $message ? __($message) : null;
    }
}
