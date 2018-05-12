<?php
/**
 * Joffrey Demetz <joffrey.demetz@gmail.com>
 * <http://joffreydemetz.com>
 */
namespace JDZ\Mailer;

use \PHPMailer;
use \phpmailerException;

/**
 * Mailer Class.
 * 
 * Provides a common interface to send emails extending PHPMailer
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Mailer extends PHPMailer
{
  /**
   * {@inheritDoc}
    */
  public function __construct($exceptions=null)
  {
    parent::__construct($exceptions);
    
    $this->CharSet    = 'utf-8';
    $this->exceptions = true;
    $this->isHTML(true);
    
    $this->setLanguage('fr');
  }
  
  /**
   * Send the mail
   * 
    * @return   bool  True if successful
   * @throws   MailerException
   */
  public function send()
  {
    if ( ($this->Mailer === 'mail') && !function_exists('mail') ){
      throw new MailerException('Mail function is not active ['.get_class($this).']', 'Mailer');
    }
    
    try {
      parent::send();
    }
    catch(phpmailerException $e){
      throw new MailerException($e->getMessage());
    }
    
    return true;
  }
  
  /**
   * Set the email subject
   *
   * @param   string  $subject  Subject of the email
   * @return   void
   */
  public function setSubject($subject)
  {
    $subject = MailerHelper::cleanSubject($subject);
    $this->Subject = $subject;
  }
  
  /**
   * Set the email body
   *
   * @param   string  $body  Body of the email
   * @return   void
   */
  public function setBody($body)
  {
    $body = MailerHelper::cleanBody($body);
    $this->Body = $body;
  }
  
  /**
   * Set the From and FromName properties
   * 
   * @param  string   $address
   * @param  string   $name
   * @param  boolean  $auto     Whether to also set the Sender address, defaults to true
   * @throws phpmailerException
   * @return boolean
   */
  public function setFrom($address, $name='', $auto=true) 
  {
    $address = MailerHelper::cleanLine($address);
    $name    = $name === '' ? '' : MailerHelper::cleanLine($name);
    
    parent::setFrom($address, $name, $auto);
  }
  
  /**
   * Adds a email/name to the email
   *
   * @param   string        $method  Php Mailer method (Address->addAddress, CC->addCC)
   * @param   array|string  $email   Either a string or array of strings [email address(es)]
   * @param   array|string  $name    Either a string or array of strings [name(s)]
   * @return   void
   * @throws   MailerException
   */
  public function setAddressFor($method, $email, $name='')
  {
    // Address
    // CC
    // BCC
    // ReplyTo
    
    if ( !in_array($method, ['Address', 'CC', 'BCC', 'ReplyTo']) || !method_exists($this, 'add'.$method) ){
      throw new MailerException('Unrecognized PHPMailer method');
    }
    
    $method = 'add'.$method;
    
    // If the email is an array, add each email... otherwise just add the one
    if ( is_array($email) ){
      foreach($email as $i => $to){
        $to = MailerHelper::cleanLine($to);
        
        if ( empty($name) ){
          $this->$method($to);
          continue;
        }
        
        if ( is_array($name) ){
          if ( isset($name[$i]) ){
            $_name = MailerHelper::cleanLine($name[$i]);
            $this->$method($to, $_name);
            continue;
          }

          $_name = MailerHelper::cleanLine($name[0]);
          $this->$method($to, $_name);
          continue;
        }
        
        $name = MailerHelper::cleanLine($name);
        $this->$method($to, $name);
      }
      
      return;
    }
    
    $email = MailerHelper::cleanLine($email);
    $name      = $name === '' ? '' : MailerHelper::cleanLine($name);
    $this->$method($email, $name);
  }
  
  /**
   * Add file attachments to the email
   *
   * @param   mixed  $attachment  Either a string or array of strings [filenames]
   * @param   mixed  $name        Either a string or array of strings [names]
   * @param   mixed  $encoding    The encoding of the attachment
   * @param   mixed  $type        The mime type
   * @return   void
   */
  public function addAttachment($path, $name='', $encoding='base64', $type='', $disposition='attachment')
  {
    if ( $type === '' ){
      $type = 'application/octet-stream';
    }
    
    // If the file attachments is an array, add each file... otherwise just add the one
    if ( is_array($path) ){
      foreach($path as $file){
        parent::AddAttachment($file, $name, $encoding, $type, $disposition);
      }
      return;
    }
    
    parent::AddAttachment($path, $name, $encoding, $type, $disposition);
  }
  
  /**
   * Set the mailer config
   *
   * @param   string    $type         Mailer type (mail, sendmail, smtp, qmail)
   * @param   array     $smtp         SMTP config
   * @param   string    $sendmail     The pass to sendmail
   * @return   void
   * @throws   MailerException
   */
  public function setMailerConfig($type, $smtp=null, $sendmail=null)
  {
    $this->Debugoutput = 'html';
    
    if ( $smtp === null && $type === 'stmp' ){
      $type = 'mail';
    }
    if ( $sendmail === null && $type === 'sendmail' ){
      $type = 'mail';
    }
    
    switch($type){
      case 'smtp':
        $this->SMTPAuth  = $smtp['auth'];
        $this->SMTPDebug = $smtp['debug'];
        $this->Host      = $smtp['host'];
        $this->Username  = $smtp['user'];
        $this->Password  = $smtp['pass'];
        $this->Port      = $smtp['port'];

        if ( $smtp['secure'] === 'ssl' || $smtp['secure'] === 'tls' ){
          $this->SMTPSecure = $smtp['secure'];
        }

        if ( ($this->SMTPAuth === true && $this->Host !== null && $this->Username !== null && $this->Password !== null)
          || ($this->SMTPAuth === false && $this->Host !== null) ){
          $this->IsSMTP();
          return;
        }
        
        $this->IsMail();
        break;
      
      case 'sendmail':
        if ( $sendmail !== '' ){
          $this->Sendmail = $sendmail;
          $this->IsSendmail();
          return;
        }
        
        $this->IsMail();
        break;
      
      case 'qmail':
        $this->IsQmail();
        break;
      
      case 'mail':
      default:
        $this->IsMail();
        break;
    }
  }
}
