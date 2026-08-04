<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('maxyss.wb');
CModule::IncludeModule("panel.manager");
/**
 *
 */
class AutoAdvDelete
{
  private $advIds = []; //ИД рекламных кампаний, за которыми склейки закреплены
  private $logPath;
  private $auth;
  private $headers;

  public function __construct(){
    $this->auth = CMaxyssWb::settings_wb('WR')["AUTHORIZATION"];
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/logs/autoAdv/AutoAdvDelete.txt';
    $this->headers = [
      "Content-Type: application/json",
      "Authorization: {$this->auth}"
    ];
  }

  public function run()
  {
    $this->writeLog('');
    $this->writeLog('START');
    $this->getAdvIds();
    $this->deleteAdverts();
    $this->writeLog('END');
  }

  public function getAdvIds()
  {
    global $DB;
    $strSql = "SELECT advId FROM auto_adv_wb WHERE pausedToDelete = 1";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $advIds = [];
    while ( $row = $resultDB->Fetch() ){
      $advIds[] = $row['advId'];
    }
    if ( !empty($advIds) ){
      $this->writeLog('ITEMS EXTRACTED SUCCESSFULLY');
      $this->advIds = $advIds;
    }else{
      $this->writeLog('THERE IS NO ADVIDS');
      $this->advIds = false;
    }
  }

  private function deleteAdverts()
  {
    if ( empty($this->advIds) ){
      return false;
    }
    foreach ($this->advIds as $advertId) {
      $url = 'https://advert-api.wb.ru/adv/v0/stop?id=' . $advertId;
      $result = $this->curl($url);
      if ( empty($result) ){
        $this->writeLog($advertId . ' DELETED');
        global $DB;
        $strSql = "DELETE FROM auto_adv_wb WHERE advId = " . $advertId;
        $DB->Query($strSql, false, $err_mess.__LINE__);
      }else{
        $this->writeLog('ERROR OCCURED: ' . $result);
      }
    }
  }

//Вспомогательные функции
  private function writeLog($message)
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }

  private function curl($url, $data = false)
  {
    $ch = curl_init($url);
    curl_setopt($ch,CURLOPT_HTTPHEADER, $this->headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ( $data != false ){
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }

}
$objAdv = new AutoAdvDelete();
$objAdv->run();
 ?>
