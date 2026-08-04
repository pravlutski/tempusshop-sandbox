<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div class="grid marginleft50" style="margin-top: 10px;" id="content-main">
<?
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule('iblock'))return;
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/AvitoHelper.php');
$avito = new AvitoHelper;
$logger = new TsLogger("/utils/content-create-product/");

global $USER;
$task_id = intval($_REQUEST["id"]);

$objContent = new CPanelContent;
$objProduct = new CPanelProduct;
if($USER->getID() == 587){
	//prent($_POST);die;
}

$logger->log("LOG", "Создаем товар", [$_POST, $task_id]);
prent($_POST);
if( $id = $objContent->addApply( $_POST, $task_id)){
	$PRODUCT_ID = $objProduct->addProduct($id);
	prent('ssssssssss');
	$logger->log("LOG", "Добавили", [$PRODUCT_ID]);
	//prent($_POST);prent($model);die;
	//prent($res);
	/*if($res["status"] == "E"){
		echo "<p style='color: red;'>Произошла ошибка</p>";
		echo "<p style='color: red;'>{$res["error"]}</p>";
	}elseif($res["status"] == "P"){
		$objContent->removeTask( $task_id );
		LocalRedirect("/admin/content/");
	}*/
//	$objContent->removeTask( $arg );
	if ($PRODUCT_ID > 0) {
		$logger->log("LOG", "Отправляем в pixApi", [$PRODUCT_ID]);
		//$command = "php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/other/pixApi.php " . (int)$PRODUCT_ID . " > /dev/null 2>&1 &";
		//shell_exec($command);
		$command = "php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/other/pixApi.php " . (int)$PRODUCT_ID . " >> /var/www/bitrix_logs/debug/pixApi_cron.log 2>&1 &";
		shell_exec($command);
		$logger->log("LOG", "Конец pixApi", [$PRODUCT_ID]);
		
		$arFilter = [
			"ID" => $PRODUCT_ID
		];

		$arElements = $avito->getElements($arFilter);

		foreach($arElements as $_id => $arProp){
			$avitoProp = $avito->genAvitoChr($arProp);
			CIBlockElement::SetPropertyValuesEx($_id, false, array(3081 => serialize($avitoProp)));
		}
		// отправляем в tempus.ru
		/*$data = [
			"create_elements" => $PRODUCT_ID,
		];
		$params = "";
		foreach($data as $k => $v){
			$params .= " {$k}={$v}";
		}

		$url = "/var/www/bitrix/data/www/tempusshop.ru/local/dev/exchange.php {$params} >/dev/null 2>&1 &";
		try{
			$json = shell_exec("/usr/bin/php81 -f {$url}");
		}catch(Exception $e){
		}*/
		// end
		
		$logger->log("LOG", "Товар создан", [$PRODUCT_ID, $task_id]);
		
		$objContent->removeTask( $task_id );
		LocalRedirect("/admin/content/");
		//
	}else{
		echo "<p style='color: red;'>Произошла ошибка</p>";
		echo "<p style='color: red;'>{$objProduct->LAST_ERROR}</p>";
		//LocalRedirect("/admin/content/apply/?id={$task_id}");
	}

}else{
	echo "<p style='color: red;'>Произошла ошибка</p>";
}
?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
