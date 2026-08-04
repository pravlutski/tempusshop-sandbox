<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class PingToken
{
  private $cabinet;
  private $api_url;

  private $db;
  private $triggers;

  public function __construct( string $cabinet )
  {
    if ( !in_array($cabinet, ['WR', 'TL']) ) die("WRONG CABINET\n");

    global $DB;
    $this->db = $DB;

    CModule::IncludeModule('panel.manager');

    $this->triggers = new TsTriggers;


    $this->cabinet = $cabinet;
    $this->api_url = "https://common-api.wildberries.ru/ping";
  }

  public function checkToken():void
  {
    $token = $this->getToken();

    $ch = curl_init( $this->api_url . $method );
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Content-Type: application/json",
      "Authorization: {$token}"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $res = curl_exec($ch);
    curl_close($ch);

    $result = json_decode( $res, true );
    if ( empty( $result['Status'] ) || $result['Status'] != 'OK' ){
      $message = "Проблема с токеном WB {$this->cabinet}. ";
      if ( $result['status'] == '401' ){
        $message .= "Причина: истёк токен";
      }else{
        $message .=  "Код ответа WB: {$result['status']}";
      }
      $this->sendMessage( $message );
    }
  }

  private function getToken():string
  {
    $strSql = "SELECT api FROM wdhs_wb_main_settings WHERE cabinet = '{$this->cabinet}'";
    $res = $this->db->Query( $strSql );

    if ( $res->SelectedRowsCount() < 1 ){
      $this->sendMessage( "Ошибка получения токена WB\n" );
      die("CANNOT GET TOKEN FROM DB\n");
    }

    $token = $res->Fetch()['api'];

    return $token;
  }


  private function sendMessage( string $message ):void
  {
    $this->triggers->SetError([$message]);
    $this->triggers->SendTriggerErrors();
  }
}

( new PingToken( $argv[1] ) )->checkToken();
 ?>
