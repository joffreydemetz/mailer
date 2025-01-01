<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\AltBody;

use JDZ\Mailer\AltBody\AltBodyInterface;

/**
 * Alt Body formatter 
 * Basic formmatter
 */
class BasicAltBody implements AltBodyInterface
{
  public function convert(string $html): string
  {
    return \html_entity_decode(
      trim(strip_tags(preg_replace('/<(head|title|style|script)[^>]*>.*?<\/\\1>/si', '', $html))),
      \ENT_QUOTES,
      'utf-8'
    );
  }
}
