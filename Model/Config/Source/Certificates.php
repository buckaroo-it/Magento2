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

namespace Buckaroo\Magento2\Model\Config\Source;

use Buckaroo\Magento2\Model\Certificate;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Buckaroo\Magento2\Api\CertificateRepositoryInterface;
use Magento\Framework\Data\OptionSourceInterface;

class Certificates implements OptionSourceInterface
{
    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var CertificateRepositoryInterface
     */
    private CertificateRepositoryInterface $certificateRepository;

    /**
     * @param SearchCriteriaBuilder          $searchCriteriaBuilder
     * @param CertificateRepositoryInterface $certificateRepository
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CertificateRepositoryInterface $certificateRepository
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->certificateRepository = $certificateRepository;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $certificateData = $this->getCertificateData();

        $options = [];

        if (count($certificateData) <= 0) {
            $options[] = [
                'value' => '',
                'label' => __('You have not yet uploaded any certificate files')
            ];

            return $options;
        }

        $options[] = ['value' => '', 'label' => __('No certificate selected')];

        /** @var Certificate $model */
        foreach ($certificateData as $model) {
            $options[] = [
                'value' => $model->getEntityId(),
                'label' => $model->getName() . ' (' . $model->getCreatedAt() . ')'
            ];
        }

        return $options;
    }

    /**
     * Get a list of all stored certificates
     *
     * @return array
     */
    protected function getCertificateData(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $list = $this->certificateRepository->getList($searchCriteria);

        if (!$list->getTotalCount()) {
            return [];
        }

        return $list->getItems();
    }
}
