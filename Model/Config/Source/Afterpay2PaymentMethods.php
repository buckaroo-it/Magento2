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

namespace Buckaroo\Magento2\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Afterpay2PaymentMethods implements OptionSourceInterface
{
    public const PAYMENT_METHOD_ACCEPTGIRO = 1;
    public const PAYMENT_METHOD_DIGIACCEPT = 2;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];

        // These are the payment methods available at Afterpay
        $options[] = ['value' => self::PAYMENT_METHOD_ACCEPTGIRO, 'label' => __('Acceptgiro')];
        $options[] = ['value' => self::PAYMENT_METHOD_DIGIACCEPT, 'label' => __('Digiaccept')];

        return $options;
    }
}
