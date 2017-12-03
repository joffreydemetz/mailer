<?php
/**
 * Joffrey Demetz <joffrey.demetz@gmail.com>
 * <http://joffreydemetz.com>
 */
namespace JDZ\Mailer;

/**
 * Email helper class
 * 
 * Provides static methods to send mails easily
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class MailerHelper
{
  /**
   * Send an email proxy
   *
   * @param   array           $config         Mailer config
   * @param   array           $data           Arguments
   *                           - recepient    array|string  Recipient (either an array [0]=>emails [1]=>names or just an email)
   *                           - subject      string        Subject
   *                           - body         string        Message body
   *                           - attachment   array|string  Attachment files (either an array [0]=>attachments [1]=>names or just an attachment)
   *                           - cc           array|string  CC (either an array [0]=>emails [1]=>names or just an email)
   *                           - bcc          array|string  BCC (either an array [0]=>emails [1]=>names or just an email)
   *                           - replyTo      array|string  Reply to (either an array [0]=>emails [1]=>names or just an email)
   * @return   boolean  True on success
   */
  public static function sendMail(array $config, array $data)
  {
    $config = array_merge([
      'type' => 'mail',
      'smtp' => null,
      'sendmail' => null,
      'from' => null,
      'fromname' => null,
    ], $config);
    
    $data = array_merge([
      'recepient' => null,
      'subject' => null,
      'body' => null,
      'replyTo' => null,
      'attachment' => null,
      'cc' => null,
      'bcc' => null,
    ], $data);
    
    foreach($data as $key => $value){
      $$key = $value;
    }
    
    $mailer = new Mailer();
    $mailer->setMailerConfig($config['type'], $config['smtp'], $config['sendmail']);
    $mailer->setFrom($config['from'], $config['fromname'], 0);
    
    if ( is_array($recepient) ){
      if ( isset($recepient[1]) ){
        $mailer->setAddressFor('Address', $recepient[0], $recepient[1]);
      }
      else {
        $mailer->setAddressFor('Address', $recepient[0]);
      }
    }
    else {
      $mailer->setAddressFor('Address', $recepient);
    }
    
    if ( $cc !== null ){
      if ( is_array($cc) ){
        $mailer->setAddressFor('CC', $cc[0], $cc[1]);
      }
      else {
        $mailer->setAddressFor('CC', $cc);
      }
    }
    
    if ( $bcc !== null ){
      if ( is_array($bcc) ){
        $mailer->setAddressFor('BCC', $bcc[0], $bcc[1]);
      }
      else {
        $mailer->setAddressFor('BCC', $bcc);
      }
    }
    
    if ( $replyTo !== null ){
      if ( is_array($replyTo) ){
        $mailer->setAddressFor('ReplyTo', $replyTo[0], $replyTo[1]);
      }
      else {
        $mailer->setAddressFor('ReplyTo', $replyTo);
      }
    }
    
    if ( $attachment !== null ){
      if ( is_array($attachment) ){
        $mailer->addAttachment($attachment[0], $attachment[1]);
      }
      else {
        $mailer->addAttachment($attachment);
      }
    }
    
    $mailer->setSubject($subject);
    $mailer->setBody($body);
    
    $send = $mailer->Send();
    
    return ( $send === true );
  }

  /**
   * Cleans single line inputs.
   *
   * @param   string  $value  String to be cleaned.
   * @return   string  Cleaned string.
   */
  public static function cleanLine($value)
  {
    return trim(preg_replace('/(%0A|%0D|\n+|\r+)/i', '', $value));
  }
  
  /**
   * Cleans multi-line inputs.
   *
   * @param   string  $value  Multi-line string to be cleaned.
   * @return   string  Cleaned multi-line string.
   */
  public static function cleanText($value)
  {
    return trim(preg_replace('/(%0A|%0D|\n+|\r+)(content-type:|to:|cc:|bcc:)/i', '', $value));
  }

  /**
   * Cleans any injected headers from the email body.
   *
   * @param   string  $body  email body string.
   * @return   string  Cleaned email body string.
   */
  public static function cleanBody($body)
  {
    // Strip all email headers from a string
    return preg_replace("/((From:|To:|Cc:|Bcc:|Subject:|Content-type:) ([\S]+))/", "", $body);
  }

  /**
   * Cleans any injected headers from the subject string.
   *
   * @param   string  $subject  email subject string.
   * @return   string  Cleaned email subject string.
   */
  public static function cleanSubject($subject)
  {
    return preg_replace("/((From:|To:|Cc:|Bcc:|Content-type:) ([\S]+))/", "", $subject);
  }
}
