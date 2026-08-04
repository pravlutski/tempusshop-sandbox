#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');

class UpdaterProps {
	private $items = [];
	private $updateItems = [];
	private $propSync = ['MECHANISM']; // свойство которые надо отдать в rabbitmq
	public function __construct() {
		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$this->logger = new TsLogger("/" . __CLASS__ . "/");
		$this->syncHelper = new SyncHelper();
	}

	private function loadModules() {
		Loader::includeModule("main");
		Loader::includeModule("iblock");
    }

	public function run() {
		$this->logger->log("LOG", "Запуск обработчика");
		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}
		
		CProSet::setOption("UPDATER_RUN_PER", "0");
		CProSet::setOption("UPDATER_RUN", "Получаем товары");
		
		$this->getItems();
		
		// собираем массив для обновления
		CProSet::setOption("UPDATER_RUN_PER", "30");
		CProSet::setOption("UPDATER_RUN", "Собираем массив для обновления");
		$this->checkProps();
		
		// само обновление свойств
		CProSet::setOption("UPDATER_RUN_PER", "60");
		CProSet::setOption("UPDATER_RUN", "Обновляем свойства");
		$this->updateProps();
		
		// отправляем на темпус
		CProSet::setOption("UPDATER_RUN_PER", "90");
		CProSet::setOption("UPDATER_RUN", "Отправляем на темпус");
		$this->syncProps();
		
		CProSet::setOption("UPDATER_RUN", "N");
	}

	private function getItems() {
		$arSelect = Array(
			"ID", "PROPERTY_MECHANISM"
		);
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"ACTIVE" => 'Y',
			//"ID" => [1002,181473]
		);

		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while ($el = $result->GetNext()){
			$this->items[] = [
				"ID" => $el["ID"],
				"MECHANISM" => $el['PROPERTY_MECHANISM_VALUE'],
			];
		}
		$this->logger->log("LOG", "Товаров " . count($this->items));
	}

	private function checkProps() {
		foreach ($this->items as $item) {
			// чек свойства Механизм. если стоит "С автоподзаводом" или "С ручным заводом" и не заполнено "Механические", то проставляем "Механические"
			if (is_array($item['MECHANISM'])) {
				$mIds = array_keys($item['MECHANISM']);
				
				if (
					(in_array(481, $mIds) || in_array(464, $mIds)) &&
					!in_array(1459, $mIds)
				) {
					$mIds[] = 1459;
					$this->updateItems[$item['ID']]['MECHANISM'] = $mIds;
				}
			}
		}
		$this->logger->log("LOG", "Свойства которые надо изменить", $this->updateItems);
	}
	
	private function updateProps() {
		if (!$this->updateItems) return;
		foreach ($this->updateItems as $productId => $props) {
			CIBlockElement::SetPropertyValuesEx($productId, false, $props);
		}
	}

	private function syncProps() {
		if (!$this->updateItems) return;
		$productIds = array_keys($this->updateItems);
		
		$this->logger->log("LOG", "Отправляем на темпус", [$productIds, $this->propSync]);
		//prent($productIds);
		foreach (array_chunk($productIds, 1000) as $ids) {
			$this->syncHelper->sendPropProduct($ids, $this->propSync);
		}
	}
}

(new UpdaterProps())->run();
?>
