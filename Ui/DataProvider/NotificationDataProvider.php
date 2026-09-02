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
declare(strict_types=1);

namespace Buckaroo\Magento2\Ui\DataProvider;

use Buckaroo\Magento2\Ui\DataProvider\Modifier\Notifications;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;

class NotificationDataProvider extends AbstractDataProvider
{
    /**
     * @var Notifications $modifier
     */
    private $modifier;

    /**
     * @param string        $name
     * @param string        $primaryFieldName
     * @param string        $requestFieldName
     * @param Notifications $modifier
     * @param array         $meta
     * @param array         $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        Notifications $modifier,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data
        );

        $this->modifier = $modifier;
    }

    /**
     * @inheritdoc
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function getMeta(): array
    {
        return $this->modifier->modifyMeta($this->meta);
    }

    /**
     * Add filter to the data provider; not supported for notifications.
     *
     * @param Filter $filter
     * @return null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function addFilter(Filter $filter)
    {
        return null;
    }
}
