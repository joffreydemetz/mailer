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
 * Mailer SMTP Config
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Config_SMTP
{
  public bool $use = false;
  public bool $valid = false;
  
  public int $debug = 0;
  public int $port = 587;
  public bool $auth = true;
  public string $host = '';
  public string $user = '';
  public string $pass = '';
  public string $secure = 'tls';
  
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
    
    if ( '' === $this->host ){
      throw new Exception('Missing SMTP host');
    }
    
    if ( true === $this->auth ){
      if ( '' === $this->user || '' === $this->pass ){
        throw new Exception('Username and password required when SMTP auth is set tot true');
      }
    }
    
    $this->valid = true;
  }
}
