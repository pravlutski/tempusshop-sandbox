<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?$APPLICATION->SetTitle("Отменить продажу");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<h1 class="page-header">Регистрация продажи</h1>
<a href="/admin/discount/" class="btn btn-default">Назад</a>
<?
global $USER;
$objDiscount = new CPanelDiscount;

if( $id = $objDiscount->getID(str_replace(' ', '', $_POST['code'])) ){
	$sum = $_POST['sum'];
	if( $objDiscount->addSale( $id, $sum ) ){
		$code = $_POST['code'];
		$objDiscount->toLog(
			"Добавлена продажа для карты #$code. Сумма $sum.",
			$USER->getID()
		);
		LocalRedirect("/admin/discount/");
	}else {
		echo "<p class='col-sm-12 label label-danger' style='margin: 10px 0 0 0;'>Ошибка внесения продажи</p>";
	}
} else {
	echo "<p class='col-sm-12 label label-danger' style='margin: 10px 0 0 0;'>Нет такой карты</p>";
}
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>