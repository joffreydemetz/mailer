<?php
/**
 * Joffrey Demetz <joffrey.demetz@gmail.com>
 * <https://jdz.joffreydemetz.com/mailer/>
 */
namespace JDZ\Mailer;

/**
 * Mail address
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */ 
class Attachment 
{
  public string $path;
  public string $name;
  public string $encoding;
  public string $type;
  public string $disposition;
  
  public function __construct(string $path, string $name='', string $encoding='', string $type='', string $disposition='')
  {
    $this->path = $path;
    $this->name = $name;
    $this->encoding = $encoding;
    $this->type = $type;
    $this->disposition = $disposition;
    
    if ( '' === $encoding ){
      $type = 'base64';
    }
    
    if ( '' === $type ){
      $type = 'application/octet-stream';
    }
    
    if ( '' === $disposition ){
      $disposition = 'attachment';
    }
  }
}
