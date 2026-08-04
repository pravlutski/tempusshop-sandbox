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

class Updater{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;

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

		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/log_updater.txt", print_r('START ', true).PHP_EOL,FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/log_updater.txt', print_r(date('Y-m-d H:i:s'), true) . "\r\n",FILE_APPEND);

		CProSet::setOption("UPDATER_RUN", "Старт выгрузки");
		CProSet::setOption("UPDATER_RUN_PER", "0");
		CProSet::setOption("UPDATER_RUN", "Получение списка товаров");
		$this->getItems();
		CProSet::setOption("UPDATER_RUN_PER", "10");
		$this->updateDateStock();
		CProSet::setOption("UPDATER_RUN_PER", "30");
		CProSet::setOption("UPDATER_RUN", "Обновление названия для маркетплейсов");
		$this->updateNameMP();
		CProSet::setOption("UPDATER_RUN_PER", "60");
		CProSet::setOption("UPDATER_RUN", "Обновление кодов ТНВД");
		$this->tnvd_update();
		CProSet::setOption("UPDATER_RUN_PER", "100");
		CProSet::setOption("UPDATER_RUN", "N");
	}

	public function getItems(){
		$arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK","PROPERTY_OZON_ID","PROPERTY_OZON_ID_TI","PROPERTY_WBARTICLE", "PROPERTY_MARKETPLACE_OZON_TAGS", "PROPERTY_MARKETPLACE_WB_TAGS", "PROPERTY_FINALCOUNTRY");
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			// "PROPERTY_CML2_ARTICLE" => array("ID-11S-2E","IQ-126-5E","IQ-133-5E","C036.407.16.050.00","C036.407.16.040.00","IQ-152-1E","IQ-152-5E","SSB430P1","SSB429P1","SSB427P1","SSB425P1","SUR555P1","SUR558P1","C039.251.33.367.00","C039.251.33.017.00","SUR557P1","C038.462.16.037.00","C030.250.11.106.00","C036.407.18.040.00","C033.051.22.118.01","C032.807.22.051.01","C032.807.22.041.10"),
			//"ID" => 181691,
			// "PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
			// "ID" => [1002,181473]
			// "ID" => 210360
		);
