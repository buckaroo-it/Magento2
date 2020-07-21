<?php
 /**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

use Buckaroo\Magento2\Logging\Log;

class SetTerminal extends \Magento\Framework\App\Action\Action
{
    /**
     * @var array
     */
    protected $response;

    /**
     * @var \Magento\Checkout\Model\ConfigProviderInterface
     */
    protected $accountConfig;

    /**
     * @var Log
     */
    protected $logger;

    protected $storemanager;

    protected $cookieManager;

    protected $cookieMetadataFactory;

    /**
     * @param \Magento\Framework\App\Action\Context               $context
     * @param Log                                                 $logger
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory          $configProviderFactory
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Log $logger,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        \Magento\Store\Model\StoreManagerInterface $storemanager,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
    ) {
        parent::__construct($context);
        $this->logger             = $logger;
        $this->accountConfig = $configProviderFactory->get('account');
        $this->storemanager = $storemanager;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $this->logger->addDebug(__METHOD__.'|1|'.var_export($this->getRequest()->getParams(), true));

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
            $this->logger->addDebug(__METHOD__.'|2|');
        }

        $redirectUrl= $this->storemanager->getStore()->getBaseUrl();
        $this->_redirect($redirectUrl);
    }

}
