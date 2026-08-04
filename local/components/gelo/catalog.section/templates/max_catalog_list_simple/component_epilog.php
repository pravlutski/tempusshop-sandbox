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
global $APPLICATION;
$arTreeName = array();
if(count($arResult["PATH"]) > 0){
	foreach($arResult["PATH"] as $key => $arItem){
		if($key == 0){
			//через жопу!!! Разобраться!!!
			$first = mb_strtoupper(substr($arItem["NAME"],0,1), "utf-8");
			//$name = mb_strtolower($arItem["NAME"]);
			$name = mb_convert_case($arItem["NAME"], MB_CASE_LOWER, 'UTF-8'); 
			$name = $first . substr($name,1);
			//$first = iconv("CP1251", "UTF-8",$first);
			//$tag = mb_strtolower($tag, 'utf-8'); 
			
			$arTreeName[] = $name;
		}else{
			$arTreeName[] = $arItem["NAME"];
		}
	}
}

if(SITE_ID == "s2"){
	//$title = $arResult["NAME"] . GetMessage("TEXT_TITLE_MINSK");
	//$title = "Часы " . $arResult["NAME"] . " купить в Минске. Каталог наручных механических, электронных часов " . $arResult["NAME"] . " по недорогим ценам";
	$title = implode(" ", $arTreeName) . " купить в Минске. Каталог наручных оригинальных часов ";
	unset($arTreeName[0]);
	$title .= implode(" ", $arTreeName) . " c доставкой по Беларуси";
}else{
//	$title = "Каталог " . $arResult["NAME"] . " от магазина tempusshop.ru";
	$title = implode(" ", $arTreeName) . " купить в Москве. Каталог наручных оригинальных часов ";
	unset($arTreeName[0]);
	$title .= implode(" ", $arTreeName) . " c доставкой по России";
}

if ($_REQUEST['PAGEN_1']){
	$title .= " | страница " . intval($_REQUEST['PAGEN_1']);

}
//Японские часы Casio G-Shock купить в Москве. Каталог наручных оригинальных часов Casio G-Shock c доставкой по России.
//prent($arTreeName);
$APPLICATION->SetPageProperty("title", $title);

if ($_REQUEST['PAGEN_2'] || $_REQUEST['pagen_2']){

	$path = array_pop($arResult["PATH"])["SECTION_PAGE_URL"];
	if(!$path){
		$path = $_SERVER["DOCUMENT_URI"];
	}
	
	$APPLICATION->AddHeadString('<link href="https://'.( SITE_ID == "s2" ? "www.": "").SITE_SERVER_NAME.$arResult['DETAIL_PAGE_URL'].'" rel="canonical" />',true);
}
?>