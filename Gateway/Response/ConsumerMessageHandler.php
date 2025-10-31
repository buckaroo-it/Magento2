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

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Payment\Gateway\Response\HandlerInterface;

class ConsumerMessageHandler implements HandlerInterface
{
    /**
     * @var MessageManager
     */
    protected $messageManager;

    /**
     * @param MessageManager $messageManager
     */
    public function __construct(
        MessageManager $messageManager
    ) {
        $this->messageManager = $messageManager;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $response = SubjectReader::readTransactionResponse($response);
        $consumerMessageData = $response->get('ConsumerMessage');

        if (!empty($consumerMessageData) && $consumerMessageData['MustRead'] == 1) {
            $title = $consumerMessageData['Title'] ?? null;
            $plainText = $consumerMessageData['PlainText'] ?? null;

            if ($title) {
                $this->messageManager->addSuccessMessage(__($title));
            }

            if ($plainText) {
                $this->messageManager->addSuccessMessage(__($plainText));
            }
        }
    }
}
