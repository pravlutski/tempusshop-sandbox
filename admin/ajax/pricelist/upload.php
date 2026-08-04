<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
header('Content-Type: application/json;charset=UTF-8');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objBrand = new CPanelBrand;

$supp_id = $brand_id = 0;
if(isset($_POST["supplier"]))
	$supp_id = intval($_POST["supplier"]);

if($supp_id > 0){
	$supplier = $objSupplier->getDetail( $supp_id );
	$supplier["settings"] = json_decode( $supplier["settings"], true );
	$supplier["settings_pricelist"] = json_decode( $supplier["settings_pricelist"], true );
	$supplier["settings_pricelist_detail"] = json_decode( $supplier["settings_pricelist_detail"], true );
	$supplier["settings_brand_sale"] = json_decode($supplier["settings_brand_sale"], true);
	if(
		!isset($supplier["settings_pricelist"]["col_price"]) ||
		!isset($supplier["settings_pricelist"]["col_article"]) ||
		!isset($supplier["settings_pricelist"]["start_row"])
	){
		$supplier["settings_pricelist"]["col_price"] = 2;
		$supplier["settings_pricelist"]["col_article"] = 1;
		$supplier["settings_pricelist"]["start_row"] = 1;
		$supplier["settings_pricelist"]["col_brand"] = 0;
	}

	if(isset($_POST["brand"]) && count($_POST["brand"]) > 0){
		foreach($_POST["brand"] as $k => &$v){
			if(!isset($supplier["settings"]["brand"][$v])){
				unset($_POST["brand"][$k]);
			}
		}
	}

}else{
	$res = array(
		"status" => "error",
		"data" => "Не выбран поставщик"
	);
	echo json_encode($res, JSON_UNESCAPED_UNICODE);
	die();
}

if(!isset($_POST["brand"]) || count($_POST["brand"]) == 0){
	$res = array(
		"status" => "error",
		"data" => "Не выбран бренд"
	);
	echo json_encode($res, JSON_UNESCAPED_UNICODE);
	die();
}
if(isset($_FILES["file_price"]) && $_FILES["file_price"]["error"] == UPLOAD_ERR_OK){
	############ Edit settings ##############
	$upload_dir	= $_SERVER['DOCUMENT_ROOT'].'/upload/pricelist_tmp/';
	##########################################
	//check if this is an ajax request
	if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])){
		$res = array(
			"status" => "error",
			"data" => "Не корректный запрос"
		);
		echo json_encode($res, JSON_UNESCAPED_UNICODE);
		die();
	}
	//Is file size is less than allowed size.
	if ($_FILES["file_price"]["size"] > 30485760) {
		$res = array(
			"status" => "error",
			"data" => "Очень большой файл!"
		);
		echo json_encode($res, JSON_UNESCAPED_UNICODE);
		die();
	}
	switch(strtolower($_FILES['file_price']['type'])){
		//allowed file types
		case 'application/vnd.ms-excel':
			break;
		case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
			break;
		//	default:
		//	$this->_ErrorOutput('Формат файла не поддерживается!');
		//	return;
	}

	$file_name			= strtolower($_FILES['file_price']['name']);
	$file_ext			= substr($file_name, strrpos($file_name, '.')); //get file extention
	$rnd				= rand(0, 9999999999); //Random number to be added to name.
	$new_filename		= $rnd.$file_ext; //new file name

	if(move_uploaded_file($_FILES['file_price']['tmp_name'], $upload_dir.$new_filename )){
		$result = $objPricelist->upload( $upload_dir.$new_filename, $_POST, $supplier );
		$res = array(
			"status" => "ok",
			"data" => $result
		);
		$triggers = new TsTriggers();
		$message = ' пользователь ' . \Bitrix\Main\Engine\CurrentUser::get()->getLogin();
		$triggers->SetError(["Загрузка прайслиста [поставщик - ".$supplier['name']."] [". date('d.m.Y H:i:s')."] [УСПЕШНО]" . $message]);
		$triggers->SendTriggerErrors();
	}else{
		$res = array(
			"status" => "error",
			"data" => "Ошибка при загрузке файла!"
		);
		$triggers = new TsTriggers();
		$message = ' пользователь ' . \Bitrix\Main\Engine\CurrentUser::get()->getLogin();
		$triggers->SetError(["Загрузка прайслиста [поставщик - ".$supplier['name']."] [". date('d.m.Y H:i:s')."] [С ОШИБКОЙ]" . $message]);
		$triggers->SendTriggerErrors();
	}
}else{
	$res = array(
		"status" => "error",
		"data" => "Something wrong with upload! Is upload_max_filesize set correctly?"
	);
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
die();
