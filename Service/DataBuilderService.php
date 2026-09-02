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

namespace Buckaroo\Magento2\Service;

class DataBuilderService
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * Get All Values Sets Already on Data Builders
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data ?? [];
    }

    /**
     * Get Element From Data Builders
     *
     * @param string|int $key
     *
     * @return mixed
     */
    public function getElement($key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Add new data in Data Builders
     *
     * @param array $data
     *
     * @return $this
     */
    public function addData(array $data): DataBuilderService
    {
        $this->data = array_replace_recursive($this->data, $data);
        return $this;
    }

    /**
     * Remove the elements from Data Builders
     *
     * @param array $data
     *
     * @return $this
     */
    public function removeData(array $data): DataBuilderService
    {
        $this->data = array_diff($this->data, $data);
        return $this;
    }

    /**
     * Reset all accumulated data
     *
     * @return $this
     */
    public function reset(): DataBuilderService
    {
        $this->data = [];
        return $this;
    }
}
