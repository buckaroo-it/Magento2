<?php

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Magento\Framework\ObjectManager\TMap;
use Magento\Framework\ObjectManager\TMapFactory;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Buckaroo\Magento2\Gateway\Response\CreditManagementOrderHandler;

class BuilderComposite implements BuilderInterface
{
    public const TYPE_ORDER = 'buckaroo_credit_management';
    public const TYPE_REFUND = 'buckaroo_credit_management_refund';
    public const TYPE_VOID = 'buckaroo_credit_management_void';

    protected $type = self::TYPE_ORDER;

    /**
     * @var BuilderInterface[] | TMap
     */
    private $builders;

    /** @var Factory */
    private $configProvider;

    /**
     * @param TMapFactory $tmapFactory
     * @param array $builders
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
                'type' => BuilderInterface::class
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
     */
    public function build(array $buildSubject)
    {
        $result = [];

        if ($this->isCreditManagementActive($buildSubject) || $this->hasCreditManagementTransaction($buildSubject)) {
            foreach ($this->builders as $builder) {
                // @TODO implement exceptions catching
                $result = $this->merge($result, [$this->type => $builder->build($buildSubject)]);
            }
        }

        return $result;
    }
    /**
     * Merge function for builders
     *
     * @param array $result
     * @param array $builder
     * @return array
     */
    protected function merge(array $result, array $builder)
    {
        return array_replace_recursive($result, $builder);
    }

    protected function isCreditManagementActive(array $buildSubject)
    {
        $this->validateBuildSubject($buildSubject);


        return $this->configProvider->get(
            $buildSubject['payment']
                ->getPayment()
                ->getMethod()
        )->getActiveStatusCm3() == true;
    }

    protected function hasCreditManagementTransaction(array $buildSubject)
    {
        $this->validateBuildSubject($buildSubject);

        $buildSubject['payment']
            ->getPayment()
            ->getAdditionalInformation(CreditManagementOrderHandler::INVOICE_KEY) != null;
    }

    private function validateBuildSubject(array $buildSubject)
    {
        if (
            !isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }
    }
}
