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

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Response\CreditManagementOrderHandler;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Framework\ObjectManager\TMap;
use Magento\Framework\ObjectManager\TMapFactory;
use Magento\Payment\Gateway\Request\BuilderInterface;

class BuilderComposite implements BuilderInterface
{
    public const TYPE_ORDER = 'buckaroo_credit_management';
    public const TYPE_REFUND = 'buckaroo_credit_management_refund';
    public const TYPE_VOID = 'buckaroo_credit_management_void';

    /**
     * @var string
     */
    protected string $type = self::TYPE_ORDER;

    /**
     * @var BuilderInterface[] | TMap
     */
    private $builders;

    /**
     * @var Factory
     */
    private Factory $configProvider;

    /**
     * @param TMapFactory $tmapFactory
     * @param Factory $configProvider
     * @param array $builders
     * @param string $type
     */
    public function __construct(
        TMapFactory $tmapFactory,
        Factory $configProvider,
        array $builders = [],
        string $type = self::TYPE_ORDER
    ) {
        $this->builders = $tmapFactory->create(
            [
                'array' => $builders,
                'type'  => BuilderInterface::class
            ]
        );
        $this->configProvider = $configProvider;
        $this->type = $type;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     * @throws Exception
     */
    public function build(array $buildSubject): array
    {
        $result = [];

        if ($this->isCreditManagementActive($buildSubject)
            || $this->hasCreditManagementTransaction($buildSubject)) {
            foreach ($this->builders as $builder) {
                // @TODO implement exceptions catching
                $result = $this->merge($result, [$this->type => $builder->build($buildSubject)]);
            }
        }

        return $result;
    }

    /**
     * Check if credit management is active
     *
     * @param array $buildSubject
     * @return bool
     * @throws Exception
     */
    protected function isCreditManagementActive(array $buildSubject): bool
    {
        $payment = SubjectReader::readPayment($buildSubject)->getPayment();

        return $this->configProvider->get($payment->getMethod())->getActiveStatusCm3();
    }

    /**
     * Checks whether the payment has a credit management transaction associated with it.
     *
     * @param array $buildSubject
     * @return bool
     */
    protected function hasCreditManagementTransaction(array $buildSubject): bool
    {
        $payment = SubjectReader::readPayment($buildSubject)->getPayment();

        return $payment->getAdditionalInformation(CreditManagementOrderHandler::INVOICE_KEY) != null;
    }

    /**
     * Merge function for builders
     *
     * @param array $result
     * @param array $builder
     * @return array
     */
    protected function merge(array $result, array $builder): array
    {
        return array_replace_recursive($result, $builder);
    }
}
