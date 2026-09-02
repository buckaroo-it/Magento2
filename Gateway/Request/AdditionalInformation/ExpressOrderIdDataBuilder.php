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

namespace Buckaroo\Magento2\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class ExpressOrderIdDataBuilder extends AbstractDataBuilder
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $expressOrderId = $paymentDO->getPayment()->getAdditionalInformation('express_order_id');
        if ($expressOrderId !== null) {
            $paymentDO->getPayment()->setAdditionalInformation('skip_push', 1);
            return ['payPalOrderId' => $expressOrderId];
        }

        return [];
    }
}
