<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") ||  !CModule::IncludeModule('panel.manager')) return;
?>
<?
error_reporting(E_ERROR);

include_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/components/adm/order.assembly/ozon_tools.php");

$arOrder = $_REQUEST["order"];
if(!is_array($arOrder)) die("not orders");

$ozon = new OzonTools();

$task_id = $ozon->createTaskSticker($arOrder);

//$task_id = 40856971;
if($task_id){
	// если задача создалась, то ждем выполнения 2 минуты
	$send = true;
	$file = "";
	do{
		sleep(2);
		$res = $ozon->getStickerFile($task_id); // Получаем файл с этикетками
		
		if(is_array($res) && isset($res["status"])){
			if($res["status"] == "completed"){
				$send = false;
				// копируем файл к себе и выводим на экран
				$file = $res["file_url"];
			}elseif($res["status"] == "error"){
				$send = false;
				die($res["error"]);
			}
		}else{
			$send = false;
			die(serialize($res));
		}

		$send = false;
	}while($send == true);
	//prent($file);die;
	if($file){
		//prent($file);
		//header("Content-Type: application/pdf");
		//header("Content-Disposition:inline;filename=\"{$file}\"");
		//header("Content-Transfer-Encoding: binary");
		
		$fileContent = file_get_contents($file);
		$filename = 'sticker_ozon.pdf';
		header('Content-Type: application/pdf');
		header('Content-Length: '.strlen( $fileContent ));
		header('Content-disposition: inline; filename="' . $filename . '"');
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: public');
		header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
		echo $fileContent;
	}else{
		die("Не получен файл с этикетками");
	}
}else{
	die("Задача на создание этикеток не создана");
}