<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\Message;

use JDZ\Mailer\Address;
use JDZ\Mailer\Exception\ConfigException;
use JDZ\Mailer\Mailer;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address as MimeAddress;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * The symfony/mime Email of a checked Mailer: addresses, subject, the html and
 * text bodies (multipart/alternative, nested in multipart/mixed when there are
 * attachments), the attachments, the priority, and the Mandrill X-MC-* headers
 * when the mail goes through Mailchimp.
 */
class EmailBuilder
{
  public function build(Mailer $mailer): Email
  {
    $email = new Email();

    try {
      if (null !== $mailer->from) {
        $email->from($this->address($mailer->from));
      }

      foreach ($mailer->recipients as $recipient) {
        $email->addTo($this->address($recipient));
      }

      foreach ($mailer->replyTos as $replyTo) {
        $email->addReplyTo($this->address($replyTo));
      }

      foreach ($mailer->ccs as $cc) {
        $email->addCc($this->address($cc));
      }

      foreach ($mailer->bccs as $bcc) {
        $email->addBcc($this->address($bcc));
      }
    } catch (RfcComplianceException $e) {
      throw new ConfigException('Invalid address: ' . $e->getMessage(), 0, $e);
    }

    $email->subject($mailer->subject);

    if ('' !== $mailer->timestamp) {
      try {
        $email->date(new \DateTimeImmutable($mailer->timestamp));
      } catch (\Exception $e) {
        throw new ConfigException('Invalid timestamp: ' . $mailer->timestamp, 0, $e);
      }
    }

    $this->bodies($mailer, $email);

    foreach ($mailer->attachments as $attachment) {
      $name = '' !== $attachment->name ? $attachment->name : null;

      if ('inline' === $attachment->disposition) {
        $email->embedFromPath($attachment->path, $name, $attachment->type);
      } else {
        $email->attachFromPath($attachment->path, $name, $attachment->type);
      }
    }

    if (true === $mailer->important) {
      $email->priority(Email::PRIORITY_HIGHEST);
    }

    if ('mailchimp' === $mailer->type) {
      $this->mandrillHeaders($mailer, $email);
    }

    return $email;
  }

  private function bodies(Mailer $mailer, Email $email): void
  {
    $content = $mailer->content;

    if (true === $content->isHtml) {
      $email->html($content->Body, $mailer->charset);

      if ('' !== $content->AltBody) {
        $email->text($content->AltBody, $mailer->charset);
      }

      return;
    }

    $email->text($content->Body, $mailer->charset);
  }

  /**
   * The Mandrill SMTP headers (the bridge turns the TagHeaders into X-MC-Tags).
   */
  private function mandrillHeaders(Mailer $mailer, Email $email): void
  {
    $headers = $email->getHeaders();
    $config = $mailer->mailchimp;
    $bool = static fn(bool $value): string => $value ? 'true' : 'false';

    foreach ($mailer->tags as $tag) {
      $headers->add(new TagHeader($tag));
    }

    $track = [];
    if (true === $config->track_opens) {
      $track[] = 'opens';
    }
    if (true === $config->track_clicks) {
      $track[] = 'clicks';
    }
    $headers->addTextHeader('X-MC-Track', $track ? implode(',', $track) : 'none');

    $headers->addTextHeader('X-MC-AutoText', $bool($config->auto_text));
    $headers->addTextHeader('X-MC-AutoHtml', $bool($config->auto_html));
    $headers->addTextHeader('X-MC-PreserveRecipients', $bool($config->preserve_recipients));

    if (true === $mailer->important) {
      $headers->addTextHeader('X-MC-Important', 'true');
    }
  }

  private function address(Address $address): MimeAddress
  {
    return new MimeAddress($address->email, $address->name);
  }
}
