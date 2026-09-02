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

namespace Buckaroo\Magento2\Block\Adminhtml\Grid\Renderer;

use Magento\Backend\Block\Context;
use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class Image extends AbstractRenderer
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Context               $context
     * @param StoreManagerInterface $storemanager
     * @param array                 $data
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storemanager,
        array $data = []
    ) {
        $this->storeManager = $storemanager;
        parent::__construct($context, $data);
        $this->_authorization = $context->getAuthorization();
    }

    /**
     * Renders grid column
     *
     * @param DataObject $row
     *
     * @throws NoSuchEntityException
     *
     * @return string
     */
    public function render(DataObject $row)
    {
        if ($img = $row['logo']) {
            $store = $this->storeManager->getStore();
            if (!$store instanceof Store) {
                return '';
            }

            $mediaDirectory = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            return '<img src="' . $mediaDirectory . $img . '" width="50" >';
        }

        return '';
    }
}
