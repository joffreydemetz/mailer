<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Mailer;

/**
 * Mail address
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */ 
class Address 
{
  public string $email;
  public string $name;
  
  public function __construct(string $email, string $name='')
  {
    $this->email = $this->clean($email);
    $this->name = $this->clean($name);
  }
  
  public function __toString(): string
  {
    if ( $this->name ){
      return $this->name.' <'.$this->email.'>';
    }
    
    return $this->email;
  }
  
  protected function clean(string $str): string
  {
    return trim(preg_replace('/(%0A|%0D|\n+|\r+)/i', '', $str));
  }
}
