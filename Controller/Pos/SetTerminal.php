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

namespace Buckaroo\Magento2\Controller\Pos;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class SetTerminal extends Action
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
     * @var Log
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
     * @param Context $context
     * @param Log $logger
     * @param Factory $configProviderFactory
     * @param StoreManagerInterface $storemanager
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @throws BuckarooException
     */
    public function __construct(
        Context $context,
        Log $logger,
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
     * @return ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $this->logger->addDebug(__METHOD__ . '|1|' . var_export($this->getRequest()->getParams(), true));

        if (($params = $this->getRequest()->getParams()) && !empty($params['id'])) {
            $metadata = $this->cookieMetadataFactory
                ->createPublicCookieMetadata()
                ->setPath('/')
                ->setDuration(86400 * 365);
            $this->cookieManager->setPublicCookie(
                'Pos-Terminal-Id',
                $params['id'],
                $metadata
            );
            $this->logger->addDebug(__METHOD__ . '|2|');
        }

        $redirectUrl = $this->storemanager->getStore()->getBaseUrl();
        return $this->_redirect($redirectUrl);
    }
}
