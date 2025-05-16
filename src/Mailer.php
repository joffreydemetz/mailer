<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer;

use JDZ\Mailer\Config\ContentConfig;
use JDZ\Mailer\Config\SmtpConfig;
use JDZ\Mailer\Config\DkimConfig;
use JDZ\Mailer\Config\MailchimpConfig;
use JDZ\Mailer\Sender\NativeMailSender;
use JDZ\Mailer\Sender\PhpmailerSender;
use JDZ\Mailer\Sender\MailchimpSender;
use JDZ\Mailer\Address;
use JDZ\Mailer\Attachment;
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
  public bool $important = false;

  public SMTPConfig $smtp;
  public DkimConfig $dkim;
  public MailchimpConfig $mailchimp;
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
    $this->mailchimp = new MailchimpConfig();
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

      case 'mailchimp':
        $this->setMailchimp($value);
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
        if (property_exists($this, $key) === false) {
          throw new ConfigException('Property ' . $key . ' does not exist in ' . get_class($this));
        }
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
    $this->type = 'smtp';
    $this->dkim->setProperties($data);
    return $this;
  }

  public function setMailchimp(array $data)
  {
    $this->type = 'mailchimp';
    $this->mailchimp->setProperties($data);
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

  public function setAsImportant()
  {
    $this->important = true;
    return $this;
  }

  /**
   * Check the configuration
   * @throws  ConfigException
   * @throws  Exception
   */
  public function check(): void
  {
    if ('mailchimp' !== $this->type) {
      if (empty($this->from)) {
        throw new ConfigException('Missing "from" Email email@domain.tld');
      }

      if ('' === $this->domain) {
        throw new ConfigException('Missing "domain" Sender domain (domain.tld)');
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

      if (empty($this->replyTos)) {
        $this->addReplyTo($this->from->email, $this->from->name);
      }
    }

    if ('' === $this->subject) {
      throw new ConfigException('No Subject for this email');
    }

    try {

      $this->smtp->check();
      $this->dkim->check();
      $this->mailchimp->check();

      if ('mailchimp' === $this->type) {
        if (false === $this->mailchimp->valid || false === $this->canUseMailchimp()) {
          $this->mailchimp->valid = false;
          $this->mailchimp->use = false;

          if (false === $this->useFallback) {
            throw new ConfigException('Mailchimp Transactional API is required to use Mailchimp features');
          }

          $this->type = 'smtp';
          $this->check();
          return;
        }
      }

      if ('smtp' === $this->type) {
        if (false === $this->smtp->valid || false === $this->canUsePhpMailer()) {
          $this->smtp->valid = false;
          $this->smtp->use = false;

          if (false === $this->useFallback) {
            throw new ConfigException('PhpMailer is required to use SMTP features');
          }

          $this->type = 'mail';
          $this->check();
          return;
        }
      }

      if ('mail' === $this->type && false === $this->canUseNativeMailer()) {
        $this->useFallback = false;
        throw new ConfigException('Native mail() function is required to use Mail features');
      }
    } catch (\Throwable $e) {
      if (false === $this->useFallback) {
        throw $e;
      }
    }

    if (false === $this->isMailerAvailable()) {
      throw new ConfigException('No mailer available to send the message !');
    }
  }

  public function send()
  {
    $this->check();
    if ('mailchimp' === $this->type) {
      $sender = new MailchimpSender();
    } elseif ('smtp' === $this->type) {
      $sender = new PhpmailerSender();
    } else {
      $sender = new NativeMailSender();
    }

    $this->content->check();

    $sender->setMailer($this);
    $sender->send();
  }

  protected function isMailerAvailable()
  {
    return true === $this->canUsePhpMailer() || true === $this->canUseNativeMailer() || true === $this->canUseMailchimp();
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

  protected function canUseMailchimp(): bool
  {
    return \class_exists('\\MailchimpTransactional\\ApiClient');
  }

  protected function canUseNativeMailer(): bool
  {
    return \function_exists('mail');
  }
}
