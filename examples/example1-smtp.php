<?php

/**
 * Example 1
 * 
 * @format      HTML
 * @smtp        true
 * @sender      PHPmailer
 * @altbody     Basic
 */

require_once realpath(__DIR__ . '/../vendor/autoload.php');

use JDZ\Mailer\Mailer as jMailer;

try {
    $mail = new jMailer();

    $mail->setProperties([
        'localMode' => true,
        'useFallback' => false,
        'domain' => '',
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
        'port' => 587,
        'secure' => 'tls',
        'auth' => true,
        'host' => '',
        'user' => '',
        'pass' => '',
    ]);

    $mail->setFrom('johndoe@test.com', 'John Doe');

    $mail->addRecipient('joe@test.com', 'Joe');

    $mail->check();
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
exit();
