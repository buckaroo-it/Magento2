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

namespace Buckaroo\Magento2\Block\Adminhtml\Grid\Renderer;

use Magento\Backend\Block\Context;
use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
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
            $mediaDirectory = $this->storeManager->getStore()->getBaseUrl(
                UrlInterface::URL_TYPE_MEDIA
            );
            return '<img src="' . $mediaDirectory . $img . '" width="50" >';
        }

        return '';
    }
}
