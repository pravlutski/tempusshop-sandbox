<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class ExitSales
{
  private $cabinet;

  private $dbPanel;
  private $db;

  private $activeSaleProducts; // Товары в акциях
  private $salesActive; // Все доступные акции

  private $triggers;

  private $api_url;
  private $client_id;
  private $token;

  private $date;

	public function __construct( $cabinet )
  {
		if ( !in_array($cabinet, ['TI', 'IP']) ) die('WRONG CABINET');

		$this->cabinet = $cabinet;
		global $DB;
		$this->loadModules();
		$this->triggers = new TsTriggers();
		$this->dbPanel = new DBPanel();
		$this->db = $DB;

		$rows = $this->dbPanel->select(["*"],"ozon_main_settings_{$this->cabinet}")->make();
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		unset($rows);

		$rows = $this->dbPanel->select(["*"], "ozon_sales_info_{$this->cabinet}")->make();
    foreach ($rows as $row) {
      $this->curSalePrice[$row['model']][$row['sale_id']] = $row['action_max_price'];
    }
	  unset($rows);

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];

    $this->date = date('d.m.Y');
	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule('panel.manager');
  }

	public function run(){

		$this->getItems();
		$this->getActiveSales();
		$this->getUsesProducts();
		$this->deactivateSales();
    $this->clearPriceTable();
	}

  public function getItems():void
  {
		if ($this->cabinet == 'TI') {
			$constPrice = 'PRICE_OZTI';
			$constID = 'OZON_ID_TI';
			$this->constPriceType = 'ozti';
		} else if ($this->cabinet == 'IP') {
			$constPrice = 'OZSB_PRICE';
			$constID = 'OZON_ID';
			$this->constPriceType = 'os';
		} else {
			die('WRONG CONST');
		}

    $arSelect = Array("ID","IBLOCK_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_".$constPrice."","PROPERTY_WBARTICLE","PROPERTY_".$constID."");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да'
    );

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

    while ($el = $result->GetNext()){
			if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') continue;
      if (empty($el['PROPERTY_'.$constPrice.'_VALUE']) or $el['PROPERTY_'.$constPrice.'_VALUE'] == 0) continue;

			$this->items[ $el["PROPERTY_".$constID."_VALUE"] ] = [
	    	"ID" => $el["ID"],
				"PRICE" => $price,
				"MODEL" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
	    	"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
	    	"OZON_ID" => $el["PROPERTY_".$constID."_VALUE"],
	    ];
    }
	}

	public function getActiveSales():void
  {
		$dateStart = date('d.m.Y');
    $strSql = "SELECT * FROM ozon_sales_{$this->cabinet} WHERE STR_TO_DATE(date_start, '%d.%m.%Y') < STR_TO_DATE('".$dateStart."', '%d.%m.%Y')";
		$result = $this->dbPanel->query( $strSql );
		$rows = $this->dbPanel->fetchAll( $result );
		foreach ( $rows as $row ) {
			$this->salesActive[ $row['sale_id'] ] = $row;
		}
	}

	public function getUsesProducts():void
  {

    $method = '/v1/actions/products';

		foreach ($this->salesActive as $sale_id => $value) {

			$check = true;
			$last_id = "";

			while ( $check == true ) {
				$data = json_encode([
					"action_id"=> $sale_id,
          "limit" => 1000,
          "last_id" => $last_id
				]);
				$res = $this->sendRequest($method, $data);
				$res = json_decode($res, true);
				if (isset($res['result'])) {
					if (count($res['result']['products']) < 1000) {
						$check = false;
					} else {
						$check = true;
					}
				} else {
					$check = false;
					print_r( "ОШИБКА В ОТВЕТЕ API OZON GetUsesProducts\n" );
					print_r($value['sale_id']. "\n");
				}

        if ( isset($res['result']['products']) && count($res['result']['products']) == 0 ) continue 2;

				foreach ($res['result']['products'] as $key => $value) {
          if (!isset($this->items[$value['id']])) continue;
					$this->activeSaleProducts[ $sale_id ][] = intval( $value['id'] );
				}

				$last_id = $res["result"]["last_id"];
			}
		}
	}

	public function deactivateSales():void
	{
    if ( empty($this->activeSaleProducts) ) {
      die('Nothing to deactivate');
    }
    $method = "/v1/actions/products/deactivate";

    foreach ( $this->activeSaleProducts as $sale_id => $products ) {
      $dataChunks = array_chunk($products, 1000);
      foreach ( $dataChunks as $chunk ){
        $json = json_encode([
          'action_id' => $sale_id,
          'product_ids' => $chunk
        ]);
				// var_dump($json);
        $res = $this->sendRequest( $method, $json );
				var_dump( $res );
				print_r("\n"); 
      }
    }

	}

  private function sendRequest(string $method, string $data_string ):string
  {
    $ch = curl_init( $this->api_url . $method );
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Api-Key:' . $this->token,
      'Client-Id:' . $this->client_id,
      'Content-Type:application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $res = curl_exec($ch);
    curl_close($ch);

    return $res;
  }

	public function clearPriceTable():void
	{
		if ( empty($this->activeSaleProducts) ) die("CANNOT DELETE DATA FROM TABLE WHILE ACTIVE SALE PRODUCTS ARRAY IS EMPTY\n");
		$this->dbPanel->query("DELETE FROM ozon_sales_prices_{$this->cabinet} WHERE 1=1");
	}

}

(new ExitSales( $argv[1] ?? ''))->run();
