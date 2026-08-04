
<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
set_time_limit(3600);
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main"))  return;

$arFilter = Array(
	"IBLOCK_ID"	=> 16,
);

$arFilter["PROPERTY_BRAND"] = '179977';

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","NAME","PROPERTY_WBARTICLE","PROPERTY_WBARTICLE2"));

while($ar = $rs->GetNext()){
	$items[] = ['ID' => $ar['ID'], 'NAME' => $ar['NAME'], 'WB1' => $ar['PROPERTY_WBARTICLE_VALUE'],'WB2' => $ar['PROPERTY_WBARTICLE2_VALUE'],];
}

print_r($items);
foreach ($items as $key => $value) {
  if (strpos($value['WB1'], "(SP)") !== false) {
    $newart = str_replace("(SP)", "", $value["WB1"]);
		CIBlockElement::SetPropertyValueCode($value["ID"], "WBARTICLE", array('VALUE' => $newart));
  }
	if (strpos($value['WB2'], "(SP)") !== false) {
    $newart2 = str_replace("(SP)", "", $value["WB2"]);
		CIBlockElement::SetPropertyValueCode($value["ID"], "WBARTICLE2", array('VALUE' => $newart2));
  }

}
//
// foreach ($readyArr as $key => $value) {
//   if (count($value) != 2) {
//     unset($readyArr[$key]);
//   }
// }
// print_r($readyArr);

//foreach ($readyArr as $key => $value) {
//   if (CIBlockElement::Delete($value['SP']['ID'])) {
//     file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/delete_log.txt", print_r('Удален: ' . $value['SP']['NAME'] . ' (ID - '.$value['SP']['ID'].')', true).PHP_EOL, FILE_APPEND);
//   }
// }
