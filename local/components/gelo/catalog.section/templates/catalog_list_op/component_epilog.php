<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $templateData */
/** @var @global CMain $APPLICATION */
use Bitrix\Main\Loader;
if (isset($templateData['TEMPLATE_LIBRARY']) && !empty($templateData['TEMPLATE_LIBRARY'])){
	$loadCurrency = false;
	if (!empty($templateData['CURRENCIES']))
		$loadCurrency = Loader::includeModule('currency');
	CJSCore::Init($templateData['TEMPLATE_LIBRARY']);
	if ($loadCurrency){?>
	<script type="text/javascript">
		BX.Currency.setCurrencies(<? echo $templateData['CURRENCIES']; ?>);
	</script>
	<?}
}?>
<? 
 if (isset($templateData['TEMPLATE_LIBRARY']) && !empty($templateData['TEMPLATE_LIBRARY'])){
	$loadCurrency = false;
	if (!empty($templateData['CURRENCIES']))
		$loadCurrency = Loader::includeModule('currency');
	CJSCore::Init($templateData['TEMPLATE_LIBRARY']);
	if ($loadCurrency){?>
	<script type="text/javascript">
		BX.Currency.setCurrencies(<? echo $templateData['CURRENCIES']; ?>);
	</script>
	<?}
}?>
<?
global $APPLICATION;
$arTreeName = array();

if(count($arResult["PATH"]) > 0){
	foreach($arResult["PATH"] as $key => $arItem){
		if($key == 0){
			//через жопу!!! Разобраться!!!
			//$first = mb_strtoupper(substr($arItem["NAME"],0,1), "utf-8");
			//$name = mb_convert_case($arItem["NAME"], MB_CASE_LOWER, 'UTF-8'); 
			$first = strtoupper(substr($arItem["NAME"],0,1));
			$name = strtolower($arItem["NAME"]); 
			
			$name = $first . substr($name,1);
			//prent($name);
			$arTreeName[] = $name;
		}else{
			$arTreeName[] = $arItem["NAME"];
		}
	}
}

