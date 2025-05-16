<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\Config;

use JDZ\Mailer\Config\Config;
use JDZ\Mailer\Exception\ConfigException;

/**
 * Mailchimp Config
 */
class MailchimpConfig extends Config
{
  public bool $use = false;

  public ?string $apiKey = null;
  public bool $track_opens = true;
  public bool $track_clicks = true;
  public bool $auto_text = true;
  public bool $auto_html = false;
  public bool $preserve_recipients = true;
  public array $tags = [];

  public function set(string $key, mixed $value)
  {
    if ('apiKey' === $key && $value && is_string($value)) {
      $this->use = true;
      $this->apiKey = $value;
    }

    return parent::set($key, $value);
  }

  public function setTags(array $tags)
  {
    $this->tags = $tags;
    return $this;
  }

  public function check(): void
  {
    if (false === $this->use) {
      return;
    }

    if (!$this->apiKey) {
      throw new ConfigException('Missing Api Key');
    }

    $this->valid = true;
  }
}
