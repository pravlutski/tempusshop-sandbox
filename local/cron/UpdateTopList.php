<?
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

set_time_limit(3600);
//error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

set_time_limit(3600);

global $DB, $USER;
use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class UpdateTopList{

	private $settings;
	private $ms;
	private $brands;
	private $sections;
	private $items;
	private $connection;

	function __construct($site_id = "s1"){
		global $DB;
		$this->site_id = $site_id;
		$this->loadModules();
		$this->connection = Application::getConnection();
		$this->db = $DB;
		$this->ms = new MoyskladAPI($this->site_id);

		$this->triggers = new TsTriggers();
		$this->logger = new TsLogger("/" . __CLASS__ . "/");
		$this->workers = new WorkersChecker(__CLASS__);
	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("panel.manager");
	}

	public function run(){
		$this->logger->log("LOG", "Запуск обработчика {$this->site_id}");
		$this->logger->StartDebugTime(__FUNCTION__);

		// if (!$this->workers->checkStatus()) {
		// 	$this->logger->log("LOG", "Не нужно обрабатывать");
		// 	exit();
		// }

		$this->workers->updateStatus("Y");
		CProSet::setOption("UPDATE_TOP_{$this->site_id}", "0");
		// получаем настройки ТОПА для сайта
		$this->getSettings();
		// получаем список брендов
		$this->getBrands();
		// ищем разделы в битриксе по брендам из настроеек
		$this->getSections();
		// получаем отчет из МС
		$this->getItems();

		if(count_($this->items) > 0){
			$this->clear();
			$this->prepareItems();
			print_r(count($this->items));
			print_r('###');
			$this->getItemsAdditional();
			print_r(count($this->items));
			$this->setItems();

			//$this->triggers->SetError(["ТОП лист {$this->site_id} обновлен. " . count($this->items) . " товаров."]);
			//$this->triggers->SendTriggerErrors();

			CProSet::setOption("UPDATE_TOP_{$this->site_id}", count($this->items));
		}else{
			$this->logger->log("LOG", "ТОП лист {$this->site_id} не обновлен.");
			$this->triggers->SetError(["ТОП лист {$this->site_id} не обновлен."]);
			$this->triggers->SendTriggerErrors();
		}

		$this->workers->updateStatus("N");
		$this->logger->log("LOG", "Затраченное время: \r\n".print_r($this->logger->debugData, true));
		$this->logger->log("LOG", "Завершение работы");
		$this->logger->EndDebugTime(__FUNCTION__);
	}

	public function getSettings(){
		$this->logger->log("LOG", "Получаем настройки");
		$this->logger->StartDebugTime(__FUNCTION__);

		$arSettings = CProSet::getOption("TOP_ITEMS_" . $this->site_id);
		$arSettings = json_decode($arSettings, true);
		foreach($arSettings["brand"] as $brand_id => $arItem){
			if($arItem["active"] == "Y" && $arItem["min"] > 0){
				$this->settings["brand"][$brand_id] = $arItem["min"];
			}
		}
		foreach($arSettings["subbrand"] as $brand_id => $arItem){
			if($arItem["min"] > 0){
				$this->settings["subbrand"][$brand_id] = $arItem["min"];
			}
		}
		$this->settings["additional"] = $arSettings["additional"];
		// if (!empty($this->settings["additional"])) {
		// 	$this->settings["additional"] = explode("\n", $this->settings["additional"]);
		// 	$this->settings["additional"] = array_filter($this->settings["additional"], function($item) {
		// 			return !empty(trim($item));
		// 	});
		// 	$this->settings["additional"] = array_values($this->settings["additional"]);
		// }
		$this->logger->EndDebugTime(__FUNCTION__);

	}

	private function getBrands(){
		if(!$this->settings["brand"]) return false;
		$this->logger->log("LOG", "Получаем бренды");
		$this->logger->StartDebugTime(__FUNCTION__);

		$brandID = array_keys($this->settings["brand"]);
		if(!$brandID) return false;

		$strSql = "SELECT id, name FROM ci_brands WHERE id IN ('".implode("','", $brandID)."')";
		$results = $this->connection->query($strSql);

        while ($row = $results->Fetch()){
            $this->brands[$row["id"]] = $row["name"];
        }

		$this->logger->log("LOG", "Получено - " . count($this->brands) . " брендов");
		$this->logger->EndDebugTime(__FUNCTION__);

	}

	private function getSections(){
		if(!$this->brands) return false;
		$this->logger->log("LOG", "Получаем разделы");
		$this->logger->StartDebugTime(__FUNCTION__);

		$strSql = "SELECT ID, NAME FROM b_iblock_section WHERE IBLOCK_ID = '16' AND DEPTH_LEVEL = '2' AND NAME IN ('".implode("','", $this->brands)."')";
		$results = $this->connection->query($strSql);

        while ($row = $results->Fetch()){
            $this->sections[$row["ID"]] = $row["NAME"];
			$brand_id = array_search($row["NAME"], $this->brands);
			if($brand_id){
				$this->sectionMatchBrand[$row["ID"]] = $brand_id;
			}
        }
		$this->logger->log("LOG", "Получено всего - " . count($this->sections) . ", связанных - " . count($this->sectionMatchBrand) . " разделов");
		$this->logger->EndDebugTime(__FUNCTION__);

	}

	public function getItems(){
		if(!$this->sections) return false;
		$this->logger->log("LOG", "Получаем отчет из MS");
		$this->logger->StartDebugTime(__FUNCTION__);

		$arFilter = array(
			"momentFrom" => date("Y-m-d", strtotime("- 12 months")),
			"momentTo" => date("Y-m-d"),
		);

		$res = $this->ms->getListProfitNew($arFilter);

		foreach($res as $key => $arItem){
			if(!$arItem["assortment"]["code"]) continue;
			$this->items[$arItem["assortment"]["code"]] = array(
				"NAME" => $arItem["assortment"]["name"],
				"XML_ID" => $arItem["assortment"]["code"],
				"ARTICLE" => $arItem["assortment"]["article"],
				"SELL_QUANTITY" => $arItem["sellQuantity"],
			);
		}

		$arXml = array_keys($this->items);

		$strSql = "SELECT el.ID as ELEMENT_ID, el.XML_ID as ELEMENT_XML_ID, psc.IBLOCK_SECTION_ID as SECTION_ID
			FROM
				b_iblock_element el
			LEFT JOIN
				b_iblock_section_element sc
			ON el.ID=sc.IBLOCK_ELEMENT_ID
			LEFT JOIN
				b_iblock_section psc
			ON sc.IBLOCK_SECTION_ID=psc.ID
			WHERE
				el.XML_ID IN ('".implode("','", $arXml)."') AND psc.IBLOCK_SECTION_ID IN ('".implode("','", array_keys($this->sections))."') AND el.IBLOCK_ID = '16'";

		$results = $this->connection->query($strSql);

        while ($row = $results->Fetch()){
					$this->items[$row["ELEMENT_XML_ID"]]["BX_ID"] = $row["ELEMENT_ID"];
					$this->items[$row["ELEMENT_XML_ID"]]["SECTION"][] = $row["SECTION_ID"];

					if($this->sectionMatchBrand[$row["SECTION_ID"]]){

						if ($this->sectionMatchBrand[$row["SECTION_ID"]] == 1) {
							$arSection = getSectionsElement($row["ELEMENT_ID"]);
							if  (!empty($this->settings["subbrand"][$arSection[2]['ID']])) {
								$this->items[$row["ELEMENT_XML_ID"]]["MIN_SELL_QUANTITY"] = $this->settings["subbrand"][$arSection[2]['ID']];
							} else {
								$this->items[$row["ELEMENT_XML_ID"]]["MIN_SELL_QUANTITY"] = $this->settings["brand"][$this->sectionMatchBrand[$row["SECTION_ID"]]];
							}
						} else {
							$this->items[$row["ELEMENT_XML_ID"]]["MIN_SELL_QUANTITY"] = $this->settings["brand"][$this->sectionMatchBrand[$row["SECTION_ID"]]];
						}

					}
        }


		$this->logger->log("LOG", "Получено элементов " . count($this->items));

		$this->logger->EndDebugTime(__FUNCTION__);

	}

	public function getItemsAdditional(){
		$this->logger->log("LOG", "Добавляем принудительно товары, если есть");
		$this->logger->StartDebugTime(__FUNCTION__);

		$arArticle = explode("\r\n", $this->settings["additional"]);
		$arArticle = array_diff($arArticle, array(''));
		//$arArticle = $this->settings["additional"];
		// print_r($arArticle);
		if(!$arArticle){
			$this->logger->log("LOG", "Нет доп артикулов");
			return;
		}

		$this->logger->log("LOG", "Дополнительные артикулы" . print_r($arArticle, true));

		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
			"=PROPERTY_CML2_ARTICLE" => $arArticle,
		);

		$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","XML_ID","NAME","PROPERTY_CML2_ARTICLE"));
		while($ar = $rs->GetNext()){
			print_r($ar["PROPERTY_CML2_ARTICLE_VALUE"]);
			if(!$this->items[$ar["XML_ID"]]){
				$this->items[$ar["XML_ID"]] = array(
					"NAME" => $ar["NAME"],
					"XML_ID" => $ar["XML_ID"],
					"ARTICLE" => $ar["PROPERTY_CML2_ARTICLE_VALUE"],
					"SELL_QUANTITY" => 1,
					"BX_ID" => $ar["ID"],
					"MIN_SELL_QUANTITY" => 1,
				);
			}
		}

		$this->settings["additional"] = $arSettings["additional"];
		$this->logger->EndDebugTime(__FUNCTION__);

	}

	private function prepareItems(){
		$this->logger->StartDebugTime(__FUNCTION__);
		$this->logger->log("LOG", "До очистки массива - " . count($this->items));

		foreach($this->items as $key => $arItem){
			if(!$arItem["MIN_SELL_QUANTITY"] || $arItem["SELL_QUANTITY"] < $arItem["MIN_SELL_QUANTITY"] || !$arItem["BX_ID"]){
				unset($this->items[$key]);
			}
		}

		$this->logger->log("LOG", "После очистки массива - " . count($this->items));
		$this->logger->EndDebugTime(__FUNCTION__);
	}
	private function clear(){
		$this->logger->StartDebugTime(__FUNCTION__);

		$this->db->Query("DELETE FROM ci_top_models WHERE site_id = '".$this->site_id."'", false, $err_mess.__LINE__);

		$this->logger->EndDebugTime(__FUNCTION__);
	}

	private function setItems(){
		$this->logger->StartDebugTime(__FUNCTION__);
		foreach($this->items as $key => $arItem){
			$in = array(
				"model" => "'".addslashes($arItem["ARTICLE"])."'",
				"sell_quantity" => "'".addslashes($arItem["SELL_QUANTITY"])."'",
				"site_id" => "'".$this->site_id."'",
				"bitrix_id" => "'".$arItem["BX_ID"]."'",
			);
			$this->db->Insert("ci_top_models", $in, $err_mess.__LINE__);
		}
		$this->logger->EndDebugTime(__FUNCTION__);
	}

}
if ( in_array($argv[1], ['RU', 'BY', 'RU_NKZ']) ){
	if ( $argv[1] == 'RU' ){
		(new UpdateTopList("s1"))->run();
	}
	if ( $argv[1] == 'BY' ){
		(new UpdateTopList("s2"))->run();
	}
	if ( $argv[1] == 'RU_NKZ' ){
		(new UpdateTopList("s1_nkz"))->run();
	}
}else{
	(new UpdateTopList("s1"))->run();
	(new UpdateTopList("s2"))->run();
	(new UpdateTopList("s1_nkz"))->run();
}
//(new UpdateTopList("s3"))->run();

?>
