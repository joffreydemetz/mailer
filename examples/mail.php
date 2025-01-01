<?php
require_once realpath(__DIR__ . '/../vendor/autoload.php');

use JDZ\Mailer\Mailer as jMailer;

try {
    $mail = new jMailer();

    $mail->setProperties([
        'localMode' => true,
        'useFallback' => false,
        'noReply' => false,
        'domain' => '',
        'timestamp' => (new \DateTime())->format(\DATE_RFC3339),
        'language' => '',
        'charset' => '',
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
        'auth' => true,
        'host' => '',
        'user' => '',
        'pass' => '',
        'secure' => 'tls',
    ]);

    $mail->setDKIM([
        'domain' => '',
        'identity' => '',
        'private' => '',
        'selector' => '',
        'passphrase' => '',
    ]);

    $mail->setFrom('johndoe@test.com', 'John Doe');

    $mail->addRecipient('joe@test.com', 'Joe');
    $mail->addRecipient('jen@test.com', 'Jen');

    $mail->addCc('joe2@test.com', 'Joe CC');
    $mail->addBcc('joe3@test.com', 'Joe BCC');

    $mail->addRecipients([
        ['email' => 'jack@test.com', 'name' => 'Joe'],
        ['email' => 'cindy@test.com', 'name' => 'Cindy'],
    ]);

    //$mail->setNoReply('no-reply@test.com', 'No Reply');
    $mail->addReplyTo('johndoe@test.com', 'John Doe');
    $mail->addReplyTo('janedoe@test.com', 'Jane Doe');

    //$mail->addReplyTos([
    //    ['email' => 'johndoe@test.com', 'name' => 'John Doe'],
    //    ['email' => 'janedoe@test.com', 'name' => 'Jane Doe'],
    //]);

    //$mail->addAttachments([]);

    /**
     * OR
     */
    /* $mail->setProperties([
        'localMode' => true,
        'useFallback' => false,
        'domain' => '',
        'timestamp' => (new \DateTime())->format(\DATE_RFC3339),
        'language' => 'fr', // for phpmailer
        'charset' => 'utf-8',
        'content' => [
            'isHtml' => true,
            'maxMailContentWidth' => 600,
            'style' => '',
            'template' => '',
            'content' => '',
            //'Body' => '',
            //'AltBody' => '',
        ],
        'smtp' => [],
        'dkim' => [],
        'from' => ['email' => 'johndoe@test.com', 'name' => 'John Doe'],
        'noReply' => ['email' => '', 'name' => ''],
        //'recipient' => ['email' => '', 'name' => ''],
        //'replyTo' => ['email' => '', 'name' => ''],
        'cc' => ['email' => '', 'name' => ''],
        'bcc' => ['email' => '', 'name' => ''],
        'recipients' => [
            ['email' => 'jack@test.com', 'name' => 'Joe'],
            ['email' => 'cindy@test.com', 'name' => 'Cindy'],
        ],
        'replyTos' => [
            ['email' => 'johndoe@test.com', 'name' => 'John Doe'],
            ['email' => 'janedoe@test.com', 'name' => 'Jane Doe'],
        ],
        'ccs' => [
            ['email' => 'joe2@test.com', 'name' => 'Joe CC'],
            ['email' => '', 'name' => ''],
        ],
        'bccs' => [
            ['email' => 'joe3@test.com', 'name' => 'Joe BCC'],
        ],
        'attachments' => [],
    ]);*/

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
