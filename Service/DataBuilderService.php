<?php

namespace Buckaroo\Magento2\Service;

class DataBuilderService
{
    /**
     * @var array
     */
    private array $data = [];

    /**
     * Get All Values Sets Already on Data Builders
     *
     * @return array
     */
    public function getData()
    {
        return $this->data ?? [];
    }

    /**
     * Get Element From Data Builders
     *
     * @param string|int $key
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
     * @return $this
     */
    public function removeData(array $data): DataBuilderService
    {
        $this->data = array_diff($this->data, $data);
        return $this;
    }
}
