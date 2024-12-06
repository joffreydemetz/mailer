<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Mailer\Config;

use JDZ\Mailer\Exception;

/**
 * Mailer HTML Config
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Config_Content
{
  public bool $valid = false;

  public bool $isHtml = false;
  public int $maxMailContentWidth = 600;
  public string $style = '';
  public string $template = '';
  public string $content = '';

  public string $Body = '';
  public string $AltBody = '';

  public function setProperties(array $properties = [])
  {
    foreach ($properties as $key => $value) {
      $this->{$key} = $value;
    }
  }

  public function check()
  {
    if ('' === $this->content) {
      throw new Exception('No Message Content was set');
    }

    $this->valid = true;
  }

  public function prepareBody()
  {
    if ('' === $this->AltBody && '' !== $this->content) {
      $this->AltBody = $this->formatAltBody($this->content);
    }

    if (false === $this->isHtml) {
      $this->Body = $this->AltBody;
      $this->AltBody = '';
      return;
    }

    if ($this->maxMailContentWidth > 0) {
      $this->Body = '<div style="max-width:' . $this->maxMailContentWidth . 'px;margin:0 auto;">' . $this->template . '</div>';
    } else {
      $this->Body = $this->template;
    }

    $this->Body = str_replace("{{BODY}}", $this->content, $this->template);

    return $this;
  }
  
  protected function formatAltBody(string $body): string
  {
    if ('' === $body) {
      return '';
    }
    
    if (!\class_exists('\\JDZ\\Utils\\HtmlToText')) {
      return \html_entity_decode(
        trim(strip_tags(preg_replace('/<(head|title|style|script)[^>]*>.*?<\/\\1>/si', '', $body))),
        \ENT_QUOTES,
        'utf-8'
      );
    }

    $htmlToText = new \JDZ\Utils\HtmlToText();
    return $htmlToText->convert($body, ['ignore_errors' => false, 'drop_links' => false, 'char_set' => 'auto']);
  }
}
