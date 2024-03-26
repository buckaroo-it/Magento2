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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\Config\Source\TinkaActiveService;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\MethodInterface;

class TinkaDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $paymentMethod = $paymentDO->getPayment()->getMethodInstance();

        return [
            'paymentMethod'  => $this->getActiveService($paymentMethod),
            'deliveryMethod' => 'ShippingPartner'
        ];
    }

    /**
     * Get Active service
     *
     * @param MethodInterface $paymentMethod
     * @return string
     */
    public function getActiveService(MethodInterface $paymentMethod): string
    {
        $activeService = $paymentMethod->getConfigData('activeservice', $paymentMethod->getStore());

        if (!in_array($activeService, TinkaActiveService::LIST)) {
            return TinkaActiveService::CREDIT;
        }
        return $activeService;
    }
}
