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
