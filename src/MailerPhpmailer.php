<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Mailer;

use JDZ\Mailer\Mailer;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * PhpMailer container
 * send a mail with PhpMailer
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class MailerPhpmailer extends PHPMailer
{
  private Mailer $mailer;
  
  public function __construct(Mailer $mailer)
  {
    $this->mailer = $mailer;
  }
  
  public function send()
  {
    $this->CharSet = $this->mailer->charset;
    $this->exceptions = true;
    $this->WordWrap = 70;
    $this->Timeout = 300;
    $this->Debugoutput = 'html';
    $this->setLanguage($this->mailer->language);
    
    switch($this->mailer->type){
      case 'smtp':
        $this->isSMTP();
        break;
      
      case 'sendmail':
        $this->isSendmail();
        break;
      
      case 'qmail':
        $this->isQmail();
        break;
      
      case 'mail':
      default:
        $this->isMail();
        break;
    }
    
    if ( true === $this->mailer->smtp->valid ){
      $this->Host = $this->mailer->smtp->host;
      $this->Port = $this->mailer->smtp->port;
      $this->Username = $this->mailer->smtp->user;
      $this->Password = $this->mailer->smtp->pass;
      $this->SMTPAuth = $this->mailer->smtp->auth;
      $this->SMTPDebug = $this->mailer->smtp->debug;
      $this->SMTPSecure = $this->mailer->smtp->secure;
      
      if ( true === $this->mailer->localMode ){
        $this->SMTPOptions = [
          'ssl' => [
             'verify_peer' => false,
             'verify_peer_name' => false,
             'allow_self_signed' => true,
          ],
        ];
      }
    }
    
    if ( true === $this->mailer->dkim->valid ){
      $this->DKIM_domain = $this->mailer->dkim->domain;
      $this->DKIM_identity = $this->mailer->dkim->identity;
      $this->DKIM_private = $this->mailer->dkim->private;
      $this->DKIM_selector = $this->mailer->dkim->selector;
      $this->DKIM_passphrase = $this->mailer->dkim->passphrase;
    }
    
    $this->isHTML($this->mailer->content->isHtml);
    
    $this->Subject = $this->mailer->subject;
    $this->Body = $this->mailer->content->Body;
    $this->AltBody = $this->mailer->content->AltBody;
    
    $this->setFrom($this->mailer->from->email, $this->mailer->from->name);
    
    foreach($this->mailer->recipients as $person){
      $this->addAddress($person->email, $person->name);
    }
    
    foreach($this->mailer->replyTos as $person){
      $this->addReplyTo($person->email, $person->name);
    }
    
    foreach($this->mailer->ccs as $person){
      $this->addCC($person->email, $person->name);
    }
    
    foreach($this->mailer->bccs as $person){
      $this->addBCC($person->email, $person->name);
    }
    
    foreach($this->mailer->attachments as $attachment){
      $this->addAttachment($attachment->path, $attachment->name, $attachment->encoding, $attachment->type, $attachment->disposition);
    }
    
    $error = null;
    
    \ob_start();
    
    try {
      
      if ( false === parent::send() ){
        echo 'Error sending mail';
      }
      
    } catch(\PHPMailer\PHPMailer\Exception $e){
      $error = $e;
      
      echo 'PHPmailer error'."\n";
      echo 'CODE    : '.$e->getCode()."\n";
      echo 'MESSAGE : '.$e->getMessage()."\n";
      
      switch($e->getCode()){
        case self::STOP_MESSAGE:
          echo 'continue processing'."\n";
          break;
        
        case self::STOP_CONTINUE:
          echo 'likely ok to continue processing'."\n";
          break;
        
        case self::STOP_CRITICAL:
          echo 'full stop, critical error reached'."\n";
          break;
      }
      
    } catch(\Throwable $e){
      $error = $e;
      
      echo 'MailerPhpmailer error'."\n";
      echo 'CODE    : '.$e->getCode()."\n";
      echo 'MESSAGE : '.$e->getMessage()."\n";
    }
    
    $DebugInfos = \ob_get_contents();
    \ob_end_clean();
    
    if ( $DebugInfos ){
      $DebugInfos = trim($DebugInfos);
      if ( $this->mailer->DebugInfos ){
        $this->mailer->DebugInfos[] = '';
      }
      $DebugInfos = explode("\n", $DebugInfos);
      $this->mailer->DebugInfos = array_merge($this->mailer->DebugInfos, $DebugInfos);
    }
    
    if ( $error ){
      throw new \RuntimeException($error->getMessage(), $error->getCode(), $error);
    }
  }
}
