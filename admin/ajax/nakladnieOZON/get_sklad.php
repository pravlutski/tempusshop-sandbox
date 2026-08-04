<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;
set_time_limit(0);
require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';
//$arGroups = $USER->GetUserGroupArray();
//if($USER->isAdmin() || in_array(6, $arGroups)) $arResult["ACCESS"] = true;
if(!CModule::IncludeModule('panel.manager'))return;

use \Bitrix\Main\Data\Cache;
?>
<div class="row">
<?
//if(!$arResult["ACCESS"]) return;
//$fileData = "/home/bitrix/logs/ms/tempPurchase/" . $USER->getID() . ".txt";


$start = debug_microtime_float();

$arResult = array();

$arResult["DATE_TO"] = $_POST["date_to"] ;
if (empty($arResult["DATE_TO"])) {
	$arResult["ERROR"][] = 'Выбреите дату начала отсчета';
} else {
	$arResult["DATETIME_TO"].= ' 00:00:00';
}
$site_id = 's1';

$agent = 'dd5b00b5-2a6e-11ec-0a80-019e000baf07';
$organization = '27af8b5c-58d1-11ec-0a80-08e7000a6716';
?>

<?if(!$arResult["ERROR"]):
	$arSklad = array();

  $strSql = "SELECT * FROM ozon_ci_nk_reports WHERE STR_TO_DATE(date, '%d.%m.%Y') < STR_TO_DATE('".$arResult['DATE_TO']."', '%d.%m.%Y') ORDER BY date ASC";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		//$arReports[] = $row;
		// $date = explode(' ',$row['datetime']);
		$arDataResult[$row['date']]['REPORT'][] = $row;
	}

	if (isset($_POST['wh']) && $_POST['wh'] != 'ns'){
		$strSql = "SELECT * FROM ozon_ci_nakladnie WHERE STR_TO_DATE(date, '%d.%m.%Y') <= STR_TO_DATE('".$arResult['DATE_TO']."', '%d.%m.%Y') AND wh = '".$_POST['wh']."' ORDER BY date ASC";
	} else {
		$strSql = "SELECT * FROM ozon_ci_nakladnie WHERE STR_TO_DATE(date, '%d.%m.%Y') <= STR_TO_DATE('".$arResult['DATE_TO']."', '%d.%m.%Y') ORDER BY date ASC";
	}
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		// $arNakladnie[] = [
		// 	'id' => $row['id'],
		// 	'name' => $row['name'],
		// 	'date' => $row['date'].' 00:00:00',
		// ];
		$date = $row['date'].' 00:00:00';
		$arDataResult[$date]['SKLAD_UPDATE'] = [
			'id' => $row['id'],
			'name' => $row['name'],
			'date' => $date,
		];
	}

	if (!empty($arDataResult)) {
		uksort($arDataResult, function($a, $b) {
		    return strtotime($a) - strtotime($b);
		});
	} else { ?>
		<div class="col-sm-12" style="background-color: rgba(223, 5, 5, 0.1);
  border-radius: 20px;
  margin-top: 15px;
  height: 40px;
  display: flex;">
				<div class="row header_table">
						<div class="title_header_table">
							<span>Отсутствуют данные на установленную дату.</span>
						</div>
				</div>
		</div>
		<?
		return;
	}



	$datetimestart = $arResult["DATETIME_TO"];

	$tmpSellCycle = array();
	foreach($arDataResult as $key => $ar):?>
	<?if (isset($ar['REPORT'])){?>
		<?
		foreach ($ar['REPORT'] as $key => $report) {
			$curAr = array();
			$fullcurArr = array();
			$curArPrint['Продажа'] = array();
			$curArPrint['Возврат'] = array();
			$curArPrint['CHRONOS'] = array();
			$strSql = "SELECT * FROM ozon_ci_nk_reports_position WHERE nk_id = '{$report["id"]}'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				if ($row['model'] != '') {
					$fullcurArr[] = ['model' => $row['model'],'type'=> $row['type'],'price'=>$row['price'],'quantity'=>$row['quantity']];
					if (isset($curAr[$row['type']][$row['model']])) {
						$setUpPrice = ($curAr[$row['type']][$row['model']]['price'] > $row['price']) ? $curAr[$row['type']][$row['model']]['price'] : $row['price'];
						$curAr[$row['type']][$row['model']] = ['quantity' => $curAr[$row['type']][$row['model']]['quantity'] + $row['quantity'],'price' => $setUpPrice];
					} else {
						$curAr[$row['type']][$row['model']] = ['quantity' => $row['quantity'],'price' => $row['price']];
					}
				}
			}
			//проверка
			// foreach ($curAr['Возврат'] as $model => $v) {
			// 	if (isset($curAr['Продажа'][$model])) {
			// 		if ($v['price'] == $curAr['Продажа'][$model]['price']) {
			// 			if (intval($curAr['Продажа'][$model]['quantity']) == intval($v['quantity'])) {
			// 				unset($curAr['Возврат'][$model]);
			// 				unset($curAr['Продажа'][$model]);
			// 			} else if (intval($curAr['Продажа'][$model]['quantity']) - intval($v['quantity']) < 0){
			// 				$curAr['Возврат'][$model]['quantity'] = intval($v['quantity']) - intval($curAr['Продажа'][$model]['quantity']);
			// 				unset($curAr['Продажа'][$model]);
			// 			} else {
			// 				$curAr['Продажа'][$model]['quantity'] = intval($curAr['Продажа'][$model]['quantity']) - intval($v['quantity']);
			// 				unset($curAr['Возврат'][$model]);
			// 			}
			// 		}
			// 	}
			// }
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/test.txt", print_r($curAr, true).PHP_EOL, FILE_APPEND);
			if (count($arSklad)>0) {
				foreach ($curAr['Продажа'] as $model => $v) {

					//print_r($arSklad);
					if (isset($arSklad[$model])) {
						$tnpQuant = $v['quantity'];

						foreach ($arSklad[$model] as $nkid => $nkid_ar) {

							foreach ($nkid_ar as $gtd => $val) {
							//for
							foreach ($val as $key => $value) {

								if ($arSklad[$model][$nkid][$gtd][$key]['quantity'] != 0 and $tnpQuant > 0) {


									 $tmpQ = $arSklad[$model][$nkid][$gtd][$key]['quantity'] - $tnpQuant;
									 // print_r($arSklad[$model][$gtd]['name']. ' - ' . $gtd);
									 // print_r('<br>');
									 // print_r($arSklad[$model][$gtd]['quantity'].' - '.$tnpQuant );
									 // print_r('<br>');
									 // print_r($tmpQ);
									 // print_r('<br>');

									 if ($tmpQ >= 0) {
										$curArPrint['Продажа'][$model][$gtd] = ['name'=>$arSklad[$model][$nkid][$gtd][$key]['name'],'q' => $tnpQuant,'price' => $v['price'], 'sum' => $v['price'] * $tnpQuant,'country' => $arSklad[$model][$nkid][$gtd][$key]['country'],'гтд' => $gtd,'nk' => $value['nk'],'row_excel' => $value['row_excel']];
										$arSklad[$model][$nkid][$gtd][$key]['quantity'] = $tmpQ;

										if (isset($tmpSellCycle[$model][$gtd])) {
											$tmpSellCycle[$model][$gtd] = $tmpSellCycle[$model][$gtd] + $tmpQ;
										} else {
											$tmpSellCycle[$model][$gtd] = $tmpQ;
										}
										//print_r($v['price'] * $tnpQuant);
										//print_r('<br>');



										$arNKExcel[$value['nk_id']]['HAVE'][$model][$gtd]['quantity'] = intval($arNKExcel[$value['nk_id']]['HAVE'][$model][$gtd]['quantity']) - $tnpQuant;
										$arNKExcel[$value['nk_id']]['SELL'][$model][$gtd]['quantity'] = intval($arNKExcel[$value['nk_id']]['SELL'][$model][$gtd]['quantity']) + $tnpQuant;
										// if ($model == 'GK.26.R.3R.2.R.3') {
										// 	print_r($tnpQuant);
										// 	print_r('<br>');
										// 	print_r($arNKExcel[$value['nk_id']]['HAVE'][$model][$gtd]['quantity']);
										// 	print_r('<br>');
										// 	print_r($arNKExcel[$value['nk_id']]['SELL'][$model][$gtd]['quantity']);
										// 	print_r('<br>');
										// 	print_r('###');
										// 	print_r('<br>');
										// }

										$tnpQuant = 0;
									} else {
										$qEdit = $tnpQuant - (0 - $tmpQ);

										if (isset($tmpSellCycle[$model][$gtd])) {
											$tmpSellCycle[$model][$gtd] = $tmpSellCycle[$model][$gtd] + $qEdit;
										} else {
											$tmpSellCycle[$model][$gtd] = $qEdit;
										}

										$curArPrint['Продажа'][$model][$gtd] = ['name'=>$arSklad[$model][$nkid][$gtd][$key]['name'],'q' => $qEdit, 'price' => $v['price'], 'sum' => $v['price'] * $qEdit ,'country' => $arSklad[$model][$nkid][$gtd][$key]['country'],'гтд' => $gtd,'nk' => $value['nk'],'row_excel' => $value['row_excel']];
										$arSklad[$model][$nkid][$gtd][$key]['quantity'] = 0;
										$arNKExcel[$value['nk_id']]['HAVE'][$model][$gtd]['quantity'] = $arNKExcel[$value['nk_id']]['HAVE'][$model][$gtd]['quantity'] - $qEdit;
										$arNKExcel[$value['nk_id']]['SELL'][$model][$gtd]['quantity'] = $arNKExcel[$value['nk_id']]['SELL'][$model][$gtd]['quantity'] + $qEdit;
										//print_r($v['price'] * $qEdit);
										//print_r('<br>');
										$tnpQuant = $tnpQuant-$qEdit;
									}
									//print_r('####################');
									//print_r('<br>');
									if (isset($freeMove[$model])) {
										$freeMove[$model] = intval($freeMove[$model]) + $tnpQuant;
									} else {
										$freeMove[$model] = $tnpQuant;
									}
								}	// code...
							}
							//endfor
							}
						}
					}
				}
			}
			// foreach ($curAr['Возврат'] as $model => $v) {
			// 	if (!isset($curAr['Продажа'][$model]) and isset($freeMove[$model])) {
			// 		$curAr['CHRONOS'][$model] = $v;
			// 		$curArPrint['CHRONOS'][$model] = $v;
			// 		$tmpFreeQ = $freeMove[$model] - $v['quantity'];
			// 		if ($tmpFreeQ > 0) {
			// 			$freeMove[$model] = $tmpFreeQ;
			// 		} else {
			// 			$freeMove[$model] = 0;
			// 			$curAr['CHRONOS'][$model]['quantity'] = 0 - $tmpFreeQ;
			// 		}
			//
			// 	}
			// }
			//print_r($curArPrint);
			// foreach ($curAr['Возврат'] as $model => $v) {
			// 	if (isset($arSklad[$model])) {
			//
			// 		$tnpQuant = $v['quantity'];
			// 		foreach ($arSklad[$model] as $gtd => $value) {
			//
			// 			if (isset($tmpSellCycle[$model][$gtd]) and $tnpQuant > 0) {
			// 				$realQuant = $tmpSellCycle[$model][$gtd] - $tnpQuant;
			// 				if ($realQuant >= 0) {
			// 					$rq = $tnpQuant;
			// 					$tmpSellCycle[$model][$gtd] = $rq;
			// 				} else {
			// 					$rq = $tnpQuant - (0 - $realQuant);
			// 					$tmpSellCycle[$model][$gtd] = 0;
			// 					$tnpQuant = 0;
			// 				}
			// 				$arSklad[$model][$gtd]['quantity'] = $arSklad[$model][$gtd]['quantity'] + $rq;
			// 				$curArPrint['Возврат'][$model][$gtd] = ['gtd' => $gtd,'q' => $rq];
			// 			}
			//
			// 		}
			// 	}
			// }
		?>
		<?}?>
	<?}?>
	<?if (isset($ar['SKLAD_UPDATE'])){?>
		<?$curAr = array();
		 $arDifTmp =  array();?>
		<?
			$strSql = "SELECT * FROM ozon_ci_nakladnie_pos WHERE naklad_id = '{$ar['SKLAD_UPDATE']['id']}'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				if(empty($row['gtd']) || $row['gtd'] == ''){
					$row['gtd'] = 0;
				}
				if(empty($row['country']) || $row['country'] == ''){
					$row['country'] = 0;
				}
				//$cuArTmp[$row['model']][$row['gtd']] = ['name'=>$row['name'],'quantity'=>$row['quantity'],'country' => $row['country']];
				if (isset($arSklad[$row['model']][$ar['SKLAD_UPDATE']['id']][$row['gtd']])) {
						$arSklad[$row['model']][$ar['SKLAD_UPDATE']['id']][$row['gtd']][] =
						[
							'quantity' => $row['quantity'],
							'name'=> $row['name'],
							'country' => $row['country'],
							'nk' => $arNINfo[$ar['SKLAD_UPDATE']['id']],
							'nk_id' => $ar['SKLAD_UPDATE']['id'],
							'row_excel' => $row['row_excel']
						];
						$arDifTmp[$row['model']][$row['gtd']][] = $row['quantity'];
					 $arDifPrint[$row['model']][$row['gtd']][] = ['name'=> $row['name'],'q' => $row['quantity']];
				} else {
					 $arSklad[$row['model']][$ar['SKLAD_UPDATE']['id']][$row['gtd']][] =
					 [
						 'quantity' => $row['quantity'],
						 'name'=> $row['name'],
						 'country' => $row['country'],
						 'nk' => $arNINfo[$ar['SKLAD_UPDATE']['id']],
						 'nk_id' => $ar['SKLAD_UPDATE']['id'],
						 'row_excel' => $row['row_excel']
					 ];
					 $arDifTmp[$row['model']][$row['gtd']][] = $row['quantity'];
					 $arDifPrint[$row['model']][$row['gtd']][] = ['name'=> $row['name'],'q' => $row['quantity']];
				}
				if (isset($arNKExcel[$ar['SKLAD_UPDATE']['id']]['HAVE'][$row['model']][$row['gtd']])) {
					$arNKExcel[$ar['SKLAD_UPDATE']['id']]['HAVE'][$row['model']][$row['gtd']] = [
						'quantity' => $arNKExcel[$ar['SKLAD_UPDATE']['id']]['HAVE'][$row['model']][$row['gtd']]['quantity'] + $row['quantity'],
						'name'=> $row['name'],
						'country' => $row['country'],
						'row_excel' => $row['row_excel']
					];
				} else {
					$arNKExcel[$ar['SKLAD_UPDATE']['id']]['HAVE'][$row['model']][$row['gtd']] = [
						'quantity' => $row['quantity'],
						'name'=> $row['name'],
						'country' => $row['country'],
						'row_excel' => $row['row_excel']
					];
				}
				$arNKExcel[$ar['SKLAD_UPDATE']['id']]['SELL'][$row['model']][$row['gtd']] = [
					'quantity' => 0,
					'name'=> $row['name'],
					'country' => $row['country'],
					'row_excel' => $row['row_excel']
				];
			}
			// foreach ($cuArTmp as $model => $value) {
			// 	$q = 0;
			// 	foreach ($value as $k=>$v) {
			// 		$name = $v['name'];
			// 		$q = $q + $v['quantity'];
			// 	}
			// 	$curAr[$model] = ['name' => $name,'quantity' => $q];
			// }

		?>
	<?}?>
		<?

