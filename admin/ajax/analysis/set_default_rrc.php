<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$id = intval($_POST["analysis_id"]);
$brand_id = intval($_POST["brand_id"]);
$price_id = false;
if(isset($_POST["website"]) && in_array($_POST["website"], array("ru","by","pl","ya","os","wb","wbtl","wbby","av", "sb", "kz","ozkz","ozti")))
	$price_id = $_POST["website"];

// var_dump($_POST);
// die;
//
$price_id_dict = [
	"ru" => 'RU',
	"by" => 'BY',
	"pl" => 'PL',
	"ya" => 'YA',
	"os" => 'OZIP',
	"wb" => 'WBWR',
	"wbtl" => 'WBTL',
	"wbby" => 'WBBY',
	"av" => 'AV',
	"sb" => 'SB',
	"kz" => 'KZ',
	"ozkz" => 'OZKZ',
	"ozti" => 'OZTI'
];
?>
<?
if(CModule::IncludeModule("panel.manager") && $price_id){

	$settingsAll = json_decode(CProSet::getOption("SETTINGS_RRC"), true);
	$settingsOld = $settingsAll;
	// var_dump($settingsAll);
	// die;
	$supersale = (float)$_POST["supersale"];
	$rules = array();
	if(is_array($_POST["profile"]) && count($_POST["profile"]) > 0){
		foreach($_POST["profile"] as $key => $arItem){
			if($arItem["price_from"])
				$price_from = (float) abs($arItem["price_from"]);
			if($arItem["price_to"])
				$price_to = (float) abs($arItem["price_to"]);
			$markup = (float) str_replace(",", ".", $arItem["markup"]);
			if($price_from >= 0 && $price_to > 0 && $markup > 0){
				$rules[] = array(
					"price_from" => (float)$arItem["price_from"],
					"price_to" => (float)$arItem["price_to"],
					"markup" => $markup,
				);
			}
		}
	}
	$rules = sort_nested_arrays($rules, ["price_from" => "ASC"]);
	$settingsAll[$price_id]["rules"] = $rules;
	$settingsAll[$price_id]["supersale"] = $supersale;
	
	if ($_POST["take-priority-supplier"] && $_POST["take-priority-supplier"] == 'on') {
		$settingsAll[$price_id]["take_priority_supplier"] = 'Y';
	} else {
		$settingsAll[$price_id]["take_priority_supplier"] = 'N';
	}
	
	$settingsAll[$price_id]["price_type"] = $_POST['price_type'];
	
	CProSet::setOption("SETTINGS_RRC", json_encode($settingsAll));

	$triggers = new TsTriggers();

	$price_id_msg = $price_id_dict[ $price_id ] ?? $price_id;
	$message = "Изменены настройки РРЦ, тип цены {$price_id_msg}\n";
	foreach ( $rules as $prId => $rule ) {
		$oldValues = $settingsOld[$price_id]['rules'][$prId];

		$markupFlag = $oldValues['markup'] != $rule['markup'];

		$oldBlock = '';
		if ( $markupFlag ){
			$oldBlock = " {$oldValues['markup']} -->";
		}
		$message .= "{$rule['price_from']} - {$rule['price_to']} ||{$oldBlock} {$rule['markup']}\n";
	}

	// if ( !empty( $settingsOld[$price_id]) ){
	// 	foreach ( $settingsOld[$price_id]['rules'] as $prId => $rule ) {
	//
	// 		$newValues = $rules[$prId];
	// 		if ( !$oldValues ){
	// 			$message .= "{$rule['price_from']} - {$rule['price_to']} || {$rule['markup']} -- Удален профиль\n";
	// 			continue;
	// 		}
	// 	}
	// }

	$message .= 'Изменил пользователь: ' . \Bitrix\Main\Engine\CurrentUser::get()->getLogin() . " " . date('Y.m.d G:i:s');

	$triggers->SetError([$message]);
	$triggers->SendTriggerErrors();

	$channelsDict = [
	  'wb' => 'WB',
	  'wbtl' => 'WBTL',
	  'ru' => 'RU',
	  'os' => 'OS',
	  'ozti' => 'OZTI',
	  'by' => 'BY',
	  'wb' => 'WB',
	  'sb' => 'SB',
	  'av' => 'AV'
	];
	if ( isset($channelsDict[$price_id]) && $markupFlag ){
	  exec("php /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_price_analys.php PRICE_ID={$channelsDict[$price_id]} > /dev/null 2>&1 &");
	  print_r('Запущено обновление цен для выбранного канала продаж<br>');
	}

	prent($settingsAll);
	//prent($settings);
}
?>
