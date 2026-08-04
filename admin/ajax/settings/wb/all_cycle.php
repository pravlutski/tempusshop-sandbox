<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

$cabinet = addslashes($_POST["cabinet"]);
//ищем процессы и убиваем если они есть
exec("pgrep -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/all_cycle.php",$output,$code);
if(is_array($output) && count($output) > 0){
	$kill = false;
	foreach($output as $pid){
		exec("ps axu | grep {$pid}",$output2,$code2); 
					
		$flg = false;
		foreach($output2 as $info){
						
			if(stripos($info, "/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/all_cycle.php cabinet=".$cabinet)){
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
		CLog::add2log(array("event" => "R", "text" => "Убили 'Полный цикл WB' {$cabinet}"));
	}

}

system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/all_cycle.php cabinet=".$cabinet." >/dev/null 2>&1 &");
$res = array(
	'text' => ("asd")
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
