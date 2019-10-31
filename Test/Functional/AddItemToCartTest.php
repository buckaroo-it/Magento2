<?php
// An example of using php-webdriver.

namespace TIG\Buckaroo\Test\Functional;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class AddItemToCartTest extends \TIG\Buckaroo\Test\BaseTest
{
    /**
     * @var \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected $driver;

    public function setUp()
    {
        parent::setUp();

        // start Firefox with 5 second timeout
        $host = 'http://localhost:4444/wd/hub'; // this is the default
        $capabilities = DesiredCapabilities::phantomjs();
        $this->driver = RemoteWebDriver::create($host, $capabilities, 5000);
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->driver->quit();
    }

    public function testAddToCart()
    {
        $this->markTestSkipped('This test fails, probably there are some elements change in the v2.0.0 -> 2.0.8 upgrade');

        $this->driver->get('http://buckaroo.jenkins/test-product.html');

        sleep(10);

        // click the link 'About'
        $link = $this->driver->findElement(
            WebDriverBy::className('tocart')
        );
        $link->click();

        // wait at most 40 seconds until at least one result is shown
        $this->driver->wait(40)->until(
            WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
                WebDriverBy::className('product-item-details')
            )
        );

        $cartCounter = $this->driver->findElement(
            WebdriverBy::className('counter-number')
        );

        $this->assertEquals(1, $cartCounter->getText());
    }
}
