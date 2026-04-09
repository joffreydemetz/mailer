# Mailer

Flexible PHP mailer with SMTP (via PHPMailer), Mailchimp Transactional, and native `mail()` support. Automatic fallback between senders, HTML/plain-text content handling, DKIM signing, and attachments.

## Installation

```bash
composer require jdz/mailer
```

Optional senders (install as needed):

```bash
composer require phpmailer/phpmailer        # SMTP / DKIM / Sendmail
composer require mailchimp/transactional     # Mailchimp Transactional API
```

Optional HTML-to-text converters for the alternative body:

```bash
composer require ph-7/html-to-text           # PH7 converter
composer require soundasleep/html2text       # Soundasleep converter
```

## Requirements

- PHP >= 8.2

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
    'language' => 'fr',
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
    'secure' => $_ENV['SMTP_SECURE'],
    'auth' => true,
    'user' => $_ENV['SMTP_USER'],
    'pass' => $_ENV['SMTP_PASS'],
]);

$mail->setFrom($_ENV['MAIL_FROM_EMAIL'], $_ENV['MAIL_FROM_NAME']);
$mail->addRecipient('recipient@example.com', 'Recipient');
$mail->set('subject', 'Hello from JDZ Mailer');

$mail->send();
```

### Mailchimp Transactional

```php
$mail = new Mailer();

$mail->setContent([
    'isHtml' => true,
    'content' => '<p>Newsletter content</p>',
    'altBodyFormatter' => new BasicAltBody(),
]);

$mail->setMailchimp([
    'apiKey' => $_ENV['MAILCHIMP_API_KEY'],
    'track_opens' => true,
    'track_clicks' => true,
]);

$mail->addRecipient('subscriber@example.com', 'Subscriber');
$mail->set('subject', 'Monthly Newsletter');
$mail->send();
```

### Native mail()

If no SMTP or Mailchimp is configured, the mailer falls back to PHP's native `mail()` function:

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

Enable `useFallback` to automatically fall back to a simpler sender if the preferred one is unavailable:

```php
$mail->useFallback = true;  // mailchimp -> smtp -> mail()
```

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

### Attachments

```php
$mail->addAttachment('/path/to/file.pdf', 'report.pdf', 'base64', 'application/pdf');
```

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

- `ConfigException` - Invalid configuration (missing fields, bad values)
- `SmtpException` - SMTP send failure
- `MailchimpException` - Mailchimp API failure
- `Exception` - General mailer errors

```php
try {
    $mail->send();
} catch (\JDZ\Mailer\Exception\ConfigException $e) {
    // Bad configuration
} catch (\JDZ\Mailer\Exception\SmtpException $e) {
    // SMTP error
} catch (\JDZ\Mailer\Exception\MailchimpException $e) {
    // Mailchimp error
} catch (\JDZ\Mailer\Exception\Exception $e) {
    // General error
}
```

## Testing

```bash
composer test
# or
vendor/bin/phpunit
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
