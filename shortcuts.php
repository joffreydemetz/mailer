<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use JDZ\Mailer\MailerHelper;
 
/**
 * Send a mail
 * 
 * @param   array   $data          Mail data
 *                  - recepient    array|string  Recipient (either an array [0]=>email [1]=>name or just an email)
 *                  - subject      string        Subject
 *                  - body         string        Message body
 *                  - attachment   array|string  Attachment files (either an array [0]=>attachments [1]=>names or just an attachment)
 *                  - cc           array|string  CC (either an array [0]=>emails [1]=>names or just an email)
 *                  - bcc          array|string  BCC (either an array [0]=>emails [1]=>names or just an email)
 *                  - replyTo      array|string  Reply to (either an array [0]=>emails [1]=>names or just an email)
 * @param  array   $config        Mailer config
 * @param  bool    $exceptionOnError Throw a runtime exception on error
 * @return bool    True if mail was sent
 * @throw  RuntimeException
 */
function MailIt(array $data, array $config=[], $exceptionOnError=true)
{
  $data = array_merge([
    'recepient' => null,
    'subject' => null,
    'body' => null,
    'replyTo' => null,
    'attachment' => null,
    'cc' => null,
    'bcc' => null,
  ], $data);
  
  $result = false;
  
  try {
    $result = MailerHelper::sendMail($config, $data);
  }
  catch(MailerException $e){
    if ( $exceptionOnError === true ){
      throw new RuntimeException($e->getMessage());
    }
  }
  
  return $result;
}

