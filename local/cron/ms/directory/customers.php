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

class CustomersDirectory{
	public function __construct($site_id = "msk"){
		global $DB;
		$this->loadModules();
		$this->ms = new MoyskladAPI($site_id);
		$this->site_id = $site_id;
		$this->db = $DB;
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

		$this->getFromMs();
    $this->setInBd();

    $this->setOptions();
	}

  public function getFromMs(){
    $start = true;
    $i = 0;
    while ($start) {
	    //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test5.txt", print_r('ENTER - '. $i , true), FILE_APPEND);
	    $res = $this->ms->send("/entity/counterparty", "GET", [], ["Content-Type" => "application/json"], false, ["offset" => $i]);
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test3.txt", print_r($res['rows'], true), FILE_APPEND);
	    if (count($res['rows']) > 0) {

	      foreach ($res['rows'] as $k => $v) {
	        foreach ($v['barcodes'] as $bk => $bv) {
	          foreach ($bv as $sl => $sv) {
	            $arBar[$sl] = $sv;
	          }
	        }
	        //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test2.txt", print_r($arBar, true));
	        $this->arMsData[] = [
	          'site_id' => $this->site_id,
	          'customer_id' => $v['id'],
						'customer_name' => $v['name'],
						'group' => str_replace('https://api.moysklad.ru/api/remap/1.2/entity/group/','',$v['group']['meta']['href']),
						'adress' => $v['actualAddress'],
						'code' => $v['code'],
						'external_code' => $v['externalCode'],
						'type' => $v['companyType'],
						'TIN' => $v['inn'],
	        ];
	        unset($arBar);
	      }
	      $i = $i + 1000;
	    } else {
	      $start = false;
	    }
  	}
    //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test3.txt", print_r($this->arMsData, true));
  }

  public function setInBd(){
    //clear
    $this->db->Query("DELETE FROM ci_ms_directory_customers WHERE site_id = '{$this->site_id}'");
    //Insert

    foreach ($this->arMsData as $key => $data) {
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test3.txt", print_r($data, true), FILE_APPEND);
      $in = array(
        'site_id' => "'".$this->site_id."'",
        'customer_id' => "'".$data['customer_id']."'",
				'customer_name' => "'".addslashes($data['customer_name'])."'",
				'group' => "'".$data['group']."'",
				'adress' => "'".addslashes($data['adress'])."'",
				'code' => "'".$data['code']."'",
				'external_code' => "'".$data['external_code']."'",
				'type' => "'".$data['type']."'",
				'TIN' => "'".$data['TIN']."'",
      );

      $this->db->Insert("ci_ms_directory_customers", $in, $err_mess.__LINE__);
    }
  }

  public function setOptions(){
    //Insert
      $in = array(
        'update' => "'".date("Y-m-d H:i:s")."'",
      );
      $this->db->Update("ci_ms_directory_options", $in, "WHERE agent ='getCustomers' AND site_id ='".$this->site_id."'", $err_mess.__LINE__);
  }
}


(new CustomersDirectory("msk"))->run();
(new CustomersDirectory("s1_opt"))->run();
(new CustomersDirectory("s1"))->run();
?>
