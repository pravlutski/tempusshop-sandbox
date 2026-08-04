<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?$APPLICATION->SetTitle("Выдать карту");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<h1 class="page-header">Выдать карту</h1>
<a href="/admin/discount/" class="btn btn-default">Назад</a>
<?
global $USER;
$objDiscount = new CPanelDiscount;
$fields = array(
	'code'      => str_replace(' ', '', $_POST['code']),
	'fullname'  => $_POST['fullname'],
	'birthday'  => $_POST['birthday'],
	'phone'     => preg_replace("/\D/","", $_POST['phone']),
	'email'     => ($_POST['email'] != '' ? $_POST['email'] : 'nomail' ),
	'start_level'=> $_POST['level'],
);
if($objDiscount->getID($fields["code"])){
	echo "<p class='col-sm-12 label label-danger' style='margin: 10px 0 0 0;'>Такая карта уже существует</p>";
}elseif($objDiscount->getIDbyPhone($fields["phone"])){
	echo "<p class='col-sm-12 label label-danger' style='margin: 10px 0 0 0;'>Карта с таким номер телефона уже создана</p>";
}else{
	$sum = $_POST['sum'];
	//TODO проверка входных данных по маске
	$objDiscount->addCard( $fields, $sum );

	$code = $_POST['code'];
	$discount = $_POST['level'] * 5 ;
	$objDiscount->toLog(
		"Добавлена карта #{$code}. Сумма {$sum}  Скидка {$discount}%.",
		$USER->getID()
	);
	LocalRedirect("/admin/discount/");
}
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>