//Array("nPageSize"=>50)
		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		var_dump( $result->selectedRowscount() );
		while ($el = $result->GetNext()){

			$this->items[] = [
				"ID" => $el["ID"],
				"BRAND_ID" => $el["PROPERTY_BRAND_VALUE"],
				"COLLECTION" => $el["PROPERTY_COLLECTION_VALUE"],
				"ARTICLE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
				"COLLECTION_UPDATE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
				"COLORTERM" => $el["PROPERTY_COLORTERM_VALUE"],
				'TYPE' => array_shift($el['PROPERTY_126_VALUE']),
				'DETAIL_PICTURE' => $el['DETAIL_PICTURE'],
				"TIMESTAMP_X" => $el['TIMESTAMP_X'],
				"CATALOG_QUANTITY" => $el['CATALOG_QUANTITY'],
				"COLORTERM_UPDATE" => "",
				"IMAGE_MARKETPLACE" => $el['PROPERTY_IMAGE_MARKETPLACE_VALUE'],
				"NAME_MARKETPLACE" => $el['PROPERTY_NAME_MARKETPLACE_VALUE'],
				"RICH_DESC" => $el['PROPERTY_DESC_RICH_OZON_VALUE']['TEXT'],
				"MECHANISM" => $el['PROPERTY_MECHANISM_VALUE'],
				"GLASS" => $el['PROPERTY_GLASS_VALUE'],
				"CASE" => array_shift($el['PROPERTY_CASE_VALUE']),
				"WR" => $el['PROPERTY_WR_VALUE'],
				"FACE" => $el['PROPERTY_FACE_ENUM_ID'],
				"LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
				"OZON_ARTICLE" => $el['PROPERTY_WBARTICLE_VALUE'],
				"OZON_ID" => $el['PROPERTY_OZON_ID_VALUE'],
				"OZON_ID_TI" => $el['PROPERTY_OZON_ID_TI_VALUE'],
				"TAGS" => $el["PROPERTY_MARKETPLACE_WB_TAGS_VALUE"],
				"FINALCOUNTRY" => $el["PROPERTY_FINALCOUNTRY_VALUE"]
			];

		}

		$this->countItems = count($this->items);
		//print_r(count($this->items));
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/arr.txt', print_r($this->items, true) . "\r\n", FILE_APPEND | LOCK_EX);
	}

	public function tnvd_update(){
		foreach($this->items as $key => $arItem){
			print_r( "Обновляем ТНВЭД у {$arItem['ID']}\n" );
			$tnvd = '';
			$tnvd_desc = '';

			try{
				$mechanism = $this->getMechanismName( $arItem['ID'], $arItem['MECHANISM'] );
			} catch ( Throwable $e ){
				var_dump( 'Error occured in method tnvd_update: ' . $e->getMessage() );
				$mechanism = $arItem['MECHANISM'];
			}
			if ( !$mechanism ) continue;

			switch ( $mechanism ) {
				case 'Кварцевые':
						if ($arItem['FACE'] == 1872) {
							//аналог
							$tnvd = '9102110000';
							$tnvd_desc = 'Часы наручные,приводимые в действие электричеством имеющие или не имеющие встроенного секундомера только с механической индикацией';
						} else if ($arItem['FACE'] == 1873) {
							//цифр
							$tnvd = '9102120000';
							$tnvd_desc = 'Часы наручные, приводимые в действие электричеством, имеющие или не имеющие встроенного секундомера, только с оптико-электронной индикацией, кроме часов и секундомеров товарной позиции 9101';
						} else {
							//аналог-цифр
							$tnvd = '9102190000';
							$tnvd_desc = 'Прочие часы наручные, приводимые в действие электричеством, имеющие или не имеющие встроенного секундомера, кроме часов и секундомеров товарной позиции 9101';
						}
					break;
				case 'Механические':
					$tnvd = '9102290000';
					$tnvd_desc = 'Прочие часы наручные, кроме часов и секундомеров товарной позиции 9101, имеющие или не имеющие встроенного секундомера, без автоматического подзавода';
					break;
				case 'Автоматические с ручным подзаводом':
					$tnvd = '9102210000';
					$tnvd_desc = 'Часы наручные прочие, кроме часов и секундомеров товарной позиции 9101, имеющие или не имеющие встроенного секундомера, с автоматическим подзаводом';
					break;
				case 'Автоматические':
					$tnvd = '9102210000';
					$tnvd_desc = 'Часы наручные прочие, кроме часов и секундомеров товарной позиции 9101, имеющие или не имеющие встроенного секундомера, с автоматическим подзаводом';
					break;
				default:
					break;
			}
			if ($tnvd != '') {
				CIBlockElement::SetPropertyValueCode($arItem["ID"], "TNVD_CODE", array('VALUE' => $tnvd));
			}
			if ($tnvd_desc != '') {
				CIBlockElement::SetPropertyValueCode($arItem["ID"], "TNVD_DESC", array('VALUE' => $tnvd_desc));
			}
			//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt', print_r($tnvd, true) . "\r\n", FILE_APPEND | LOCK_EX);
		}
	}

	public function updateNameMP(){
		foreach($this->items as $key => &$arItem){
				$arSection = getSectionsElement($arItem["ID"]);
				if ($arSection[1]['ID'] == 411) {
					$arSection[1]['NAME'] = 'Luch';
				}

                if($arSection[1]["ID"] == 4541) {
                    unset($arSection[1]);
                    $arSection = array_values($arSection);
                }

				$dsc2 = "Часы {$arSection[1]["NAME"]} {$arSection[2]["NAME"]} {$arItem['ARTICLE']}";
				$dscYA = "Часы " . mb_strtolower($arItem['TYPE']) . " наручные {$arSection[1]["NAME"]}";

				// var_dump( $arItem['FINALCOUNTRY'] );
				// var_dump( $arItem['FINALCOUNTRY'] == 'Швейцария' );
				if ( $arItem['FINALCOUNTRY'] == 'Швейцария' ){
					$country_word = 'швейцарские';
					$flag = true;
				}else{
					$country_word = 'наручные';
					$flag = false;
				}

				if ($arSection[2]['ID'] == 2987 || $arSection[2]['ID'] == 1887) {
					$dsc1 = $arItem['TYPE'] . " {$country_word} часы " . mb_strtolower($arSection[0]["NAME"]) . " {$arSection[1]["NAME"]} {$arItem['ARTICLE']}";
				} else {
					$dsc1 = $arItem['TYPE'] . " {$country_word} часы " . mb_strtolower($arSection[0]["NAME"]) . " {$arSection[1]["NAME"]} {$arSection[2]["NAME"]} {$arItem['ARTICLE']}";

					if (strlen($dsc1) > 50) {
						$dsc1 = $arItem['TYPE'] . " {$country_word} часы {$arSection[1]["NAME"]} {$arSection[2]["NAME"]} {$arItem['ARTICLE']}";

						if ( $flag ) $dsc1 = $arSection[1]["NAME"] . ' ' . $arItem['TYPE'] . " {$country_word} часы {$arSection[2]["NAME"]} {$arItem['ARTICLE']}";
					}
				}

				//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt', print_r($dsc1, true) . "\r\n", FILE_APPEND | LOCK_EX);
				$r1 = CIBlockElement::SetPropertyValueCode($arItem["ID"], "NAME_MARKETPLACE", array('VALUE' => $dsc1));
				$r2 = CIBlockElement::SetPropertyValueCode($arItem["ID"], "NAME_WB_MP", array('VALUE' => $dsc2));
				$r3 = CIBlockElement::SetPropertyValueCode($arItem["ID"], "NAME_YA_MP", array('VALUE' => $dscYA));

				// var_dump($arItem['ID']);
				// var_dump( $dsc1 );

				unset($dsc1);
		}
		unset($arItem);
	}

	public function updateDateStock(){

		foreach($this->items as $key => &$arItem){
			if (strlen($arItem["ARTICLE"]) > 0) {
				if (intval($arItem['CATALOG_QUANTITY']) > 0) {
					$res = CIBlockElement::SetPropertyValueCode($arItem["ID"], "DATE_LAST_STOCK", array('VALUE' => date("d.m.Y")));
				}
			}
			//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/active.txt', print_r('item: '.$arItem['ID'] . ' // Активность:' . $res, true) . "\r\n", FILE_APPEND | LOCK_EX);
		}
	}

	private function getMechanismName( string|int $id, array|string $mechanism ):string|bool
	{
		if ( empty($mechanism) ){
			print_r( "{$id} - не указан механизм" . PHP_EOL );
			return false;
		}
		$variants = [
			42 => 'Кварцевые',
			481 => 'Автоматические',
			1459 => 'Автоматические с ручным подзаводом',
			464 => 'Механические',
		];
		$name = '';
		// var_dump($mechanism);
		if ( is_array( $mechanism ) ){
			$mech_id = array_key_first( $mechanism );
			// var_dump($mech_id);
			$name = $variants[ $mech_id ] ?? '';
		}else{
			if ( is_numeric($mechanism) && isset( $variants[ intval($mechanism) ] ) ) {
        $name = $variants[ intval($mechanism) ];
      }else{
      	$name = $mechanism;
      }
		}

		if ( empty($name) ){
			print_r( "{$id} - неизвестный механизм" . PHP_EOL );
			return false;
		}

		return $name;
	}


}

(new Updater())->run();
?>
