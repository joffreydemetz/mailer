<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\AltBody;

use JDZ\Mailer\AltBody\AltBodyInterface;
use PH7\HtmlToText\Convert;

/**
 * Alt Body formatter 
 * PH7 proxy
 */
class Ph7AltBody implements AltBodyInterface
{
  public function convert(string $html): string
  {
    $html2Text = new Convert($html);
    return $html2Text->getText();
  }
}