if(SITE_ID == "s1"){
	//if($USER->isAdmin()){prent($arTreeName);die;}
	
	if(count($arTreeName) == 2){
		//Часы Adriatica купить в Москве – подлинные часы Tempusshop
		$start_title = "Часы " . $arTreeName[1];
		
		//Швейцарские часы Adriatica купить в интернет-магазине Tempusshop. Большой ассортимент подлинных часов, с доставкой по всей России.
		$description = implode(" ", $arTreeName) . " купить в интернет-магазине Tempusshop. Большой ассортимент подлинных часов, с доставкой по всей России.";
	
		//Часы Adriatica
		$h1 = "Часы " . $arTreeName[1];
	}elseif(count($arTreeName) == 3){
		//Adriatica Ceramic купить в Москве – подлинные часы Tempuss.by
		$start_title = $arTreeName[1] . " " . $arTreeName[2];
		
		//Часы Adriatica Ceramic купить в интернет-магазине Tempusshop. Широкий ассортимент подлинных часовAdriatica, с доставкой по всей России.
		$description = "Часы {$arTreeName[1]} {$arTreeName[2]} купить в интернет-магазине Tempusshop. Широкий ассортимент подлинных часов {$arTreeName[1]}, с доставкой по всей России.";
	
		//Adriatica Ceramic
		$h1 = $arTreeName[1] . " " . $arTreeName[2];
	}else{
		$start_title = implode(" ", $arTreeName);
		$description = implode(" ", $arTreeName) . " купить в интернет-магазине Tempusshop. Большой ассортимент подлинных часов, с доставкой по всей России.";
		$h1 = implode(" ", $arTreeName);
	}
	
	$title = $start_title . " купить в Москве – подлинные часы Tempusshop";

	if(count($arTreeName[0]) > 1) unset($arTreeName[0]);
//	$title .= implode(" ", $arTreeName) . " c доставкой по России";
	$t_page = "страница";
	
	
	
}elseif(SITE_ID == "s2"){

	if(count($arTreeName) == 2){
		//Roamer Ceraline купить в Минске – подлинные часы Tempus.by
//		$start_title = "Часы " . $arTreeName[1];
		
		//Часы Roamer Ceraline купить в интернет-магазине Tempus.by. Широкий выбор подлинных часов от официальных дистрибьютеров, с доставкой по Беларуси.
//		$description = implode(" ", $arTreeName) . " купить в интернет-магазине Tempusshop. Большой ассортимент подлинных часов, с доставкой по всей России.";
//		$description = "Часы " . implode(" ", $arTreeName) . " купить в интернет-магазине Tempus.by. Широкий выбор подлинных часов от официальных дистрибьютеров, с доставкой по Беларуси.";
		//Часы Adriatica
		$h1 = "Часы " . $arTreeName[1];
	}elseif(count($arTreeName) == 3){
		//Roamer Ceraline купить в Минске – подлинные часы Tempus.by
//		$start_title = $arTreeName[1] . " " . $arTreeName[2];
		
		//Часы Adriatica Ceramic купить в интернет-магазине Tempusshop. Широкий ассортимент подлинных часовAdriatica, с доставкой по всей России.
//		$description = "Часы {$arTreeName[1]} {$arTreeName[2]} купить в интернет-магазине Tempus.by. Широкий выбор подлинных часов от официальных дистрибьютеров, с доставкой по Беларуси.";
	
		//Adriatica Ceramic
		$h1 = $arTreeName[1] . " " . $arTreeName[2];
	}else{
//		$start_title = implode(" ", $arTreeName);
//		$description = "Часы " . implode(" ", $arTreeName) . " купить в интернет-магазине Tempus.by. Широкий выбор подлинных часов от официальных дистрибьютеров, с доставкой по Беларуси.";
		$h1 = implode(" ", $arTreeName);
	}
	// Интернет-магазин TEMPUS предлагает купить оригинальные наручные часы. Прямые поставки от производителя, богатый выбор моделей различного класса и стиля, доступные цены, быстрая доставка по Беларуси.
	$description = "Интернет-магазин TEMPUS предлагает купить оригинальные наручные часы. Прямые поставки от производителя, богатый выбор моделей различного класса и стиля, доступные цены, быстрая доставка по Беларуси.";
//	$title = $start_title . " купить в Минске – подлинные часы Tempus.by";
	//Fashion часы Michael Kors купить в Минске. Каталог наручных оригинальных часов Michael Kors c доставкой по Беларуси
	$title = implode(" ", $arTreeName) . " купить в Минске. Каталог наручных оригинальных часов ";
	
	if(count($arTreeName[0]) > 1) unset($arTreeName[0]);
	$title .= implode(" ", $arTreeName) . " c доставкой по Беларуси";


	//unset($arTreeName[0]);
	$t_page = "страница";
	
	
	//unset($arTreeName[0]);
	/*
	$title = implode(" ", $arTreeName) . " купить в Минске. Каталог наручных оригинальных часов ";
	
	$title .= implode(" ", $arTreeName) . " c доставкой по Беларуси";
	$t_page = "страница";
	
	$description = "Широкий выбор часов в каталоге на сайте tempus.by. Заказать наручные часы для мужчин и женщин в Беларуси по доступной цене";
	*/
	

	 
	$h1 = implode(" ", $arTreeName);
	//prent($h1);
	$APPLICATION->SetPageProperty("keywords", "");
}elseif(SITE_ID == "s3"){
	$filename = $_SERVER['DOCUMENT_ROOT'].'/upload/translate/menu.csv';
	//подключаем класс для работы с csv
	require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/classes/csv.class.php');
	$csv = new CSV($filename);
	if($csv->error != "no_found"){

		$arCsv = array();
		$get_csv = $csv->getCSV();
		foreach ($get_csv as $key => $value){
			//$first = mb_strtoupper(substr($value[0],0,1), "utf-8");
			//$tmp = mb_convert_case($value[0], MB_CASE_LOWER, 'UTF-8'); 
			
			$first = strtoupper(substr($value[0],0,1));
			$tmp = strtolower($value[0]); 
			
			$tmp = $first . substr($tmp,1);
			
			$arCsv[$tmp] = $value[1];
		}
	}
	foreach($arTreeName as $key => $name){
		if(isset($arCsv[$name])){
			$arTreeName[$key] = $arCsv[$name];
		}
	}
	//prent($arResult);
	//prent($arTreeName);
	unset($arItem);
	$title = implode(" ", $arTreeName) . " kupować w Polsce. Katalog oryginalnych zegarków ";
	if(count($arTreeName[0]) > 1) unset($arTreeName[0]);
	$title .= implode(" ", $arTreeName) . " z dostawą w Polsce";
	$t_page = "strona";
	
	//$asd = $APPLICATION->GetPageProperty("keywords");
	//prent($arResult);
	$description = "Szeroki wybór zegarków w katalogu na stronie tempusshop.pl. Zamów zegarki na rękę dla kobiet i mężczyzn w Polsce w przystępnej cenie.";

	$h1 = implode(" ", $arTreeName);
}
//prent($arTreeName);
$APPLICATION->SetTitle($h1);

if ($_REQUEST['PAGEN_1'] || $_REQUEST['pagen_1']){
	$title .= " | {$t_page} " . intval($_REQUEST['PAGEN_1']);
	$path = array_pop($arResult["PATH"])["SECTION_PAGE_URL"];
	$APPLICATION->AddHeadString('<link href="https://'.( SITE_ID == "s2" ? "www.": "").SITE_SERVER_NAME.$arResult['DETAIL_PAGE_URL'].'" rel="canonical" />',true);
}

$APPLICATION->SetPageProperty("title", $title);
$APPLICATION->SetPageProperty("description", $description);

//if ($APPLICATION->GetCurPage()=='/catalog/watches/Citizen/') $APPLICATION->SetPageProperty("DESCRIPTION","test");
?>