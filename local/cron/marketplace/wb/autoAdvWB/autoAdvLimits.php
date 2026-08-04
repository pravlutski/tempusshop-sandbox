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
class AutoAdvLimits
{
  private $advIds = []; //ИД рекламных кампаний, за которыми склейки закреплены
  private $logPath;
  private $auth;
  private $headers;
  private $advSettings;
  private $reportDRR;
  private $toEnd;

  public function __construct(){
    $this->auth = CMaxyssWb::settings_wb('WR')["AUTHORIZATION"];
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/logs/autoAdv/AutoAdvLimits.txt';
    $this->headers = [
      "Content-Type: application/json",
      "Authorization: {$this->auth}"
    ];
    $this->advSettings = self::getAdvSettings();
  }

  public function run()
  {
    $this->writeLog('');
    $this->writeLog('START');
    $this->getAdvIds();
    $this->getDailyReports();
    $this->checkCompleteBudget();
    $this->pauseAdverts();
    $this->writeLog('END');
  }

  public function getAdvIds()
  {
    global $DB;
    $strSql = "SELECT aaw.advId, iw.nmid, aaw.creationDate FROM illiquid_wb AS iw
      JOIN auto_adv_wb AS aaw ON iw.completeId = aaw.completeId
      WHERE aaw.advId IS NOT NULL";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $advIds = [];
    while ( $row = $resultDB->Fetch() ){
      $advIds[$row['advId']]['date'] = $row['creationDate'];
      $advIds[$row['advId']]['nmid'][] = (int)$row['nmid'];
    }
    if ( !empty($advIds) ){
      $this->writeLog('ITEMS EXTRACTED SUCCESSFULLY');
      $this->advIds = $advIds;
    }else{
      $this->writeLog('THERE IS NO ADVIDS');
      $this->advIds = false;
    }
  }

  private function getDailyReports()
  {
    if ( $this->advIds == false ){
      return false;
    }
    $data = [];

    foreach ($this->advIds as $id => $goods) {
      $data[] = [
        'id' => (int)$id,
        'interval' => [
          'begin' => $goods['date'],
          'end' => date('Y-m-d')
        ]
      ];
    }
    $url = 'https://advert-api.wb.ru/adv/v2/fullstats';
    $result = $this->curl($url, $data);
    $reports = json_decode($result, 1);
    if ( !empty($reports) && is_array($reports) ){
      foreach ($reports as $adv) {
        $this->reportDRR[$adv['advertId']] = ['spentSum' => $adv['sum']];
      }
    }else{
      $this->reportDRR = false;
      return false;
    }
  }

  private function checkCompleteBudget()
  {
    if ($this->reportDRR == false){
      return false;
    }
    foreach ($this->reportDRR as $advertId => $report){
      if ( $report['spentSum'] > $this->advSettings['allSpent'] ){
        $this->writeLog($advertId . ' OUT OF COMPLETE BUDGET');
        $this->toEnd[] = $advertId;
      }
    }
    if ( empty($this->toEnd) ){
      $this->writeLog('EVERYTHING IS FINE. WAITING FOR THE NEXT CYCLE');
    }
  }

  private function pauseAdverts()
  {
    if ( empty($this->toEnd) ){
      return false;
    }
    foreach ($this->toEnd as $advertId) {
      $url = 'https://advert-api.wb.ru/adv/v0/pause?id=' . $advertId;
      $result = $this->curl($url);
      if ( empty($result) ){
        $this->writeLog($advertId . ' IS PAUSED');
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

  static function getAdvSettings()
  {
    global $DB;
    $strSql = "SELECT * FROM auto_adv_wb_settings WHERE id = 1";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $advSettings = [];
    while ( $row = $resultDB->Fetch() ){
      $advSettings = [
        'stockDays' => (int)$row['stockDays'],
        'startBudget' => (int)$row['startBudget'],
        'startCpm' => (int)$row['startCpm'],
        'refillBudget' => (int)$row['refillBudget'],
        'minDRR' => (int)$row['minDRR'],
        'minCart' => (int)$row['minCart'],
        'dailySpent' => (int)$row['dailySpent'],
        'allSpent' => (int)$row['allSpent'],
        'ordersCount' => (int)$row['ordersCount'],
        'stepCpmChange' => (int)$row['stepCpmChange']
      ];
    }
    return $advSettings;
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
$objAdv = new AutoAdvLimits();
$objAdv->run();
 ?>
