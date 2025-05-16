<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\Sender;

use JDZ\Mailer\Mailer;
use JDZ\Mailer\Exception\Exception;

/**
 * Native Mailer container
 * send a mail with the native mail() function
 */
class NativeMailSender
{
  const WORDWRAP_MAXLENGTH_HTML = 76;
  const WORDWRAP_MAXLENGTH_TEXT = 300;

  private Mailer $mailer;

  public string $subject = 'Mail Subject';
  public string $timestamp;
  public string $messageId;
  public string $boundary;
  public string $xMailer;
  public int $xPriority = 1;

  public function setMailer(Mailer $mailer)
  {
    $this->mailer = $mailer;
    return $this;
  }

  public function send()
  {
    $this->timestamp = $this->mailer->timestamp;
    $this->messageId = md5((string)$this->mailer->from->email . (string)time()) . '@' . $this->mailer->domain;
    $this->boundary = 'PHP-alt-' . md5(time());
    $this->xMailer = 'PHP/' . \phpversion();

    try {
      if (false === mail($this->formatTo(), $this->subject, $this->formatBody(), $this->formatHeaders())) {
        throw new Exception('Error sending mail');
      }
    } catch (\Throwable $e) {
      throw new Exception($e->getMessage(), $e->getCode(), $e);
    }
  }

  protected function formatTo(): string
  {
    $to = [];
    foreach ($this->mailer->recipients as $person) {
      $to[] = (string)$person;
      //$to[] = $person->email;
    }
    return implode(', ', $to);
  }

  protected function formatHeaders(): array
  {
    $headers = [];

    $headers['Date'] = $this->timestamp;
    $headers['Message-ID'] = '<' . $this->messageId . '>';
    $headers['From'] = (string)$this->mailer->from;

    if ($this->mailer->replyTos) {
      foreach ($this->mailer->replyTos as $person) {
        $headers['Reply-To'][] = (string)$person;
      }
      $headers['Reply-To'] = implode(', ', $headers['Cc']);
    }

    if ($this->mailer->ccs) {
      foreach ($this->mailer->ccs as $person) {
        $headers['Cc'][] = (string)$person;
      }
      $headers['Cc'] = implode(', ', $headers['Cc']);
    }

    if ($this->mailer->bccs) {
      foreach ($this->mailer->bccs as $person) {
        $headers['Bcc'][] = (string)$person;
      }
      $headers['Bcc'] = implode(', ', $headers['Bcc']);
    }

    $headers['X-Sender'] = (string)$this->mailer->from;
    $headers['X-Mailer'] = (string)$this->xMailer;
    $headers['X-Priority'] = (string)$this->xPriority;
    $headers['Return-Path'] = $this->mailer->from->email;
    $headers['Content-Transfer-Encoding'] = 'quoted-printable';
    $headers['MIME-Version'] = '1.0';

    if (count($this->mailer->attachments) > 0) {
      $headers['Content-type'] = 'multipart/mixed; boundary="' . $this->boundary . '"';
    } elseif ('' !== $this->mailer->content->AltBody) {
      $headers['Content-type'] = 'multipart/alternative; boundary="' . $this->boundary . '"';
    } elseif (true === $this->mailer->content->isHtml) {
      $headers['Content-type'] = 'text/html; charset=' . $this->mailer->charset;
    } else {
      $headers['Content-type'] = 'text/plain; charset=' . $this->mailer->charset;
    }

    return $headers;
  }

  protected function formatBody(): string
  {
    $withBoundaries = false;
    if ('' !== $this->mailer->content->AltBody) {
      $withBoundaries = true;
    }
    if (!empty($this->mailer->attachments)) {
      $withBoundaries = true;
    }

    $body = [];

    if ('' !== $this->mailer->content->AltBody) {
      $body[] = '--' . $this->boundary;
      $body[] = 'Content-Type: text/plain; charset=' . $this->mailer->charset;
      $body[] = 'Content-Transfer-Encoding: 8bit';
      $body[] = '';
      $body[] = wordwrap($this->mailer->content->AltBody, self::WORDWRAP_MAXLENGTH_TEXT, "\r\n");
    }

    if (true === $withBoundaries) {
      $body[] = '';
      $body[] = '--' . $this->boundary;
    }

    if (true === $this->mailer->content->isHtml) {
      if (true === $withBoundaries) {
        $body[] = 'Content-Type: text/html; charset=' . $this->mailer->charset;
        $body[] = 'Content-Transfer-Encoding: 8bit';
        $body[] = '';
      }

      $body[] = '<html>';
      $body[] = ' <head>';
      $body[] = '  <meta http-equiv="Content-Type" content="text/html; charset=' . $this->mailer->charset . '" />';
      $body[] = '  <title>' . $this->subject . '</title>';
      $body[] = ' </head>';
      $body[] = ' <body>';
      $body[] = wordwrap($this->mailer->content->Body, self::WORDWRAP_MAXLENGTH_HTML, "\r\n");
      $body[] = ' </body>';
      $body[] = '</html>';
    } else {
      if (true === $withBoundaries) {
        $body[] = 'Content-Type: text/plain; charset=' . $this->mailer->charset;
        $body[] = 'Content-Transfer-Encoding: 8bit';
        $body[] = '';
      }

      $body[] = wordwrap($this->mailer->content->Body, self::WORDWRAP_MAXLENGTH_TEXT, "\r\n");
    }

    if (!empty($this->mailer->attachments)) {
      foreach ($this->mailer->attachments as $attachement) {
        $file = \fopen($attachement->path, "rb");
        $data = \fread($file, \filesize($attachement->path));
        \fclose($file);
        $data = \chunk_split(\base64_encode($data));

        $body[] = '';
        $body[] = '--' . $this->boundary;
        $body[] = 'Content-Type: ' . $attachement->type . '; name="' . $attachement->name . '"';
        $body[] = 'Content-Transfer-Encoding: ' . $attachement->encoding . '';
        $body[] = 'Content-Disposition: ' . $attachement->disposition . '; filename="' . $attachement->name . '';
        $body[] = '';
        $body[] = $data;
        $body[] = '';
      }
    }

    if (true === $withBoundaries) {
      $body[] = '';
      $body[] = '--' . $this->boundary;
    }

    return implode("\r\n", $body);
  }
}
