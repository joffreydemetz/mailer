<?php

/**
 * Mailchimp Transactional Test
 *
 * Copy ../.env.dist to ../.env and fill in your credentials.
 *
 * @format      HTML
 * @mailchimp   true
 * @altbody     Basic
 */

error_reporting(E_ALL & ~E_DEPRECATED);

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

    $mail->setContent([
        'isHtml' => true,
        'maxMailContentWidth' => 0,
        'style' => '',
        'template' => '<html><body>{{BODY}}</body></html>',
        'content' => \file_get_contents(__DIR__ . '/mail.html'),
        'altBodyFormatter' => new \JDZ\Mailer\AltBody\BasicAltBody(),
    ]);

    $mail->setMailchimp([
        'apiKey' => $_ENV['MAILCHIMP_API_KEY'] ?? '',
        'track_opens' => true,
        'track_clicks' => true,
        'auto_text' => true,
        'auto_html' => false,
        'preserve_recipients' => true,
        'tags' => [],
    ]);

    $mail->addRecipient($_ENV['MAIL_TO_EMAIL'] ?? '', $_ENV['MAIL_TO_NAME'] ?? '');
    $mail->set('subject', 'Test Email mailchimp');
    $mail->send();

    echo 'Email sent. ';
} catch (\JDZ\Mailer\Exception\MailchimpException $e) {
    echo 'Mail could not be sent. ';
    echo 'Mailchimp error. ';
    echo $e->getMessage();
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