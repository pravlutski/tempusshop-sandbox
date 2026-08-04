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
class AutoAdvCpm
{
  private $advIds = []; //ИД рекламных кампаний, за которыми склейки закреплены
  private $reportDRR = [];
  private $advActualCpm;
  private $toIncrease;
  private $toDecrease;
  private $logPath;
  private $auth;
  private $headers;
  private $advSettings;

  public function __construct(){
    $this->auth = CMaxyssWb::settings_wb('WR')["AUTHORIZATION"];
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/logs/autoAdv/AutoAdvCpm.txt';
    $this->headers = [
      "Content-Type: application/json",
      "Authorization: {$this->auth}"
    ];
    $this->advSettings = self::getAdvSettings();
  }

  public function run()
  {
    $this->writeLog(' ');
    $this->writeLog('START');
    $this->getAdvIds();
    $this->getReports();
    $this->getAdvActualCpm();
    $this->checkIfWorth();
    $this->changeCpm();
    $this->writeLog('END');
  }

  public function getAdvIds()
  {
    global $DB;
    $strSql = "SELECT aaw.advId, iw.nmid FROM illiquid_wb AS iw
      JOIN auto_adv_wb AS aaw ON iw.completeId = aaw.completeId
      WHERE aaw.advId IS NOT NULL AND aaw.pausedToDelete = 0";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $advIds = [];
    while ( $row = $resultDB->Fetch() ){
      $advIds[$row['advId']][] = (int)$row['nmid'];
    }
    if ( !empty($advIds) ){
      $this->writeLog('ITEMS EXTRACTED SUCCESSFULLY');
      $this->advIds = $advIds;
    }else{
      $this->writeLog('THERE IS NO ADVIDS');
      $this->advIds = false;
    }
  }

  public function getReports()
  {
    if ($this->advIds == false){
      return false;
    }
    $data = [];

    foreach ($this->advIds as $id => $goods) {
      $data[] = [
        'id' => (int)$id,
      ];
    }
    $url = 'https://advert-api.wb.ru/adv/v2/fullstats';
    $result = $this->curl($url, $data);
    $reports = json_decode($result, 1);
    if ( !empty($reports) && is_array($reports) ){
      foreach ($reports as $adv) {
        $this->reportDRR[$adv['advertId']] = ['spentSum' => $adv['sum'], 'actualCpm' => $adv['cpm']];
      }
    }else{
      $this->reportDRR = false;
      return false;
    }
    unset($data);

    $sales = 0;
    $ordersCount = 0;
    foreach ($this->advIds as $id => $goods) {
      $data = [
        'nmIDs' => $goods,
        'period' => [
          'begin' =>date("Y-m-d G:i:s", strtotime("-24 hour")),
          'end' => date("Y-m-d G:i:s")
        ],
        'page' => 1
      ];
      $url = 'https://seller-analytics-api.wildberries.ru/api/v2/nm-report/detail';
      $result = $this->curl($url, $data);
      // $this->writeLog($result);
      $result = json_decode($result, 1);
      if ( empty($result['error']) && is_array($result) ){
        foreach ( $result['data']['cards'] as $card ){
          $sales += $card['statistics']['selectedPeriod']['ordersSumRub'];
          $ordersCount += $card['statistics']['selectedPeriod']['ordersCount'];
        }
        $this->reportDRR[$id]['salesSum'] = $sales == 0 ? $this->reportDRR[$id]['spentSum'] : $sales;
        $this->reportDRR[$id]['ordersCount'] = $ordersCount;
        $this->writeLog('GOT REPORT! FOR ' . $id);
      }
      sleep(20);
    }
  }

    public function getAdvActualCpm()
    {
      global $DB;
      $strSql = "SELECT advId, actualCpm FROM auto_adv_wb WHERE actualCpm IS NOT NULL";
      $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
      $advActualCpm = [];
      while ( $row = $resultDB->Fetch() ){
        $advActualCpm[$row['advId']] = (int)$row['actualCpm'];
      }
      if ( !empty($advActualCpm) ){
        $this->writeLog('ADVERTS CPMs EXTRACTED SUCCESSFULLY');
        $this->advActualCpm = $advActualCpm;
      }else{
        $this->writeLog('THERE IS NO CPM');
        $this->advActualCpm = false;
      }
    }

  public function checkIfWorth()
  {
    if ($this->reportDRR == false || $this->advActualCpm == false){
      $this->writeLog('checkIfWorth SKIPPED');
      return false;
    }
    global $DB;
    foreach ($this->reportDRR as $advertId => $report) {
      // $this->writeLog(intval($this->advActualCpm[$advertId]) - intval($this->advSettings['stepCpmChange']));
      if (
        ($report['ordersCount'] < $this->advSettings['ordersCount']) &&
        ((intval($this->advActualCpm[$advertId]) - intval($this->advSettings['stepCpmChange'])) >= 125)
      ){
        $this->toDecrease[$advertId] = intval($this->advActualCpm[$advertId]) - intval($this->advSettings['stepCpmChange']);
      }
      elseif(
        ($report['ordersCount'] > $this->advSettings['ordersCount']) &&
        ((intval($this->advActualCpm[$advertId]) + intval($this->advSettings['stepCpmChange'])) <= 500)
      ){
        $this->toIncrease[$advertId] = intval($this->advActualCpm[$advertId]) + intval($this->advSettings['stepCpmChange']);
      }
    }
    $this->writeLog('ADVERTS WHERE CPM WILL BE INCREASED: ' . count($this->toIncrease));
    $this->writeLog('ADVERTS WHERE CPM WILL BE DECREASED: ' . count($this->toDecrease));
  }

  public function changeCpm()
  {
    if ($this->advIds == false){
      return false;
    }
    global $DB;
    foreach ($this->advIds as $advertId => $goods) {
      $data = [
        'advertId' => (int)$advertId,
        'type' => 8,
        'cpm' => $this->advSettings['startCpm']
      ];
      if( !empty($this->toIncrease[$advertId]) ){
        $data['cpm'] = $this->toIncrease[$advertId];
      }
      elseif ( !empty($this->toDecrease[$advertId]) ) {
        $data['cpm'] = $this->toDecrease[$advertId];
      }
      $url = 'https://advert-api.wb.ru/adv/v0/cpm';
      $result = $this->curl($url, $data);
      $response = json_decode($result, 1);

      if ( empty($response) ){
        $this->writeLog('NEW CPM OF ' . $advertId . ' IS ' . $data['cpm']);
        $strSql = "UPDATE auto_adv_wb SET actualCpm = '{$data['cpm']}' WHERE advId = '{$advertId}'";
        $DB->Query($strSql, false, $err_mess.__LINE__);
      }else{
        $this->writeLog('ERROR OCCURED: ' . $result);
      }
      sleep(1);
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

$objAdv = new AutoAdvCpm();
$objAdv->run();
