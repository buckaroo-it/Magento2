<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Buckaroo\Magento2\Model\Export;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\Export\MetadataProvider;

class ConvertToCsv extends \Magento\Ui\Model\Export\ConvertToCsv
{
    /**
     * @var DirectoryList
     */
    protected $directory;

    /**
     * @var MetadataProvider
     */
    protected $metadataProvider;

    /**
     * @var int|null
     */
    protected $pageSize = null;

    /**
     * @var Filter
     */
    protected $filter;

    protected $orderRepository;
    protected $groupTransaction;
    protected $giftcardCollection;
    protected $configProviderAccount;
    protected $storeManager;

    /**
     * @param Filesystem $filesystem
     * @param Filter $filter
     * @param MetadataProvider $metadataProvider
     * @param int $pageSize
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $filesystem,
        Filter $filter,
        MetadataProvider $metadataProvider,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Buckaroo\Magento2\Helper\PaymentGroupTransaction $groupTransaction,
        \Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection $giftcardCollection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $configProviderAccount,
        $pageSize = 200
    ) {
        $this->filter                = $filter;
        $this->directory             = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->metadataProvider      = $metadataProvider;
        $this->pageSize              = $pageSize;
        $this->orderRepository       = $orderRepository;
        $this->groupTransaction      = $groupTransaction;
        $this->giftcardCollection    = $giftcardCollection;
        $this->storeManager          = $storeManager;
        $this->configProviderAccount = $configProviderAccount;
    }

    /**
     * Returns CSV file
     *
     * @return array
     * @throws LocalizedException
     */
    public function getCsvFile()
    {
        $component = $this->filter->getComponent();

        $name = sha1(microtime());
        $file = 'export/' . $component->getName() . $name . '.csv';

        $this->filter->prepareComponent($component);
        $this->filter->applySelectionOnTargetProvider();
        $dataProvider = $component->getContext()->getDataProvider();
        $fields       = $this->metadataProvider->getFields($component);
        $options      = $this->metadataProvider->getOptions();

        $this->directory->create('export');
        $stream = $this->directory->openFile($file, 'w+');
        $stream->lock();
        $stream->writeCsv($this->metadataProvider->getHeaders($component));
        $i              = 1;
        $searchCriteria = $dataProvider->getSearchCriteria()
            ->setCurrentPage($i)
            ->setPageSize($this->pageSize);
        $totalCount = (int) $dataProvider->getSearchResult()->getTotalCount();
        while ($totalCount > 0) {
            $items = $dataProvider->getSearchResult()->getItems();
            foreach ($items as $item) {
                if ($this->configProviderAccount->getAdvancedExportGiftcards($this->storeManager->getStore())) {
                    $this->convertGiftCardsValue($item);
                }
                $this->metadataProvider->convertDate($item, $component->getName());
                $stream->writeCsv($this->metadataProvider->getRowData($item, $fields, $options));
            }
            $searchCriteria->setCurrentPage(++$i);
            $totalCount = $totalCount - $this->pageSize;
        }
        $stream->unlock();
        $stream->close();

        return [
            'type'  => 'filename',
            'value' => $file,
            'rm'    => true, // can delete file after use
        ];
    }

    public function convertGiftCardsValue($document)
    {
        $item             = $document->toArray();
        $orderId          = $item['entity_id'];
        $order            = $this->orderRepository->get($orderId);
        $orderIncrementId = $order->getIncrementId();
        if ($items = $this->groupTransaction->getGroupTransactionItems($orderIncrementId)) {
            $result = [$document->getDataByKey('payment_method')];
            foreach ($items as $key => $giftcard) {
                array_push($result, $giftcard['servicecode']);
            }
            $document->setData('payment_method', implode(",", $result));
        }
    }
}
