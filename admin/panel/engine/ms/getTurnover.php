<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("admin_panel_engine_ms_getTurnover_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(0);
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');

class GetTurnover{
	public function __construct(){

		global $DB;
		$this->loadModules();
		$this->cabinet = $cabinet;
		$this->CurDB = new DBPanel();

		$this->module = 'msTurnover';

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
    }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}
		$timeStart = date('Y.m.d G:i:s');
		//Агент-Инфо
		$arStat = [
			'status' => 'PROCESS',
			'status_text' => 'Запуск скрипта',
			'percent' => 0,
			'time_start' => $timeStart
		];
		$this->updateStatus($this->module, $arStat);

		// $this->GetTurnover();
		$this->getTurnoverPerAgent();
		$this->PrintResult();

		$timeEnd = date('Y.m.d G:i:s');
		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => 100,
			'time_end' => $timeEnd
		];
		$this->updateStatus($this->module, $arStat);

	}


	public function GetTurnover()	{
		$this->fromMS = array();
		$ms = new MoyskladAPI('s1');
		// $filter = $this->buildFilter();
		// $filter = '&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/79ed7d71-0aa6-11ea-0a80-004200039aa4' . $filter;
		// var_dump($filter);
		// dсie;
		// $msItems = $ms->getTurnoverDay(2);
		// die;
		//print_r($msItems);
		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Поулчаем отчет оборотов за 2 дня', 'percent' => 20]);

		foreach ($msItems as $key => $value) {
			if (!empty($value['assortment']['article']) and ($value['income']['quantity'] != '0') and empty($this->fromMS[$value['assortment']['article']]) ) {
				$this->fromMS[$value['assortment']['article']] = intval(($value['income']['sum'] / 100) / $value['income']['quantity']);
			}
		}
		$perc = 20;
		for ( $i = 1; $i <= 10; $i++ ){
			$perc = $perc + 7;
			$this->updateStatus($this->module, ['status_text' => "Получаем отчет оборотов за {$i} неделю", 'percent' => $perc]);
			$msItems = $ms->getTurnoverWeek($i);

			foreach ($msItems as $key => $value) {
				if (!empty($value['assortment']['article']) and ($value['income']['quantity'] != '0') and empty($this->fromMS[$value['assortment']['article']]) ) {
					$this->fromMS[$value['assortment']['article']] = intval(($value['income']['sum'] / 100) / $value['income']['quantity']);
				}
			}
		}
		// var_dump( count($this->fromMS) );
		// die;
	}

	private function getTurnoverPerAgent():void
	{
		$ms = new MoyskladAPI('s1');
		$suppliers = $this->buildFilter();
		$metaHref = 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/';
		$filterStore = '&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/79ed7d71-0aa6-11ea-0a80-004200039aa4';
		$filterStart = '&filter=agent=';
		$formatted = [];

		foreach ( $suppliers as $supp ){
			$filter = $filterStore . $filterStart . $metaHref . $supp;

			$items = $ms->getTurnoverDayNew( dayFrom: 3, dayTo: 1, filter: $filter );
			// if ( empty($items) ){
			// 	print_r("Skipped {$supp} day. No items\n");
			// 	continue;
			// }
			if ( !empty($items) ){
				$tmp = $this->processResponse( $items, 0, $supp );
				$formatted = $this->mergeSupplierData( $formatted, $tmp );
			}

			for ( $i = 1; $i <= 10; $i++ ){
				$items = $ms->getTurnoverWeek( $i, $filter );
				if ( empty($items) ){
					print_r("Skipped {$supp} week. No items\n");
					continue;
				}
				$tmp = $this->processResponse( $items, $i, $supp );
				$formatted = $this->mergeSupplierData( $formatted, $tmp );
				sleep(1);
			}
		}
		$formatted = $this->sortDataStructure( $formatted );

		// file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ms/turnover.log', print_r($formatted, true));
		$this->calculateCostMS( $formatted );
		// file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ms/fromMS.log', print_r($this->fromMS, true));
		// var_dump( count($this->fromMS) );
		// die;
	}

	private function sortDataStructure(array $data): array
	{

    foreach ($data as &$modelData) {
        // Итерации - по номеру (0, 1, 2, ...)
        ksort($modelData, SORT_NUMERIC);
    }

    return $data;
	}

	private function mergeSupplierData(array $current, array $new): array
	{
	    foreach ($new as $model => $modelData) {
	        foreach ($modelData as $iteration => $iterationData) {
	            foreach ($iterationData as $supplier => $supplierData) {
	                if ( !isset($current[$model][$iteration][$supplier]) ) {
											$current[$model][$iteration][$supplier] = $supplierData;
	                }
	            }
	        }
	    }
	    return $current;
	}

	private function calculateCostMS( array $data ):void
	{
		foreach ( $data as $model => $modelData ){
			foreach ( $modelData as $iteration => $iterData ){
				$this->fromMS[$model] = $this->getAverageCost( $iterData );
				continue 2;
			}
		}
	}

	private function getAverageCost( array $data ):int
	{
		$sum = 0;
		$quan = 0;
		foreach ( $data as $value ){
			$quan += $value['quantity'];
			$sum += $value['cost'];
		}

		return round( $sum / $quan );
	}

	private function processResponse( array $items, int $iteration, string $supp ):array
	{
		$suppData = [];
		foreach ($items as $key => $value) {
			if ( empty($value['assortment']['article']) ) continue;
			if ( $value['income']['quantity'] == '0' ) continue;

			$model = $value['assortment']['article'];
			// $modelCost = ($value['income']['sum'] / 100) / $value['income']['quantity'];

			$suppData[$model][$iteration][$supp] = [
				'cost' => $value['income']['sum'] / 100,
				'quantity' => $value['income']['quantity']
			];
		}

		return $suppData;
	}

	private function buildFilter():array|bool
	{
		$path = '/var/www/bitrix/data/www/tempusshop.ru/bitrix/components/adm/utils.partners/configs/182118.json';

		$filterStart = '&filter=agent=';

		if ( !file_exists( $path ) ) return false;

		$json = file_get_contents($path);
		$data = json_decode( $json, true );

		$storeSupps = [
			'a389e34e-6901-11ef-0a80-0c11003c56a7', // Chronos
			'2b831384-f9a1-11ef-0a80-07570009a737', // Novatime
			'1de0fc7d-3c75-11ea-0a80-0652000ce149', // TimeTrade
			'b8e7c736-3bc2-11f0-0a80-09fd0010bf8f', // WR
		];

		if ( !is_array($data) || empty($data) ) return false;

		$whereCond = array_map(function($item){
			return "'".$item."'";
		}, $data);
		$whereCond = implode( ',', $whereCond );

		$db = \Bitrix\Main\Application::getConnection();
		$strSql = "SELECT settings FROM ci_suppliers WHERE id IN ($whereCond)";
		$result = $db->Query( $strSql );
		$ms_ids = [];

		while ( $row = $result->Fetch() ){
			$ms_ids[] = json_decode($row['settings'], true)['mc_name'];
		}

		$ms_ids = array_merge($ms_ids, $storeSupps);

		return $ms_ids;
	}

	public function PrintResult()
	{
		if (!empty($this->fromMS)) {
			$this->CurDB->Query("DELETE FROM ms_turnover WHERE 1=1", false, $err_mess.__LINE__);
		}
		$this->updateStatus($this->module, ['status_text' => "Сохраняем значния", 'percent' => 90]);

		foreach ($this->fromMS as $model => $q) {
			$in = array(
				"model" => "'".$model."'",
				"quantity" => "'".$q."'",
				"date" => "'".date('Y-m-d G:i:s')."'",
			);

			$fields = implode(",", array_keys($in));
			$values = implode(",",$in);

			$sql = "INSERT INTO ms_turnover ($fields) VALUES ($values)";
			$this->CurDB->query($sql);
		}

	}

	public function updateStatus( string $code, array $arStat ):void
	{
		if ( empty($arStat) ) return;
		$strSql = "UPDATE ozon_agents SET ";
		foreach ($arStat as $field => $value) {
			if ( array_key_last($arStat) == $field ){
				$str = "{$field} = '{$value}'";
			}else{
				$str = "{$field} = '{$value}', ";
			}
			$strSql .= $str;
		}
		$strSql .= " WHERE code = '{$code}'";
		try{
			$this->CurDB->query( $strSql );
		}catch( Throwable $ignored){
			print_r('Не удалось обновить статус' . $ignored . "\n");
		}
	}

}

(new GetTurnover())->run();
