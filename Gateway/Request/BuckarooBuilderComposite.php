<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Service\DataBuilderService;
use Magento\Framework\ObjectManager\TMap;
use Magento\Framework\ObjectManager\TMapFactory;
use Magento\Payment\Gateway\Request\BuilderInterface;

class BuckarooBuilderComposite implements BuilderInterface
{
    /**
     * @var BuilderInterface[] | TMap
     */
    private $builders;

    /**
     * @var bool
     */
    private $usingId;

    /**
     * @var DataBuilderService
     */
    private $dataBuilderService;

    /**
     * @param TMapFactory $tmapFactory
     * @param DataBuilderService $dataBuilderService
     * @param array $builders
     * @param bool $usingId
     */
    public function __construct(
        TMapFactory        $tmapFactory,
        DataBuilderService $dataBuilderService,
        array              $builders = [],
        bool               $usingId = false
    ) {
        $this->builders = $tmapFactory->create(
            [
                'array' => $builders,
                'type' => BuilderInterface::class
            ]
        );
        $this->usingId = $usingId;
        $this->dataBuilderService = $dataBuilderService;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if ($this->usingId) {
            foreach ($this->builders as $key => $builder) {
                $this->dataBuilderService->addData([$key => $builder->build($buildSubject)]);
            }
        } else {
            foreach ($this->builders as $builder) {
                $this->dataBuilderService->addData($builder->build($buildSubject));
            }
        }

        return $this->dataBuilderService->getData();
    }

    /**
     * Add Data to the Request
     *
     * @param array $data
     * @return void
     */
    public function addData(array $data)
    {
        $this->dataBuilderService->addData($data);
    }

    /**
     * Get Data to the Request
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->dataBuilderService->getData();
    }
}
