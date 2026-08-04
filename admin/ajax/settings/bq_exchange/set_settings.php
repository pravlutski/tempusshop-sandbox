<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;
$ID = intval($_POST["id"]);
global $DB;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ID >= 0){

	$strSql = "SELECT * FROM bq_exchange WHERE ID = '{$ID}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	if ($row = $results->Fetch()){

		$name = trim(htmlspecialchars($_POST["name"]));
		$sort = intval($_POST["sort"]);

		$settings = unserialize($row["SETTINGS"]);
		$settings["PERIOD"] = trim(htmlspecialchars($_POST["period"]));
		$settings["ADD_FILTER"] = trim(htmlspecialchars($_POST["add_filter"]));

		$settings["NAME_FILE"] = trim(htmlspecialchars($_POST["name_file"]));
		$settings["LOGIN"] = trim(htmlspecialchars($_POST["login"]));
		$settings["FIRST_DATE"] = trim(htmlspecialchars($_POST["first_date"]));
		$settings["BQ_TABLE"] = trim(htmlspecialchars($_POST["bq_table"]));
		//wdhs
		$settings["BQ_TABLE_POS"] = trim(htmlspecialchars($_POST["bq_table_pos"]));
		$settings["NAME_FILE_POS"] = trim(htmlspecialchars($_POST["name_file_pos"]));
		$settings["COLUMN"] = $_POST["column"];
		$settings["DATASET"] = trim(htmlspecialchars($_POST["dataset"]));
		$settings["COLUMN_POS"] = $_POST["column_pos"];

		//$settings["METHOD"] = $_POST["column"];

		$arFields = [
			"ACTIVE" => "'" . ($_POST["active"] == "Y" ? "Y" : "N") . "'",
			"NAME" => "'" . (strlen($name) > 3 ? $name : $row["NAME"]) . "'",
			"SORT" => "'" . $sort . "'",
			"SETTINGS" => "'" . serialize($settings) . "'",
		];
		//prent($arFields);
		$DB->Update("bq_exchange", $arFields, "WHERE ID = '".$ID."'", $err_mess.__LINE__);

		$res = ["STATUS" => "ok", "TEXT" => "Настройки сохранены", "ID" => $ID];
	}else{

		$group_report = intval($_POST["group_report"]);

		$settings = ["METHOD" => $_POST["type_data"]];
		$arTypeData = BQ_EXCHANGE[$group_report]["METHOD"][$_POST["type_data"]];

		if($arTypeData){
			$arFields = [
				"NAME" => "'Новый отчет {$arTypeData["NAME"]}'",
				"TYPE" => "'" . intval($group_report) . "'",
				"SORT" => "'100'",
				"SETTINGS" => "'" . serialize($settings) . "'",
			];

			$ID = $DB->Insert("bq_exchange", $arFields, $err_mess.__LINE__);

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
