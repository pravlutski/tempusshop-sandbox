#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}
set_time_limit(3600);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');

$el = new CIBlockElement;

$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objUtils = new CPanelUtils;

// $arLink = array(
// 	"TYPE" => array(
// 		"Мужские" => "Мужской",
// 		"Женские" => "Женский",
// 		"Унисекс" => "Мужской",
// 		"Детские" => "Детский",
// 	),
// 	"GLASS" => array(
// 		"Органическое" => "органическое",
// 		"Минеральное" => "Минеральное стекло",
// 		"Сапфировое" => "сапфировое",
// 	),
// 	"MECHANISM" => array(
// 		"Кварцевые" => "кварцевые",
// 		"Автоматические" => "Автоматический",
// 		"Автоматические с ручным подзаводом" => "Автоматический",
// 		"Механические" => "механические",
// 	),
// 	"WR" => array(
// 		"WR30" => "WR30 (3 atm)",
// 		"WR50" => "WR50 (5 atm)",
// 		"WR100" => "WR100 (10 atm)",
// 		"WR200" => "WR200 (20 atm)",
// 		"WR300" => "WR300 (30 atm)",
// 		"WR500" => "WR500 (50 atm)",
// 		"WR600" => "WR600 (60 atm)",
// 		"WR1000" => "WR1000 (100 atm)",
// 	),
//
// 	"FACE" => array(
// 		"Аналоговый" => "аналоговый",
// 		"Цифровой" => "цифровой",
// 	),
//
// 	"FACE2" => array(
// 		"Аналоговый" => "стрелочный",
// 		"Цифровой" => "арабские цифры",
// 	),
// 	"WARRANTY" => array(
// 		"1 год, от производителя" => "1 год от производителя",
// 		"1 года, от производителя" => "1 год от производителя",
// 		"2 года, от производителя" => "2 года",
// 		"3 года, от производителя" => "3 года",
// 	),
// 	"MATERIAL" => array(
// 		"Нержавеющая сталь" => "Нержавеющая сталь 316L",
// 		"Полимер" => "Полимер",
// 		"Кожа" => "Кожаный",
// 		"Текстиль" => "текстиль",
// 		"Латунь" => "латунь",
// 		"Титан" => "титан",
// 		"Алюминий" => "алюминий",
// 		"Каучук" => "каучук",
// 		"Силикон" => "Силикон гипоаллергенный",
// 		"Дерево" => "дерево",
// 		"Керамика" => "керамика",
// 		"Карбон" => "карбон",
// 	),
// 	"POPULAR_TAG" => array(
// 		"Военные" => "военные",
// 		"Пилотские" => "пилотские",
// 		"На каждый день" => "на каждый день",
// 		"Под костюм" => "офисный стиль",
// 		"Дизайнерские" => "Дизайнерские",
// 		"Спортивные часы" => "спортивные",
// 		"Скелетоны" => "скелетон",
// 	),
// 	"dial_color" => array(
// 		"Черный" => "черный",
// 		"Белый" => "белый",
// 		"Серый" => "Серый",
// 		"Бежевый" => "бежевый",
// 		"Золотистый" => "золотистый",
// 		"Серебристый" => "серебристый",
// 		"Красный" => "красный",
// 		"Коричневый" => "коричневый",
// 		"Оранжевый" => "оранжевый",
// 		"Желтый" => "желтый",
// 		"Зеленый" => "зеленый",
// 		"Голубой" => "голубой",
// 		"Синий" => "синий",
// 		"Фиолетовый" => "фиолетовый",
// 		"Розовый" => "розовый",
// 		"Перламутровый" => "перламутровый",
// 		"Разноцветный" => "разноцветный",
// 	),
// 	"FEATURES" => array(
// 		"Ударопрочность" => "Ударопрочные",
// 		"Солнечная батарея" => "питание от солнечных батарей",
// 		"Компас" => "с компасом",
// 		"Bluetooth" => "Bluetooth 5.0 LE",
// 		"Высотомер (альтиметр)" => "высотомер",
// 		"Барометр" => "барометр",
// 		"Шагомер" => "Шагомер",
// 		"Фазы луны" => "с индикатором фаз луны",
// 		"Хронограф" => "хронограф",
// 		"Таймер" => "таймер",
// 		"Ежечасный сигнал" => "ежечасный сигнал",
// 		"Будильник" => "с будильником",
// 		"Индикатор запаса хода" => "с индикатором запаса хода",
// 		"Мировое время" => "мировое время",
// 		"12/24-часовое отображение времени" => "12/24 формат | двойное время",
// 		"SMART-часы" => "smart watch",
// 		"Автоматический календарь" => "автоматический календарь",
// 		"Калькулятор" => "калькулятор",
// 	),
// 	"CALENDAR" => array(
// 		"Число" => "с датой",
// 		"Число и день недели" => "с датой и днем недели",
// 		"Число и месяц" => "с датой",
// 		"Число, месяц и день недели" => "с датой и днем недели",
// 		"Число, месяц, год и день недели" => "с датой и днем недели",
// 	),
// 	"BACKLIGHT" => array(
// 		"Светодиодная/Люминисцентная" => "с подсветкой",
// 	),
// 	"FORM_WATCH" => array(
// 		"Круг" => "круглая",
// 		"Прямоугольник" => "прямоугольная",
// 		"Бочка" => "бочкообразная",
// 		"Овал" => "овальная",
// 	),
// );
//
// $arBrandReplace = array(
// /*	"Anne Klein" => "ANNE KLEIN",
// 	"Aviator" => "AVIATOR",
// 	"Calvin Klein" => "CALVIN KLEIN",
// 	"Casio" => "CASIO",
// 	"Citizen" => "CITIZEN",
// 	"Daniel Klein" => "DANIEL KLEIN",
// 	"Essence" => "essence",
// 	"Guess_n" => "GUESS",
// 	"Jacques Lemans" => "Jacques Lemans",
// 	"Le Temps" => "Le temps",
// 	"Moschino" => "MOSCHINO",
// 	"Orient" => "ORIENT Watch",
// 	"RADO" => "Rado",
// 	"Raymond Weil" => "RAYMOND WEIL",
// 	"Sergio Tacchini" => "SERGIO TACCHINI",
// 	"Timex" => "TIMEX",
// 	"Wenger" => "WENGER",
// 	"Zeppelin" => "ZEPPELIN",
// 	"Luch" => "ЛУЧ.",
// 	"Michael Kors" => "Michael Kors EU",
// 	"Armani Exchange" => "Armani Exchange EU",
// 	"Calvin Klein" => "Calvin Klein Inc.",
// 	"DKNY" => "DKNY EU",
// 	"DOLCE & GABBANA" => "DOLCE & GABBANA EU",
// 	"MOSCHINO" => "MOSCHINO EU",
// 	"Emporio Armani" => "Emporio Armani EU",
// 	"Guess" => "GUESS EU",
// 	"Tommy Hilfiger" => "Tommy Hilfiger EU",
// */
// );
//
// $arFilter = Array(
// 	"IBLOCK_ID" => CProSet::IB_BRANDS,
// );
// $result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
// while($arFields = $result->GetNext()){
// 	$arBrand[$arFields["ID"]] = ($arBrandReplace[$arFields["NAME"]] ? $arBrandReplace[$arFields["NAME"]] : $arFields["NAME"]);
// }
//
// //die;
// $arFilter = Array(
// 	"IBLOCK_ID"	=> 16,
// 	"ACTIVE"	=> "Y",
// 	//"ID" => 156635,
// 	//"SECTION_ID" => 228,
// 	">CATALOG_QUANTITY" => 0,
// 	//"!PROPERTY_AEN" => false,
// 	//"!PROPERTY_WBPRICE" => false,
// 	//"!PROPERTY_WBARTICLE" => false,
// 	"!PROPERTY_BRAND" => false,
// );
// $arFilter["ID"] = CMaxyssWb::getItemsWB();
// //$arFilter["ID"] = 172362;
// //$arFilter["ID"] = 4704;
// $cntAll = 0;
// $rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "CODE", "IBLOCK_ID", "NAME", "PREVIEW_TEXT", "DATE_ACTIVE_FROM","PROPERTY_*"));
// while($ob = $rs->GetNextElement()){
// 	$arFields = $ob->GetFields();
// 	$arProps = $ob->GetProperties();
//
//
// 	$arWB = array();
// 	if($arBrand[$arProps["BRAND"]["VALUE"]]){
// 		$arWB["0"] = array(
// 			"type" => "Бренд",
// 			"params" => array(array(
// 				"value" => $arBrand[$arProps["BRAND"]["VALUE"]]
// 			)),
// 		);
//
// 		if($arBrand[$arProps["BRAND"]["VALUE"]] == "Casio"){
// 			$arWB["500"] = array(
// 				"type" => "Номер декларации соответствия",
// 				"params" => array(array(
// 					"value" => "ЕАЭС N RU Д-JP.РА07.В.80938/22"
// 				)),
// 			);
// 			$arWB["501"] = array(
// 				"type" => "Дата регистрации сертификата/декларации",
// 				"params" => array(array(
// 					"value" => "31.10.2022"
// 				)),
// 			);
// 			$arWB["502"] = array(
// 				"type" => "Дата окончания действия сертификата/декларации",
// 				"params" => array(array(
// 					"value" => "29.10.2027"
// 				)),
// 			);
// 		}elseif(in_array($arBrand[$arProps["BRAND"]["VALUE"]], array("Michael Kors", "Emporio Armani", "Diesel", "Fossil", "DKNY", "Anne Klein", "Skagen", "Aemani Exchange", "Esprit", "Gc", "Nautica"))){
// 			$arWB["500"] = array(
// 				"type" => "Номер декларации соответствия",
// 				"params" => array(array(
// 					"value" => "ЕАЭС N RU Д-US.РА03.В.48718/23"
// 				)),
// 			);
// 			$arWB["501"] = array(
// 				"type" => "Дата регистрации сертификата/декларации",
// 				"params" => array(array(
// 					"value" => "26.04.2023"
// 				)),
// 			);
// 			$arWB["502"] = array(
// 				"type" => "Дата окончания действия сертификата/декларации",
// 				"params" => array(array(
// 					"value" => "25.04.2028"
// 				)),
// 			);
//
// 		}
// 		//prent($arWB,0,1);die;
// 	}
// //prent($arWB);die;
//
// 	$arWB["2"] = array(
// 		"type" => "Комплектация",
// 		"params" => array(array(
// 			"value" => "Коробка, инструкция, часы, гарантия"
// 		)),
// 	);
// 	/*$arWB["3"] = array(
// 		"type" => "Тнвэд",
// 		"params" => array(array(
// 			"value" => "9102290000"
// 		)),
// 	);*/
//
// 	if($arLink["TYPE"][$arProps["TYPE"]["VALUE"][0]]){
// 		$arWB["4"] = array(
// 			"type" => "Пол",
// 			"params" => array(array(
// 				"value" => $arLink["TYPE"][$arProps["TYPE"]["VALUE"][0]]
// 			)),
// 		);
//
//
// 		$params = array();
// 		foreach($arProps["TYPE"]["VALUE"] as $k => $value){
// 			if($value == "Мужские"){
// 				$params[] = array("value" => "подарок для отца");
// 				$params[] = array("value" => "подарок парню");
// 				$params[] = array("value" => "подарок сыну");
// 			}
// 			if($value == "Женские"){
// 				$params[] = array("value" => "подарок для любимой женщины");
// 				$params[] = array("value" => "подарок девушке");
// 				$params[] = array("value" => "подарок дочке");
// 			}
// 			if($value == "Детские"){
// 				$params[] = array("value" => "ребенку");
// 				$params[] = array("value" => "мальчику");
// 				$params[] = array("value" => "девочке");
// 			}
// 		}
// 		if(count($params) > 0){
// 			$params = array_slice($params, 0, 3);
// 			$arWB["30"] = array(
// 				"type" => "Назначение подарка",
// 				"params" => $params,
// 			);
// 		}
//
// 	}
//
//
//
// 	$arWB["8"] = array(
// 		"type" => "Глубина упаковки",
// 		"params" => array(array(
// 			"count" => (float) 20,
// 			"units" => "см",
// 		)),
// 	);
// 	$arWB["31"] = array(
// 		"type" => "Ширина упаковки",
// 		"params" => array(array(
// 			"count" => (float) 20,
// 			"units" => "см",
// 		)),
// 	);
// 	$arWB["32"] = array(
// 		"type" => "Высота упаковки",
// 		"params" => array(array(
// 			"count" => (float) 20,
// 			"units" => "см",
// 		)),
// 	);
//
// 	if($arProps["THICKNESS"]["VALUE"]){
// 		$arWB["9"] = array(
// 			"type" => "Толщина корпуса",
// 			"params" => array(array(
// 				"count" => (float)$arProps["THICKNESS"]["VALUE"],
// 				"units" => "мм",
// 			)),
// 		);
// 	}
// 	$arWB["120"] = array(
// 		"type" => "Длина упаковки",
// 		"params" => array(array(
// 			"count" => (float) 10,
// 			"units" => "см",
// 		)),
// 	);
//
//
// 	//if($arLink["WARRANTY"][$arProps["WARRANTY"]["VALUE"]]){
// 	if($arProps["WARRANTY"]["VALUE"]){
// 		$arWB["12"] = array(
// 			"type" => "Гарантийный срок",
// 			"params" => array(array(
// 				"value" => $arProps["WARRANTY"]["VALUE"]
// 			)),
// 		);
// 	}
//
//
// 	if($arProps["DIAMETER"]["VALUE"]){
// 		$arWB["13"] = array(
// 			"type" => "Диаметр корпуса",
// 			"params" => array(array(
// 				"count" => (float) ($arProps["DIAMETER"]["VALUE"] / 10),
// 				"units" => "см",
// 			)),
// 		);
// 	}
// 	if($arLink["GLASS"][$arProps["GLASS"]["VALUE"]]){
// 		$arWB["16"] = array(
// 			"type" => "Вид стекла",
// 			"params" => array(array(
// 				"value" => $arLink["GLASS"][$arProps["GLASS"]["VALUE"]]
// 			)),
// 		);
// 	}
//
// 	if($arProps["FACE"]["VALUE"] == "Аналогово-цифровой"){
// 		$arWB["17"] = array(
// 			"type" => "Циферблат часов",
// 			"params" => array(array("value" => "аналоговый"), array("value" => "цифровой")),
// 		);
// 		$arWB["100"] = array(
// 			"type" => "Тип индикации",
// 			"params" => array(array("value" => "комбинированный")),
// 		);
// 	}else{
// 		$arWB["17"] = array(
// 			"type" => "Циферблат часов",
// 			"params" => array(array(
// 				"value" => $arLink["FACE"][$arProps["FACE"]["VALUE"]]
// 			)),
// 		);
//
//
// 		$arWB["100"] = array(
// 			"type" => "Тип индикации",
// 			"params" => array(array("value" => $arLink["FACE2"][$arProps["FACE"]["VALUE"]])),
// 		);
//
// 	}
//
// 	/***********************/
// 	//мн
// 	$pFeatures = array();
// 	if(!empty($arProps["FEATURES"]["VALUE"])){
// 		foreach($arProps["FEATURES"]["VALUE"] as $k => $value){
// 			if($arLink["FEATURES"][$value]){
// 				$pFeatures[] = array("value" => $arLink["FEATURES"][$value]);
// 			}
// 		}
// 	}
//
// 	// Отображение даты
// 	if($arLink["CALENDAR"][$arProps["CALENDAR"]["VALUE"]]){
// 		$pFeatures[] = array("value" => $arLink["CALENDAR"][$arProps["CALENDAR"]["VALUE"]]);
// 	}
// 	//Стиль/дизайн
// 	if(isset($arLink["BACKLIGHT"][$arProps["BACKLIGHT"]["VALUE"][0]])){
// 		$pFeatures[] = array("value" => $arLink["BACKLIGHT"][$arProps["BACKLIGHT"]["VALUE"][0]]);
// 	}
//
// 	if(count($pFeatures) > 0){
// 		$params = array_slice($pFeatures, 0, 3);
// 		$arWB["19"] = array(
// 			"type" => "Особенности часов",
// 			"params" => $params,
// 		);
// 	}
// 	/***********************/
//
//
//
// 	if($arLink["MECHANISM"][$arProps["MECHANISM"]["VALUE"]]){
// 		$arWB["20"] = array(
// 			"type" => "Механизм часов",
// 			"params" => array(array(
// 				"value" => $arLink["MECHANISM"][$arProps["MECHANISM"]["VALUE"]]
// 			)),
// 		);
// 	}
//
//
//
// 	//мн
// 	if(!empty($arProps["POPULAR_TAG"]["VALUE"]) || $arProps["FACE"]["VALUE"] == "Цифровой"){
// 		$params = array();
// 		foreach($arProps["POPULAR_TAG"]["VALUE"] as $value){
// 			if($arLink["POPULAR_TAG"][$value]){
// 				$params[] = array("value" => $arLink["POPULAR_TAG"][$value]);
// 			}
// 			if($value == "Спортивные часы"){
//
// 				$arWB["101"] = array(
// 					"type" => "Спортивное назначение",
// 					"params" => array(array(
// 						"value" => "бег, плавание, фитнес"
// 					)),
// 				);
// 			}
// 		}
//
// 		if($arProps["FACE"]["VALUE"] == "Цифровой"){
// 			$params[] = array("value" => "Электронные");
// 		}
//
// 		if(count($params) > 0){
// 			$params = array_slice($params, 0, 3);
// 			$arWB["22"] = array(
// 				"type" => "Стиль часов",
// 				"params" => $params,
// 			);
// 		}
// 	}
// 	if($arLink["WR"][$arProps["WR"]["VALUE"]]){
// 		$arWB["23"] = array(
// 			"type" => "Класс водонепроницаемости",
// 			"params" => array(array(
// 				"value" => $arLink["WR"][$arProps["WR"]["VALUE"]]
// 			)),
// 		);
// 	}
//
// 	if($arLink["FORM_WATCH"][$arProps["FORM_WATCH"]["VALUE"]]){
// 		$arWB["102"] = array(
// 			"type" => "Форма корпуса",
// 			"params" => array(array(
// 				"value" => $arLink["FORM_WATCH"][$arProps["FORM_WATCH"]["VALUE"]]
// 			)),
// 		);
// 	}
//
//
//
// 	//print_r($arProps["dial_color"]);
// 	//мн
// 	if(!empty($arProps["dial_color"]["VALUE"]) && !in_array("Разноцветный", $arProps["dial_color"]["VALUE"])){
// 		$params = array();
// 		foreach($arProps["dial_color"]["VALUE"] as $value){
// 			if($arLink["dial_color"][$value]){
// 				$params[] = array("value" => $arLink["dial_color"][$value]);
// 			}
// 		}
// 		if(count($params) > 0){
// 			$arWB["26"] = array(
// 				"type" => "Цвет циферблата",
// 				"params" => $params,
// 			);
// 		}
// 	}
// 	//мн
//
// 	if(!empty($arProps["MATERIAL"]["VALUE"])){
// 		$params = array();
// 		foreach($arProps["MATERIAL"]["VALUE"] as $value){
// 			if($arLink["MATERIAL"][$value]){
// 				$params[] = array("value" => $arLink["MATERIAL"][$value]);
// 			}
// 		}
//
// 		if(count($params) > 0){
// 			$arWB["27"] = array(
// 				"type" => "Материал браслета",
// 				"params" => $params,
// 			);
// 		}
// 	}
//
//
// 	/*
// 	if($arLink["FINALCOUNTRY"][$arProps["FINALCOUNTRY"]["VALUE"]]){
// 		$arWB["26"] = array(
// 			"type" => "Страна производства",
// 			"params" => array(
// 				"value" => $arLink["FINALCOUNTRY"][$arProps["FINALCOUNTRY"]["VALUE"]]
// 			),
// 		);
// 	}
// 	Финальная сборка	Страна производства
// 	*/
//
//
//
//
// 	$arWB["29"] = array(
// 		"type" => "Повод",
// 		"params" => array(
// 			array("value" => "день рождения"),
// 			array("value" => "просто так"),
// 			array("value" => "для себя"),
// 			//array("value" => array("день рождения")),
// 		),
// 	);
//
// 	$arWB["object"] = "Часы наручные";
// 	CIBlockElement::SetPropertyValuesEx($arFields["ID"], false, array("PROP_MAXYSS_WB" => json_encode($arWB, JSON_UNESCAPED_UNICODE)));
//
// 	//
// 	/*
// 	Генерировать для всех товаров, для которых генерируем "Артикул WB"
//
// 	Шаблон
// 	Оригинальные %Пол с маленькой буквы% %раздел первого уровня с маленькой буквы% %Раздел второго уровня% %раздел третьего уровня% %артикул%
//
// 	Пример: Оригинальные женские наручные часы Daniel Wellington Classic Petite DW00100201
// 	*/
//
// 	/*
// 	$arSection = getSectionsElement($arFields["ID"]);
// 	$txt = "Оригинальные " . mb_strtolower($arProps["TYPE"]["VALUE"][0]) . " " . mb_strtolower($arSection[0]["NAME"]) . " {$arSection[1]["NAME"]} {$arSection[2]["NAME"]} {$arProps["CML2_ARTICLE"]["VALUE"]}";
//
// 	if($arFields["PREVIEW_TEXT"] != $txt){
// 		$el->Update($arFields["ID"], array("PREVIEW_TEXT" => $txt));
// 	}*/
//
//
//
// 	\Bitrix\Catalog\ProductTable::update($arFields["ID"], array("WIDTH" => 200, "LENGTH" => 200, "HEIGHT" => 200, "WEIGHT" => 200));
//
// 	//CIBlockElement::SetPropertyValuesEx($arFields["ID"], false, array("WBARTICLE" => $arFields["CODE"]));
// //	prent($arProps);
// //	die;
// 	$cntAll++;
// }
// echo date("Y-m-d H:i:s") . " - Обработано {$cntAll} товаров/r/n";
// CProSet::setOption("WB_SET_ITEMS_PROP", "{$cntAll}");

