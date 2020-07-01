<?php
namespace Buckaroo\Magento2\Ui\Component\Listing\Column\Method;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Options extends Column
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Options constructor.
     *
     * @param  ContextInterface $context
     * @param  UiComponentFactory $uiComponentFactory
     * @param  ResourceConnection $resourceConnection
     * @param  array $components
     * @param  array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    )
    {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $incrementIds = array_map(static function ($item) {
                return $item['increment_id'];
            }, $dataSource['data']['items'] ?? []);

            if ($incrementIds) {
                $result = $this->resourceConnection->getConnection()->fetchAll(
                    $this->resourceConnection->getConnection()->select()->from(
                        ['sop' => $this->resourceConnection->getTableName('sales_order_payment')],
                        [
                            'sop.method',
                            'group_concat(distinct(bmgt.servicecode) SEPARATOR "-") as giftcard_codes',
                            'so.increment_id',
                        ]
                    )->joinInner(
                        ['so' => $this->resourceConnection->getTableName('sales_order')],
                        'so.entity_id = sop.parent_id',
                        ''
                    )->joinInner(
                        ['bmgt' => $this->resourceConnection->getTableName('buckaroo_magento2_group_transaction')],
                        'bmgt.order_id = so.increment_id',
                        ''
                    )->where(
                        'so.increment_id in (?)', $incrementIds
                    )->group('so.increment_id')
                );

                $additionalOptions = [];
                foreach ($result as $row) {
                    $additionalOptions[$row['increment_id']] = $row['method'] . '-' . $row['giftcard_codes'];
                }

                if ($additionalOptions) {
                    foreach ($dataSource['data']['items'] as &$item) {
                        if (isset($additionalOptions[$item['increment_id']])) {
                            $item[$this->getData('name')] = $additionalOptions[$item['increment_id']];
                        }
                    }
                }
            }
        }

        return $dataSource;
    }
}
