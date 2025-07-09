<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Mailer\Sender;

use JDZ\Mailer\Mailer;
use JDZ\Mailer\Exception\MailchimpException;
use MailchimpTransactional\ApiClient;

/**
 * Mailchimp container
 * send a mail with the MailChimp transactional API
 * https://mailchimp.com/developer/transactional/api-overview/
 */
class MailchimpSender
{
  private Mailer $mailer;

  public function setMailer(Mailer $mailer)
  {
    $this->mailer = $mailer;
    return $this;
  }

  public function send()
  {
    try {
      $mailchimp = new ApiClient();
      $mailchimp->setApiKey($this->mailer->mailchimp->get('apiKey'));
      $mailchimp->setDefaultOutputFormat('json');

      $messageData = [];
      $messageData['html'] = $this->mailer->content->Body;
      $messageData['text'] = $this->mailer->content->AltBody;
      $messageData['subject'] = $this->mailer->subject;

      if ($this->mailer->from) {
        $messageData['from_email'] = $this->mailer->from->email;
        $messageData['from_name'] = $this->mailer->from->name;
      }

      foreach ($this->mailer->recipients as $recipient) {
        $messageData['to'][] = [
          'email' => $recipient->email,
          'name' => $recipient->name,
          'type' => 'to',
        ];
      }

      foreach ($this->mailer->ccs as $cc) {
        $messageData['to'][] = [
          'email' => $cc->email,
          'name' => $cc->name,
          'type' => 'cc',
        ];
      }

      foreach ($this->mailer->bccs as $bcc) {
        $messageData['to'][] = [
          'email' => $bcc->email,
          'name' => $bcc->name,
          'type' => 'bcc',
        ];
      }

      foreach ($this->mailer->attachments as $attachment) {
        if (!($content = $this->fetchAttachedFile($attachment->path))) {
          continue;
        }

        $messageData['attachments'][] = [
          'type' => $attachment->type,
          'name' => $attachment->name,
          'content' => base64_encode($content),
        ];
      }

      $messageData['tags'] = $this->mailer->tags;
      $messageData['track_opens'] = $this->mailer->mailchimp->track_opens;
      $messageData['track_clicks'] = $this->mailer->mailchimp->track_clicks;
      $messageData['auto_text'] = $this->mailer->mailchimp->auto_text;
      $messageData['auto_html'] = $this->mailer->mailchimp->auto_html;
      $messageData['preserve_recipients'] = $this->mailer->mailchimp->preserve_recipients;
      $messageData['important'] = $this->mailer->important;

      $headers = [];
      // $headers['X-Mailer'] = 'JDZ Mailer';
      // $headers['X-Mailchimp-Tag'] = implode(',', array_map(fn($tag) => $tag->name, $this->mailer->tags));
      if ($this->mailer->replyTos) {
        $headers['Reply-To'] = implode(',', array_map(fn($replyTo) => $replyTo->name . ' <' . $replyTo->email . '>', $this->mailer->replyTos));
      }

      if ($headers) {
        $messageData['headers'] = $headers;
      }

      $mailchimp->messages->send([
        'message' => $messageData,
      ]);
    } catch (\Throwable $e) {
      throw new MailchimpException($e->getMessage(), $e->getCode(), $e);
    }
  }

  private function fetchAttachedFile(string $path): string|false
  {
    if (!file_exists($path)) {
      return false;
    }
    return file_get_contents($path);
  }
}
