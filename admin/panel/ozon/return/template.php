<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") ||  !CModule::IncludeModule('panel.manager')) return;
//if(!$_REQUEST["order_wb_submit"]) return;
?>
<?

error_reporting(E_ERROR);

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$inputFileName = $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/ozon/return/template_files/main.xlsx';
$spreadsheet = IOFactory::load($inputFileName);

$sheet = $spreadsheet->getActiveSheet();

$data = [
    ['Иван', 'Иванов', 'ivan@example.com'],
    ['Петр', 'Петров', 'petr@example.com'],
];

$data = json_decode($_REQUEST["items"], true);

//$sheet->setCellValue('A2', $_REQUEST["warehouse_id"]);
$sheet->setCellValueExplicit('A2', $_REQUEST["warehouse_id"], DataType::TYPE_STRING);
$sheet->setCellValue('B2', $_REQUEST["warehouse_name"]);

$sheet->getStyle('G')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
	
$row = 6;
foreach ($data as $item) {
	$sheet->setCellValue('A'.$row, $item["SKU"]);
	$sheet->setCellValue('B'.$row, $item["NAME"]);
	$sheet->setCellValue('C'.$row, $item["ARTICLE"]);
	$sheet->setCellValue('D'.$row, $item["STOCK_COUNT"]);
	$sheet->setCellValue('E'.$row, $item["STOCK_COUNT"]);
	//$sheet->setCellValue('F'.$row, $item["SKU"]);
	//$sheet->setCellValue('G'.$row, $item["BARCODE"]);
	$sheet->setCellValueExplicit('G'.$row, $item["BARCODE"], DataType::TYPE_STRING);
	//$sheet->setCellValue('H'.$row, $item["BARCODE"]);
	$sheet->setCellValue('I'.$row, "Доступно к продаже");
	
    $cellRange = 'A'.$row.':I'.$row;
    $styleArray = [
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];
	$sheet->getStyle($cellRange)->applyFromArray($styleArray);
	
	$row++;
}

//$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
//$writer->save($_SERVER['DOCUMENT_ROOT'] . '/admin/panel/ozon/return/template_files/main2.xlsx');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="modified_template.xlsx"');
header('Cache-Control: max-age=0');

// 5. Отправляем файл в выходной поток
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
