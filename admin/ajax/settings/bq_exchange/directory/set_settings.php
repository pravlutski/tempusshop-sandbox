<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;
$ID = intval($_POST["id"]);
global $DB;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ID >= 0){
	$strSql = "SELECT * FROM ci_ms_directory WHERE ID = '{$ID}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	if ($row = $results->Fetch()){
		$site_id = $_POST["login"];
		$name = trim(htmlspecialchars($_POST["name"]));

		$settings = unserialize($row["settings"]);

		$settings["NAME_FILE"] = trim(htmlspecialchars($_POST["name_file"]));
		$settings["LOGIN"] = trim(htmlspecialchars($_POST["login"]));
		$settings["BQ_TABLE"] = trim(htmlspecialchars($_POST["bq_table"]));
		//wdhs
		$settings["COLUMN"] = $_POST["column"];

		//$settings["METHOD"] = $_POST["column"];

		$arFields = [
			"active" => "'" . ($_POST["active"] == "Y" ? "Y" : "N") . "'",
			"name" => "'" . (strlen($name) > 3 ? $name : $row["name"]) . "'",
			"site_id" => "'" . $site_id . "'",
			"settings" => "'" . serialize($settings) . "'",
		];
		//prent($arFields);
		$DB->Update("ci_ms_directory", $arFields, "WHERE ID = '".$ID."'", $err_mess.__LINE__);

		$in = [
			"agent_id" => "'" . $ID . "'",
			"agent" => "'" . $settings['METHOD'] . "'",
			"site_id" => "'" . $site_id . "'",
			//"update" => "0000-00-00 00:00:00",
			//"update_bq" => "0000-00-00 00:00:00",
		];

		$optId = $DB->Update("ci_ms_directory_options", $in, "WHERE agent_id = '".$ID."'", $err_mess.__LINE__);

		$res = ["STATUS" => "ok", "TEXT" => "Настройки сохранены", "ID" => $ID];
	}else{

		$group_report = intval($_POST["group_report"]);
		$site_id = $_POST["login"];
		$settings = ["METHOD" => $_POST["type_data"]];

		$arTypeData = BQ_DIRECTORY["METHOD"][$_POST["type_data"]];

		if($arTypeData){
			$arFields = [
				"active" => "'Y'",
				"name" => "'Новый cправочник: {$arTypeData["NAME"]}'",
				"site_id" => "'" . $site_id . "'",
				"settings" => "'" . serialize($settings) . "'",
			];

			$ID = $DB->Insert("ci_ms_directory", $arFields, $err_mess.__LINE__);
			$in = [
				"agent_id" => "'" . $ID . "'",
				"agent" => "'" . $settings['METHOD'] . "'",
        "site_id" => "'" . $site_id . "'",
				//"update" => "0000-00-00 00:00:00",
				//"update_bq" => "0000-00-00 00:00:00",
      ];

      $optId = $DB->Insert("ci_ms_directory_options", $in, $err_mess.__LINE__);
			file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/settings/bq_exchange/directory/test.txt', print_r($optId,true));
			if($ID > 0){
				$res = ["STATUS" => "ok", "TEXT" => "Элемент добавлен", "ID" => $ID];
			}else{
				$res = ["STATUS" => "error", "TEXT" => "Ошибка при добавлении в таблицу"];
			}
		}else{
			$res = ["STATUS" => "error", "TEXT" => "Нет настроеек"];
		}

	}

}else{
	$res = ["STATUS" => "error", "TEXT" => "Не корректный запрос"];
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
?>
