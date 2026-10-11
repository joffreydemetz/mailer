# Mailer

A small facade over [symfony/mailer](https://symfony.com/doc/current/mailer.html): the same `Mailer` you configure with `set()` / `setSMTP()` / `setMailchimp()` / `setContent()`, sent through Symfony's transports — SMTP, sendmail, PHP's native mail, Mailchimp Transactional (Mandrill) — with HTML + plain-text alternatives, attachments, DKIM signing and an optional failover between transports.

## Installation

```bash
composer require jdz/mailer
```

Optional:

```bash
composer require symfony/mailchimp-mailer    # Mailchimp Transactional (type mailchimp)
composer require ph-7/html-to-text           # PH7 html-to-text converter
composer require soundasleep/html2text       # Soundasleep html-to-text converter
```

## Requirements

- PHP >= 8.2
- symfony/mailer ^7.4 (installed with the package)

## Configuration

Copy `.env.dist` to `.env` and fill in your credentials:

```bash
cp .env.dist .env
```

The `.env` file is gitignored and never committed.

## Usage

### SMTP Email

```php
use Symfony\Component\Dotenv\Dotenv;
use JDZ\Mailer\Mailer;
use JDZ\Mailer\AltBody\BasicAltBody;

(new Dotenv())->loadEnv(__DIR__ . '/.env');

$mail = new Mailer();

$mail->setProperties([
    'domain' => 'example.com',
    'charset' => 'utf-8',
]);

$mail->setContent([
    'isHtml' => true,
    'content' => '<h1>Hello</h1><p>This is a test email.</p>',
    'template' => '<html><body>{{BODY}}</body></html>',
    'altBodyFormatter' => new BasicAltBody(),
]);

$mail->setSMTP([
    'host' => $_ENV['SMTP_HOST'],
    'port' => (int)$_ENV['SMTP_PORT'],
    'secure' => $_ENV['SMTP_SECURE'],   // tls (STARTTLS) or ssl (smtps)
    'auth' => true,
    'user' => $_ENV['SMTP_USER'],
    'pass' => $_ENV['SMTP_PASS'],
]);

$mail->setFrom($_ENV['MAIL_FROM_EMAIL'], $_ENV['MAIL_FROM_NAME']);
$mail->addRecipient('recipient@example.com', 'Recipient');
$mail->set('subject', 'Hello from JDZ Mailer');

$mail->send();
```

`localMode = true` skips the TLS peer verification (a local SMTP with a self-signed certificate). The SMTP conversation of the send is in `$mail->DebugInfos`.

### A symfony/mailer DSN

A DSN set on the mailer bypasses the `type` / `smtp` / `mailchimp` configs and is handed to Symfony as is:

```php
$mail->dsn = $_ENV['MAILER_DSN'];   // smtp://user:pass@host:587, sendmail://default, native://default, failover(...)...
```

### Mailchimp Transactional

Needs `symfony/mailchimp-mailer`. The mail goes through Mandrill's SMTP endpoint (`mandrill+smtp://`), where every option below is a documented `X-MC-*` header; the tags become `X-MC-Tags`.

```php
$mail = new Mailer();

$mail->setContent([
    'isHtml' => true,
    'content' => '<p>Newsletter content</p>',
    'altBodyFormatter' => new BasicAltBody(),
]);

$mail->setMailchimp([
    'apiKey' => $_ENV['MAILCHIMP_API_KEY'],
    'user' => 'My account',          // the SMTP username, any string; the from address when empty
    'track_opens' => true,
    'track_clicks' => true,
    'preserve_recipients' => true,
    'auto_text' => true,
    'auto_html' => false,
]);

$mail->setFrom('news@example.com', 'News');
$mail->addRecipient('subscriber@example.com', 'Subscriber');
$mail->addTag('newsletter');
$mail->set('subject', 'Monthly Newsletter');
$mail->send();
```

A recipient Mandrill rejects is an SMTP error: a `MailchimpException`.

### Native mail() and sendmail

With no SMTP nor Mailchimp config, the mail goes through Symfony's `native://default` transport (PHP's `sendmail_path`, or the ini SMTP settings on Windows). `type = 'sendmail'` uses `sendmail://default`, `type = 'qmail'` the qmail binary.

```php
$mail = new Mailer();
$mail->domain = 'example.com';
$mail->setFrom('sender@example.com', 'Sender');
$mail->addRecipient('recipient@example.com');
$mail->set('subject', 'Simple email');
$mail->content->setProperties([
    'content' => 'Plain text message',
    'altBodyFormatter' => new BasicAltBody(),
]);
$mail->send();
```

### Fallback

With `useFallback`, a config that fails its check rules its transport out at `check()` time (mailchimp → smtp → mail), and the transports that remain are chained in a Symfony `failover()` DSN: a transport that is down at send time falls through to the next one.

```php
$mail->useFallback = true;  // mailchimp -> smtp -> mail()
```

### DKIM

```php
$mail->setDKIM([
    'domain' => 'example.com',    // defaults to the mailer's domain
    'selector' => 'mail',
    'private' => $privateKeyPem,
    'passphrase' => '',
]);
```

