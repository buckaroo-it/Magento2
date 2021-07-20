<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace Buckaroo\Magento2\Cron;

use Buckaroo\Magento2\Model\SecondChanceRepository as SecondChanceRepository;
use Magento\Store\Api\StoreRepositoryInterface as StoreRepositoryInterface;

class SecondChancePrune
{
    /**
     * @param SecondChanceRepository       $secondChanceRepository
     */
    protected $secondChanceRepository;
    
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    public function __construct(
        SecondChanceRepository $secondChanceRepository,
        StoreRepositoryInterface $storeRepository
    ) {
        $this->secondChanceRepository = $secondChanceRepository;
        $this->storeRepository = $storeRepository;
    }

    public function execute()
    {
        $stores = $this->storeRepository->getList();
        foreach ($stores as $store) {
            $this->secondChanceRepository->deleteOlderRecords($store);
        }

        return $this;
    }
}
