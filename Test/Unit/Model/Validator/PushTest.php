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

namespace Buckaroo\Magento2\Test\Unit\Model\Validator;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Validator\Push;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Encryption\Encryptor;
use PHPUnit\Framework\Attributes\DataProvider;

class PushTest extends BaseTest
{
    /**
     * Secret key as it would be stored (encrypted) in core_config_data.
     */
    private const ENCRYPTED_SECRET = 'encrypted-secret-key-value';

    /**
     * Secret key as configured in the Buckaroo plaza.
     */
    private const SECRET = 'S3cr3tBuckarooKey!';

    /**
     * @var string
     */
    protected $instanceClass = Push::class;

    public function testValidate()
    {
        $instance = $this->getInstance();
        $this->expectException(\LogicException::class);
        $instance->validate(null);
    }

    /* ---------------------------------------------------------------------
     * calculateSignature()
     * ------------------------------------------------------------------ */

    /**
     * Pins the exact signature algorithm against a hand-computed vector:
     * case-insensitive sort of accepted keys, concatenation of key=value
     * pairs (original key casing preserved), secret appended, SHA-1 digest.
     */
    public function testCalculateSignatureMatchesHandComputedVector()
    {
        $payload = [
            'brq_websitekey'           => 'ABCDEFGH',
            'cust_customerName'        => 'John Doe',
            'brq_amount'               => '10.00',
            'BRQ_CURRENCY'             => 'EUR',
            'add_initiated_by_magento' => '1',
            'brq_invoicenumber'        => '100000001',
            'brq_statuscode'           => '190',
            'brq_transactions'         => '9BAE00E4B4C34DF08A1F5A3B1E7B27E2',
            'brq_signature'            => 'must-not-be-part-of-the-signature',
            'foo_bar'                  => 'must-not-be-signed',
        ];

        // Expected signature string derived by hand from the documented
        // Buckaroo algorithm (case-insensitive alphabetical key order):
        $expectedSignatureString =
            'add_initiated_by_magento=1'
            . 'brq_amount=10.00'
            . 'BRQ_CURRENCY=EUR'
            . 'brq_invoicenumber=100000001'
            . 'brq_statuscode=190'
            . 'brq_transactions=9BAE00E4B4C34DF08A1F5A3B1E7B27E2'
            . 'brq_websitekey=ABCDEFGH'
            . 'cust_customerName=John Doe'
            . self::SECRET;

        $instance = $this->getPushInstance();

        $this->assertSame(sha1($expectedSignatureString), $instance->calculateSignature($payload));
    }

    public function testCalculateSignatureExcludesSignatureFieldsInBothCasings()
    {
        $basePayload = [
            'brq_amount'        => '25.50',
            'brq_invoicenumber' => '100000002',
        ];

        $withSignatures = $basePayload + [
            'brq_signature' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'BRQ_SIGNATURE' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        ];

        $instance = $this->getPushInstance();

        $this->assertSame(
            $instance->calculateSignature($basePayload),
            $instance->calculateSignature($withSignatures)
        );
    }

    public function testCalculateSignatureIgnoresKeysWithoutAcceptedPrefix()
    {
        $basePayload = ['brq_amount' => '1.00'];

        $polluted = $basePayload + [
            'form_key'      => 'attacker-controlled',
            'random'        => 'value',
            'brqamount'     => 'no-underscore-so-no-prefix-match',
            'custom_field'  => 'prefix is custom, not cust',
            'brq2_amount'   => 'prefix brq2 is not brq',
        ];

        $instance = $this->getPushInstance();

        $this->assertSame(
            $instance->calculateSignature($basePayload),
            $instance->calculateSignature($polluted)
        );
    }

    #[DataProvider('acceptedPrefixProvider')]
    public function testCalculateSignatureAcceptsAllDocumentedPrefixes(string $key, string $value)
    {
        $instance = $this->getPushInstance();

        $this->assertSame(
            sha1($key . '=' . $value . self::SECRET),
            $instance->calculateSignature([$key => $value])
        );
    }

