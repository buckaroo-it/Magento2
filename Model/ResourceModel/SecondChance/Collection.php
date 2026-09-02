<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Model\ResourceModel\SecondChance;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * SecondChance record collection.
 *
 * This collection is the single owner of the SecondChance selection rules: which status and
 * timestamp column belong to an email step, when a record counts as due, and how a retention
 * window is translated into a cutoff. Both the cron gate that counts candidates and the
 * processing path that iterates them build their query through these methods, so the two can
 * never select different sets of records.
 */
class Collection extends AbstractCollection
{
    public const STEP_FIRST_EMAIL  = 1;
    public const STEP_SECOND_EMAIL = 2;

    /**
     * Status of a record that is waiting for the first email.
     */
    private const STATUS_PENDING = 'pending';

    /**
     * Status of a record that received the first email and is waiting for the second.
     */
    private const STATUS_STEP1_SENT = 'step1_sent';

    /**
     * Statuses of records that are still waiting for a reminder to go out.
     */
    private const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_STEP1_SENT];

    private const SECONDS_PER_HOUR = 3600;
    private const SECONDS_PER_DAY  = 86400;

    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface        $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface       $eventManager
     * @param DateTime               $dateTime
     * @param AdapterInterface|null  $connection
     * @param AbstractDb|null        $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        DateTime $dateTime,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        $this->dateTime = $dateTime;

        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    /**
     * Define a resource model
     */
    protected function _construct()
    {
        $this->_init(
            \Buckaroo\Magento2\Model\SecondChance::class,
            \Buckaroo\Magento2\Model\ResourceModel\SecondChance::class
        );
    }

    /**
     * Limit the collection to one or more stores.
     *
     * @param int|int[] $storeIds
     * @return $this
     */
    public function addStoreFilter($storeIds)
    {
        $this->addFieldToFilter('store_id', ['in' => array_map('intval', (array) $storeIds)]);

        return $this;
    }

    /**
     * Limit the collection to records that are due for the given email step.
     *
     * A delay of zero means "process immediately", so the cutoff is inclusive.
     *
     * @param int $step
     * @param int $delayHours
     * @return $this
     */
    public function addStepDueFilter(int $step, int $delayHours)
    {
        $delayHours = max(0, $delayHours);
        $isFirstStep = $step === self::STEP_FIRST_EMAIL;

        $this->addFieldToFilter('status', $isFirstStep ? self::STATUS_PENDING : self::STATUS_STEP1_SENT);
        $this->addFieldToFilter(
            $isFirstStep ? 'created_at' : 'first_email_sent',
            [
                ($delayHours === 0 ? 'lteq' : 'lt') => $this->getCutoff($delayHours * self::SECONDS_PER_HOUR),
            ]
        );

        return $this;
    }

    /**
     * Limit the collection to records created before the retention window.
     *
     * @param int $days
     * @return $this
     */
    public function addCreatedBeforeFilter(int $days)
    {
        $this->addFieldToFilter('created_at', ['lt' => $this->getCutoff($days * self::SECONDS_PER_DAY)]);

        return $this;
    }

    /**
     * Limit the collection to records that may be removed.
     *
     * A record is removable once it is older than the retention window, except while it is
     * still waiting for a reminder that has not had the chance to go out yet. Without that
     * exception a retention window shorter than the configured reminder timings would delete
     * records before their reminder was ever sent.
     *
     * @param int $retentionDays
     * @param int $reminderWindowHours Combined delay of the reminders that are still enabled
     * @return $this
     */
    public function addRemovableFilter(int $retentionDays, int $reminderWindowHours)
    {
        $this->addCreatedBeforeFilter($retentionDays);
        $this->addFieldToFilter(
            ['status', 'created_at'],
            [
                ['nin' => self::ACTIVE_STATUSES],
                ['lt' => $this->getCutoff(max(0, $reminderWindowHours) * self::SECONDS_PER_HOUR)],
            ]
        );

        return $this;
    }

    /**
     * Return the cutoff timestamp for a window that ends now.
     *
     * @param int $seconds
     * @return string
     */
    private function getCutoff(int $seconds): string
    {
        return $this->dateTime->gmtDate(null, $this->dateTime->gmtTimestamp() - $seconds);
    }
}
