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
     * @var array
     */
    private $buildersArray;

    /**
     * @var TMapFactory
     */
    private $tmapFactory;

    /**
     * @param TMapFactory        $tmapFactory
     * @param DataBuilderService $dataBuilderService
     * @param array              $builders
     * @param bool               $usingId
     */
    public function __construct(
        TMapFactory $tmapFactory,
        DataBuilderService $dataBuilderService,
        array $builders = [],
        bool $usingId = false
    ) {
        $this->tmapFactory = $tmapFactory;
        $this->buildersArray = $builders;
        $this->usingId = $usingId;
        $this->dataBuilderService = $dataBuilderService;
    }

    /**
     * Builds ENV request
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $this->dataBuilderService = new DataBuilderService();

        $result = [];
        if ($this->usingId) {
            foreach ($this->getBuilders() as $key => $builder) {
                $result = $this->addData($result, [$key => $builder->build($buildSubject)]);
            }
        } else {
            foreach ($this->getBuilders() as $builder) {
                $result = $this->addData($result, $builder->build($buildSubject));
            }
        }

        return $result;
    }

    /**
     * Merge function for builders
     *
     * @param  array $result
     * @param  array $builder
     * @return array
     */
    protected function merge(array $result, array $builder): array
    {
        return array_replace_recursive($result, $builder);
    }

    /**
     * Return builders
     *
     * @return BuilderInterface[]
     */
    private function getBuilders()
    {
        if ($this->builders === null) {
            $this->builders = $this->tmapFactory->create(
                [
                    'array' => $this->buildersArray,
                    'type'  => BuilderInterface::class
                ]
            );
        }

        return $this->builders;
    }

    /**
     * Add Data to the Request
     *
     * @param  array $result
     * @param  array $data
     * @return array
     */
    public function addData(array $result, array $data): array
    {
        $result = $this->merge($result, $data);
        $this->dataBuilderService->addData($data);
        return $result;
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
