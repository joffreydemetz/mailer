<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer;

use JDZ\Mailer\Config\ContentConfig;
use JDZ\Mailer\Config\SmtpConfig;
use JDZ\Mailer\Config\DkimConfig;
use JDZ\Mailer\Sender\NativeMailSender;
use JDZ\Mailer\Sender\PhpmailerSender;
use JDZ\Mailer\AltBody\BasicAltBody;
use JDZ\Mailer\Exception\Exception;
use JDZ\Mailer\Exception\ConfigException;

/**
 * Mail
 */
class Mailer
{
  public string $type = 'mail';
  public string $domain = '';
  public string $timestamp = '';
  public string $language = 'fr';
  public string $charset = 'utf-8';

  public bool $localMode = false;
  public bool $useFallback = false;

  public SMTPConfig $smtp;
  public DkimConfig $dkim;
  public ContentConfig $content;

  public ?Address $from = null;
  public array $recipients = [];
  public array $replyTos = [];
  public array $ccs = [];
  public array $bccs = [];
  public array $attachments = [];
  public string $subject = '';

  public array $DebugInfos = [];

  private bool $noReply = false;

  public function __construct()
  {
    $this->smtp = new SMTPConfig();
    $this->dkim = new DkimConfig();
    $this->content = new ContentConfig();

    $this->content->altBodyFormatter = new BasicAltBody();
  }

  public function setProperties(array $properties = [])
  {
    foreach ($properties as $key => $value) {
      $this->set($key, $value);
    }
    return $this;
  }

  public function set(string $key, mixed $value)
  {
    switch ($key) {
      case 'content':
        $this->setContent($value);
        break;

      case 'smtp':
        $this->setSMTP($value);
        break;

      case 'dkim':
        $this->setDKIM($value);
        break;

      case 'from':
        $this->setFrom($value['email'], $value['name'] ?? '');
        break;

      case 'noReply':
        $this->setNoReply($value['email'], $value['name'] ?? '');
        break;

      case 'recipient':
        $this->addRecipient($value['email'], $value['name'] ?? '');
        break;

      case 'replyTo':
        $this->addReplyTo($value['email'], $value['name'] ?? '');
        break;

      case 'cc':
        $this->addCc($value['email'], $value['name'] ?? '');
        break;

      case 'bcc':
        $this->addBcc($value['email'], $value['name'] ?? '');
        break;

      case 'recipients':
        $this->addRecipients($value);
        break;

      case 'replyTos':
        $this->addReplyTos($value);
        break;

      case 'ccs':
        $this->addCcs($value);
        break;

      case 'bccs':
        $this->addBccs($value);
        break;

      case 'attachments':
        $this->addAttachments($value);
        break;

      default:
        $this->{$key} = $value;
        break;
    }

    return $this;
  }

  public function setSMTP(array $data)
  {
    $this->type = 'smtp';
    $this->smtp->setProperties($data);
    return $this;
  }

  public function setDKIM(array $data)
  {
    $this->dkim->setProperties($data);
    return $this;
  }

  public function setContent(array $data)
  {
    $this->content->setProperties($data);
    return $this;
  }

  public function setFrom(string $email, string $name = '')
  {
    if ($address = $this->checkAddress($email, $name)) {
      $this->from = $address;
    }
    return $this;
  }

  public function addRecipients(array $people)
  {
    foreach ($people as $person) {
      $this->addRecipient($person['email'], $person['name'] ?? '');
    }
    return $this;
  }

  public function addRecipient(string $email, string $name = '')
  {
    if ($address = $this->checkAddress($email, $name)) {
      $this->recipients[] = $address;
    }
    return $this;
  }

  public function setNoReply(string $email, string $name = 'No Reply')
  {
    $this->noReply = true;

    $this->replyTos = [];
    if ($address = $this->checkAddress($email, $name)) {
      $this->replyTos[] = $address;
    }
    return $this;
  }

  public function addReplyTos(array $people)
  {
    foreach ($people as $person) {
      $this->addReplyTo($person['email'], $person['name'] ?? '');
    }
    return $this;
  }

