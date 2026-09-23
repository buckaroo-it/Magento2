<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Validator;

use Buckaroo\Config\DefaultConfig;
use Buckaroo\Handlers\HMAC\Generator;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\RequestPush\JsonPushRequest;
use Buckaroo\Magento2\Model\Validator\PushSDK;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Request;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Wires the full push-validation delegation stack (adapter + SDK config + JSON/HMAC),
 * so a high collaborator count is inherent to what it exercises.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PushSdkSignatureTest extends BaseTest
{
    private const WEBSITE = 'test-website';
    private const SECRET = 'test-secret';
    private const URI = 'https://example.com/rest/V1/buckaroo/push';

    protected $instanceClass = BuckarooAdapter::class;

    public function testJsonCannotUseAValidFormSignatureWithoutHmac(): void
    {
        foreach (['Transaction', 'DataRequest'] as $root) {
            $payload = [
                $root => ['Invoice' => 'OTHER-ORDER', 'Status' => ['Code' => ['Code' => 190]]],
                'brq_statuscode' => '690',
                'brq_signature' => sha1('brq_statuscode=690' . self::SECRET),
            ];
            foreach ([false, '', null] as $header) {
                $this->assertFalse($this->request($payload, $header)->validate(), $root);
            }
        }
    }

    public function testValidHmacCoversTheEntireJsonEnvelope(): void
    {
        foreach (['Transaction', 'DataRequest'] as $root) {
            $payload = [$root => ['Invoice' => 'SIGNED-ORDER', 'Status' => ['Code' => ['Code' => 690]]]];
            $header = (new Generator(new DefaultConfig(self::WEBSITE, self::SECRET), $payload, self::URI))->generate();
            $this->assertTrue($this->request($payload, $header)->validate(), $root);

            $payload[$root]['Status']['Code']['Code'] = 190;
            $payload['brq_statuscode'] = '690';
            $payload['brq_signature'] = sha1('brq_statuscode=690' . self::SECRET);
            $this->assertFalse($this->request($payload, $header)->validate(), $root);
        }
    }

    public function testAdapterStillSelectsHttpPostForFormPayloads(): void
    {
        $payload = [
            'Brq_statuscode' => '190',
            'Brq_Signature' => sha1('Brq_statuscode=190' . self::SECRET),
        ];
        $adapter = $this->adapter();
        $this->assertTrue($adapter->validate($payload, null, null));

        $payload['brq_statuscode'] = '690';
        $this->assertFalse($adapter->validate($payload, null, null));
    }

    public function testSdkRejectsMalformedInputThroughMagentoValidator(): void
    {
        foreach (['{invalid json', 'null', '[]'] as $payload) {
            $this->assertFalse($this->validator($payload, 'invalid-header')->validate(null));
        }
        $payload = ['Transaction' => ['Invoice' => 'TEST']];
        $this->assertFalse($this->request($payload, 'invalid-header')->validate());
    }

    private function request(array $payload, $header): JsonPushRequest
    {
        return new JsonPushRequest($payload, $this->validator(json_encode($payload), $header));
    }

    private function validator(string $content, $header): PushSDK
    {
        $httpRequest = $this->createStub(Request::class);
        $httpRequest->method('getContent')->willReturn($content);
        $httpRequest->method('getHeader')->willReturn($header);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getDirectUrl')->willReturn(self::URI);

        return new PushSDK($this->adapter(), $httpRequest, $url);
    }

    private function adapter(): BuckarooAdapter
    {
        $account = $this->createStub(Account::class);
        $account->method('getActive')->willReturn(2);
        $account->method('getMerchantKey')->willReturn(self::WEBSITE);
        $account->method('getSecretKey')->willReturn(self::SECRET);
        $factory = $this->createStub(Factory::class);
        $factory->method('get')->willReturn($account);
        $encryptor = $this->createStub(Encryptor::class);
        $encryptor->method('decrypt')->willReturnArgument(0);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $locale = $this->createStub(Resolver::class);
        $locale->method('getLocale')->willReturn('en_US');

        return $this->getInstance([
            'configProviderFactory' => $factory,
            'encryptor' => $encryptor,
            'storeManager' => $storeManager,
            'localeResolver' => $locale,
        ]);
    }
}
