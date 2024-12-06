<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Mailer\Config;

use JDZ\Mailer\Exception;

/**
 * Mailer DKIM Config
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Config_DKIM
{
  public bool $use = false;
  public bool $valid = false;
  
  public string $domain = '';
  public string $identity = '';
  public string $private = '';
  public string $selector = '';
  public string $passphrase = '';
  
  public function setProperties(array $properties=[])
  {
    $this->use = true;
    foreach($properties as $key => $value){
      $this->{$key} = $value;
    }
  }
  
  public function check()
  {
    if ( false === $this->use ){
      return;
    }
    
    if ( '' === $this->domain ){
      throw new Exception('Missing DKIM "domain"');
    }
    
    if ( '' === $this->identity ){
      throw new Exception('Missing DKIM "identity"');
    }
    
    if ( '' === $this->private ){
      throw new Exception('Missing DKIM "private" key');
    }
    
    if ( '' === $this->selector ){
      throw new Exception('Missing DKIM "selector" key');
    }
    
    $this->valid = true;
  }
}
