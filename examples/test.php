<?php

/**
 * SMTP Test
 *
 * Copy ../.env.dist to ../.env and fill in your credentials.
 *
 * @format      HTML
 * @smtp        true
 * @sender      symfony/mailer (smtp)
 * @altbody     Basic
 */

require_once realpath(__DIR__ . '/../vendor/autoload.php');

use Symfony\Component\Dotenv\Dotenv;
use JDZ\Mailer\Mailer as jMailer;

(new Dotenv())->loadEnv(__DIR__ . '/../.env');

try {
    $mail = new jMailer();

    $mail->setProperties([
        'localMode' => true,
        'useFallback' => false,
        'domain' => 'localhost',
        'timestamp' => (new \DateTime())->format(\DATE_RFC3339),
        'language' => 'fr',
        'charset' => 'utf-8',
    ]);

    $mail->content->setProperties([
        'isHtml' => true,
        'maxMailContentWidth' => 600,
        'style' => '',
        'template' => \file_get_contents(__DIR__ . '/template.html'),
        'content' => \file_get_contents(__DIR__ . '/mail.html'),
        'altBodyFormatter' => new \JDZ\Mailer\AltBody\BasicAltBody(),
    ]);

    $mail->setSMTP([
        'debug' => 0,
        'port' => (int)($_ENV['SMTP_PORT'] ?? 587),
        'secure' => $_ENV['SMTP_SECURE'] ?? 'tls',
        'auth' => filter_var($_ENV['SMTP_AUTH'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'host' => $_ENV['SMTP_HOST'] ?? '',
        'user' => $_ENV['SMTP_USER'] ?? '',
        'pass' => $_ENV['SMTP_PASS'] ?? '',
    ]);

    $mail->setFrom($_ENV['MAIL_FROM_EMAIL'] ?? '', $_ENV['MAIL_FROM_NAME'] ?? '');
    $mail->addRecipient($_ENV['MAIL_TO_EMAIL'] ?? '', $_ENV['MAIL_TO_NAME'] ?? '');
    $mail->set('subject', 'Test Email');
    $mail->send();

    echo 'Email sent. ';
} catch (\JDZ\Mailer\Exception\ConfigException $e) {
    echo 'Mail could not be sent. ';
    echo 'Bad configuration. ';
    echo $e->getMessage();
} catch (\JDZ\Mailer\Exception\SmtpException $e) {
    echo 'Mail could not be sent. ';
    echo 'SMTP error. ';
    echo $e->getMessage();
} catch (\JDZ\Mailer\Exception\Exception $e) {
    echo 'Mail could not be sent. ';
    echo 'Mailer error. ';
    echo $e->getMessage();
}
