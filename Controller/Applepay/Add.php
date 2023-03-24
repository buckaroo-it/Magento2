<?php
/**
 * NOTICE OF LICENSE.
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

namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Service\Applepay\Add as AddService;

class Add extends AbstractApplepay
{
    /**
     * @var AddService
     */
    protected $addService;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param Log $logging
     * @param AddService $addService
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        Log $logging,
        AddService $addService
    ) {
        parent::__construct(
            $resultJsonFactory,
            $request,
            $logging
        );
        $this->addService = $addService;
    }

    /**
     * Add Applepay
     *
     * @return Json
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($this->getParams(), true));
        $data = $this->addService->process($this->getParams());
        $errorMessage = $data['error'] ?? false;
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($data, true));

        return $this->commonResponse($data, $errorMessage);
    }
}
