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

namespace Buckaroo\Magento2\Test\Unit\Helper;

use Buckaroo\Magento2\Helper\JsString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JsStringTest extends TestCase
{
    public static function textProvider(): array
    {
        return [
            'apostrophe'       => ["vedi anche l'articolo"],
            'link with quotes' => ['<a href="https://plaza.buckaroo.nl" target="_blank">Plaza</a>.'],
            'script end tag'   => ['</script><script>'],
            'accented letters' => ['Réduction à la commande'],
        ];
    }

    #[DataProvider('textProvider')]
    public function testTextBecomesASafeJavascriptStringLiteral(string $text): void
    {
        $encoded = JsString::encode($text);

        // A double-quoted literal that gives back exactly the text
        $this->assertSame('"', $encoded[0]);
        $this->assertSame('"', substr($encoded, -1));
        $this->assertSame($text, json_decode($encoded));

        // Nothing inside can end the string, the onclick handler or the surrounding <script>
        $inner = substr($encoded, 1, -1);
        foreach (["'", '"', '<', '>'] as $character) {
            $this->assertStringNotContainsString($character, $inner);
        }
    }

    public function testAPhraseIsEncodedAsItsText(): void
    {
        $this->assertSame('"Sure?"', JsString::encode(new \Magento\Framework\Phrase('Sure?')));
    }
}
