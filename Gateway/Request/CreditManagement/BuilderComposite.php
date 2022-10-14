<?php

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Magento\Framework\ObjectManager\TMapFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Buckaroo\Magento2\Gateway\Request\BuckarooBuilderComposite;

class BuilderComposite extends BuckarooBuilderComposite
{
   
    public const KEY = 'buckaroo_credit_management';


    /** @var Factory */
    private $configProvider;

     /**
     * @param TMapFactory $tmapFactory
     * @param array $builders
     */
    public function __construct(
        TMapFactory $tmapFactory,
        Factory $configProvider,
        array       $builders = []
    )
    {
        parent::__construct($tmapFactory, $builders);
        $this->configProvider = $configProvider;
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
        
        if ($this->isCreditManagmentActive($buildSubject)) {
            foreach ($this->builders as $builder) {
                // @TODO implement exceptions catching
                $result = $this->merge($result, [self::KEY => $builder->build($buildSubject)]);
            }
        }

        return $result;
    }
    public function isCreditManagmentActive(array $buildSubject)
    {

        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        return $this->configProvider->get(
            $buildSubject['payment']->getPayment()->getCode()
        )
        ->getActiveStatusCm3() == true;
    }
   
}

