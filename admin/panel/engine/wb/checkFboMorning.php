<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');

class checkFBO{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$this->CurDB = new DBPanel;

		$this->auth = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("panel.manager");
    }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$this->arLog = array();
		$this->arLog['TIME_START'] = date('H:i:s');
		$date = date('Y-m-d');

		$strSql = "SELECT * FROM wdhs_wb_fbo_correct" ;
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$this->correct[$row['model']] = $row['quantity'];
		}

		$startT = microtime(true);
		$this->sell =  $this->parseWBProductIds( 4000 );
		// print_r($res);
		// die();
		$this->getItemsWB_New();
		//$this->getItemsWB();
		$this->getItems();
		//$this->getSebes();
		//$this->getCiBrandID();
		// $this->GetTurnover();
		// $this->GetTurnoverBP();
		$this->checkFBOStock();
		$itemsT = microtime(true);

		//$this->db->Update("wdhs_WB_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='price'", $err_mess.__LINE__);

	}

	function makeRequest( $url ) {

	    $curl = curl_init();
	    curl_setopt( $curl, CURLOPT_URL, $url );
	    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	    curl_setopt( $curl, CURLOPT_HEADER, false );
	    $data = curl_exec( $curl );

	    curl_close($curl);

	    return $data;
	}


	function  parseWBProductIds( $pages ) {
	    $starttime = time();
	    $log = ["Parser started..."];
	    $result_set = [];

	    for( $i = 1; $i < $pages; $i++ ) {
	        $data = $this->makeRequest(sprintf( "https://catalog.wb.ru/sellers/v2/catalog?appType=1&curr=rub&dest=-1257786&page=%d&sort=popular&spp=30&supplier=724646", $i) );

	        $log[] = sprintf( "Trying to parse page: %d", $i );

	        $data_arr = json_decode( $data, true );

	        if( ! is_array( $data_arr ) ) {
	            $log[] = sprintf( "Parse error of page %d... Skipping", $i );
	            continue;
	        }

	        if( ! isset( $data_arr['data'] ) || ! isset( $data_arr['data']['products'] ) || ! isset( $data_arr['data']['total'] ) ) {
	            $log[] = "Unexpected array structure: ";
	            $log[] = print_r( $data_arr, true );
	            continue;
	        }

	        $log[] = sprintf( "Total count of products is: %d", $data_arr['data']['total'] );
	        $log[] = sprintf( "Count of products on current page is: %d", count( $data_arr['data']['products'] ) );

	        if( count( $data_arr['data']['products'] ) === 0 ) {
	            $log[] = "Exit.";
	            break;
	        }

	        foreach( $data_arr['data']['products'] as $product ) {
	            $result_set[] = $product['id'];
	        }
	    }
	    $worktime = time() - $starttime;
	    $log[] = "Spent {$worktime} seconds";
	    //file_put_contents( "debug.log", implode( PHP_EOL, $log ) . "\n", FILE_APPEND | LOCK_EX );


			$strSql = "SELECT nmid, article FROM wdhs_wb_props WHERE cabinet = 'WR'" ;
			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
			  if (in_array($row['nmid'],$result_set)) {
			    $wbSells[] = $row['article'];
			  }
			}
			 return $wbSells;
	}


	public function wbTest(){
		$base_url = WB_BASE_URL;
		$path = "/api/v1/supplier/stocks";

		//$data_string = array('dateFrom' => date("Y-m-d\TH:i:s"));

		$author = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");
		$api = new RestClient([
			'base_url' => 'https://discounts-prices-api.wb.ru',
			'curl_options' => array(
					CURLOPT_POST => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => false,
					CURLOPT_RETURNTRANSFER => TRUE,
					CURLOPT_HEADER => TRUE,
					CURLOPT_CUSTOMREQUEST => 'GET',
					CURLOPT_HTTPHEADER => array(
							'Content-Type: application/json',
							'Authorization: ' . $author,
					)
			)
	]);
	$path = '/api/v2/list/goods/filter?limit=10&filterNmID=75491826';
	$str_result = $api->post($path, []);
	//print_r(json_decode($str_result->response,true));
	$arResult = json_decode($str_result->response,true);
	//print_r($arResult);
	// print_r($this->stockFbo);
	// print_r($arResult);
}

	  public function getItemsWB(){
			$base_url = WB_BASE_URL;
			$path = "/api/v1/supplier/stocks";

			//$data_string = array('dateFrom' => date("Y-m-d\TH:i:s"));
			$data_string = array('dateFrom' => '2020-01-01');
			$author = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");
			$api = new RestClient([
				'base_url' => 'https://statistics-api.wildberries.ru',
				'curl_options' => array(
						CURLOPT_POST => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_SSL_VERIFYHOST => false,
						CURLOPT_RETURNTRANSFER => TRUE,
						CURLOPT_HEADER => TRUE,
						CURLOPT_CUSTOMREQUEST => 'GET',
						CURLOPT_HTTPHEADER => array(
								'Content-Type: application/json',
								'Authorization: ' . $author,
						)
				)
		]);
		$path = '/api/v1/supplier/stocks?dateFrom=2020-01-01';
		$str_result = $api->post($path, []);
		//print_r(json_decode($str_result->response,true));
		$arResult = json_decode($str_result->response,true);
		foreach ($arResult as $key => $value) {
			if (isset($this->stockFbo[$value['supplierArticle']])) {
				$this->stockFbo[$value['supplierArticle']] =  intval($this->stockFbo[$value['supplierArticle']]) + intval($value['quantity']);
			} else {
				$this->stockFbo[$value['supplierArticle']] =  intval($value['quantity']);
			}
		}
		// print_r($this->stockFbo);
		//print_r($arResult);
	}

	public function getItemsWB_New(){
		$author = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");
		$ch = curl_init('https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains?groupBySa=true');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: ' . $author,
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		$res = curl_exec($ch);
		curl_close($ch);

		$res = json_decode($res, true);


		$taskid = $res['data']['taskId'];

		unset($res);
		//$taskid = '8082f045-fec9-491a-b958-5a3ce5bf6fbd';
		sleep(20);

		 $ch = curl_init('https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains/tasks/'.$taskid.'/download');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: ' . $author,
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		$res = curl_exec($ch);
		curl_close($ch);

		$res = json_decode($res, true);

		$arResult = [];
		foreach ($res as $key => $value) {
			if (count($value['warehouses']) > 0) {
				foreach ($value['warehouses'] as $wh) {
					if ($wh['warehouseName'] != 'Всего находится на складах' && $wh['warehouseName'] != 'В пути до получателей' && $wh['warehouseName'] != 'В пути возвраты на склад WB') {
						if (isset($this->stockFbo[$value['vendorCode']])) {
							$this->stockFbo[$value['vendorCode']] = intval($this->stockFbo[$value['vendorCode']]) + intval($wh['quantity']);
						} else {
							$this->stockFbo[$value['vendorCode']] = intval($wh['quantity']);
						}
					}
				}
			}
		}

	}

  public function getItems(){
	    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_WBPRICE","PROPERTY_WBARTICLE2","PROPERTY_TYPEOFSKLAD");
	    $arFilter = Array(
	      "IBLOCK_ID" => CProSet::IB_CATALOG,
				"!PROPERTY_WBARTICLE2" => false,
	      //"ID" => 5045,
				//"SECTION_ID" => 558
	      // "ID" => 178901
	    );
			//$arFilter["!ID"] = 14124;
	    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);


	    while ($el = $result->GetNext()){
				if (isset($this->stockFbo[$el["PROPERTY_WBARTICLE2_VALUE"]])) {
					if (empty($el['PROPERTY_WBARTICLE2_VALUE']) or $el['PROPERTY_WBARTICLE2_VALUE'] == '') {
							$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
					}	else if (empty($el['PROPERTY_WBPRICE_VALUE']) or $el['PROPERTY_WBPRICE_VALUE'] == 0) {
							$this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
					} else {
						$arSection = getSectionsElement($el["ID"]);
						// if ($arSection[1]['ID'] == '558') {
			    	$this->items[$el["PROPERTY_WBARTICLE2_VALUE"]] = [
			    		"ID" => $el["ID"],
							"ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
			    		"WB_ARTICLE" => $el["PROPERTY_WBARTICLE2_VALUE"],
			    		"PRICE" => $el["PROPERTY_WBPRICE_VALUE"],
			    	];
					}
		    }
			}
			//print_r(count($this->items));
		}



  public function checkFBOStock()
  {
	  //$this->db->Query("DELETE FROM wdhs_wb_fbo_correct WHERE 1=1", false, $err_mess.__LINE__);

		foreach ($this->stockFbo as $key => $value) {
			if (isset($this->items[$key]) && !in_array($this->items[$key]['ARTICLE'],$this->sell)) {
				    //$this->answer[] = $this->items[$key]['ARTICLE'];
					if (!isset($this->correct[$this->items[$key]['ARTICLE']])) {
							$in = array(
								"model" => "'".$this->items[$key]['ARTICLE']."'",
								"quantity" => "'".$value."'",
							);
							$this->db->Insert("wdhs_wb_fbo_correct", $in, $err_mess.__LINE__);
					} else {
						$value = intval($value) + intval($this->correct[$this->items[$key]['ARTICLE']]);
						$in = array(
							"quantity" => "'".$value."'",
						);
						$this->db->Update("wdhs_wb_fbo_correct", $in, "WHERE model='".$this->items[$key]['ARTICLE']."'", $err_mess.__LINE__);
					}
			}

			// 	if ($value != 0 && $value >= 1) {
			// 		$in = array(
			// 			"article" => "'".$this->items[$key]['ARTICLE']."'",
			// 		);
			// 		$this->db->Insert("wdhs_wb_fbo_stock", $in, $err_mess.__LINE__);
			// 		if (isset($this->fromMS[$this->items[$key]['ARTICLE']])) {
			// 			$newprice = self::getNewPrice($this->items[$key]['BRAND_ID'],$this->fromMS[$this->items[$key]['ARTICLE']]);
			// 			$this->answer[$this->items[$key]['ARTICLE']]['count'] = $value;
			// 			$this->answer[$this->items[$key]['ARTICLE']]['asnw'] = "Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
			// 			Остаток на FBO WB: {$value}<br>
			// 			Себес из МС:{$this->fromMS[$this->items[$key]['ARTICLE']]}<br>
			// 			Новая цена:{$newprice}<br>
			// 			<span style=\"color:green\">Уснавливаем цену из МС</span><br><hr><br><br>";
			// 			$in = array(
			// 				"article" => "'".$this->items[$key]['ARTICLE']."'",
			// 				"price" => "'".$newprice."'",
			// 			);
			// 			$this->db->Insert("wdhs_wb_fbo_price", $in, $err_mess.__LINE__);
			// 		}
			//
			//
			//
			// 	}
			// 	//}
			// }
		}
  	print_r($this->answer);
  }


}

(new checkFBO())->run();
