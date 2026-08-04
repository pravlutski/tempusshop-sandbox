#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);
//if (function_exists('ini_set')) ini_set('memory_limit','1512M');

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class ProductsDirectory{
	public function __construct($site_id = "msk"){
		global $DB;
		$this->loadModules();
		$this->ms = new MoyskladAPI($site_id);
		$this->site_id = $site_id;
		$this->db = $DB;
    $this->arTmpReport = array();
	}

	private function loadModules()
    {
  		Loader::includeModule("panel.manager");
  		Loader::includeModule("iblock");
      Loader::includeModule("catalog");
    }

	public function run(){
		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$this->getReports();

    $this->setReportsInBd();

    $this->getReportsPositions();

		$this->setPositionsInBd();
    // $this->setInBd();
    //
    // $this->setOptions();
	}

  public function getReports(){
    $start = true;
    $i = 0;
    while ($start) {
	    //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test5.txt", print_r('ENTER - '. $i , true), FILE_APPEND);
	    $res = $this->ms->send("/entity/commissionreportin", "GET", [], [], false, ["offset" => $i]);
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test2.txt", print_r($res['rows'], true), FILE_APPEND);
	    if (count($res['rows']) > 0) {
        //print_r($res['rows']);
	      foreach ($res['rows'] as $k => $v) {
          $agent = explode('https://api.moysklad.ru/api/remap/1.2/entity/counterparty/',$v['agent']['meta']['href']);
					$organization = explode('https://api.moysklad.ru/api/remap/1.2/entity/organization/',$v['organization']['meta']['href']);
            $itmes[] = [
              'link' => $v['meta']['href'],
              'commision_id' => $v['id'],
              'datetime' => $v['moment'],
              'agent' => $agent[1],
							'organization' => $organization[1],
            ];
	      }
	      $i = $i + 1000;
	    } else {
	      $start = false;
	    }
  	}
		$this->arTmpReport = $itmes;
  }

  public function setReportsInBd(){
    //clear
    $this->db->Query("DELETE FROM ci_ms_directory_commission WHERE site_id = '{$this->site_id}'");
    //Insert
    foreach ($this->arTmpReport as $key => $data) {
      $in = array(
        'site_id' => "'".$this->site_id."'",
        'link' => "'".$data['link']."'",
        'commision_id' => "'".$data['commision_id']."'",
        'datetime' => "'".$data['datetime']."'",
        'agent' => "'".$data['agent']."'",
				'organization' => "'".$data['organization']."'",
      );

      $this->db->Insert("ci_ms_directory_commission", $in, $err_mess.__LINE__);
    }
  }

  public function getReportsPositions(){

    foreach ($this->arTmpReport as $key => $value) {
    //  $value = $this->arTmpReport[0];

      $start = true;
      $i = 0;
      while ($start) {
        //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test5.txt", print_r('ENTER - '. $i , true), FILE_APPEND);
        $res = $this->ms->send('/entity/commissionreportin/'.$value['commision_id'].'/positions', "GET", [], [], false, ["offset" => $i]);
        if (count($res['rows']) > 0) {

          foreach ($res['rows'] as $k => $v) {
            	$position_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/product/',$v['assortment']['meta']['href']);
              $items[] = [
								'site_id' => $this->site_id,
								'report_id' => $value['commision_id'],
                'position_id' => $position_id[1],
                'quantity' => $v['quantity'],

              ];
          }

          $i = $i + 1000;
        } else {
          $start = false;
        }
      }
			$this->tmpArrayPos = $items;

    }
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test2.txt", print_r($this->tmpArrayPos, true), FILE_APPEND);
  }

	public function setPositionsInBd(){
    //clear
    $this->db->Query("DELETE FROM ci_ms_directory_commission_positon WHERE site_id = '{$this->site_id}'");
    //Insert
    foreach ($this->tmpArrayPos as $key => $data) {
      $in = array(
				'site_id' => "'".$data['site_id']."'",
        'report_id' => "'".$data['report_id']."'",
        'position_id' => "'".$data['position_id']."'",
        'quantity' => "'".$data['quantity']."'",
      );
      $this->db->Insert("ci_ms_directory_commission_positon", $in, $err_mess.__LINE__);
    }
  }

  public function setOptions(){
    //Insert
      $in = array(
        'update' => "'".date("Y-m-d H:i:s")."'",
      );
      $this->db->Update("ci_ms_directory_options", $in, "WHERE agent ='getProducts' AND site_id ='".$this->site_id."'", $err_mess.__LINE__);
  }


}

(new ProductsDirectory("s1"))->run();
?>
