<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("panel_engine_ozon_cleaning_clearSalesLog_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");

class SalesLogCleaner
{
  private string $periodUnit = 'week';
  private int $period = 2;
  private string $table = 'ozon_sales_detail_log_IP';

  private ?DBPanel $panel = null;

  public function run():void
  {
    $this->init();
    $this->clear();
  }

  private function clear():void
  {
    $period = $this->buildDate();
    $strSql = "DELETE FROM {$this->table} WHERE date < '{$period}'";

    $this->panel->query( $strSql );
  }

  private function init():void
  {
    $this->panel = new DBPanel;
  }

  private function buildDate():string
  {
    $period = "- {$this->period} {$this->periodUnit}";
    $date = date("Y-m-d", strtotime($period));

    return $date;
  }
}

(new SalesLogCleaner)->run();
$workers->updateStatus("N");
 ?>
