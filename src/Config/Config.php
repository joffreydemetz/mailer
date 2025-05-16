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
    if (property_exists($this, $key) === false) {
      throw new ConfigException('Property ' . $key . ' does not exist in ' . get_class($this));
    }

    $this->{$key} = $value;
    return $this;
  }

  public function get(string $key): mixed
  {
    return $this->{$key} ?? null;
  }

  /**
   * @throws  ConfigException
   */
  abstract public function check(): void; // check if the config is valid
}
