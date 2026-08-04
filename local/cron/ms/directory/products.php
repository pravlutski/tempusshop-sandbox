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
	    $res = $this->ms->send("/entity/product/", "GET", [], ["Content-Type" => "application/json"], false, ["offset" => $i]);
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test2.txt", print_r($res['rows'], true), FILE_APPEND);
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
	          'product_id' => $v['id'],
	          'product_name' => $v['name'],
	          'group' => $v['pathName'],
	          'country' => $v['country']['meta']['id'],
	          'supplier' => str_replace('https://api.moysklad.ru/api/remap/1.2/entity/counterparty/','',$v['supplier']['meta']['href']),
	          'item_number' => $v['article'],
	          'code' => $v['code'],
	          'vat' => $v['vat'],
	          'external_code' => $v['externalCode'],
	          'EAN8' => $arBar['ean8'],
	          'EAN13' => $arBar['ean13'],
	          'Code128' => $arBar['code128'],
	          'GTIN' => $arBar['gtin'],
	          'UPC' => $arBar['upc'],
	        ];
	        unset($arBar);
	      }
	      $i = $i + 1000;
	    } else {
	      $start = false;
	    }
  	}

		$res = $this->ms->send("/entity/service", "GET", [], ["Content-Type" => "application/json"], false, ["offset" => 0]);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/test2.txt", print_r($res['rows'], true), FILE_APPEND);
		if (count($res['rows']) > 0) {
			foreach ($res['rows'] as $k => $v) {
				foreach ($v['barcodes'] as $bk => $bv) {
					foreach ($bv as $sl => $sv) {
						$arBar[$sl] = $sv;
					}
				}
				$this->arMsData[] = [
					'site_id' => $this->site_id,
					'product_id' => $v['id'],
					'product_name' => $v['name'],
					'group' => $v['pathName'],
					'country' => $v['country']['meta']['id'],
					'supplier' => str_replace('https://api.moysklad.ru/api/remap/1.2/entity/counterparty/','',$v['supplier']['meta']['href']),
					'item_number' => $v['article'],
					'code' => $v['code'],
					'vat' => $v['vat'],
					'external_code' => $v['externalCode'],
					'EAN8' => $arBar['ean8'],
					'EAN13' => $arBar['ean13'],
					'Code128' => $arBar['code128'],
					'GTIN' => $arBar['gtin'],
					'UPC' => $arBar['upc'],
				];
				unset($arBar);
			}
		}

  }

  public function setInBd(){
    //clear
    $this->db->Query("DELETE FROM ci_ms_directory_products WHERE site_id = '{$this->site_id}'");
    //Insert
    foreach ($this->arMsData as $key => $data) {
      $in = array(
        'site_id' => "'".$this->site_id."'",
        'product_id' => "'".$data['product_id']."'",
        'product_name' => "'".$data['product_name']."'",
        'group' => "'".$data['group']."'",
        'country' => "'".$data['country']."'",
        'supplier' => "'".$data['supplier']."'",
        'item_number' => "'".$data['item_number']."'",
        'code' => "'".$data['code']."'",
        'vat' => "'".$data['vat']."'",
        'external_code' => "'".$data['external_code']."'",
        'EAN8' => "'".$data['EAN8']."'",
        'EAN13' => "'".$data['EAN13']."'",
        'Code128' => "'".$data['Code128']."'",
        'GTIN' => "'".$data['GTIN']."'",
        'UPC' => "'".$data['UPC']."'",
      );

      $this->db->Insert("ci_ms_directory_products", $in, $err_mess.__LINE__);
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


(new ProductsDirectory("msk"))->run();
(new ProductsDirectory("s1"))->run();
?>
