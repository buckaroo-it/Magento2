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

namespace Buckaroo\Magento2\Controller\Pos;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Exception;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class SetTerminal extends Action implements HttpGetActionInterface
{
    /**
     * @var array
     */
    protected $response;

    /**
     * @var ConfigProviderInterface
     */
    protected $accountConfig;

    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storemanager;

    /**
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    protected $cookieMetadataFactory;

    /**
     * @param Context                 $context
     * @param BuckarooLoggerInterface $logger
     * @param Factory                 $configProviderFactory
     * @param StoreManagerInterface   $storemanager
     * @param CookieManagerInterface  $cookieManager
     * @param CookieMetadataFactory   $cookieMetadataFactory
     *
     * @throws BuckarooException
     */
    public function __construct(
        Context $context,
        BuckarooLoggerInterface $logger,
        Factory $configProviderFactory,
        StoreManagerInterface $storemanager,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->accountConfig = $configProviderFactory->get('account');
        $this->storemanager = $storemanager;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * Process action
     *
     * @throws Exception
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $params = $this->getRequest()->getParams();
        $this->logger->addDebug(sprintf(
            '[POS] | [Controller] | [%s:%s] - Set Terminal | request: %s',
            __METHOD__,
            __LINE__,
            var_export($params, true)
        ));

        if (!empty($params['id'])) {
            $metadata = $this->cookieMetadataFactory
                ->createPublicCookieMetadata()
                ->setPath('/')
                ->setDuration(86400 * 365);
            $this->cookieManager->setPublicCookie(
                'Pos-Terminal-Id',
                $params['id'],
                $metadata
            );
        }

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('');
    }
}
