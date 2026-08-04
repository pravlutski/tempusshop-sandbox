<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main" class="col-sm-12 row">
<?
$objContent = new CPanelContent;

$tmp = $objContent->getProps(); 
$tmp = sort_nested_arrays($tmp, $args = array('sort2' => 'asc', 'sort' => 'asc'));
foreach($tmp as $arItem) $arResult["PROPS"][$arItem["id"]] = $arItem;
//prent($arResult["PROPS"],0,1);
$filepath = $_SERVER["DOCUMENT_ROOT"] . "/dev/prop.csv";
foreach($arResult["PROPS"] as $key => $arItem){
	$ar = array();
	$ar[0] = '"'.$arItem["name"].'"';
	$str_csv = implode(";", $ar) . "\r\n\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
	foreach($arItem["values"] as $k => $prop){
		$ar = array();
		$ar[0] = '"'.$prop.'"';
		$str_csv = implode(";", $ar) . "\r\n";
		file_put_contents($filepath , $str_csv, FILE_APPEND);
	}
	
	$ar = array();
	$str_csv = implode(";", $ar) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
}
?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>