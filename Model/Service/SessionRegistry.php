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
namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Model\Session;
 
class SessionRegistry
{
   protected $session;
 
   public function __construct(
       Session $session,
       Log $logging
   ) {
       $this->session = $session;
   }
 
   /**
    * Get data
    * @param string $key
    * @return mixed
    */
    public function getData($key)
    {
        return $this->session->getData($key);
    }

   /**
    * Set data
    * @param string $key
    * @param mixed $value
    */
   public function setData($key, $value)
   {
       $this->session->setData($key, $value);
   }
   
   /**
    * Unset data
    * @param string $key
    */
    public function unsetData($key)
    {
        $this->session->getData($key, true);//gets and clears session var
    }
}