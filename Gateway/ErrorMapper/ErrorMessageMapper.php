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
    private DataInterface $messageMapping;

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
