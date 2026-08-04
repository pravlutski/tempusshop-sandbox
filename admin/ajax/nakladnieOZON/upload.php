<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
header('Content-Type: application/json;charset=UTF-8');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;
require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';
global $DB;
if(isset($_FILES["file_price"]) && $_FILES["file_price"]["error"] == UPLOAD_ERR_OK){
	############ Edit settings ##############
	$upload_dir	= $_SERVER['DOCUMENT_ROOT'].'/upload/nakladnie_tmp/';
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
	if ($_FILES["file_price"]["size"] > 10485760) {
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
	$months = [
    'января' => 'January',
    'февраля' => 'February',
    'марта' => 'March',
    'апреля' => 'April',
    'мая' => 'May',
    'июня' => 'June',
    'июля' => 'July',
    'августа' => 'August',
    'сентября' => 'September',
    'октября' => 'October',
    'ноября' => 'November',
    'декабря' => 'December'
];
	if(move_uploaded_file($_FILES['file_price']['tmp_name'], $upload_dir.$new_filename )){
		//$result = $objPricelist->upload( $upload_dir.$new_filename, $_POST, $supplier );
		//exel decode
		$dateReport = $_POST['date_detail'];
		$excel = PHPExcel_IOFactory::load($upload_dir.$new_filename);
		foreach($excel ->getWorksheetIterator() as $worksheet) {
		 $lists[] = $worksheet->toArray();
		}
		$list = $lists[0];
		if ($list[0][0] != 'внешний код') {
			$res = array(
				"status" => "error",
				"data" => "Ошибка при загрузке файла! Файл не является накладной"
			);
			echo json_encode($res, JSON_UNESCAPED_UNICODE);
			die();
		}
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnieOZON/list.txt", print_r($list , true).PHP_EOL, FILE_APPEND);
		$items = array();
		$fullname = 'Накладная от '.$dateReport;
		$strSql = "SELECT * FROM ozon_ci_nakladnie WHERE date = '{$dateReport}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$i = 1;
		while ($row = $results->Fetch()){
			$i++;
			// $res = array(
			// 	"status" => "error",
			// 	"data" => "Данная накладная уже загружена!"
			// );
			// echo json_encode($res, JSON_UNESCAPED_UNICODE);
			// return;
		}
		if ($i > 1) {
			$fullname = 'Накладная от '.$dateReport .' № '.$i;
		}
		if (isset($_POST['wh']) && $_POST['wh'] != 'ns'){
			$in = array(
				'name' => "'".$fullname."'",
				'date' => "'".$dateReport."'",
				'wh' => "'".$_POST['wh']."'",
			);
		}else{
			$in = array(
				'name' => "'".$fullname."'",
				'date' => "'".$dateReport."'",
			);
		}
		$id_naklad_bd = $DB->Insert("ozon_ci_nakladnie", $in, $err_mess.__LINE__);

		for ($i=1; $i < count($list); $i++) {
			if ($list[$i][1] == '#NULL!' or empty($list[$i][1])) {
				break;
			}
			$name = $list[$i][1];
			$modelEx = explode(' ',$name);
			if (strpos($name, 'George Kini') !== false ) {
				$model = explode('George Kini ',$name);
				$model = $model[1];
			}else if (strpos($name, 'Emporio Armani') !== false ) {
				$model = explode('Emporio Armani ',$name);
				$model = $model[1];
			} else if (strpos($name, 'Michael Kors') !== false ) {
				$model = explode('Michael Kors',$name);
				$model = $model[1];
			} else if (strpos($name, 'Armani Exchange') !== false ) {
				$model = explode('Armani Exchange',$name);
				$model = $model[1];
			} else if (count($modelEx) >= 3) {
				$model = $modelEx[count($modelEx)-1];
				//$model = $model[1];
			} else {
				$model = explode(' ',$name);
				$model = $model[1];
			}
			if (!empty($model)) {
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/quant.txt", print_r('#####' , true).PHP_EOL, FILE_APPEND);
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/quant.txt", print_r($model , true).PHP_EOL, FILE_APPEND);
				$quant = trim($list[$i][2]);
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/quant.txt", print_r($quant , true).PHP_EOL, FILE_APPEND);
				$quant = str_replace(' ', '', $quant);
				$quant = str_replace(',', '', $quant);
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/quant.txt", print_r($quant , true).PHP_EOL, FILE_APPEND);
				$quant = intval($quant);
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/quant.txt", print_r($quant , true).PHP_EOL, FILE_APPEND);
				$in = array(
					'naklad_id' => "'".$id_naklad_bd."'",
					'name' => "'".$name."'",
					'model' => "'".$model."'",
					'gtd' => "'".$list[$i][4]."'",
					'country' => "'".$list[$i][3]."'",
					'model' => "'".$model."'",
					'quantity' => "'".$quant."'",
					'row_excel' => "'".$i."'",
				);
				$res = $DB->Insert("ozon_ci_nakladnie_pos", $in, $err_mess.__LINE__);
			}
		}


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