    public static function acceptedPrefixProvider(): array
    {
        return [
            'lowercase brq'  => ['brq_amount', '1.00'],
            'lowercase add'  => ['add_service_action', 'something'],
            'lowercase cust' => ['cust_name', 'John'],
            'uppercase BRQ'  => ['BRQ_AMOUNT', '1.00'],
            'uppercase ADD'  => ['ADD_FIELD', 'x'],
            'uppercase CUST' => ['CUST_FIELD', 'y'],
        ];
    }

    public function testCalculateSignatureOfEmptyPayloadIsHashOfSecretOnly()
    {
        $instance = $this->getPushInstance();

        $this->assertSame(sha1(self::SECRET), $instance->calculateSignature([]));
    }

    public function testCalculateSignatureRestoresSpaceMangledParameterName()
    {
        // PHP turns "brq_SERVICE_ideal_Additional Info" into
        // "brq_SERVICE_ideal_Additional_Info" while parsing POST data.
        // The signature must be computed over the original (spaced) name.
        $payload = ['brq_SERVICE_ideal_Additional_Info' => 'some info'];

        $instance = $this->getPushInstance();

        $this->assertSame(
            sha1('brq_SERVICE_ideal_Additional Info=some info' . self::SECRET),
            $instance->calculateSignature($payload)
        );
    }

    public function testCalculateSignaturePassesStoreToSecretKeyProvider()
    {
        $accountMock = $this->getFakeMock(Account::class)->getMock();
        $accountMock->expects($this->once())
            ->method('getSecretKey')
            ->with(42)
            ->willReturn(self::ENCRYPTED_SECRET);

        $instance = $this->getPushInstance(self::SECRET, $accountMock);

        $this->assertSame(
            sha1('brq_amount=1.00' . self::SECRET),
            $instance->calculateSignature(['brq_amount' => '1.00'], 42)
        );
    }

    /* ---------------------------------------------------------------------
     * validateSignature()
     * ------------------------------------------------------------------ */

    public function testValidateSignatureReturnsTrueForCorrectlySignedPayload()
    {
        $payload = self::examplePushPayload();
        $payload['brq_signature'] = self::signPayload($payload, self::SECRET);

        $instance = $this->getPushInstance();

        $this->assertTrue($instance->validateSignature($payload, $payload));
    }

    #[DataProvider('tamperedPayloadProvider')]
    public function testValidateSignatureReturnsFalseForTamperedPayload(string $key, string $tamperedValue)
    {
        $payload = self::examplePushPayload();
        $payload['brq_signature'] = self::signPayload($payload, self::SECRET);

        $tampered = $payload;
        $tampered[$key] = $tamperedValue;

        $instance = $this->getPushInstance();

        $this->assertFalse($instance->validateSignature($tampered, $tampered));
    }

    public static function tamperedPayloadProvider(): array
    {
        return [
            'amount changed'      => ['brq_amount', '0.01'],
            'status forged'       => ['brq_statuscode', '190'],
            'invoice redirected'  => ['brq_invoicenumber', '900000099'],
            'signature replaced'  => ['brq_signature', sha1('forged')],
        ];
    }

    public function testValidateSignatureReturnsFalseWhenSignedWithWrongSecret()
    {
        $payload = self::examplePushPayload();
        $payload['brq_signature'] = self::signPayload($payload, 'attacker-guessed-secret');

        $instance = $this->getPushInstance();

        $this->assertFalse($instance->validateSignature($payload, $payload));
    }

    public function testValidateSignatureReturnsFalseWhenSignatureIsMissing()
    {
        $payload = self::examplePushPayload();

        $instance = $this->getPushInstance();

        $this->assertFalse($instance->validateSignature($payload, $payload));
    }

    public function testValidateSignatureReturnsFalseForEmptyPayloads()
    {
        $instance = $this->getPushInstance();

        $this->assertFalse($instance->validateSignature([], []));
    }

