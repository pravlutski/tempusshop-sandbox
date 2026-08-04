<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$id = intval($_POST["analysis_id"]);
$brand_id = intval($_POST["brand_id"]);
$collection_id = intval($_POST["selected_collection_id"]);
$article = intval($_POST["article_value"]);
$price_id = false;
$_POST["website"] = mb_strtolower($_POST["website"]);
if(isset($_POST["website"]) && in_array($_POST["website"], array("ru","by","pl","ya","os","wb","wbtl","av", "sb", "kz","ozkz","ozti")))
	$price_id = $_POST["website"];

//prent($_POST);
//
function getProfileValues( int $brand_id, string $price_id ):array
{
	if ( empty($brand_id) || empty($price_id) ) return [];
	$db = \Bitrix\Main\Application::getConnection();

	$strSql = "SELECT settings FROM ci_analysis WHERE brand_id = '{$brand_id}' AND price_id = '{$price_id}'";
	$result = $db->Query( $strSql );
	$settings = [];

	while ( $row = $result->Fetch() ){
		$settings = json_decode( $row['settings'], true );
	}

	$r = [];
	foreach ( $settings as $profile ){
		$profile_id = $profile['price_from'] . '-' . $profile['price_to'];
		$r[ $profile_id ] = $profile['markup'];
	}

	return $r;
}

function getBrandName( int $price_id ):string
{
	if ( empty($price_id) ) return '';
	$db = \Bitrix\Main\Application::getConnection();

	$strSql = "SELECT name FROM ci_brands WHERE id = '{$price_id}'";
	$result = $db->Query( $strSql );

	while ( $row = $result->Fetch() ){
		return $row['name'];
	}

	return '';
}

function compareProfilesAndBuildMessage( array $oldProfile, array $newProfile, string $brandName, string $price_id ):string
{
	$message = "Изменены настройки РРЦ для профиля {$brandName} {$price_id}:\n";
	foreach ( $newProfile as $profile ) {
		$profile_id = $profile['price_from'] . '-' . $profile['price_to'];
		$markup = str_replace(",", ".", $profile["markup"]);

		if ( empty( $oldProfile[$profile_id] ) ){
			$message .= "{$profile_id} || {$markup} (Новый)\n";
			continue;
		}

		$oldMarkup = $oldProfile[ $profile_id ];

		if ( $markup != $oldMarkup ){
			$message .= "{$profile_id} || {$oldMarkup} --> {$markup}\n";
		}
		unset( $oldProfile[ $profile_id ] );
	}
	foreach ( $oldProfile as $id => $markup ){
		$message .= "{$id} || {$markup} (Удален)\n";
	}
	$message .= "Изменил пользователь: " . \Bitrix\Main\Engine\CurrentUser::get()->getLogin() . " " . date('Y.m.d G:i:s');

	return $message;
}

$triggers = new TsTriggers();
?>
<?
if(CModule::IncludeModule("panel.manager") && $brand_id > 0 && $price_id){
	$in = array(
		"id" => $id,
		"brand_id" => $brand_id,
		"selected_collection_id" => $collection_id,
		"article_value" => $article,
		"price_id" => $price_id,
	);
	$settings = array();
	if(is_array($_POST["profile"]) && count($_POST["profile"]) > 0){
		foreach($_POST["profile"] as $key => $arItem){
			$price_from = (float) abs($arItem["price_from"]);
			$price_to = (float) abs($arItem["price_to"]);
			$markup = (float) str_replace(",", ".", $arItem["markup"]);
			if($price_from >= 0 && $price_to > 0 && $markup > 0){
				$settings[] = array(
					"price_from" => (float)$arItem["price_from"],
					"price_to" => (float)$arItem["price_to"],
					"markup" => $markup,
				);
			}
		}
		$message = compareProfilesAndBuildMessage(
			oldProfile: getProfileValues( $brand_id, $price_id ),
			newProfile: $settings,
			brandName: getBrandName( $brand_id ),
			price_id: mb_strtoupper( $price_id )
		);
	}
	$settings = sort_nested_arrays($settings, ["price_from" => "ASC"]);
	$in["settings"] = $settings;
	$analysis = new CPanelAnalysis;
	$res = $analysis->apply($in);
	$res = array(
		'status' => ($res ? "ok" : "error"),
		'text' => ($res ? "Настройки сохранены" : "Не удалось сохранить"),
		'asd' => $in
	);
	if ( $res ){
		$triggers->SetError( [$message] );
		$triggers->SendTriggerErrors();
	}
}else{
	$res = array(
		'status' => 'error',
		'text' => "Не удалось сохранить. Не корректные данные"
	);
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
?>
