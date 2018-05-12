<?php
/**
 * Joffrey Demetz <joffrey.demetz@gmail.com>
 * <http://joffreydemetz.com>
 */
namespace JDZ\Mailer;

use PHPUnit\Framework\TestCase;

/**
 * @package Test
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class MailerTest extends TestCase
{
  /**
   * Test mail
   */
  public function testMail()
  {
    $this->setExpectedException('Exception');
    
    $config = [
      'type' => 'mail',
      'from' => 'toto@tutu.com',
      'fromname' => 'Toto',
      'sendmail' => null,
      'smtp' => null,
    ];
    
    $data = [
      'recepient' => [ 'joffrey.demetz.pro@gmail.com', 'Joffrey Demetz' ],
      'subject' => 'Test',
      'body' => '<p>Coucou <strong>Toi</strong></p>',
      'replyTo' => 'joffrey.demetz.pro@gmail.com',
      'attachment' => null,
      'cc' => null,
      'bcc' => null,
    ];
    
    $sent = false;
    
    try {
      $sent = \JDZ\Mailer\MailerHelper::sendMail($config, $data);
    }
    catch(Exception $e){
      echo "\n ---> ".$e->getMessage()."\n";
      $sent = false;
    }
    
    $this->assertTrue($sent, 'Mail was not sent.');
  }
}
