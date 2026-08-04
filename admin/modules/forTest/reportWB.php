<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('maxyss.wb');

$arSettings = CMaxyssWb::settings_wb( 'WR' );
$auth = $arSettings["AUTHORIZATION"];
// $token = 'eyJhbGciOiJFUzI1NiIsImtpZCI6IjIwMjMxMjI1djEiLCJ0eXAiOiJKV1QifQ.eyJlbnQiOjEsImV4cCI6MTcyMDczNzc4MiwiaWQiOiJlOWY0Mzg5NC1lOTAyLTQwNTctOGE0YS1jNTFhOWNjZjJiNTgiLCJpaWQiOjYxNTAwNjgsIm9pZCI6NzI0NjQ2LCJzIjozMiwic2lkIjoiZTQzYzg4MjktYzlhZC00Y2M3LWJlZWQtNTQ3ZjRmZmUyMzJiIiwidCI6ZmFsc2UsInVpZCI6NjE1MDA2OH0.XcqKz-ZZZIjQbHf3hlurYbLxNvVZwCyMmjDzMYsEekv6wlVmiXGaFah_10hLaJAY8GlxdS8WDZ7gs2qvXVqIRA';
// for ($rrdid = 0;)
$rrdid = 0;
$data = [
  'dateFrom' => '2024-04-01',
  'dateTo' => '2024-04-07',
  'rrdid' => $rrdid,
];

  $url = 'https://statistics-api.wildberries.ru/api/v5/supplier/reportDetailByPeriod?dateFrom='.$data['dateFrom'].'&dateTo='.$data['dateTo'].'&rrdid='.$data['rrdid'].'&limit=100000';
  $ch = curl_init($url);
  curl_setopt(
    $ch,
    CURLOPT_HTTPHEADER,
    array(
      "Content-Type: application/json",
      "Authorization: {$auth}"
    )
  );
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
  $result = curl_exec($ch);
  curl_close($ch);
  $report = json_decode($result, 1);
  // var_dump($result);
  if ( empty($result) ) die('Вернуло null или нет данных за этот период');
  // var_dump( json_decode($result, 1) );
  require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
  use PhpOffice\PhpSpreadsheet\Spreadsheet;
  use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
  if (!class_exists('SpreadsheetReader')){
    require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
    require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
    require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
  }

$xls = new PHPExcel();
$xls->setActiveSheetIndex(0);
$sheet = $xls->getActiveSheet();
$sheet->setTitle('listOne');

