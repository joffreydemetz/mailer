<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer;

class Attachment
{
  public string $path;
  public string $name;
  public string $encoding;
  public string $type;
  public string $disposition;

  public function __construct(string $path, string $name = '', string $encoding = '', string $type = '', string $disposition = '')
  {
    if ('' === $encoding) {
      $encoding = 'base64';
    }

    if ('' === $type) {
      $type = 'application/octet-stream';
    }

    if ('' === $disposition) {
      $disposition = 'attachment';
    }

    $this->path = $path;
    $this->name = $name;
    $this->encoding = $encoding;
    $this->type = $type;
    $this->disposition = $disposition;
  }
}