The message is signed with `Symfony\Component\Mime\Crypto\DkimSigner` (rsa-sha256, relaxed/relaxed) before the SMTP, sendmail or native transport. Mandrill signs on its own side: a Mailchimp mail is not signed here. symfony/mime signs as the domain (`i=@domain`): the `identity` key is accepted but not used.

### Recipients

```php
$mail->addRecipient('to@example.com', 'To');
$mail->addCc('cc@example.com', 'CC');
$mail->addBcc('bcc@example.com', 'BCC');
$mail->addReplyTo('reply@example.com', 'Reply To');

// Or bulk:
$mail->addRecipients([
    ['email' => 'alice@example.com', 'name' => 'Alice'],
    ['email' => 'bob@example.com', 'name' => 'Bob'],
]);

// No-reply (prevents further addReplyTo calls):
$mail->setNoReply('noreply@example.com');
```

An address that is not RFC-compliant is a `ConfigException` at `send()`.

### Attachments

```php
$mail->addAttachment('/path/to/file.pdf', 'report.pdf', '', 'application/pdf');
$mail->addAttachment('/path/to/logo.png', 'logo.png', '', 'image/png', 'inline');   // embedded
```

The name defaults to the file's; the `encoding` argument is accepted for compatibility and ignored (Symfony picks it). An HTML mail with a text alternative and attachments is a `multipart/mixed` holding a `multipart/alternative`.

### Content Replacements

```php
$mail->setContent([
    'isHtml' => true,
    'content' => '<p>Hello {{NAME}}, your order #{{ORDER}} is ready.</p>',
    'replacements' => [
        '{{NAME}}' => 'John',
        '{{ORDER}}' => '12345',
    ],
    'altBodyFormatter' => new BasicAltBody(),
]);
```

### Template-Only Mode

Use a full template without separate content:

```php
$mail->setContent([
    'templateOnly' => true,
    'template' => '<html><body><h1>Full HTML template</h1></body></html>',
    'altBodyFormatter' => new BasicAltBody(),
]);
```

## Alt Body Formatters

Three HTML-to-plain-text converters are available:

- `BasicAltBody` - Built-in, uses `strip_tags()` (no extra dependency)
- `Ph7AltBody` - Uses `ph-7/html-to-text`
- `SoundasleepAltBody` - Uses `soundasleep/html2text`

## Exceptions

- `ConfigException` - Invalid configuration (missing fields, bad values, an invalid address, an unreadable DKIM key)
- `SmtpException` - An SMTP, sendmail or native transport failure (Symfony's `TransportExceptionInterface`, available through `getPrevious()`)
- `MailchimpException` - A Mandrill failure, a rejected recipient included
- `Exception` - Anything else (an unreadable attachment...)

```php
try {
    $mail->send();
} catch (\JDZ\Mailer\Exception\ConfigException $e) {
    // Bad configuration
} catch (\JDZ\Mailer\Exception\SmtpException $e) {
    // Transport error
} catch (\JDZ\Mailer\Exception\MailchimpException $e) {
    // Mailchimp error
} catch (\JDZ\Mailer\Exception\Exception $e) {
    // General error
}
```

## Upgrading to 2.0

Nothing changes in a consumer that only uses the facade: every 1.x property, setter, config key and exception is still there. What is different underneath:

- `symfony/mailer` is required; `phpmailer/phpmailer` and `mailchimp/transactional` are not used any more (`composer remove` them if nothing else needs them). Mailchimp needs `symfony/mailchimp-mailer`.
- `check()` messages: "Mailchimp needs an API key and symfony/mailchimp-mailer" and "SMTP needs a complete config (host, and user + pass when auth is on)" replace the two "... is required" ones.
- `SmtpException` is thrown (the README used to promise it; `Exception` was thrown).
- Mailchimp goes through Mandrill's SMTP endpoint: `mailchimp.user` is the SMTP username (optional).
- The MIME is Symfony's: the text alternative is nested in the mixed part with the attachments (mail clients showed both versions), the subject is RFC 2047-encoded, `Date` and `Message-ID` are generated when not set, `X-Priority: 1` only on an important mail.
- `DebugInfos` holds the SMTP conversation Symfony reports.
- DKIM signs as the domain (`i=@domain`); the `identity` key is not used.
- `type = 'sendmail'` / `'qmail'` are real sendmail transports (they used to go through `mail()`).
- `language` is kept and unused.

## Changelog

- **2.0.0** (2026-10-11) — on symfony/mailer: `DsnBuilder` (configs → DSN, `failover()` with `useFallback`, a `dsn` property to bypass the configs), `EmailBuilder` (facade → `Email`, Mandrill `X-MC-*` headers, DKIM through `DkimSigner`); `SmtpException` thrown on a transport failure, a rejected Mandrill recipient is a `MailchimpException`; the senders, PHPMailer, the Mailchimp API client and the hand-rolled MIME are gone. See *Upgrading to 2.0*.

## Testing

```bash
composer test
# or
vendor/bin/phpunit
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