$sheet->setCellValueExplicit("A1", 'Номер отчёта', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("B1", 'Дата начала отчётного периода', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("C1", 'Дата конца отчётного периодаа', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("D1", 'Дата формирования отчёта', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("E1", 'Валюта отчёта', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("F1", 'Договор', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("G1", 'Номер строки', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("H1", 'Номер поставки', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("I1", 'Предмет', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("J1", 'Артикул WB', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("K1", 'Бренд', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("L1", 'Артикул продавца', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("M1", 'Размер', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("N1", 'Баркод', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("O1", 'Тип документа', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("P1", 'Количество', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("Q1", 'Цена розничная', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("R1", 'Сумма продаж (возвратов)', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("S1", 'Согласованная скидка', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("T1", 'Процент комиссии', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("W1", 'Склад', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("X1", 'Обоснование для оплаты', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("Y1", 'Дата заказа', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("Z1", 'Дата продажи', PHPExcel_Cell_DataType::TYPE_STRING);

$sheet->setCellValueExplicit("AA1", 'Дата операции', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AB1", 'Штрих-код', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AC1", 'Цена розничная с учетом согласованной скидки', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AD1", 'Количество доставок', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AE1", 'Количество возвратов', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AF1", 'Стоимость логистики', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AG1", 'Тип коробов', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AH1", 'Согласованный продуктовый дисконт', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AI1", 'Промокод', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AJ1", 'Уникальный идентификатор заказа', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AK1", 'Скидка постоянного покупателя', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AL1", 'Размер кВВ без НДС, % базовый', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AM1", 'Итоговый кВВ без НДС, %', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AN1", 'Размер снижения кВВ из-за рейтинга', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AO1", 'Размер снижения кВВ из-за акции', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AP1", 'Вознаграждение с продаж до вычета услуг поверенного, без НДС', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AQ1", 'К перечислению продавцу за реализованный товар', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AR1", 'Возмещение за выдачу и возврат товаров на ПВЗ', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AS1", 'Возмещение издержек по эквайрингу', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AT1", 'Наименование банка-эквайера', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AW1", 'Вознаграждение WB без НДС', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AX1", 'НДС с вознаграждения WB', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AY1", 'Номер офиса', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("AZ1", 'Наименование офиса доставки', PHPExcel_Cell_DataType::TYPE_STRING);

$sheet->setCellValueExplicit("BA1", 'Номер партнера', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BB1", 'Партнер', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BC1", 'ИНН партнера', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BD1", 'Номер таможенной декларации', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BE1", 'Обоснование штрафов и доплат', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BF1", 'Цифровое значение стикера, который клеится на товар в процессе сборки заказа по схеме "Маркетплейс"', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BH1", 'Страна продажи', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BI1", 'Штрафы', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BJ1", 'Доплаты', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BK1", 'Возмещение издержек по перевозке', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BL1", 'Организатор перевозки', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BM1", 'Код маркировки', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BN1", 'Стоимость хранения', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BO1", 'Прочие удержания/выплаты', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BP1", 'Стоимость платной приёмки', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BQ1", 'Уникальный идентификатор заказа', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("BR1", 'Тип отчёта', PHPExcel_Cell_DataType::TYPE_STRING);

foreach ($report as $key => $row) {
  $i = $key + 2;
  $sheet->setCellValueExplicit("A" . $i, $row['realizationreport_id'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("B" . $i, $row['date_from'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("C" . $i, $row['date_to'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("D" . $i, $row['create_dt'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("E" . $i, $row['currency_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("F" . $i, $row['suppliercontract_code'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("G" . $i, $row['rrd_id'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("H" . $i, $row['gi_id'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("I" . $i, $row['subject_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("J" . $i, $row['nm_id'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("K" . $i, $row['brand_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("L" . $i, $row['sa_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("M" . $i, $row['ts_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("N" . $i, $row['barcode'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("O" . $i, $row['doc_type_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("P" . $i, $row['quantity'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("Q" . $i, $row['retail_price'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("R" . $i, $row['retail_amount'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("S" . $i, $row['sale_percent'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("T" . $i, $row['commission_percent'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("W" . $i, $row['office_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("X" . $i, $row['supplier_oper_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("Y" . $i, $row['order_dt'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("Z" . $i, $row['sale_dt'], PHPExcel_Cell_DataType::TYPE_STRING);

  $sheet->setCellValueExplicit("AA" . $i, $row['rr_dt'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AB" . $i, $row['shk_id'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AC" . $i, $row['retail_price_withdisc_rub'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AD" . $i, $row['delivery_amount'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AE" . $i, $row['return_amount'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AF" . $i, $row['delivery_rub'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AG" . $i, $row['gi_box_type_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AH" . $i, $row['product_discount_for_report'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AI" . $i, $row['supplier_promo'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AJ" . $i, $row['rid'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AK" . $i, $row['ppvz_spp_prc'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AL" . $i, $row['ppvz_kvw_prc_base'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AM" . $i, $row['ppvz_kvw_prc'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AN" . $i, $row['sup_rating_prc_up'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AO" . $i, $row['is_kgvp_v2'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AP" . $i, $row['ppvz_sales_commission'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AQ" . $i, $row['ppvz_for_pay'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AR" . $i, $row['ppvz_reward'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AS" . $i, $row['acquiring_fee'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AT" . $i, $row['acquiring_bank'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AW" . $i, $row['ppvz_vw'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AX" . $i, $row['ppvz_vw_nds'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AY" . $i, $row['ppvz_office_id'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("AZ" . $i, $row['ppvz_office_name'], PHPExcel_Cell_DataType::TYPE_STRING);

  $sheet->setCellValueExplicit("BA" . $i, $row['ppvz_supplier_id'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BB" . $i, $row['ppvz_supplier_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BC" . $i, $row['ppvz_inn'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BD" . $i, $row['declaration_number'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BE" . $i, $row['bonus_type_name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BF" . $i, $row['sticker_id'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BH" . $i, $row['site_country'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BI" . $i, $row['penalty'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BJ" . $i, $row['additional_payment'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BK" . $i, $row['rebill_logistic_cost'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BL" . $i, $row['rebill_logistic_org'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BM" . $i, $row['kiz'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BN" . $i, $row['storage_fee'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BO" . $i, $row['deduction'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BP" . $i, $row['acceptance'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BQ" . $i, $row['srid'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("BR" . $i, $row['report_type'], PHPExcel_Cell_DataType::TYPE_STRING);
}

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/';
$filename = date('d-m-Y') . '_reportWB.xlsx';
$objWriter->save( $dirPath . $filename );

echo 'CHECK IT HERE: ' . $dirPath . $filename;
 ?>
