#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/classes/class.xmltoarray.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/classes/class.xmlreader.php');
	
set_time_limit(3600);
global $DB;
class OnlinerXml extends SimpleXMLReader
{
	private $fileLocal;
	private $cntItem;
	
	public $arError = array();
    public function __construct(){
		$this->fileLocal = '/var/www/bitrix/data/www/tempusshop.ru/upload/structure.xml';
		$this->logger = new TsLogger("/onliner/" . __CLASS__ . "/");
		$this->triggers = new TsTriggers();
		$this->cntItem = 0;
    }
	
	public function run(){
		if (copy("https://content.onliner.by/catalog/structure.xml", $this->fileLocal)) {
			$this->exchange();
			$this->open($this->fileLocal);
			$this->parse();
		}else{
			$this->logger->log("LOG", "Файл скачать не удалось");
			$this->triggers->SetError(["Файл структуры онлайнера скачать не удалось\r\n"]);
			
		}
		
		if($this->cntItem == 0){
			$this->triggers->SetError(["Файл структуры онлайнера не удалось распарсить."]);
		}
		$this->logger->log("LOG", "Загружено {$this->cntItem} моделей");
		
		$this->triggers->SendTriggerErrors(); 
	}
	
	public function exchange(){
		//$this->clear();
		$this->registerCallback("/onliner/category", array($this, "callbackItem"));
	} 
    protected function callbackItem($reader){
		$arItem = $arError = array();
        $xml = $reader->expandSimpleXml();
		$arRes = json_decode(json_encode($xml), TRUE);

		if($arRes["@attributes"]["name"] == "Наручные часы"){
			// пробуем тут очистку
			$this->clear();
			
			$ar = array();
			
			foreach($arRes["vendor"] as $key => $arVendor){
				$vendor = $arVendor["@attributes"]["name"];
				foreach($arVendor["model"] as $key_model => $arModel){
					$modelID = $arModel["@attributes"]["id"];
					$name = $arModel["@attributes"]["name"];
					$arArticle = array();
					
					$find_article = true;
					if(is_array($arModel["articles"]["article"]) && count($arModel["articles"]["article"]) > 0){
						foreach($arModel["articles"]["article"] as $article){
							if($article)
								$arArticle[] = $article;
						}
					}elseif($arModel["articles"]["article"]){
							$arArticle[] = $arModel["articles"]["article"];
					}else{
						$arArticle[] = $arModel["@attributes"]["name"];
						$find_article = false;
					}
					
					if($modelID && $name && $arArticle){
						$arItem = array(
							"MODEL_ID" => $modelID,
							"ARTICLES" => $arArticle,
							"NAME" => $name,
							"VENDOR" => $vendor,
							"FIND_ARTICLE" => $find_article,
						);

						$this->addItem($arItem);
						$this->cntItem++;
					}

				}
			}
		}
		

		
        return true;
    }
	private function addItem($arItem){
		global $DB;

		if(count_($arItem["ARTICLES"]) <= 0) return false;
		
		foreach($arItem["ARTICLES"] as $key => $article){
			if($arItem["FIND_ARTICLE"] == true){
				$in = array(
					"id"		=> "'".addslashes($arItem["MODEL_ID"])."'", 
					"vendor"	=> "'".addslashes($arItem["VENDOR"])."'", 
					"article"	=> "'".addslashes($article)."'", 
					"name"	=> "'".addslashes($arItem["NAME"])."'", 
				);
			}else{
				$in = array(
					"id"		=> "'".addslashes($arItem["MODEL_ID"])."'", 
					"vendor"	=> "'".addslashes($arItem["VENDOR"])."'", 
					"name"	=> "'".addslashes($arItem["NAME"])."'", 
				);
			}
			$DB->Insert("ci_onliner_articles", $in, $err_mess.__LINE__);
		}

	}

	function findModelID( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT id FROM ci_catalog_onliner WHERE id = {$id} LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}
	private function clear(){
		global $DB;
		$DB->Query("TRUNCATE TABLE ci_onliner_articles", false, $err_mess.__LINE__);
	}
}

(new OnlinerXml())->run();
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>