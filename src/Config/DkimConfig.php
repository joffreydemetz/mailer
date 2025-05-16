<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\Config;

use JDZ\Mailer\Config\Config;
use JDZ\Mailer\Exception\ConfigException;

/**
 * DKIM Config
 */
class DkimConfig extends Config
{
  public bool $use = false;

  public string $domain = '';
  public string $identity = '';
  public string $private = '';
  public string $selector = '';
  public string $passphrase = '';

  public function set(string $key, mixed $value)
  {
    $this->use = true;
    return parent::set($key, $value);
  }

  public function check(): void
  {
    if (false === $this->use) {
      return;
    }

    if ('' === $this->domain) {
      throw new ConfigException('Missing DKIM "domain"');
    }

    if ('' === $this->identity) {
      throw new ConfigException('Missing DKIM "identity"');
    }

    if ('' === $this->private) {
      throw new ConfigException('Missing DKIM "private" key');
    }

    if ('' === $this->selector) {
      throw new ConfigException('Missing DKIM "selector" key');
    }

    $this->valid = true;
  }
}
