<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\Transport;

use JDZ\Mailer\Mailer;

/**
 * The symfony/mailer DSN of a Mailer: its type and the configs that passed check().
 *
 *   mailchimp  mandrill+smtp://user:APIKEY@default   (the bridge's SMTP transport: every
 *                                                     MailchimpConfig option is an X-MC-* header)
 *   smtp       smtp://user:pass@host:port            (smtps:// when secure is ssl,
 *                                                     ?verify_peer=0 in localMode)
 *   sendmail   sendmail://default
 *   qmail      sendmail://default?command=...
 *   mail       native://default
 *
 * With useFallback, the valid transports are chained in a failover(): a transport
 * that is down at send time falls through to the next one.
 */
class DsnBuilder
{
  public const QMAIL_COMMAND = '/var/qmail/bin/sendmail -t';

  public function build(Mailer $mailer): string
  {
    if ('' !== $mailer->dsn) {
      return $mailer->dsn;
    }

    $dsns = [$this->forType($mailer, $mailer->type)];

    if (true === $mailer->useFallback) {
      foreach ($this->fallbacks($mailer) as $type) {
        $dsns[] = $this->forType($mailer, $type);
      }
    }

    $dsns = array_values(array_unique($dsns));

    if (1 === count($dsns)) {
      return $dsns[0];
    }

    return 'failover(' . implode(' ', $dsns) . ')';
  }

  /**
   * The types a failover may continue with, after the mailer's own: the
   * valid ones, in the mailchimp > smtp > mail order of check().
   *
   * @return list<string>
   */
  private function fallbacks(Mailer $mailer): array
  {
    $types = [];

    if (true === $mailer->mailchimp->valid && true === $mailer->mailchimp->use) {
      $types[] = 'mailchimp';
    }

    if (true === $mailer->smtp->valid && true === $mailer->smtp->use) {
      $types[] = 'smtp';
    }

    $types[] = 'mail';

    return array_values(array_filter($types, static fn(string $type): bool => $type !== $mailer->type));
  }

  private function forType(Mailer $mailer, string $type): string
  {
    return match ($type) {
      'mailchimp' => $this->mailchimp($mailer),
      'smtp' => $this->smtp($mailer),
      'sendmail' => 'sendmail://default',
      'qmail' => 'sendmail://default?command=' . rawurlencode(self::QMAIL_COMMAND),
      default => 'native://default',
    };
  }

  private function mailchimp(Mailer $mailer): string
  {
    $user = $mailer->mailchimp->user;

    if ('' === $user) {
      $user = null !== $mailer->from ? $mailer->from->email : 'mandrill';
    }

    return 'mandrill+smtp://' . rawurlencode($user) . ':' . rawurlencode((string)$mailer->mailchimp->apiKey) . '@default';
  }

  private function smtp(Mailer $mailer): string
  {
    $smtp = $mailer->smtp;
    $scheme = 'ssl' === strtolower($smtp->secure) ? 'smtps' : 'smtp';

    $dsn = $scheme . '://';

    if (true === $smtp->auth) {
      $dsn .= rawurlencode($smtp->user) . ':' . rawurlencode($smtp->pass) . '@';
    }

    $dsn .= $smtp->host;

    if ($smtp->port > 0) {
      $dsn .= ':' . $smtp->port;
    }

    if (true === $mailer->localMode) {
      $dsn .= '?verify_peer=0';
    }

    return $dsn;
  }
}