//По крону записывать поле "Символьный код" в поле "Артикул WB"

$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_BRANDS,
);
$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
while($arFields = $result->GetNext()){
	$arBrand[$arFields["ID"]] = $arFields["NAME"];
}

$arFilter = Array(
	"IBLOCK_ID"	=> 16,
//	"PROPERTY_WBARTICLE" => false,
	"!PROPERTY_CML2_ARTICLE" => false,
//	"!CODE" => false,
);
//$arFilter["ID"] = CMaxyssWb::getItemsWB();
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","CODE","PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND", "PROPERTY_WBARTICLE", "PROPERTY_WBARTICLE2","PROPERTY_WBARTICLE3","PROPERTY_WBARTICLE_KZ","PROPERTY_WBARTICLE_TI"));
$i = 0;
while($ob = $rs->GetNextElement()){

	$arFields = $ob->GetFields();

	$code = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];

	$brand = $arBrand[$arFields["PROPERTY_BRAND_VALUE"]];

	//T_Casio_GA-100-1A1
	if(strlen($brand . "_" . $code) <= 34){
		$code = "T_" . $brand . "_" . $code;
		$code2 = "W_" . $brand . "_" . $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
		$code3 = "L_" . $brand . "_" . $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
		$codekz = "K_" . $brand . "_" . $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
	}else{
		$code = "T_" . $code;
		$code2 = "W_" . $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
		$code3 = "L_" . $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
		$codekz = "K_" . $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
	}

	$code = str_replace(" ", "_", $code);
	$code = mb_strtoupper($code);

	$code2 = str_replace(" ", "_", $code2);
	$code2 = mb_strtoupper($code2);

	$code3 = str_replace(" ", "_", $code3);
	$code3 = mb_strtoupper($code3);

	$codekz = str_replace(" ", "_", $codekz);
	$codekz = mb_strtoupper($codekz);

	$codeti = str_replace(" ", "_", $codeti);
	$codeti = mb_strtoupper($codeti);

	$arUpdate = [];
	if($arFields["PROPERTY_WBARTICLE_VALUE"] != $code){
		$arUpdate["WBARTICLE"] = $code;
	}
	if($arFields["PROPERTY_WBARTICLE_KZ_VALUE"] != $code){
		$arUpdate["WBARTICLE_KZ"] = $codekz;
	}
	if($arFields["PROPERTY_WBARTICLE2_VALUE"] != $code2){
		$arUpdate["WBARTICLE2"] = $code2;
	}
	if($arFields["PROPERTY_WBARTICLE3_VALUE"] != $code3){
		$arUpdate["WBARTICLE3"] = $code3;
	}
	if(count($arUpdate) > 0)
		CIBlockElement::SetPropertyValuesEx($arFields["ID"], false, $arUpdate);

	//CIBlockElement::SetPropertyValuesEx($arFields["ID"], false, array("WBARTICLE" => $code, "WBARTICLE2" => $code2));
	$i++;
}
echo date("Y-m-d H:i:s") . " - Запись артикулов из символьных кодов. Обработано {$cntAll} товаров/r/n";
CProSet::setOption("WB_SET_ITEMS_CODE", "{$i}");
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
