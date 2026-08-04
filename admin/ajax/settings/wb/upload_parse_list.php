<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
header('Content-Type: application/json;charset=UTF-8');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objBrand = new CPanelBrand;

if(isset($_FILES["file_parse"]) && $_FILES["file_parse"]["error"] == UPLOAD_ERR_OK){
	############ Edit settings ##############
	$upload_dir	= $_SERVER['DOCUMENT_ROOT'].'/upload/';
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
	if ($_FILES["file_parse"]["size"] > 10485760) {
		$res = array(
			"status" => "error",
			"data" => "Очень большой файл!"
		);
		echo json_encode($res, JSON_UNESCAPED_UNICODE);
		die();
	}
	switch(strtolower($_FILES['file_parse']['type'])){
		//allowed file types
		case 'application/vnd.ms-excel':
			break;
		case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
			break;
		//	default:
		//	$this->_ErrorOutput('Формат файла не поддерживается!');
		//	return;
	}
							
	$file_name			= strtolower($_FILES['file_parse']['name']);
	$file_ext			= substr($file_name, strrpos($file_name, '.')); //get file extention
	//$rnd				= rand(0, 9999999999); //Random number to be added to name.
	//$new_filename		= $rnd.$file_ext; //new file name
	$new_filename		= "wb_parse" . $file_ext; //new file name
	
	if(move_uploaded_file($_FILES['file_parse']['tmp_name'], $upload_dir . $new_filename )){
		//$result = $objPricelist->upload( $upload_dir.$new_filename, $_POST, $supplier );
		$obj = new CWbParserURI;
		$result = $obj->parseFile($upload_dir . $new_filename);
		$res = array(
			"status" => "ok",
			"data" => $result
		);
	}else{
		$res = array(
			"status" => "error",
			"data" => "Ошибка при загрузке файла!"
		);
	}
}else{
	$res = array(
		"status" => "error",
		"data" => "Something wrong with upload! Is upload_max_filesize set correctly?"
	);
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
die();