?>
	<?endforeach?>

	<?$objPHPExcel = new PHPExcel();
	$objPHPExcel->setActiveSheetIndex(0);
	$sheet = $objPHPExcel->getActiveSheet();
	$title = 'Отчет от '.$report['date'].'';
	$sheet->setTitle($title);
	$sheet->getColumnDimension("A")->setWidth(40);
	$sheet->getColumnDimension("B")->setWidth(20);
	$sheet->getColumnDimension("C")->setWidth(20);
	$sheet->getColumnDimension("D")->setWidth(20);
	$sheet->setCellValue("A1", "Модель");
	$sheet->getStyle("A1")->getFont()->setBold(true);
	$sheet->setCellValue("B1", "Кол-во");
	$sheet->getStyle("B1")->getFont()->setBold(true);
	$sheet->setCellValue("C1", "ГТД");
	$sheet->getStyle("C1")->getFont()->setBold(true);
	$sheet->setCellValue("D1", "Страна");
	$sheet->getStyle("D1")->getFont()->setBold(true);
	$row = 2;

	$arSklad[$row['model']][$ar['SKLAD_UPDATE']['id']][$row['gtd']][] =
	[
		'quantity' => $row['quantity'],
		'name'=> $row['name'],
		'country' => $row['country'],
		'nk' => $arNINfo[$ar['SKLAD_UPDATE']['id']],
		'nk_id' => $ar['SKLAD_UPDATE']['id'],
		'row_excel' => $row['row_excel']
	];

	foreach($arSklad as $model => $nkids){
		foreach($nkids as $nkid => $nkidtmp){
			foreach($nkidtmp as $gtd =>$skld) {
					foreach($skld as $q) {
						$column = 0;
						$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column, $row, $q['name']);
						$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+1, $row, $q['quantity']);
						$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+2, $row, $gtd);
						$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+3, $row, $q['country']);
						$row++;
					}
			}
		}
	}
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$name = $report['date'] .'-'.rand();
	$objWriter->save('/var/www/bitrix/data/www/tempusshop.ru/upload/nakladnie_cache/back_'.$name.'.xlsx');?>

	<div class="col-sm-12" style="background-color: rgba(134, 245, 0, 0.3); border-radius: 20px;margin-top: 15px;">
		<div class="row header_table" style="height: 70px;">
				<div class="title_header_table">
					<span>Текущее состояние склада</span>
				</div>
				<div style="margin-left:50px;font-size:14px;">
					<a href="/upload/nakladnie_cache/back_<?=$name?>.xlsx" target="_blank">Скачать excel</a>
				</div>
		</div>
		<div class="table" style="display:block!important;">
		<table>
			<thead>
				<tr>
					<th style="width: 15%">Модель</span></th>
					<th style="width: 15%">Кол-во</th>
					<th style="width: 25%">ГТД</th>
					<th style="width: 15%">Страна</th>
				</tr>
			</thead>
			<tbody>
				<?foreach($arSklad as $model => $nkids):?>
					<?foreach($nkids as $nkid => $nkidtmp):?>
						<?foreach($nkidtmp as $gtd =>$skld):?>
						<?foreach($skld as $q):?>
						<tr>
							<td><?=$q['name']?></td>
							<td><?=$q['quantity']?></td>
							<td><?=$gtd?></td>
							<td><?=$q['country']?></td>
						</tr>
						<?endforeach?>
						<?endforeach?>
					<?endforeach?>
				<?endforeach?>
			</tbody>
		</table>
		</div>
	</div>

<?endif;?>
<?file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/sl.txt", print_r($tmpSellCycle, true).PHP_EOL, FILE_APPEND);?>
</div>
<script>
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
