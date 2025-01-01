<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer;

class Address
{
  public string $email;
  public string $name;

  public function __construct(string $email, string $name = '')
  {
    $this->email = $this->clean($email);
    $this->name = $this->clean($name);
  }

  public function __toString(): string
  {
    if ($this->name) {
      return $this->name . ' <' . $this->email . '>';
    }

    return $this->email;
  }

  protected function clean(string $str): string
  {
    return trim(preg_replace('/(%0A|%0D|\n+|\r+)/i', '', $str));
  }
}
