<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Mailer;

use JDZ\Mailer\Config\Config_Content;
use JDZ\Mailer\Config\Config_SMTP;
use JDZ\Mailer\Config\Config_DKIM;
use JDZ\Mailer\MailerNative;
use JDZ\Mailer\MailerPhpmailer;

/**
 * Mail
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Mailer
{
  public string $type = 'mail';
  public string $domain = '';
  public string $timestamp = '';
  public string $language = 'fr';
  public string $charset = 'utf-8';
  
  public bool $localMode = false;
  public bool $useFallback = false;
  public bool $noReply = false;
  
  public bool $fallback = false;
  
  public Config_SMTP $smtp;
  public Config_DKIM $dkim;
  public Config_Content $content;
  
  public ?Address $from = null;
  public array $recipients = [];
  public array $replyTos = [];
  public array $ccs = [];
  public array $bccs = [];
  public array $attachments = [];
  public string $subject = '';
  
  public array $DebugInfos = [];
  
  public function __construct()
  {
    $this->smtp = new Config_SMTP();
    $this->dkim = new Config_DKIM();
    $this->content = new Config_Content();
  }
  
  public function setProperties(array $properties=[])
  {
    foreach($properties as $key => $value){
      $this->set($key, $value);
    }
    return $this;
  }
  
  public function set(string $key, mixed $value)
  {
    switch($key){
      case 'html':
        $this->setHTML($value);
        break;
      
      case 'smtp':
        $this->setSMTP($value);
        break;
      
      case 'dkim':
        $this->setDKIM($value);
        break;
      
      case 'from':
        $this->setFrom($value['email'], $value['name']??'');
        break;
      
      case 'noReply':
        $this->setNoReply($value['email'], $value['name']??'');
        break;
      
      case 'recipient':
        $this->addRecipient($value['email'], $value['name']??'');
        break;
      
      case 'replyTo':
        $this->addReplyTo($value['email'], $value['name']??'');
        break;
      
      case 'cc':
        $this->addCc($value['email'], $value['name']??'');
        break;
      
      case 'bcc':
        $this->addBcc($value['email'], $value['name']??'');
        break;
      
      case 'recipients':
        $this->addRecipients($value);
        break;
      
      case 'replyTos':
        $this->addReplyTos($value);
        break;
      
      case 'ccs':
        $this->addCcs($value);
        break;
      
      case 'bccs':
        $this->addBccs($value);
        break;
      
      case 'attachments':
        $this->addAttachments($value);
        break;
      
      case 'type':
      case 'domain':
      case 'timestamp':
      case 'localMode':
      case 'useFallback':
      default:
        $this->{$key} = $value;
        break;
    }
    
    return $this;
  }
  
  public function setSMTP(array $data)
  {
    $this->smtp->setProperties($data);
    return $this;
  }
  
  public function setDKIM(array $data)
  {
    $this->dkim->setProperties($data);
    return $this;
  }
  
  public function setContent(array $data)
  {
    $this->content->setProperties($data);
    return $this;
  }
  
  public function setFrom(string $email, string $name='')
  {
    if ( $address = $this->checkAddress($email, $name) ){
      $this->from = $address;
    }
    return $this;
  }
  
  public function setNoReply(string $email, string $name='No Reply')
  {
    $this->noReply = true;
    $this->replyTos = [];
    if ( $address = $this->checkAddress($email, $name) ){
      $this->replyTos[] = $address;
    }
    return $this;
  }
  
  public function addRecipients(array $people)
  {
    foreach($people as $person){
      $this->addRecipient($person['email'], $person['name']??'');
    }
    return $this;
  }
  
  public function addReplyTos(array $people)
  {
    foreach($people as $person){
      $this->addReplyTo($person['email'], $person['name']??'');
    }
    return $this;
  }
  
  public function addCcs(array $people)
  {
    foreach($people as $person){
      $this->addCc($person['email'], $person['name']??'');
    }
    return $this;
  }
  
  public function addBccs(array $people)
  {
    foreach($people as $person){
      $this->addBcc($person['email'], $person['name']??'');
    }
    return $this;
  }
  
  public function addAttachments(array $attachments)
  {
    foreach($attachments as $attachment){
      $this->addAttachment($attachment['path']??'', $attachment['name']??'', $attachment['encoding']??'', $attachment['type']??'', $attachment['disposition']??'');
    }
    return $this;
  }
  
  public function addRecipient(string $email, string $name='')
  {
    if ( $address = $this->checkAddress($email, $name) ){
      $this->recipients[] = $address;
    }
    return $this;
  }
  
  public function addReplyTo(string $email, string $name='')
  {
    if ( false === $this->noReply ){
      if ( $address = $this->checkAddress($email, $name) ){
        $this->replyTos[] = $address;
      }
    }
    return $this;
  }
  
  public function addCc(string $email, string $name='')
  {
    if ( $address = $this->checkAddress($email, $name) ){
      $this->ccs[] = $address;
    }
    return $this;
  }
  
  public function addBcc(string $email, string $name='')
  {
    if ( $address = $this->checkAddress($email, $name) ){
      $this->bccs[] = $address;
    }
    return $this;
  }
  
  public function addAttachment(string $path, string $name='', string $encoding='', string $type='', string $disposition='')
  {
    if ( $attachment = $this->checkAttachment($path, $name, $encoding, $type, $disposition) ){
      $this->attachments[] = $attachment;
    }
    return $this;
  }
  
  public function send()
  {
    if ( '' === $this->domain ){
      throw new \ValueError('Missing "domain" Sender domain (domain.tld)');
    }
    
    if ( empty($this->from) ){
      throw new \ValueError('Missing "from" Email email@domain.tld');
    }
    
    if ( '' === $this->subject ){
      throw new \ValueError('No Subject for this email');
    }
    
    if ( empty($this->replyTos) ){
      $this->addReplyTo($this->from->email, $this->from->name);
    }
    
    if ( '' === $this->smtp->host ){
      $this->smtp->host = $this->domain;
    }
    
    if ( '' === $this->dkim->domain ){
      $this->dkim->domain = $this->domain;
    }
    
    if ( '' === $this->dkim->identity ){
      $this->dkim->identity = $this->from->name;
    }
    
    $this->content->check();
    
    try {
      
      $this->smtp->check();
      $this->dkim->check();
      
      if ( true === $this->smtp->valid || true === $this->dkim->valid ){
        if ( false === $this->canUsePhpMailer() ){
          if ( false === $this->useFallback ){
            throw new \RuntimeException('PhpMailer is required to use SMTP and DKIM features');
          }
          
          $this->fallback = true;
          $this->smtp->valid = false;
          $this->dkim->valid = false;
        }
      }
      
      $this->type = true === $this->smtp->valid ? 'smtp' : 'mail';
      
    } catch(\Throwable $e){
      if ( false === $this->useFallback ){
        throw $e;
      }
    }
    
    $this->content->prepareBody();
    
    if ( false === $this->isMailerAvailable() ){
      throw new \RuntimeException('No mailer available to send the message !');
    }
    
    if ( true === $this->canUsePhpMailer() ){
      $this->sendWithPhpMailer();
    }
    elseif ( true === $this->canUseNativeMailer() ){
      $this->sendWithNativeMail();
    }
  }
  
  protected function isMailerAvailable()
  {
    return true === $this->canUsePhpMailer() || $this->canUseNativeMailer();
  }
  
  protected function sendWithPhpMailer()
  {
    $mail = new MailerPhpmailer($this);
    $mail->send();
  }
  
  protected function sendWithNativeMail()
  {
    $mail = new MailerNative($this);
    $mail->xMailer = 'JDZ Native Mailer Container';
    $mail->xPriority = 1;
    $mail->charset = 'utf-8';
    $mail->boundary = 'PHP-alt-'.md5(time());
    $mail->send();
  }
  
  protected function checkAddress(string $email, string $name=''): ?Address
  {
    $address = new Address($email, $name);
    
    if ( '' === $address->email ){
      $address = null;
    }
    
    return $address;
  }
  
  protected function checkAttachment(string $path, string $name='', string $encoding='', string $type='', string $disposition=''): ?Attachment
  {
    $attachment = new Attachment($path, $name, $encoding, $type, $disposition);
    
    if ( '' === $attachment->path ){
      $attachment = null;
    }
    
    return $attachment;
  }
  
  protected function canUsePhpMailer(): bool
  {
    return \class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');
  }
  
  protected function canUseNativeMailer(): bool
  { 
    return \function_exists('mail');
  }
  
  protected function doSend()
  {
    if ( true === $this->phpMailer ){
      $mailer = new \Callisto\Mailer\PhpMailer();
      $mailer->setMailerConfig($this->config);
      
      if ( true === $this->localMode ){
        $mailer->SMTPOptions = [
          'ssl' => [
             'verify_peer' => false,
             'verify_peer_name' => false,
             'allow_self_signed' => true,
          ],
        ];
      }
    }
    else {
      $mailer = new \Callisto\Mailer\SimpleMailer($this->timestamp, md5((string)$this->config['from'].(string)time()).'@'.$this->domain);
    }
    
    $mailer->isHTML($this->isHtml);
    
    $mailer->setFrom($this->config['from'], $this->config['fromname']);
    
    foreach($tos as $to){
      $mailer->setAddressFor('Address', new Address($to));
    }
    
    foreach($ccs as $cc){
      $mailer->setAddressFor('CC', new Address($cc));
    }
    
    foreach($bccs as $bcc){
      $mailer->setAddressFor('BCC', new Address($bcc));
    }
    
    if ( $this->data['replyTo'] ){
      $mailer->setAddressFor('ReplyTo', new Address($this->data['replyTo']));
    }
    
    foreach($attachments as $attachment){
      if ( is_array($attachment) ){
        $mailer->addAttachment($attachment[0], $attachment[1]);
      }
      else {
        $mailer->addAttachment($attachment);
      }
    }
    
    if ( false === $this->isHtml ){
      $this->data['body'] = $this->data['altBody'];
      $this->data['altBody'] = '';
    }
    
    $mailer->Subject = $this->data['subject'];
    $mailer->Body = $this->data['body'];
    $mailer->AltBody = $this->data['altBody'];
    
    return true === $mailer->send();
  }
}
