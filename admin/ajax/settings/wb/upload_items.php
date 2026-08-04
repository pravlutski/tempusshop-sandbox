<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

//ищем процессы и убиваем если они есть
/*
exec("pgrep -f /userscripts/wb/upload_items.php",$output,$code); 
if(is_array($output) && count($output) > 0){
	foreach($output as $pid)
		exec("kill -9 {$pid}");
}
	
system("/usr/bin/php81 -f /userscripts/wb/upload_items.php >>/userscripts/logs/upload_items.txt >/dev/null 2>&1 &");
*/
$cabinet = addslashes($_POST["cabinet"]);
//ищем процессы и убиваем если они есть
exec("pgrep -f /userscripts/wb/upload_items.php",$output,$code); 
if(is_array($output) && count($output) > 0){
	$kill = false;
	foreach($output as $pid){
		exec("ps axu | grep {$pid}",$output2,$code2); 
					
		$flg = false;
		foreach($output2 as $info){
						
			if(stripos($info, "/userscripts/wb/upload_items.php cabinet=".$cabinet)){
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
		CLog::add2log(array("event" => "R", "text" => "Убили 'Загрузить на WB' {$cabinet}"));
	}

}

system("/usr/bin/php81 -f /userscripts/wb/upload_items.php cabinet=".$cabinet." >>/userscripts/logs/upload_items.txt >/dev/null 2>&1 &");

$res = array(
	'text' => ("asd")
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
