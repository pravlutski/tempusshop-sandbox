<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?$APPLICATION->SetTitle("Удаление карты");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<h1 class="page-header">Удаление карты</h1>
<a href="/admin/discount/" class="btn btn-default">Назад</a>
<?
global $USER;
$id = intval($_REQUEST["id"]);
$objDiscount = new CPanelDiscount;
if($USER->isAdmin()){
	$card = $objDiscount->getCard( $id );
	$res = $objDiscount->remove($id);
	if($res === false){
		echo "<p class='col-sm-12 label label-danger' style='margin: 10px 0 0 0;'>Карта не найдена. Удалить не удалось</p>";
	}else{
		$objDiscount->toLog(
			"Удалена карта #$card[code].",
			$USER->getID()
		);
		LocalRedirect("/admin/discount/");
	}
	//
}else{
	echo "<p class='col-sm-12 label label-danger' style='margin: 10px 0 0 0;'>Упс. Не хватает прав)</p>";
}
			
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>