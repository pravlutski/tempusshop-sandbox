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

  $strSql = "SELECT * FROM ci_nk_reports WHERE STR_TO_DATE(date, '%d.%m.%Y') < STR_TO_DATE('".$arResult['DATE_TO']."', '%d.%m.%Y') ORDER BY date ASC";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		//$arReports[] = $row;
		// $date = explode(' ',$row['datetime']);
		$arDataResult[$row['date']]['REPORT'][] = $row;
	}

	$strSql = "SELECT * FROM ci_nakladnie WHERE STR_TO_DATE(date, '%d.%m.%Y') <= STR_TO_DATE('".$arResult['DATE_TO']."', '%d.%m.%Y') ORDER BY date ASC";
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
			$curArPrint['Продажа'] = array();
			$curArPrint['Возврат'] = array();
			$strSql = "SELECT * FROM ci_nk_reports_position WHERE nk_id = '{$report["id"]}'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				if ($row['model'] != '') {
					if (isset($curAr[$row['type']][$row['model']])) {
						$setUpPrice = ($curAr[$row['type']][$row['model']]['price'] > $row['price']) ? $curAr[$row['type']][$row['model']]['price'] : $row['price'];
						$curAr[$row['type']][$row['model']] = ['quantity' => $curAr[$row['type']][$row['model']]['quantity'] + $row['quantity'],'price' => $setUpPrice];
					} else {
						$curAr[$row['type']][$row['model']] = ['quantity' => $row['quantity'],'price' => $row['price']];
					}
				}
			}

			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/nakladnie/test.txt", print_r($curAr, true).PHP_EOL, FILE_APPEND);
			if (count($arSklad)>0) {
				foreach ($curAr['Продажа'] as $model => $v) {

					if (isset($arSklad[$model])) {
						$tnpQuant = $v['quantity'];

						foreach ($arSklad[$model] as $gtd => $value) {

							if ($arSklad[$model][$gtd]['quantity'] != 0 and $tnpQuant > 0) {

								 $tmpQ = $arSklad[$model][$gtd]['quantity'] - $tnpQuant;

								 if ($tmpQ > 0) {
									$curArPrint['Продажа'][$model][$gtd] = ['name'=>$arSklad[$model][$gtd]['name'],'q' => $tnpQuant,'гтд' => $gtd,'country' => $arSklad[$model][$gtd]['country'],'price' => $v['price'], 'sum' => $v['price'] * $tmpQ];
									$arSklad[$model][$gtd]['quantity'] = $tmpQ;

									if (isset($tmpSellCycle[$model][$gtd])) {
										$tmpSellCycle[$model][$gtd] = $tmpSellCycle[$model][$gtd] + $tmpQ;
									} else {
										$tmpSellCycle[$model][$gtd] = $tmpQ;
									}

									$tnpQuant = 0;

								} else {
									$qEdit = $tnpQuant - (0 - $tmpQ);

									if (isset($tmpSellCycle[$model][$gtd])) {
										$tmpSellCycle[$model][$gtd] = $tmpSellCycle[$model][$gtd] + $qEdit;
									} else {
										$tmpSellCycle[$model][$gtd] = $qEdit;
									}
									$tnpQuant = $qEdit;
									$curArPrint['Продажа'][$model][$gtd] = ['name'=>$arSklad[$model][$gtd]['name'],'q' => $qEdit,'гтд' => $gtd,'country' => $arSklad[$model][$gtd]['country'], 'price' => $v['price'], 'sum' => $v['price'] * $qEdit];
									$arSklad[$model][$gtd]['quantity'] = 0;
								}

							}
						}
					}
				}
			}
			//print_r($curArPrint);
			foreach ($curAr['Возврат'] as $model => $v) {
				if (isset($arSklad[$model])) {

					$tnpQuant = $v['quantity'];
					foreach ($arSklad[$model] as $gtd => $value) {

						if (isset($tmpSellCycle[$model][$gtd]) and $tnpQuant > 0) {
							$realQuant = $tmpSellCycle[$model][$gtd] - $tnpQuant;
							if ($realQuant >= 0) {
								$rq = $tnpQuant;
								$tmpSellCycle[$model][$gtd] = $rq;
							} else {
								$rq = $tnpQuant - (0 - $realQuant);
								$tmpSellCycle[$model][$gtd] = 0;
								$tnpQuant = 0;
							}
							$arSklad[$model][$gtd]['quantity'] = $arSklad[$model][$gtd]['quantity'] + $rq;
							$curArPrint['Возврат'][$model][$gtd] = ['q' => $rq];
						}

					}
				}
			}
		?>
		<?}?>
	<?}?>
	<?if (isset($ar['SKLAD_UPDATE'])){?>
		<?$curAr = array();
		 $arDifTmp =  array();?>
		<?
			$strSql = "SELECT * FROM ci_nakladnie_pos WHERE naklad_id = '{$ar['SKLAD_UPDATE']['id']}'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				//$cuArTmp[$row['model']][$row['gtd']] = ['name'=>$row['name'],'quantity'=>$row['quantity'],'country' => $row['country']];
				if (isset($arSklad[$row['model']][$row['gtd']])) {
						$arSklad[$row['model']][$row['gtd']]['quantity'] = intval($arSklad[$row['model']][$row['gtd']]['quantity']) + intval($row['quantity']);
						$arDifTmp[$row['model']][$row['gtd']] = $row['quantity'];
				} else {
					 $arSklad[$row['model']][$row['gtd']]['quantity'] = $row['quantity'];
					 $arSklad[$row['model']][$row['gtd']]['name'] = $row['name'];
					 $arSklad[$row['model']][$row['gtd']]['country'] = $row['country'];
					 $arDifTmp[$row['model']][$row['gtd']] = $row['quantity'];
				}
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
	foreach($arSklad as $model => $modelArr){
		foreach($modelArr as $gtd => $q) {
			$column = 0;
			$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column, $row, $q['name']);
			$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+1, $row, $q['quantity']);
			$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+2, $row, $gtd);
			$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+3, $row, $q['country']);
			$row++;
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
				<?foreach($arSklad as $model => $modelArr):?>
					<?foreach($modelArr as $gtd => $q):?>
					<tr>
						<td><?=$q['name']?></td>
						<td><?=$q['quantity']?></td>
						<td><?=$gtd?></td>
						<td><?=$q['country']?></td>
					</tr>
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
