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

namespace Buckaroo\Magento2\Gateway\Request\Capayable;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Model\ConfigProvider\Method\CapayableIn3;
use Magento\Payment\Gateway\Request\BuilderInterface;

class In3V3DataBuilder implements BuilderInterface
{
    /**
     * @var CapayableIn3
     */
    private CapayableIn3 $capayableIn3Config;

    /**
     * @param CapayableIn3 $capayableIn3Config
     */
    public function __construct(
        CapayableIn3 $capayableIn3Config,
    ) {
        $this->capayableIn3Config = $capayableIn3Config;
    }
    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $data = [];

        if ($this->capayableIn3Config->isV3()) {
            $payment->setAdditionalInformation("buckaroo_in3_v3", true);
            $data['payment_method'] = 'in3';
        }

        return $data;
    }
}
