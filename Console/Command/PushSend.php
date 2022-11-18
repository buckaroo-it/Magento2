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

namespace Buckaroo\Magento2\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Buckaroo\Magento2\Model\Validator\Push;
use Magento\Framework\HTTP\AsyncClientInterface;
use Magento\Framework\HTTP\AsyncClient\Request;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\App\State;

class PushSend extends Command
{
    protected $appState;
    protected $pushValidator;
    protected $asyncHttpClient;
    protected $configProviderAccount;
    protected $encryptor;

    private $requests = 2;
    private $url = 'https://magento24.buckaroo.vlad.hysdev.com/rest/V1/buckaroo/push';

    public function __construct(
        State $appState,
        AsyncClientInterface $asyncHttpClient,
        Account $configProviderAccount,
        Encryptor $encryptor,
        $name = null
    ) {
        $this->appState = $appState;
        $this->asyncHttpClient = $asyncHttpClient;
        $this->configProviderAccount = $configProviderAccount;
        $this->encryptor = $encryptor;

        parent::__construct('buckaroo:push:send');
    }

    protected function configure()
    {
        $this->setDescription('Buckaroo. Sign and send a push notitification.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->appState->setAreaCode('global');
        $this->pushValidator = \Magento\Framework\App\ObjectManager::getInstance()->get(Push::class);

        $postData = [
            'ADD_initiated_by_magento' => '1',
            'ADD_service_action_from_magento' => 'pay',
            'brq_amount' => '43.84',
            'brq_currency' => 'EUR',
            'brq_customer_name' => 'J. de Tèster',
            'brq_description' => 'Default Store View',
            'brq_invoicenumber' => '051400250',
            'brq_mutationtype' => 'Collecting',
            'brq_ordernumber' => '051400250',
            // @codingStandardsIgnoreStart
            'brq_payer_hash' => '2d26d34584a4eafeeaa97eed10cfdae22ae64cdce1649a80a55fafca8850e3e22cb32eb7c8fc95ef0c6f96669a21651d4734cc568816f9bd59c2092911e6c0da',
            // @codingStandardsIgnoreEnd
            'brq_payment' => 'E6CB308C8BDC4609A7D5A4C00BD316FA',
            'brq_SERVICE_ideal_consumerBIC' => 'RABONL2U',
            'brq_SERVICE_ideal_consumerIBAN' => 'NL44RABO0123456789',
            'brq_SERVICE_ideal_consumerIssuer' => 'ABN AMRO',
            'brq_SERVICE_ideal_consumerName' => 'J. de Tèster',
            'brq_SERVICE_ideal_transactionId' => '0000000000000001',
            'brq_statuscode' => '190',
            'brq_statuscode_detail' => 'S001',
            'brq_statusmessage' => 'Transaction successfully processed',
            'brq_test' => 'true',
            'brq_timestamp' => '2022-02-09 10:49:49',
            'brq_transaction_method' => 'ideal',
            'brq_transaction_type' => 'C021',
            'brq_transactions' => '52F3C5A0E8D74F13A4F7767C2D029AB3',
            'brq_websitekey' => $this->encryptor->decrypt($this->configProviderAccount->getMerchantKey()),
        ];

        $signature = $this->pushValidator->calculateSignature($postData);
        $output->writeln($signature);
        $postData['brq_signature'] = $signature;

        $responses = [];

        for ($i = 0; $i < $this->requests; $i++) {
            $request = new Request(
                $this->url,
                Request::METHOD_POST,
                ['Content-Type' => 'application/x-www-form-urlencoded'],
                http_build_query($postData)
            );

            try {
                $responses[] = $this->asyncHttpClient->request($request);
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
                return false;
            }
        }

        foreach ($responses as $response) {
            $output->writeln($response->get()->getBody());
        }
    }
}
