<?php

require_once __DIR__ . '/thirdParty/PHPMailer/src/Exception.php';
require_once __DIR__ . '/thirdParty/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/thirdParty/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SendMessage {
    const DEFAULT_TIMEZONE = 'America/Chicago';
    const DEFAULT_CHARSET = 'UTF-8';
    const DEFAULT_CREDS = '/var/www/config/.email';
    const PHONE_PATTERN = '/^[0-9]{10}@/';
    const DEFAULT_PORT = 25;
    const DEFAULT_SEC = 'ssl'; //tls
    public $encoding = "base64";
    public $type = "text/plain";
    protected $host;
    protected $username;
    protected $password;
    protected $port;
    public $mime_types = array(
        "123" => "application/vnd.lotus-1-2-3", "3dml" => "text/vnd.in3d.3dml", "3g2" => "video/3gpp2",
        "3gp" => "video/3gpp", "aac" => "audio/x-aac", "ai" => "application/postscript", "bmp" => "image/bmp",
        "css" => "text/css", "doc" => "application/msword", "eps" => "application/postscript", "flv" => "video/x-flv",
        "gif" => "image/gif", "htm" => "text/html", "html" => "text/html", "ics" => "text/x-icalendar",
        "jpe" => "image/jpeg", "jpeg" => "image/jpeg", "jpg" => "image/jpeg", "json" => "application/json",
        "mov" => "video/quicktime", "mp3" => "audio/mpeg", "ods" => "application/vnd.oasis.opendocument.spreadsheet",
        "odt" => "application/vnd.oasis.opendocument.text", "pdf" => "application/pdf", "php" => "text/html",
        "png" => "image/png", "ppt" => "application/vnd.ms-powerpoint", "ps" => "application/postscript",
        "psd" => "image/vnd.adobe.photoshop", "qt" => "video/quicktime", "rtf" => "application/rtf",
        "svg" => "image/svg+xml", "svgz" => "image/svg+xml", "tif" => "image/tiff", "tiff" => "image/tiff",
        "txt" => "text/plain", "vcf" => "text/x-vcard", "vcs" => "text/x-vcalendar", "wav" => "audio/wav",
        "xls" => "application/vnd.ms-excel", "xml" => "application/xml",
    );
    /**
     * Send constructor.
     *
     * @param $parameters
     * to, from, fromName, subject, body, attachemnts
     * TO FROM SUBJECT BODY -- REQUIRED
     * $message = new Send($array);
     */
    public function __construct($parameters,$host = null,$port = null,$username = null,$password = null)
    {
        $this->_parseHostDetails($host,$port,$username,$password);
        // to and attachments expect arrays
        $parameters = $this->checkForScalarInArray($parameters);
        foreach ($parameters as $name => $value) {
            $this->$name = $value;
        }
        $this->setTimeZone()
            ->send();
    }
    // Send blind carbon copy to each member of array
    private function blindCarboCopies($email)
    {
        if (isset($this->bcc) && !empty($this->bcc)) {
            if (is_array($this->bcc)) {
                foreach ($this->bcc as $b) {
                    $email->AddBCC($b);
                }
            } else {
                $email->AddBCC($this->bcc);
            }
        }
        return $email;
    }
    // Send carbon copy to each member of array
    private function carbonCopies($email)
    {
        if (isset($this->cc) && !empty($this->cc)) {
            if (is_array($this->cc)) {
                foreach ($this->cc as $c) {
                    $email->AddCC($c);
                }
            } else {
                $email->AddCC($this->cc);
            }
        }
        return $email;
    }
    private function checkForScalarInArray($parameters)
    {
        // 2017-07-17 Scalar variable
        if (isset($parameters["to"]) && !empty($parameters["to"])) {
            if (!is_array($parameters["to"])) {
                $this->to = array(
                    $parameters["to"],
                );
            }
        }
        // 2018-10-01 Scalar variable
        if (isset($parameters["attachments"]) && !empty($parameters["attachments"])) {
            if (!is_array($parameters["attachments"])) {
                $this->attachments = array(
                    $parameters["attachments"],
                );
            }
        }
        return $parameters;
    }
    private function cleanUp($email)
    {
        $email->ClearAddresses();
        $email->ClearAttachments();
        return $this;
    }
    private function send()
    {
      $this->uniqueTo();
      // Single thread each message to prevent group messages
      foreach($this->to as $to){
        $email = new phpmailer(true);
        $email->SMTPDebug = 2;
        $email->isSMTP();
        $email->Host = $this->host;
        $email->Port = $this->port;
        $email->CharSet = self::DEFAULT_CHARSET;
        $email->SMTPSecure = self::DEFAULT_SEC;
        $email->SMTPAuth = true;
        $email->Username = $this->username;
        $email->Password = $this->password;
        if(isset($this->replyTo)){
          $email->AddReplyTo($this->replyTo);
        }
        if(isset($this->from)){
          $email->Sender = $this->username;
          $from = isset($this->fromName) ? $this->fromName : $this->from;
          $email->SetFrom($this->from,$from,false);
        }else{
          $email->From = $this->username;
        }
        $email->AddAddress($to,"ToEmail");
        $email->Body = $this->body;
        if(preg_match(self::PHONE_PATTERN,$to)){
          $email->isHTML(false);
          $email->Subject = "";
        }else{
          $email->isHTML(true);
          $email->Subject = $this->subject;
        }
        if(isset($this->attachments)){
          $email = $this->_addAttachments($email);
        }
        if(isset($this->cc)){
          $email = $this->carbonCopies($email);
        }
        if(isset($this->bcc)){
          $email = $this->blindCarboCopies($email);
        }
        try{
          $email->send();
          $this->cleanUp($email);
        }catch(\Exception $e){
          throw new \Exception($e->getMessage());
        }
      }
      return $this;
    }
    private function setTimeZone($timeZone = false)
    {
        if(!$timeZone){
          date_default_timezone_set(self::DEFAULT_TIMEZONE);
        }else{
          date_default_timezone_set($timeZone);
        }
        return $this;
    }
    private function uniqueTo()
    {
        $this->to = array_unique($this->to);
        $this->to = array_values($this->to);
        return $this;
    }
    private function write_log($str)
    {
        $out = date('Y-m-d') . ' ' . date('H:i:s') . ' ' . $str . "\n";
        $f = '/srv/www/htdocs/log/send.log';
        if (filesize($f) > 1000000) {
            file_put_contents($f, $out);
        } else {
            file_put_contents($f, $out, FILE_APPEND);
        }
        return $this;
    }
    protected function _parseHostDetails($host,$port,$username,$password){
      if(is_null($host) || is_null($port) || is_null($username) || is_null($password)){
        if(!file_exists(self::DEFAULT_CREDS)){
          throw new \Exception('Cannot Read Default credentials.');
        }
        $file = file(self::DEFAULT_CREDS);
        $this->host = trim($file[0]);
        $this->port = trim($file[1]);
        $this->username = trim($file[2]);
        $this->password = trim($file[3]);
      }else{
        $this->host = $host;
        $this->port = self::DEFAULT_PORT;
        $this->username = $username;
        $this->password = $password;
      }
      return $this;
    }
    protected function _addAttachments($email){
      foreach ($this->attachments as $attachment) {
          foreach ($this->mime_types as $key => $val) {
              if (preg_match("/" . $key . "$/", $attachment)) {
                  $this->type = $val;
              }
          }
          $email->AddAttachment($attachment, basename($attachment), $encoding = 'base64', $this->type);
      }
      return $email;
    }
}  // class
