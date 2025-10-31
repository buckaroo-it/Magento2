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
     * @param  string|int $key
     * @return mixed
     */
    public function getElement($key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Add new data in Data Builders
     *
     * @param  array $data
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
     * @param  array $data
     * @return $this
     */
    public function removeData(array $data): DataBuilderService
    {
        $this->data = array_diff($this->data, $data);
        return $this;
    }
}
