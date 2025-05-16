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
 * 
 * templateOnly: if true, the content will be ignored and only the template will be used
 * isHtml: if true, the content will be treated as HTML
 * maxMailContentWidth: max width of the mail content in px
 * style: style to be added to the content
 * template: template to be used for the content
 * content: content to be used for the mail
 * Body: body of the mail
 * AltBody: alternative body of the mail
 * replacements: replacements to be used in the content
 * altBodyFormatter: formatter to be used for the alternative body
 */
class ContentConfig extends Config
{
  public bool $isHtml = false;
  public bool $templateOnly = false; // if true, the content will be ignored and only the template will be used
  public int $maxMailContentWidth = 0;
  public string $style = '';
  public string $template = '{{BODY}}';
  public string $content = '';

  public string $Body = '';
  public string $AltBody = '';

  public array $replacements = [];

  public AltBodyInterface $altBodyFormatter;

  public function check(): void
  {
    if (false === $this->templateOnly) {
      // if not templateOnly, we need to check if the content is set
      if ('' === $this->content) {
        throw new ConfigException('No message content nor template was set');
      }

      // and if the template is set, we need to check if it contains the {{BODY}} placeholder
      if ('' !== $this->template && !preg_match('/{{BODY}}/', $this->template)) {
        throw new ConfigException('Template must contain {{BODY}} placeholder to allow the content to be inserted');
      }
    } else {
      if ('' === $this->template) {
        throw new ConfigException('Template is not set but templateOnly is set to true');
      }
    }

    $this->prepareBody();

    $this->Body = $this->replaceInContent($this->Body);

    $this->valid = true;
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

  private function prepareBody(): void
  {
    if (true === $this->templateOnly) {
      $this->maxMailContentWidth = 0;
      $this->isHtml = true;
      $this->Body = $this->template;
      return;
    }

    // if no AltBody is set, we need to set it to the content converted to plain text
    $this->content = $this->replaceInContent($this->content);

    if ('' === $this->AltBody) {
      $this->AltBody = $this->formatAltBody($this->content);
    }

    // content is not HTML : the Body is the AltBody
    if (false === $this->isHtml) {
      $this->Body = $this->AltBody;
      $this->AltBody = '';
      return;
    }

    // wrap the content if needed
    if ($this->template && preg_match('/{{BODY}}/', $this->template)) {
      $this->Body = str_replace("{{BODY}}", $this->content, $this->formatTemplate());
    } else {
      $this->Body = $this->content;
    }

    $this->Body = $this->replaceInContent($this->Body);
  }

  private function replaceInContent(string $content): string
  {
    if ('' === $content || empty($this->replacements)) {
      return $content;
    }

    return str_replace(array_keys($this->replacements), array_values($this->replacements), $content);
  }
}
