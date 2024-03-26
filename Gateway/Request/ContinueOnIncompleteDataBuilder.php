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
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * @inheritdoc
 */
class ContinueOnIncompleteDataBuilder implements BuilderInterface
{
    /**
     * @var string
     */
    private string $continueOnIncompleteField;

    /**
     * @param string $continueOnIncompleteField
     */
    public function __construct(string $continueOnIncompleteField = 'show_issuers')
    {
        $this->continueOnIncompleteField = $continueOnIncompleteField;
    }

    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $method = $paymentDO->getPayment()->getMethodInstance();
        if ($method->getConfigData($this->continueOnIncompleteField) === '0') {
            return ['continueOnIncomplete' => '1'];
        }

        return [];
    }
}