    public function testValidateSignatureUsesOriginalPostDataForCalculation()
    {
        // The signature is calculated from the first argument
        // ($originalPostData) while the signature itself is read from the
        // second argument ($postData). Pin that contract.
        $original = self::examplePushPayload();
        $signature = self::signPayload($original, self::SECRET);

        $casedPostData = array_change_key_case($original, CASE_UPPER);
        $casedPostData['brq_signature'] = $signature;

        $instance = $this->getPushInstance();

        $this->assertTrue($instance->validateSignature($original, $casedPostData));
    }

    /* ---------------------------------------------------------------------
     * buckarooArraySort()
     * ------------------------------------------------------------------ */

    public function testBuckarooArraySortSortsCaseInsensitivelyAndKeepsOriginalKeys()
    {
        // Case-sensitive (byte) order would put 'BRQ_Websitekey' before
        // 'brq_amount'; the Buckaroo algorithm requires case-insensitive
        // order, so 'brq_amount' must come first.
        $input = [
            'cust_name'       => 'John',
            'BRQ_Websitekey'  => 'KEY',
            'brq_amount'      => '1.00',
            'ADD_service'     => 'ideal',
            'brq_STATUSCODE'  => '190',
        ];

        $expected = [
            'ADD_service'     => 'ideal',
            'brq_amount'      => '1.00',
            'brq_STATUSCODE'  => '190',
            'BRQ_Websitekey'  => 'KEY',
            'cust_name'       => 'John',
        ];

        $result = $this->invokeArgs('buckarooArraySort', [$input], $this->getPushInstance());

        $this->assertSame($expected, $result);
    }

    /**
     * Pins current behavior: keys that differ only in casing collapse into
     * a single entry (the last one wins). This silently drops data from the
     * signature base and is a suspected bug, but it is the behavior Buckaroo
     * pushes rely on today.
     */
    public function testBuckarooArraySortCollapsesKeysDifferingOnlyInCase()
    {
        $input = [
            'brq_test' => 'first',
            'BRQ_TEST' => 'second',
        ];

        $result = $this->invokeArgs('buckarooArraySort', [$input], $this->getPushInstance());

        $this->assertSame(['BRQ_TEST' => 'second'], $result);
    }

    /* ---------------------------------------------------------------------
     * fixParameterNamesWithSpaces()
     * ------------------------------------------------------------------ */

    public function testFixParameterNamesWithSpacesRestoresAdditionalInfo()
    {
        $input = ['brq_SERVICE_ideal_Additional_Info' => 'value kept'];

        $result = $this->invokeArgs('fixParameterNamesWithSpaces', [$input], $this->getPushInstance());

        $this->assertSame(['brq_SERVICE_ideal_Additional Info' => 'value kept'], $result);
    }

    #[DataProvider('unmodifiedParameterNameProvider')]
    public function testFixParameterNamesWithSpacesLeavesOtherKeysUntouched(string $key)
    {
        $input = [$key => 'value'];

        $result = $this->invokeArgs('fixParameterNamesWithSpaces', [$input], $this->getPushInstance());

        $this->assertSame($input, $result);
    }

    public static function unmodifiedParameterNameProvider(): array
    {
        return [
            'plain brq key'               => ['brq_amount'],
            'service key, other suffix'   => ['brq_SERVICE_ideal_consumerName'],
            'lowercase service segment'   => ['brq_service_ideal_Additional_Info'],
            'service name with underscore' => ['brq_SERVICE_after_pay_Additional_Info'],
            'suffix only, no service'     => ['brq_Additional_Info'],
        ];
    }

    /* ---------------------------------------------------------------------
     * validateStatusCode()
     * ------------------------------------------------------------------ */

