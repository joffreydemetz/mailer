<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\Config;

use JDZ\Mailer\Config\Config;
use JDZ\Mailer\Exception\ConfigException;
use JDZ\Mailer\AltBody\AltBodyInterface;

/**
 * Mailer HTML Config
 */
class ContentConfig extends Config
{
  public bool $isHtml = false;
  public int $maxMailContentWidth = 600;
  public string $style = '';
  public string $template = '';
  public string $content = '';

  public string $Body = '';
  public string $AltBody = '';

  public AltBodyInterface $altBodyFormatter;

  public function check()
  {
    if ('' === $this->content) {
      throw new ConfigException('No Message Content was set');
    }

    if ('' === $this->AltBody) {
      $this->AltBody = $this->formatAltBody($this->content);
    }

    if (false === $this->isHtml) {
      $this->Body = $this->AltBody;
      $this->AltBody = '';
      return true;
    }

    $this->Body = str_replace("{{BODY}}", $this->content, $this->formatTemplate());

    return true;
  }

  protected function formatTemplate(): string
  {
    if ($this->maxMailContentWidth > 0) {
      return '<div style="max-width:' . $this->maxMailContentWidth . 'px;margin:0 auto;">' . $this->template . '</div>';
    }
    return $this->template;
  }

  protected function formatAltBody(string $html): string
  {
    if ('' === $html) {
      return '';
    }

    return $this->altBodyFormatter->convert($html);
  }
}
