<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\AltBody;

use JDZ\Mailer\AltBody\AltBodyInterface;
use Soundasleep\Html2Text;

/**
 * Alt Body formatter 
 * Soundasleep HtmlToText proxy
 */
class SoundasleepAltBody implements AltBodyInterface
{
  public function convert(string $html): string
  {
    return Html2Text::convert($html, [
      'ignore_errors' => false,
      'drop_links' => true,
      'char_set' => 'auto',
    ]);
  }
}
