#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("admin_panel_engine_ozon_updateProps_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
require($_SERVER["DOCUMENT_ROOT"]."/admin/modules/descGen/classes/DescriptionGenerator.php");

set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class Updater{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$this->CurDB = new DBPanel();
		$this->descGen = new DescriptionGenerator;
		$this->module = 'updateProps';
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

		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/log_updater.txt", print_r('START ', true).PHP_EOL,FILE_APPEND);
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/log_updater.txt', print_r(date('Y-m-d H:i:s'), true) . "\r\n",FILE_APPEND);

		$arStat = [
			'status' => 'PROCESS',
			'status_text' => 'Запуск скрипта',
			'percent' => 0,
			'time_start' => date('Y.m.d G:i:s')
		];
		$this->updateStatus($this->module, $arStat);
		$this->updateStatus($this->module, ['status_text' => 'Получение списка товаров', 'percent' => 10]);
		$this->getItems();

		$this->updateStatus($this->module, ['status_text' => 'Установка даты последнего наличия', 'percent' => 20]);
		$this->updateDateStock();
		sleep(5);
		$this->updateStatus($this->module, ['status_text' => 'Установка свойств активности', 'percent' => 30]);
		$this->updateDisappear();

		$this->updateStatus($this->module, ['status_text' => 'Обновление рич контента', 'percent' => 50]);
		$this->updateRich();
		$this->updateRichTissot();

		$this->tnvd_update();
		$this->updateNameMP();

		// $this->updateStatus($this->module, ['status_text' => 'Обновление OZON ID (TI)', 'percent' => 75]);
		// $this->updateOzonId_TI();
		$this->updateStatus($this->module, ['status_text' => 'Обновление OZON ID (IP)', 'percent' => 85]);
		$this->updateOzonId();

		$timeEnd = date('Y.m.d G:i:s');
		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => 100,
			'time_end' => $timeEnd
		];
		$this->updateStatus($this->module, $arStat);
	}

	public function getItems(){
		$arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK","PROPERTY_OZON_ID","PROPERTY_OZON_ID_TI","PROPERTY_WBARTICLE", "PROPERTY_MARKETPLACE_OZON_TAGS", "PROPERTY_MARKETPLACE_WB_TAGS", "PROPERTY_TNVD_CODE", "PROPERTY_TNVD_DESC");

		// $json = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/configs/no_tnvd_list.json');
		// $noTnvd = json_decode( $json, true );

		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			// "PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
			// "=PROPERTY_CML2_ARTICLE" => $noTnvd,
			// "ID" => 213317
			// "ID" => 13263
		);

		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

		while ( $el = $result->GetNext() ){

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
				"IMAGE_MARKETPLACE" => $el['DETAIL_PICTURE'],
				// "IMAGE_MARKETPLACE" => $el['PROPERTY_IMAGE_MARKETPLACE_VALUE'],
				"NAME_MARKETPLACE" => $el['PROPERTY_NAME_MARKETPLACE_VALUE'],
				"RICH_DESC" => $el['PROPERTY_DESC_RICH_OZON_VALUE']['TEXT'],
				"MECHANISM" => is_array($el['PROPERTY_MECHANISM_VALUE']) ? reset($el['PROPERTY_MECHANISM_VALUE']) : $el['PROPERTY_MECHANISM_VALUE'],
				"GLASS" => $el['PROPERTY_GLASS_VALUE'],
				"CASE" => array_shift($el['PROPERTY_CASE_VALUE']),
				"WR" => $el['PROPERTY_WR_VALUE'],
				"FACE" => $el['PROPERTY_FACE_ENUM_ID'],
				"LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
				"OZON_ARTICLE" => $el['PROPERTY_WBARTICLE_VALUE'],
				"OZON_ID" => $el['PROPERTY_OZON_ID_VALUE'],
				"OZON_ID_TI" => $el['PROPERTY_OZON_ID_TI_VALUE'],
				"TAGS" => $el["PROPERTY_MARKETPLACE_WB_TAGS_VALUE"],
				"TNVD_CODE" => $el["PROPERTY_TNVD_CODE_VALUE"],
				"TNVD_DESC" => $el["PROPERTY_TNVD_DESC_VALUE"],
			];

		}

		$this->countItems = count($this->items);
	}

	public function updateDateStock()
	{

		foreach( $this->items as $key => &$arItem ) {

			if (strlen($arItem["ARTICLE"]) == 0 || intval($arItem['CATALOG_QUANTITY']) <= 0) {
				continue;
			}

			$res = CIBlockElement::SetPropertyValueCode($arItem["ID"], "DATE_LAST_STOCK", array('VALUE' => date("d.m.Y")));
		}
	}

	public function updateOzonId(){
		$result = $this->CurDB->query("SELECT * FROM ozon_main_settings_IP");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}

		$conn['api_url'] = $arSetting['api_url'];
		$conn['client_id'] = $arSetting['client_id'];
		$conn['token'] = $arSetting['key'];

		$offerIds = [];
		$dict = [];
		foreach( $this->items as $arItem ){
			if ( empty($arItem['OZON_ARTICLE']) && empty($arItem['OZON_ID']) ) continue;
			$offerIds[] = $arItem['OZON_ARTICLE'];
			$dict[ $arItem['OZON_ARTICLE'] ] = $arItem['ID'];
		}

		$offerChunks = array_chunk( $offerIds, 1000 );

		$allStaff = [];
		foreach ( $offerChunks as $key => $chunk ){
			file_put_contents('/var/www/bitrix_logs/ozon/timing.txt', print_r(['updateOzonId', date('Y-m-d H:i:s')], true), 8);
			$dstring = json_encode(array("offer_id" => $chunk), JSON_UNESCAPED_UNICODE);
			$ch = curl_init( $conn['api_url'] . '/v3/product/info/list');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Api-Key:' . $conn['token'],
				'Client-Id:' . $conn['client_id'],
				'Content-Type:application/json'
			));
			$data = array("language" => "DEFAULT");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $dstring);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HEADER, false);
			$res = curl_exec($ch);
			curl_close($ch);

			$res = json_decode($res, true);

			if ( !empty($res['items']) ){
				$allStaff = array_merge( $allStaff, $res['items'] );
			}
		}

		foreach( $allStaff as $item ){
			if ( !empty($item['id']) ){
				CIBlockElement::SetPropertyValueCode(
					$dict[ $item['offer_id'] ],
					"OZON_ID",
					array( 'VALUE' => $item['id'] )
				);
			}
		}

	}

	public function updateOzonId_TI(){
		$result = $this->CurDB->query("SELECT * FROM ozon_main_settings_TI");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}

		$conn['api_url'] = $arSetting['api_url'];
		$conn['client_id'] = $arSetting['client_id'];
		$conn['token'] = $arSetting['key'];

		foreach($this->items as $key => &$arItem){
			if (empty($arItem['OZON_ID_TI']) || strpos($arItem['OZON_ID_TI'], '.0') !== false) {
				file_put_contents('/var/www/bitrix_logs/ozon/timing.txt', print_r(['updateOzonId_TI', date('Y-m-d H:i:s')], true), 8);
				$dstring = json_encode(array("offer_id" => $arItem['OZON_ARTICLE']), JSON_UNESCAPED_UNICODE);
				$ch = curl_init( $conn['api_url'] . '/v2/product/info');
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				  'Api-Key:' . $conn['token'],
				  'Client-Id:' . $conn['client_id'],
				  'Content-Type:application/json'
				));
				$data = array("language" => "DEFAULT");
				curl_setopt($ch, CURLOPT_POSTFIELDS, $dstring);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_HEADER, false);
				$res = curl_exec($ch);
				curl_close($ch);

				$res = json_decode($res, true);

				if (!empty($res['result']['id'])) {
					CIBlockElement::SetPropertyValueCode($arItem["ID"], "OZON_ID_TI", array('VALUE' => $res['result']['id']));
				}
			}
		}
	}

	public function tnvd_update(){
		foreach($this->items as $key => &$arItem){
			$tnvd = '';
			$tnvd_desc = '';

			switch ( $arItem['MECHANISM'] ) {
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
				case 'С ручным заводом':
					// $tnvd = '9102210000';
					// $tnvd_desc = 'Часы наручные прочие, кроме часов и секундомеров товарной позиции 9101, имеющие или не имеющие встроенного секундомера, с автоматическим подзаводом';
					$tnvd = '9102290000';
					$tnvd_desc = 'Прочие часы наручные, кроме часов и секундомеров товарной позиции 9101, имеющие или не имеющие встроенного секундомера, без автоматического подзавода';
					break;
				case 'С автоподзаводом':
					$tnvd = '9102210000';
					$tnvd_desc = 'Часы наручные прочие, кроме часов и секундомеров товарной позиции 9101, имеющие или не имеющие встроенного секундомера, с автоматическим подзаводом';
					break;
				default:
					break;
			}

			// var_dump($tnvd);
			// var_dump($tnvd_desc);
			//
			// var_dump( $arItem['TNVD_CODE'] );
			// var_dump( $arItem['TNVD_DESC'] );
			// die;

			if ( $tnvd != '' && $tnvd != $arItem['TNVD_CODE']) {
				CIBlockElement::SetPropertyValueCode($arItem["ID"], "TNVD_CODE", array('VALUE' => $tnvd));
			}
			if ($tnvd_desc != '' && $tnvd_desc != $arItem['TNVD_DESC']) {
				CIBlockElement::SetPropertyValueCode($arItem["ID"], "TNVD_DESC", array('VALUE' => $tnvd_desc));
			}
			//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt', print_r($tnvd, true) . "\r\n", FILE_APPEND | LOCK_EX);
		}
	}

	private function updateTnvdCodeAndDesc():string
	{
		$mech = [
			''
		];

		foreach ( $this->items as $key => $item ){
			$installedCode = $item['TNVD_CODE'];
			$installedDesc = $item['TNVD_DESC'];

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

				if ($arSection[2]['ID'] == 2987 || $arSection[2]['ID'] == 1887) {
					$dsc1 = $arItem['TYPE'] . " наручные часы " . mb_strtolower($arSection[0]["NAME"]) . " {$arSection[1]["NAME"]} {$arItem['ARTICLE']}";
				} else {
					$dsc1 = $arItem['TYPE'] . " наручные часы " . mb_strtolower($arSection[0]["NAME"]) . " {$arSection[1]["NAME"]} {$arSection[2]["NAME"]} {$arItem['ARTICLE']}";
					if (strlen($dsc1) > 50) {
						$dsc1 = $arItem['TYPE'] . " наручные часы {$arSection[1]["NAME"]} {$arSection[2]["NAME"]} {$arItem['ARTICLE']}";
					}
				}

				//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt', print_r($dsc1, true) . "\r\n", FILE_APPEND | LOCK_EX);
				CIBlockElement::SetPropertyValueCode($arItem["ID"], "NAME_MARKETPLACE", array('VALUE' => $dsc1));
				CIBlockElement::SetPropertyValueCode($arItem["ID"], "NAME_WB_MP", array('VALUE' => $dsc2));
				CIBlockElement::SetPropertyValueCode($arItem["ID"], "NAME_YA_MP", array('VALUE' => $dscYA));

				unset($dsc1);
		}
		unset($arItem);
	}

	public function updateDisappear(){
		$halfYearAgo = strtotime('-180 days');


		foreach($this->items as $key => &$arItem){

			if (strlen($arItem["ARTICLE"]) > 0) {
					if (empty($arItem['LAST_STOCK']) or $arItem['LAST_STOCK'] == '') {
							$ind = 'Не активен';
							CIblockElement::SetPropertyValuesEx($arItem['ID'], CProSet::IB_CATALOG, ["OZON_ACTIVE" => 1944]);
					}	else {

						$dateTime = DateTime::createFromFormat('d.m.Y', $arItem['LAST_STOCK']);
						$timeTmp = $dateTime->getTimestamp();

						if (!empty($timeTmp)) {
							if ($timeTmp >= $halfYearAgo) {
								 CIblockElement::SetPropertyValuesEx($arItem['ID'], CProSet::IB_CATALOG, ["OZON_ACTIVE" => 1943]);
								 $ind = 'Активен';
								 //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/log_updater.txt", print_r('2', true).PHP_EOL,FILE_APPEND);
							} else {
								 $ind = 'Не активен';
								 CIblockElement::SetPropertyValuesEx($arItem['ID'], CProSet::IB_CATALOG, ["OZON_ACTIVE" => 1944]);
								 //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/log_updater.txt", print_r('3', true).PHP_EOL,FILE_APPEND);
							}
						} else {
							CIblockElement::SetPropertyValuesEx($arItem['ID'], CProSet::IB_CATALOG, ["OZON_ACTIVE" => 1943]);
							$ind = 'Активен';
							//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/log_updater.txt", print_r('4', true).PHP_EOL,FILE_APPEND);
						}
					}
				}
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/log_updater.txt", print_r('////', true).PHP_EOL,FILE_APPEND);
			}
	}

	public function updateRich(){
		foreach($this->items as $key => &$arItem){
		//spravochnik
		if ( $item['BRAND_ID'] == 43508 ) continue;
		$imgs = CFile::GetPath($arItem['IMAGE_MARKETPLACE']);
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/i.txt', print_r($arItem['IMAGE_MARKETPLACE'], true) . "\r\n", FILE_APPEND | LOCK_EX);
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/i.txt', print_r(CFile::GetPath($arItem['IMAGE_MARKETPLACE']), true) . "\r\n", FILE_APPEND | LOCK_EX);
		switch ($arItem['MECHANISM']) {
			case 'Кварцевые':
				$c1 = 'Кварцевый механизм';
				$c2 = 'Кварцевые часы имеют высокую точность и стабильность хода, что позволяет им показывать точное время с минимальной погрешностью.';
				break;
			case 'Механические':
				$c1 = 'Механические часы с ручным подзаводом';
				$c2 = 'Часы, использующие пружинный источник энергии. Ручной подзавод.';
				break;
			case 'Автоматические с ручным подзаводом':
				$c1 = 'Автоматические часы с ручным подзаводом';
				$c2 = 'Используется специальный механизм, который вращается при движении руки и заводит часы автоматически. Также возможен ручной завод.';
				break;
			case 'Автоматические':
				$c1 = 'Механические часы с автоматическим подзаводом';
				$c2 = 'Используется специальный механизм, который вращается при движении руки и заводит часы автоматически.';
				break;
			case 'Автокварц (кинетик)':
				$c1 = 'Автокварц (кинетик)';
				$c2 = 'Гибридный механизм, сочетающий преимущества кварцевых часов и механических с автоподзаводом.';
				break;
			case 'Процессор':
				$c1 = 'Smart-часы';
				$c2 = 'Компьютеризированные наручные часы с расширенной функциональностью.';
				break;
			default:
				$c1 = '';
				$c2 = '';
				break;
		}

		switch ($arItem['GLASS']) {
			case 'Минеральное':
				$a1 = 'Минеральное стекло';
				$a2 = 'Минеральное стекло устойчиво к царапинам, что делает его отличным выбором для часов, которые часто используются в повседневной жизни.';
				break;
			case 'Пластиковое':
				$a1 = 'Органическое стекло';
				$a2 = 'За счёт гибкости стекло из акрила практически невозможно разбить. В случае удара циферблат и стрелки часов останутся без повреждений.';
				break;
			case 'Сапфировое':
				$a1 = 'Сапфировое стекло';
				$a2 = 'Сапфир обладает очень высокой твердостью и поцарапать его практически невозможно. Материал не тускнеет и не теряет со временем свой первоначальный вид.';
				break;
			default:
				$a1 = '';
				$a2 = '';
				break;
		}

		switch ($arItem['CASE']) {
			case 'Латунь':
				$b1 = 'Латунный корпус';
				$b2 = 'Латунные часы имеют высокую прочность и долговечность, что делает их надежным выбором для повседневного использования.';
				break;
			case 'Нержавеющая сталь':
				$b1 = 'Стальной корпус';
				$b2 = 'Высококачественный стальной сплав с высокими антикоррозийными свойствами. Выдерживает большие нагрузки и не деформируется со временем.';
				break;
			case 'Полимер':
				$b1 = 'Полимерный корпус';
				$b2 = 'Полимерные материалы легче, чем металлы, но при этом обладают достаточной прочностью и жесткостью, чтобы обеспечить надежную защиту часов.';
				break;
			case 'Карбон':
				$b1 = 'Карбоновый корпус';
				$b2 = 'Легкий и сверх-прочный полиуретановый корпус с углеродным армированием.';
				break;
			case 'Алюминий':
				$b1 = 'Алюминиевый корпус';
				$b2 = 'Алюминий является очень легким материалом, что делает часы комфортными для ношения на руке.';
				break;
			case 'Дерево':
				$b1 = 'Корпус из дерева';
				$b2 = 'Часы изготавливлены из натурального гипоаллергенного материала. Дерево устойчиво и к ультрафиолетовому излучению, и к процессу окисления.';
				break;
			case 'Каучук':
				$b1 = 'Каучуковый корпус';
				$b2 = 'Износостойкий гипоаллергенный материал, устойчивый к деформации и температурным перепадам.';
				break;
			case 'Керамика':
				$b1 = 'Керамический корпус';
				$b2 = 'Легкий гипоаллергенный материал, устойчивый к царапинам и механическим повреждениям. При длительной носке изделия сохраняют исходный внешний вид.';
				break;
			case 'Титан':
				$b1 = 'Титановый корпус';
				$b2 = 'Гипоаллергенный материал повышенной прочности с высокими антикоррозийными свойствами, устойчивый к температурным перепадам.';
				break;
			default:
				$b1 = '';
				$b2 = '';
				break;
		}

		switch ($arItem['WR']) {
			case 'WR30m':
				$d1 = 'Водозащита WR30';
				$d2 = 'Часы защищены от попадания мелких брызг и капель, однако не предназначены для длительного контакта с водой.';
				break;
			case 'WR50m':
				$d1 = 'Водозащита WR50';
				$d2 = 'Часы способны стойко выдержать мытье рук, попадание брызг и капель дождя.';
				break;
			case 'WR100m':
				$d1 = 'Водозащита WR100';
				$d2 = 'Часы могут быть использованы для плавания и других водных видов спорта, но не предназначены для длительного погружения под воду.';
				break;
			case 'WR150m':
				$d1 = 'Водозащита WR150';
				$d2 = 'Часы могут быть использованы для плавания и других водных видов спорта, но не предназначены для длительного погружения под воду.';
				break;
			case 'WR180m':
				$d1 = 'Водозащита WR180';
				$d2 = 'Часы могут быть использованы для плавания и других водных видов спорта, но не предназначены для длительного погружения под воду.';
				break;
			case 'WR200m':
				$d1 = 'Водозащита WR200';
				$d2 = 'Часы подходят, чтобы погружаться на глубину до 40м и находиться в воде не более 2 часов.';
				break;
			case 'WR300m':
				$d1 = 'Водозащита WR300';
				$d2 = 'Профессиональные дайверские часы.';
				break;
			case 'WR500m':
				$d1 = 'Водозащита WR500';
				$d2 = 'Профессиональные дайверские часы.';
				break;
			case 'WR600m':
				$d1 = 'Водозащита WR600';
				$d2 = 'Профессиональные дайверские часы.';
				break;
			case 'WR1000m':
				$d1 = 'Водозащита WR1000';
				$d2 = 'Профессиональные дайверские часы.';
				break;
			default:
				$d1 = '';
				$d2 = '';
				break;
		}


	  if ($arItem['FACE'] == 1872) {
			$srcPm = 'https://tempusshop.ru/upload/rich_thumbs/rules_analog.jpg';
		} else {
			$srcPm = 'https://tempusshop.ru/upload/rich_thumbs/rules_digital.jpg';
		}

		$rich = '{
			"content": [
				{
					"widgetName": "raShowcase",
					"type": "roll",
					"blocks": [
						{
							"imgLink": "",
							"img": {
								"src": "https://tempusshop.ru/upload/rich_thumbs/RICH_TISSOT_PC.png",
								"srcMobile": "https://tempusshop.ru/upload/rich_thumbs/RICH_TISSOT_MOB.png",
								"alt": "",
								"position": "to_the_edge",
								"positionMobile": "to_the_edge"
							}
						}
					]
				},';

		$rich .= '{
				      "widgetName": "raShowcase",
				      "type": "tileXL",
				      "blocks": [
				        {
				          "img": {
				            "src": "https://tempusshop.ru' . CFile::GetPath($arItem['IMAGE_MARKETPLACE']) . '",
				            "srcMobile": "'.$srcPm.'",
				            "alt": "",
				            "position": "to_the_edge",
				            "positionMobile": "to_the_edge",
				            "widthMobile": 900,
				            "heightMobile": 1200
				          },
				          "imgLink": "",
				          "title": {
				            "content": [],
				            "size": "size4",
				            "align": "left",
				            "color": "color1"
				          },
				          "text": {
				            "size": "size2",
				            "align": "left",
				            "color": "color1",
				            "content": [
				              ""
				            ]
				          }
				        },
				        {
				          "img": {
				            "src": "'.$srcPm.'",
				            "srcMobile": "https://tempusshop.ru' . CFile::GetPath($arItem['IMAGE_MARKETPLACE']) . '",
				            "alt": "",
				            "position": "to_the_edge",
				            "positionMobile": "to_the_edge",
				            "widthMobile": 680,
				            "heightMobile": 1100
				          },
				          "imgLink": "",
				          "title": {
				            "content": [],
				            "size": "size4",
				            "align": "left",
				            "color": "color1"
				          },
				          "text": {
				            "size": "size2",
				            "align": "left",
				            "color": "color1",
				            "content": [
				              ""
				            ]
				          }
				        }
				      ]
				    },';

		if ( $arItem['BRAND_ID'] == 7971 && !empty($arItem['TAGS']) ){
			$tags_block = ',"","'.$arItem['TAGS'].'"';
		}else{
			$tags_block = '';
		}

		$rich .= '{
      "widgetName": "raTextBlock",
      "title": {
        "content": [
          "'.$arItem['NAME_MARKETPLACE'].'"
        ],
        "size": "size5",
        "color": "color1",
        "align": "center"
      },
      "theme": "tertiary",
      "padding": "type2",
      "gapSize": "s",
      "text": {
        "size": "size3",
        "align": "left",
        "color": "color1",
        "content": [
          '.$this->divideTextByblocks($arItem["RICH_DESC"], $arItem['ID']). $tags_block .'
        ]
      }
    },';

		$rich .= '{
      "widgetName": "raShowcase",
      "type": "tileSecondary",
      "blocks": [';

		//
		//if (!empty($arItem['MECHANISM'])) {
			$rich .= '        {
          "img": {
            "src": "https://tempusshop.ru/upload/rich_thumbs/mecha.jpg",
            "srcMobile": "https://tempusshop.ru/upload/rich_thumbs/mecha.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "fill"
          },
          "imgLink": "",
          "title": {
            "content": [
              "'.$c1.'"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "'.$c2.'"
            ]
          }
        },';
		//}

		//if (!empty($arItem['GLASS'])) {
			$rich .= '        {
          "img": {
            "src": "https://tempusshop.ru/upload/rich_thumbs/glass.jpg",
            "srcMobile": "https://tempusshop.ru/upload/rich_thumbs/glass.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "to_the_edge"
          },
          "imgLink": "",
          "title": {
            "content": [
              "'.$a1.'"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "'.$a2.'"
            ]
          }
        },';
		//}

		//if (!empty($arItem['WR'])) {
			$rich .= '        {
          "img": {
            "src": "https://tempusshop.ru/upload/rich_thumbs/wr.jpg",
            "srcMobile": "https://tempusshop.ru/upload/rich_thumbs/wr.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "to_the_edge"
          },
          "imgLink": "",
          "title": {
            "content": [
              "'.$d1.'"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "'.$d2.'"
            ]
          }
        },';
		//}

		//if (!empty($arItem['CASE'])) {
			$rich .= '        {
          "img": {
            "src": "https://tempusshop.ru/upload/rich_thumbs/warranty.jpg",
            "srcMobile": "https://tempusshop.ru/upload/rich_thumbs/warranty.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "to_the_edge"
          },
          "imgLink": "",
          "title": {
            "content": [
              "'.$b1.'"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "'.$b2.'"
            ]
          }
        }';
		//}

		$rich .= '      ]
		    }
		  ],
		  "version": 0.3
		}';

		CIBlockElement::SetPropertyValueCode($arItem['ID'], "rich_ozon", array('VALUE' => $rich));

		}
		unset($arItem);
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/rich.txt', print_r($rich, true) . "\r\n", FILE_APPEND | LOCK_EX);
	}

	private function divideTextByblocks( $string, $item_id )
	{
		if ( empty($string) ){
			try{
				$stringGen = $this->descGen->getRichDescription( $item_id );
			}catch( Throwable $e ){
				var_dump( $e );
			}
			if ( !empty($stringGen) ) $string = $stringGen;
		}

		if ( strpos( $string, '%BR%' ) ){
			$descBlocks = explode( '%BR%', $string );
			// var_dump($descBlocks);
			$result = '';
			foreach ( $descBlocks as $paragraph ){
				$result .= "\"{$paragraph}\",";
			}
			$result .= '""';
		}else{
			$result = '
			"' . $string . '",
			""
			';
		}
		return $result;
	}

	private function updateRichTissot()
	{
		$json = file_get_contents( $_SERVER['DOCUMENT_ROOT'] .  '/admin/panel/engine/ozon/configs/tissot_rich.json' );
		$template_raw = json_decode( $json, true );
		$sections = $this->getSections();
		$brands = $this->getBrands();

		foreach ( $this->items as $item ) {
			if ( $item['BRAND_ID'] != 43508 ) continue;

			$template = $template_raw; // Делаем копию переменной, чтобы не писать в базовый шаблон

			$arFilter = [
			  'IBLOCK_ID' => 16,
			  'ID' => $item['ID'],
			];

			$arSelect = ['IBLOCK_ID', 'ID', 'PROPERTY_CML2_ARTICLE', 'PROPERTY_BRAND', 'PROPERTY_DESC_RICH_OZON', 'DETAIL_PICTURE', 'IBLOCK_SECTION_ID'];
			$row = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect )->GetNextElement()->GetFields();

			$card = [
			  'model' => $row['PROPERTY_CML2_ARTICLE_VALUE'],
			  'description' => $row['PROPERTY_DESC_RICH_OZON_VALUE']['TEXT'],
			  'picture' => 'https://tempusshop.ru'.CFile::GetPath($row['DETAIL_PICTURE']),
			  'section' => $sections[ $row['IBLOCK_SECTION_ID'] ],
			  'brand' => $brands[ $row['PROPERTY_BRAND_VALUE'] ],
			];

			// Изображение (Деталка)
			$template['content'][2]['blocks'][0]['img']['src'] = $card['picture'];
			$template['content'][2]['blocks'][0]['img']['srcMobile'] = $card['picture'];
			// Наименование
			$template['content'][2]['blocks'][0]['title']['items'][0]['content'] = "{$card['brand']} {$card['section']}";
			$template['content'][2]['blocks'][0]['text']['items'][0]['content'] = $card['model'];
			// Описание
			$template['content'][3]['text']['items'][0]['content'] = $card['description'] ?? '';

			// print_r( json_encode($template, JSON_UNESCAPED_UNICODE) );
			// die;

			$res = CIBlockElement::SetPropertyValueCode($item['ID'], 'rich_ozon', array('VALUE' => json_encode($template)));
		}
	}

	private function getBrands()
	{
	  $arFilter = Array(
	    "IBLOCK_ID" => CProSet::IB_BRANDS,
	  );
	  $result = CIBlockElement::GetList( Array(), $arFilter, false, false, array("ID", "NAME") );
	  while ( $arFields = $result->GetNext() ){
	    $brands[ $arFields["ID"] ] = $arFields["NAME"];
	  }

	  return $brands;
	}

	private function getSections()
	{
	  $res = CIBlockSection::GetList(
	    Array("SORT"=>"ASC"),
	    Array("IBLOCK_ID" => 16),
	    false,
	    Array('ID','NAME'),
	    false
	  );

	  while ( $item = $res->GetNext() ){
	    $sections[ $item['ID'] ] = $item['NAME'];
	  }

	  return $sections;
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

(new Updater())->run();
$workers->updateStatus("N");
?>
