<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$arTypePrice = [
	"RU", "BY", "PL", "YA", "OS", "WB",
	"WBTL", "WBBY", "AV", "SB", "KZ", "OZKZ", "OZTI",
];
if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !in_array($_POST["site_id"], $arTypePrice)) return;


//ищем процессы и убиваем если они есть
$file = $_SERVER['DOCUMENT_ROOT'] . "/local/cron/catalog/update_price_analys.php";
exec("pgrep -f {$file}",$output,$code); 
if(is_array($output) && count($output) > 0){
	$kill = false;
	foreach($output as $pid){
		exec("ps axu | grep {$pid}",$output2,$code2); 
					
		$flg = false;
		foreach($output2 as $info){
						
			if(stripos($info, "{$file} PRICE_ID=".addslashes($_POST["site_id"]))){
				$flg = true;
				$p = $pid;
			}	
		}
					
		if($flg === true){
			$kill = true;
			exec("kill -9 {$p}");
		}
	}
	if($kill === true){
		CLog::add2log(array("event" => "R", "text" => "Убили обновление цен {$_POST["site_id"]}"));
	}

}

system("/usr/bin/php -f {$file} PRICE_ID=".addslashes($_POST["site_id"])." force=Y >/dev/null 2>&1 &");// >/dev/null 2>&1 &");

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
