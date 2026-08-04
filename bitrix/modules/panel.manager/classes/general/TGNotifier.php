<?php

class TGNotifier
{
  private $token;
  private $chat_id;
  private $tokenType = 'notify_market';
  private $proxyUrl = 'http://45.135.234.186/api/send_tg.php';
  private $timeout = 30;
  private $totalTimeout = 60;


  public function __construct( bool $isTest = false, bool $ordersBot = false )
  {
    $this->token = '7573576515:AAEVE_AaMAEzAxSeS6CikaVqwh_fQcA1Y8w';
    // $this->chat_id = '-1002188267998'; // паблик
    if ( $isTest ){
      $this->chat_id = '-4636535496'; // отладка
    }else{
      $this->chat_id = '-4835094484'; // паблик
    }

    $this->tokenType = 'notify_market';

    if ( $ordersBot ){
      $this->chat_id = '-1003765978602';
      $this->tokenType = 'notify_orders';
    }
  }

  public function sendMessage( string $message )
  {
    // $data = [
    //   "chat_id" 	=> $this->chat_id,
    //   "text"  	=> $message,
    //   "parse_mode" => "html",
    // ];

    $data = [
      'token' => $this->tokenType,
      'chat_id' => $this->chat_id,
      'text' => $message,
      "parse_mode" => "HTML",
    ];

    // $res = $this->request( $data, 'sendMessage' );
    $res = $this->requestViaProxy( $data );
    return $res;
  }

  public function getChatIdByUpdate()
  {
    $res = $this->request( false, 'getUpdates');
    var_dump( $res );
  }

  public function sendFileAsLink( string $path, string $name, int $messageId = 0 )
  {
    $template = '<a href="%s" download>%s</a>';
    $link = str_replace(
      search: $_SERVER['DOCUMENT_ROOT'],
      replace: 'https://tempusshop.ru',
      subject: $path
     );
     $message = sprintf( $template, $link, $name );
     sleep(2);
     $this->sendMessage( $message );
    // return $res;
  }

  public function sendFile( string $path, string $name, int $messageId = 0 )
  {
    $this->sendFileAsLink($path, $name); // Когда-нибудь ТГ разблокируют...
    return;
    if ( !file_exists($path) ) return false;
    $mime = mime_content_type($path);
    $data = [
      "chat_id" => $this->chat_id,
      "document" => curl_file_create( $path, $mime, $name ),
    ];
    $res = $this->request( $data, 'sendDocument', 'POST');
    return $res;
  }

  public function request( array|false $data, string $method, string $type = 'GET' )
  {
    if ( $data ){
      $query = "?" . http_build_query($data);
    }else{
      $query = '';
    }

    if ( $type === 'GET' ){
      $url = "https://api.telegram.org/bot{$this->token}/{$method}{$query}";
    } elseif ( $type == 'POST'){
      $url = "https://api.telegram.org/bot{$this->token}/{$method}";
    } else {
      return false;
    }

    $ch = curl_init( $url );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ( $type == 'POST' ){
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);

    $result = curl_exec($ch);
    curl_close($ch);
    // var_dump( $result );

    return $result;
  }

  public function requestViaProxy( array $data )
  {
    $options = [
      CURLOPT_URL => $this->proxyUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query( $data ),
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_CONNECTTIMEOUT => $this->timeout,
      CURLOPT_TIMEOUT => $this->totalTimeout,
      CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ];
    // var_dump($options);
    // die;
    $ch = curl_init();
    curl_setopt_array($ch, $options);

    $curlResult = curl_exec($ch);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // $this->Logger->log("DEBUG", "Proxy request to {$this->proxyUrl}, token_type: {$tokenType}, chat_id: {$chatId}");

    return [
        'result' => $curlResult,
        'error' => $curlError,
        'errno' => $curlErrno,
        'http_code' => $httpCode
    ];
  }
}
 ?>
