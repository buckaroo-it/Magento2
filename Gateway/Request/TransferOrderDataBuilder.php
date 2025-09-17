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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Magento\Payment\Gateway\Request\BuilderInterface;

class TransferOrderDataBuilder implements BuilderInterface
{
    /**
     * @var Transfer
     */
    protected Transfer $transferConfig;

    /**
     * @param Transfer $transferConfig
     */
    public function __construct(Transfer $transferConfig)
    {
        $this->transferConfig = $transferConfig;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();
        return [
            'dateDue'  => $this->transferConfig->getDueDateFormated($order->getStore()),
            'sendMail' => $this->transferConfig->hasOrderEmail($order->getStore()),
        ];
    }
}
