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

class SecondChancePrune
{
    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Account
     */
    protected $accountConfig;

    /**
     * @var Log $logging
     */
    public $logging;

    /**
     * @var \Buckaroo\Magento2\Model\SecondChanceRepository
     */
    protected $secondChanceRepository;

    private $resourceSecondChance;

    /**
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account      $accountConfig
     * @param \Buckaroo\Magento2\Model\SecondChanceFactory         $secondChanceFactory
     */
    public function __construct(
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig,
        \Buckaroo\Magento2\Model\ResourceModel\SecondChance $resourceSecondChance,
        \Buckaroo\Magento2\Logging\Log $logging,
        \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository
    ) {
        $this->accountConfig        = $accountConfig;
        $this->resourceSecondChance = $resourceSecondChance;
        $this->logging              = $logging;
        $this->secondChanceRepository = $secondChanceRepository;
    }

    public function execute()
    {
        $this->secondChanceRepository->deleteOlderRecords();
        /*
        $table_name = $this->resourceSecondChance->getMainTable();
        $prune_days = $this->accountConfig->getSecondChancePruneDays();

        echo $table_name . PHP_EOL;
        $query = "DELETE FROM ".$table_name." WHERE `created_at` < (NOW() - INTERVAL ".$prune_days." days)";
        echo $query;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $query = "DELETE FROM `".$table_name."` WHERE `created_at` < (NOW() - INTERVAL ".$prune_days." DAY)";
        $connection->query($query);
        */
        return $this;
    }
}