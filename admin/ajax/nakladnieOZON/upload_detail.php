<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
header('Content-Type: application/json;charset=UTF-8');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;
require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';
global $DB;
if(isset($_FILES["file_detail"]) && $_FILES["file_detail"]["error"] == UPLOAD_ERR_OK){
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
	if ($_FILES["file_detail"]["size"] > 10485760) {
		$res = array(
			"status" => "error",
			"data" => "Очень большой файл!"
		);
		echo json_encode($res, JSON_UNESCAPED_UNICODE);
		die();
	}
	switch(strtolower($_FILES['file_detail']['type'])){
		//allowed file types
		case 'application/vnd.ms-excel':
			break;
		case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
			break;
		//	default:
		//	$this->_ErrorOutput('Формат файла не поддерживается!');
		//	return;
	}

	$file_name			= strtolower($_FILES['file_detail']['name']);
	$file_ext			= substr($file_name, strrpos($file_name, '.')); //get file extention
	$rnd				= rand(0, 9999999999); //Random number to be added to name.
	$new_filename		= $rnd.$file_ext; //new file name
	if(move_uploaded_file($_FILES['file_detail']['tmp_name'], $upload_dir.$new_filename )){
		//$result = $objPricelist->upload( $upload_dir.$new_filename, $_POST, $supplier );
		//exel decode

		$arSelect = Array("ID", "PROPERTY_WBARTICLE2","PROPERTY_WBARTICLE","PROPERTY_123");
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			//"ID" => 173381
			//"ID" => 178901
		);
		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while ($el = $result->GetNext()){
			$itemsBx[$el['PROPERTY_WBARTICLE2_VALUE']] = $el['PROPERTY_123_VALUE'];
			$itemsBxOz[$el['PROPERTY_WBARTICLE_VALUE']] = $el['PROPERTY_123_VALUE'];
		}
		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/all.txt", print_r($itemsBx, true) . "\r\n", FILE_APPEND);
		// die();
		$dateReport = $_POST['date_detail'];
		$excel = PHPExcel_IOFactory::load($upload_dir.$new_filename);
		foreach($excel ->getWorksheetIterator() as $worksheet) {
		 $lists[] = $worksheet->toArray();
		}
		$list = $lists[0];

		if ($list[0][0] != '№' && $list[11][1] != '№ п/п' && $list[0][0] != '№ п/п') {
			$res = array(
				"status" => "error",
				"data_e" => $list[11][1],
				"data" => "Ошибка при загрузке файла! Файл не является детализацией"
			);
			echo json_encode($res, JSON_UNESCAPED_UNICODE);
			die();
		}

		if ($list[0][0] == '№') {
			$type_report = 'wb';
		}
		else if ($list[11][1] == '№ п/п'){
			$type_report = 'oz';
		}
		else if ($list[0][0] == '№ п/п'){
			$type_report = 'oz_n';
		}
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/in.txt", print_r($list, true) . "\r\n", FILE_APPEND);

		unset($list[0]);


		// $strSql = "SELECT * FROM ci_nk_reports WHERE date = '{$dateReport}'";
		// $results = $DB->Query($strSql, false, $err_mess.__LINE__);
		// if ($row = $results->Fetch()){
		// 	$res = array(
		// 		"status" => "error",
		// 		"data" => "Данная детализация уже загружена!"
		// 	);
		// 	echo json_encode($res, JSON_UNESCAPED_UNICODE);
		// 	return;
		// }


		$in = array(
			'date' => "'".$dateReport."'",
			'type' => "'".$type_report."'",
		);

		$id_naklad_bd = $DB->Insert("ozon_ci_nk_reports", $in, $err_mess.__LINE__);

		if ($type_report == 'wb') {
			foreach ($list as $key => $value) {


				if (($value[9] == 'Продажа' or $value[9] == 'Возврат') and ($value[10] == 'Продажа' or $value[10] == 'Корректная продажа' or $value[10] == 'Сторно возвратов' or $value[10] == 'Авансовая оплата за товар без движения'
				or $value[10] == 'Возврат' or $value[10] == 'Сторно продаж' or $value[10] == 'Корректный возврат')) {

					$wbArt = $value[5];
					$model = $itemsBx[$wbArt];
					$q = intval($value[13]);
					$type = $value[9];
					$price = $value[19];

					$in = array(
						'nk_id' => "'".$id_naklad_bd."'",
						'model' => "'".$model."'",
						'type' => "'".$type."'",
						'price' => "'".$price."'",
						'quantity' => "'".$q."'",
					);
					$res = $DB->Insert("ozon_ci_nk_reports_position", $in, $err_mess.__LINE__);
				}

				}

		}	elseif ($type_report == 'oz') {
			for ($i=0; $i < 14; $i++) {
				unset($list[$i]);
			}
			foreach ($list as $key => $value) {
					$ozArt = $value[5];
					$model = $itemsBxOz[$ozArt];
					if(!empty($value[29])) {
						$q = intval($value[29]);
						$type = 'Возврат';
						$price = $value[25];
					} else {
						$q = intval($value[15]);
						$type = 'Продажа';
						$price = $value[16];
					}
					if (!empty($model)) {
						$in = array(
							'nk_id' => "'".$id_naklad_bd."'",
							'model' => "'".$model."'",
							'type' => "'".$type."'",
							'price' => "'".$price."'",
							'quantity' => "'".$q."'",
						);
						$res = $DB->Insert("ozon_ci_nk_reports_position", $in, $err_mess.__LINE__);
					}
				}
		}		elseif ($type_report == 'oz_n') {
			for ($i=0; $i < 2; $i++) {
				unset($list[$i]);
			}
			foreach ($list as $key => $value) {
					$ozArt = $value[2];
					$model = $itemsBxOz[$ozArt];

					$q = intval($value[8]);
					$type = 'Продажа';
					$price = $value[9];

					if (!empty($model)) {
						$in = array(
							'nk_id' => "'".$id_naklad_bd."'",
							'model' => "'".$model."'",
							'type' => "'".$type."'",
							'price' => "'".$price."'",
							'quantity' => "'".$q."'",
						);
						$res = $DB->Insert("ozon_ci_nk_reports_position", $in, $err_mess.__LINE__);
					}
				}
		}


		$result = 1;
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/in.txt", print_r($in, true) . "\r\n", FILE_APPEND);
		// die();

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