    public function testValidateStatusCodeReturnsMessageAndStatusForKnownCode()
    {
        $helperMock = $this->getFakeMock(Data::class)->getMock();
        $helperMock->method('getStatusByValue')
            ->with(190)
            ->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');

        $instance = $this->getInstance(['helper' => $helperMock]);

        $this->assertSame(
            [
                'message' => 'Success',
                'status'  => 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS',
                'code'    => 190,
            ],
            $instance->validateStatusCode(190)
        );
    }

    public function testValidateStatusCodeReturnsNeutralForUnknownCode()
    {
        $helperMock = $this->getFakeMock(Data::class)->getMock();
        $helperMock->method('getStatusByValue')->willReturn(null);

        $instance = $this->getInstance(['helper' => $helperMock]);

        $this->assertSame(
            [
                'message' => 'Onbekende responsecode: 999',
                'status'  => 'BUCKAROO_MAGENTO2_STATUSCODE_NEUTRAL',
                'code'    => 999,
            ],
            $instance->validateStatusCode(999)
        );
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------ */

    /**
     * Build a Push instance whose secret-key chain
     * (Account::getSecretKey -> Encryptor::decrypt) yields $secret.
     *
     * @param string       $secret
     * @param Account|null $accountMock
     *
     * @return Push
     */
    private function getPushInstance(string $secret = self::SECRET, ?Account $accountMock = null): Push
    {
        if ($accountMock === null) {
            $accountMock = $this->getFakeMock(Account::class)->getMock();
            $accountMock->method('getSecretKey')->willReturn(self::ENCRYPTED_SECRET);
        }

        $encryptorMock = $this->getFakeMock(Encryptor::class)->getMock();
        $encryptorMock->method('decrypt')
            ->with(self::ENCRYPTED_SECRET)
            ->willReturn($secret);

        return $this->getInstance([
            'configProviderAccount' => $accountMock,
            'encryptor'             => $encryptorMock,
        ]);
    }

    /**
     * A realistic Buckaroo push payload (without signature).
     *
     * @return array<string, string>
     */
    private static function examplePushPayload(): array
    {
        return [
            'brq_amount'                     => '52.79',
            'brq_currency'                   => 'EUR',
            'brq_customer_name'              => 'J. de Tester',
            'brq_invoicenumber'              => '100000001',
            'brq_mutationtype'               => 'Collecting',
            'brq_payment'                    => 'F2AE41A78E52D6AB4B4B4B4B4B4B4B4B',
            'brq_payment_method'             => 'ideal',
            'brq_SERVICE_ideal_consumerName' => 'J. de Tester',
            'brq_statuscode'                 => '890',
            'brq_statusmessage'              => 'Cancelled by consumer',
            'brq_test'                       => 'true',
            'brq_timestamp'                  => '2026-07-22 10:15:00',
            'brq_transactions'               => '9BAE00E4B4C34DF08A1F5A3B1E7B27E2',
            'brq_websitekey'                 => 'ABCDEFGH',
            'add_initiated_by_magento'       => '1',
            'cust_customerName'              => 'John Doe',
        ];
    }

    /**
     * Independent re-implementation of the Buckaroo signing algorithm used
     * to produce signatures for validateSignature() tests. Kept deliberately
     * separate from the production code so the tests fail if the production
     * algorithm drifts.
     *
     * @param array<string, string> $payload
     * @param string                $secret
     *
     * @return string
     */
    private static function signPayload(array $payload, string $secret): string
    {
        $accepted = ['brq', 'add', 'cust', 'BRQ', 'ADD', 'CUST'];

        $filtered = [];
        foreach ($payload as $key => $value) {
            if (strtolower((string)$key) === 'brq_signature') {
                continue;
            }
            if (in_array(explode('_', (string)$key)[0], $accepted, true)) {
                $filtered[$key] = $value;
            }
        }

        uksort($filtered, static function (string $a, string $b): int {
            return strcmp(strtolower($a), strtolower($b));
        });

        $signatureString = '';
        foreach ($filtered as $key => $value) {
            $signatureString .= $key . '=' . $value;
        }

        return sha1($signatureString . $secret);
    }
}
