<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("admin_modules_promcom_cron_getTurnover_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");

CModule::IncludeModule("panel.manager");

class TurnoverClass
{

  function __construct()
  {
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/cron/getTurnover.txt';
  }

  public function run()
  {
    $this->writeLog(' ');
    $this->writeLog('START');
    $this->getTurnover();
    $this->writeDB();
    $this->writeLog('END');
  }

  private function getTurnover(){
    $this->fromMS = array();
    $ms = new MoyskladAPI('s1');
    for ($i = 3; $i <= 24; $i = $i + 3){
      $msItems = $ms->getTurnoverByFullPeriod($i - 3, $i);
      foreach ($msItems as $key => $value) {

        if ( !empty($value['assortment']['article']) and
        ($value['income']['quantity'] != '0') and
        empty($this->fromMS[$value['assortment']['article']])) {

          $this->fromMS[ $value['assortment']['article'] ] = ($value['income']['sum'] / 100) / $value['income']['quantity'];

        }
      }
      $this->writeLog('Получен оборот за ' . $i . ' месяцев: ' . count($msItems));
      sleep(1);
    }
  }

  private function prepareData()
  {
    if ( empty($this->fromMS) ){
      $this->writeLog('Не получена себестоимость товаров');
      return false;
    }
    $preparedData = [];
    foreach ($this->fromMS as $key => $value) {
      $preparedData[] = ['model' => $key, 'cost' => $value];
    }
    return $preparedData;
  }

  private function writeDB()
  {
    global $DB;
    $data = $this->prepareData();
    if ( empty($data) ){
      $this->writeLog('Ошибка: нечего импортировать');
      return;
    }
    $this->writeLog('Будет импортировано ' . count($data) . ' товаров');
    $strSql = "TRUNCATE TABLE current_cost_ms";
    $DB->Query($strSql, false, $err_mess.__LINE__);
    $chunks = array_chunk($data, 200);
    foreach ( $chunks as $key => $value ){
      $this->fuckYouBitrixORM( 'current_cost_ms', $value );
      $this->writeLog('Обработан пакет ' . $key);
      sleep(1);
    }
  }

  private function writeLog($message)
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }

  private function fuckYouBitrixORM($tableName , $arrayData)
  {
    global $DB;
    $cardSample = $arrayData[0];
    $fields = [];
    foreach ($cardSample as $key => $value) {
      $fields[] = $key;
    }
    if (empty($fields) || count($fields) < 2) return false;
    $strSql = "INSERT INTO {$tableName} " . '(';

    $i = 0;
    foreach ($fields as $fname) {
      $strSql .= (count($fields) - 1 != $i) ? "{$fname}," : $fname;
      $i++;
    }
    $strSql .= ') VALUES ';
    $c = 0;
    foreach ($arrayData as $card){
      $strSql .= '(';
      $k = 0;
      foreach ($card as $field) {
        $strSql .= (count($card) - 1 != $k) ? "'{$field}'," : "'{$field}'";
        $k++;
      }
      $strSql .= ( count($arrayData) - 1 != $c ) ? '),' : ')';
      $c++;
    }
    // var_dump($strSql);
    $DB->Query($strSql, false, $err_mess.__LINE__);
  }

}

(new TurnoverClass)->run();


 ?>