  public function addReplyTo(string $email, string $name = '')
  {
    if (true === $this->noReply) {
      throw new ConfigException('Trying to set a reply to address after setting a noReply one');
    }

    if ($address = $this->checkAddress($email, $name)) {
      $this->replyTos[] = $address;
    }
    return $this;
  }

  public function addCcs(array $people)
  {
    foreach ($people as $person) {
      $this->addCc($person['email'], $person['name'] ?? '');
    }
    return $this;
  }

  public function addCc(string $email, string $name = '')
  {
    if ($address = $this->checkAddress($email, $name)) {
      $this->ccs[] = $address;
    }
    return $this;
  }

  public function addBccs(array $people)
  {
    foreach ($people as $person) {
      $this->addBcc($person['email'], $person['name'] ?? '');
    }
    return $this;
  }

  public function addBcc(string $email, string $name = '')
  {
    if ($address = $this->checkAddress($email, $name)) {
      $this->bccs[] = $address;
    }
    return $this;
  }

  public function addAttachments(array $attachments)
  {
    foreach ($attachments as $attachment) {
      $this->addAttachment($attachment['path'] ?? '', $attachment['name'] ?? '', $attachment['encoding'] ?? '', $attachment['type'] ?? '', $attachment['disposition'] ?? '');
    }
    return $this;
  }

  public function addAttachment(string $path, string $name = '', string $encoding = '', string $type = '', string $disposition = '')
  {
    if ($attachment = $this->checkAttachment($path, $name, $encoding, $type, $disposition)) {
      $this->attachments[] = $attachment;
    }
    return $this;
  }

  public function check(): bool
  {
    if ('' === $this->domain) {
      throw new ConfigException('Missing "domain" Sender domain (domain.tld)');
    }

    if (empty($this->from)) {
      throw new ConfigException('Missing "from" Email email@domain.tld');
    }

    if ('' === $this->subject) {
      throw new ConfigException('No Subject for this email');
    }

    if (empty($this->replyTos)) {
      $this->addReplyTo($this->from->email, $this->from->name);
    }

    if ('' === $this->smtp->host) {
      $this->smtp->host = $this->domain;
    }

    if ('' === $this->dkim->domain) {
      $this->dkim->domain = $this->domain;
    }

    if ('' === $this->dkim->identity) {
      $this->dkim->identity = $this->from->name;
    }

    try {

      $this->smtp->check();
      $this->dkim->check();

      if (true === $this->smtp->valid || true === $this->dkim->valid) {
        if (false === $this->canUsePhpMailer()) {
          if (false === $this->useFallback) {
            throw new Exception('PhpMailer is required to use SMTP and DKIM features');
          }

          $this->smtp->valid = false;
          $this->dkim->valid = false;
        }
      }
    } catch (\Throwable $e) {
      if (false === $this->useFallback) {
        throw $e;
      }
    }

    if (false === $this->isMailerAvailable()) {
      throw new ConfigException('No mailer available to send the message !');
    }

    $this->type = true === $this->smtp->valid ? 'smtp' : 'mail';
    $this->content->check();

    return true;
  }

  public function send()
  {
    $this->check();

    if ('mail' === $this->type) {
      $mail = new NativeMailSender();
    } else {
      $mail = new PhpmailerSender();
    }

    $mail->setMailer($this);
    $mail->send();
  }

  protected function isMailerAvailable()
  {
    return true === $this->canUsePhpMailer() || true === $this->canUseNativeMailer();
  }

  protected function checkAddress(string $email, string $name = ''): ?Address
  {
    $address = new Address($email, $name);

    if ('' === $address->email) {
      $address = null;
    }

    return $address;
  }

  protected function checkAttachment(string $path, string $name = '', string $encoding = '', string $type = '', string $disposition = ''): ?Attachment
  {
    $attachment = new Attachment($path, $name, $encoding, $type, $disposition);

    if ('' === $attachment->path) {
      $attachment = null;
    }

    return $attachment;
  }

  protected function canUsePhpMailer(): bool
  {
    return \class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');
  }

  protected function canUseNativeMailer(): bool
  {
    return \function_exists('mail');
  }
}
