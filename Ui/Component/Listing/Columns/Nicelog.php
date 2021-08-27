<?php

namespace Buckaroo\Magento2\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;

class Nicelog extends \Magento\Ui\Component\Listing\Columns\Column
{

    public function prepareDataSource(array $dataSource)
    {
        $dataSource = parent::prepareDataSource($dataSource);

        if (empty($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as & $item) {
            if (isset($item['message'])) {
                $item['message'] = "<pre>" . $item['message'] . "</pre>";
            }
        }
        return $dataSource;
    }
}
