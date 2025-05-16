<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\Config;

use JDZ\Mailer\Config\Config;
use JDZ\Mailer\Exception\ConfigException;

/**
 * SMTP Config
 */
class SmtpConfig extends Config
{
  public bool $use = false;

  public int $debug = 0;
  public int $port = 587;
  public bool $auth = true;
  public string $host = '';
  public string $user = '';
  public string $pass = '';
  public string $secure = 'tls';

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

    if ('' === $this->host) {
      throw new ConfigException('Missing SMTP host');
    }

    if (true === $this->auth) {
      if ('' === $this->user || '' === $this->pass) {
        throw new ConfigException('Username and password required when SMTP auth is set to true');
      }
    }

    $this->valid = true;
  }
}
