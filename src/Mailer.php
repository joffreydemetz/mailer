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
use JDZ\Mailer\Message\EmailBuilder;
use JDZ\Mailer\Transport\DsnBuilder;
use JDZ\Mailer\Address;
use JDZ\Mailer\Attachment;
use JDZ\Mailer\AltBody\BasicAltBody;
use JDZ\Mailer\Exception\Exception;
use JDZ\Mailer\Exception\ConfigException;
use JDZ\Mailer\Exception\MailchimpException;
use JDZ\Mailer\Exception\SmtpException;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Crypto\DkimSigner;

/**
 * Mail — a facade over symfony/mailer: the config keys become a transport DSN
 * (DsnBuilder), the addresses and content a Symfony Email (EmailBuilder).
 */
class Mailer
{
  public string $type = 'mail';
  /** a symfony/mailer DSN set here bypasses the type / smtp / mailchimp configs */
  public string $dsn = '';
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
  public array $tags = [];
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

      case 'tags':
        $this->addTags($value);
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

  public function addTags(array $tags)
  {
    foreach ($tags as $tag) {
      $this->addTag($tag);
    }
    return $this;
  }

  public function addTag(string $tag)
  {
    if ('' !== $tag) {
      $this->tags[] = $tag;
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

      // the signature's i= tag is an address, not a display name
      if ('' === $this->dkim->identity) {
        $this->dkim->identity = $this->from->email;
      }

      if (empty($this->replyTos)) {
        $this->addReplyTo($this->from->email, $this->from->name);
      }
    }

    if ('' === $this->subject) {
      throw new ConfigException('No Subject for this email');
    }

    // with a fallback, a config that fails its check rules its transport out (the
    // error used to be swallowed, leaving the invalid transport in use); without
    // one, it stops the mail
    foreach ([$this->smtp, $this->dkim, $this->mailchimp] as $config) {
      try {
        $config->check();
      } catch (ConfigException $e) {
        if (false === $this->useFallback) {
          throw $e;
        }

        $config->valid = false;
      }
    }

    if ('mailchimp' === $this->type) {
      if (false === $this->mailchimp->valid || false === $this->canUseMailchimp()) {
        $this->mailchimp->valid = false;
        $this->mailchimp->use = false;

        if (false === $this->useFallback) {
          throw new ConfigException('Mailchimp needs an API key and symfony/mailchimp-mailer');
        }

        $this->type = 'smtp';
        $this->check();
        return;
      }
    }

    if ('smtp' === $this->type) {
      if (false === $this->smtp->valid || false === $this->canUseSmtp()) {
        $this->smtp->valid = false;
        $this->smtp->use = false;

        if (false === $this->useFallback) {
          throw new ConfigException('SMTP needs a complete config (host, and user + pass when auth is on)');
        }

        $this->type = 'mail';
        $this->check();
        return;
      }
    }

    if ('mail' === $this->type && false === $this->canUseNativeMailer()) {
      throw new ConfigException('Native mail() function is required to use Mail features');
    }

    if (false === $this->isMailerAvailable()) {
      throw new ConfigException('No mailer available to send the message !');
    }
  }

  /**
   * @throws ConfigException     the config or an address
   * @throws SmtpException       an smtp / sendmail / native transport failure
   * @throws MailchimpException  a Mandrill failure (a rejected recipient is one)
   * @throws Exception           anything else (an unreadable attachment...)
   */
  public function send()
  {
    $this->check();
    $this->content->check();

    $email = (new EmailBuilder())->build($this);

    if (true === $this->dkim->valid && 'mailchimp' !== $this->type) {
      $email = $this->signer()->sign($email);
    }

    try {
      $sent = $this->createTransport((new DsnBuilder())->build($this))->send($email);
    } catch (TransportExceptionInterface $e) {
      if ('mailchimp' === $this->type) {
        throw new MailchimpException($e->getMessage(), (int)$e->getCode(), $e);
      }

      throw new SmtpException($e->getMessage(), (int)$e->getCode(), $e);
    } catch (Exception $e) {
      throw $e;
    } catch (\Throwable $e) {
      throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
    }

    if (null !== $sent && '' !== ($debug = trim($sent->getDebug()))) {
      if ($this->DebugInfos) {
        $this->DebugInfos[] = '';
      }

      $this->DebugInfos = array_merge($this->DebugInfos, preg_split('/\r?\n/', $debug));
    }
  }

  /**
   * The transport of a DSN (symfony/mailer's factories, the Mailchimp bridge
   * when installed). Overridden by the tests with an in-memory transport.
   */
  protected function createTransport(string $dsn): TransportInterface
  {
    return Transport::fromDsn($dsn);
  }

  protected function signer(): DkimSigner
  {
    try {
      return new DkimSigner(
        $this->dkim->private,
        $this->dkim->domain,
        $this->dkim->selector,
        ['headers_to_ignore' => []],
        $this->dkim->passphrase
      );
    } catch (\Throwable $e) {
      throw new ConfigException('DKIM: ' . $e->getMessage(), 0, $e);
    }
  }

  protected function isMailerAvailable()
  {
    return true === $this->canUseSmtp() || true === $this->canUseNativeMailer() || true === $this->canUseMailchimp();
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

  protected function canUseSmtp(): bool
  {
    return \class_exists(EsmtpTransport::class);
  }

  protected function canUseMailchimp(): bool
  {
    return \class_exists(MandrillTransportFactory::class);
  }

  protected function canUseNativeMailer(): bool
  {
    return \function_exists('mail');
  }
}
