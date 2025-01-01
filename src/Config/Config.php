<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\Config;

use JDZ\Mailer\Exception\ConfigException;

/**
 * Mailer Config
 */
abstract class Config
{
  public bool $valid = false;

  public function setProperties(array $properties = [])
  {
    foreach ($properties as $key => $value) {
      $this->set($key, $value);
    }
  }

  public function set(string $key, mixed $value)
  {
    $this->{$key} = $value;
    return $this;
  }

  /**
   * @throws  ConfigException
   */
  abstract public function check();
}
