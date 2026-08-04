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
class AutoAdvAnalisys
{
  private $advIds = []; //ИД рекламных кампаний, за которыми склейки закреплены
  private $reportDRR = [];
  private $logPath;
  private $auth;
  private $headers;
  private $advSettings;

  public function __construct(){
    $this->auth = CMaxyssWb::settings_wb('WR')["AUTHORIZATION"];
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/logs/autoAdv/AutoAdvAnalisys.txt';
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
    $this->checkIfWorthReplensih();
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
        $this->reportDRR[$adv['advertId']] = ['spentSum' => $adv['sum']];
      }
    }else{
      $this->reportDRR = false;
      return false;
    }
    unset($data);

    $sales = 0;
    $cardAdded = 0;
    foreach ($this->advIds as $id => $goods) {
      $data = [
        'nmIDs' => $goods,
        'period' => [
          'begin' =>date("Y-m-d G:i:s", strtotime("-12 hour")),
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
          $cardAdded += $card['statistics']['selectedPeriod']['addToCartCount'];
        }
        $this->reportDRR[$id]['salesSum'] = $sales == 0 ? $this->reportDRR[$id]['spentSum'] : $sales;
        $this->reportDRR[$id]['cardAdded'] = $cardAdded;
        $this->writeLog('GOT REPORT! FOR ' . $id);
      }
      sleep(20);
    }
  }

  public function checkIfWorthReplensih()
  {
    if ($this->advIds == false || $this->reportDRR == false){
      return false;
    }
    global $DB;
    foreach ($this->reportDRR as $advertId => $value) {
      if ( $value['cardAdded'] < $this->advSettings['minCart'] ){
        $strSql = "UPDATE auto_adv_wb SET worthToReplenish = 0 WHERE advId = '{$advertId}'";
        $DB->Query($strSql, false, $err_mess.__LINE__);
        $this->writeLog('BUDGET OF ' . $advertId . ' WILL NOT BE UPDATED IN NEXT CYCLE');
      }else{
        $strSql = "UPDATE auto_adv_wb SET worthToReplenish = 1 WHERE advId = '{$advertId}'";
        $DB->Query($strSql, false, $err_mess.__LINE__);
        $this->writeLog('BUDGET OF ' . $advertId . ' WILL BE UPDATED IN NEXT CYCLE');
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

$objAdv = new AutoAdvAnalisys();
$objAdv->run();

 ?>
