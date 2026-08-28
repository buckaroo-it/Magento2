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

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PushUrlDataBuilder implements BuilderInterface
{
    /**
     * @var PushUrlBuilder
     */
    protected $pushUrlBuilder;

    /**
     * TransactionBuilder constructor.
     *
     * @param PushUrlBuilder $pushUrlBuilder
     */
    public function __construct(
        PushUrlBuilder $pushUrlBuilder
    ) {
        $this->pushUrlBuilder = $pushUrlBuilder;
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function build(array $buildSubject): array
    {
        $pushUrl = $this->pushUrlBuilder->getPushUrl(
            SubjectReader::readPayment($buildSubject)->getOrder()->getStoreId()
        );

        return [
            'pushURL' => $pushUrl,
            'pushURLFailure' => $pushUrl
        ];
    }
}
