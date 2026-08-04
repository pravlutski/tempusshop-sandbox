<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

class SKUGetter
{
  private $items;
  private $arData;

  private $api;
  private $dbPanel;

	public function __construct( string $cabinet )
	{
		if ( !in_array( $cabinet, ["IP"] ) ) die( "WRONG CABINET"); // Кабинет ТИ и любой кроме ИП не поддерживатются, так как мне лень создавать под них таблицы
		CModule::IncludeModule('panel_manager');

		$this->cabinet = $cabinet;
		$this->dbPanel = new DBpanel;

		$rows = $this->dbPanel->select(["*"],"ozon_main_settings_{$this->cabinet}")->make();
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		$this->api_url = $arSetting['api_url'];
		$this->client_id = $arSetting['client_id'];
		$this->token = $arSetting['key'];


		$this->table = "ozon_sku_dict_{$cabinet}";
	}

	public function run()
	{
		$this->getItems();
		$this->getSkus();
		$this->writeDB();
	}

	private function getItems():void
	{
		$exceptions = $this->getExceptions();

		$arFilter = [
			"IBLOCK_ID" => 16,
			"PROPERTY_OZON_ACTIVE" => 1943,
		];

		$arSelect = [ 'ID', 'IBLOCK_ID', 'PROPERTY_CML2_ARTICLE', 'PROPERTY_OZON_ACTIVE', 'PROPERTY_WBARTICLE' ];

		$res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

		$this->items = [];

		while ( $row = $res->GetNext() ){
			if ( in_array($row['PROPERTY_CML2_ARTICLE_VALUE'], $exceptions) ) continue;
			$this->items[$row['PROPERTY_WBARTICLE_VALUE']] = [
				'ID' => $row['ID'],
				'ARTICLE' => $row['PROPERTY_CML2_ARTICLE_VALUE'],
			];
		}

		/*$this->items = [
			'T_TISSOT_T116.410.11.047.00' => [
				'ID' => 145529,
				'ARTICLE' => 'T116.410.11.047.00',
			]
		];*/
	}

	private function getSkus():void
	{
		if ( empty( $this->items ) ) die("ITEMS ARRAY CANNOT BE EMPTY (getSkus)\n");

		$arArticle = array_keys($this->items);

		$dataChunks = array_chunk($arArticle, 1000);

		$this->arData = [];

		foreach ($dataChunks as $chunk) {
			$data = json_encode([
				'filter' => [
					'offer_id' => $chunk,
				],
				'limit' => 1000,
			]);
			$res = $this->sendRequest( '/v4/product/info/attributes', $data );

			$res = json_decode( $res, true );
			//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/a.txt', print_r($res, true));

			if ( !isset($res['result']) || count($res['result']) == 0 ){
				echo "NO RESULT\n";
				continue;
			}

			foreach ( $res['result'] as $item ){
				$this->arData[] = [
					'model' => $this->items[$item['offer_id']]['ARTICLE'],
					'offer_id' => $item['offer_id'],
					'sku' => $item['sku'],
				];
			}
			sleep( 2 );
		}
	}

	private function writeDB():void
	{
		if ( empty($this->arData) ) die("NOTHING TO IMPORT");

		$arModels = array_column($this->arData, 'model');

		$result = $this->dbPanel->query("DELETE FROM {$this->table} WHERE model IN ('".implode("','", $arModels)."')");

		$this->dbPanel->insert( $this->table, $this->arData );
  }

	private function getExceptions():array
	{
		$res = $this->dbPanel->select( ["*"], $this->table )->make();

		$return = [];
		foreach ( $res as $row ){
			$return[] = $row['model'];
		}

		return $return;
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
}

( new  SKUGetter( $argv[1] ) )->run();

 ?>
