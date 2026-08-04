<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") ||  !CModule::IncludeModule('panel.manager')) return;
//if(!$_REQUEST["order_wb_submit"]) return;
?>
<?

error_reporting(E_ERROR);

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
/*
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => [58, 43],
    'orientation' => 'L',
    'margin_left' => 0,
    'margin_right' => 0,
    'margin_top' => 0,
    'margin_bottom' => 0,
    'margin_header' => 0,
    'margin_footer' => 0,
]);
//$mpdf = new \Mpdf\Mpdf('L', 'mm', array('58','43'), true, 'UTF-8', false);

$mpdf->WriteHTML('<h1>Hello world!</h1>');

$arOrder = explode(",", $_REQUEST["order_wb"]);
//prent($arOrder);die;
if(!$arOrder) die("нет кодов");

// add a page
$mpdf->AddPage();
$cnt = count($arOrder) - 1;
foreach($arOrder as $k => $order_wb){
	if(file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/wb/{$order_wb}.svg")){
		$mpdf->ImageSVG("/upload/wb/{$order_wb}.svg", -11, -1, 80, 42);
		//$pdf->ImageSVG("/upload/wb/{$order_wb}.svg", -10, -8, 80, 50);
	}


	if($k < $cnt)
		$mpdf->AddPage();
}

$mpdf->Output();
/*
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/classes/tcpdf/tcpdf.php');
*/
class PDF_WB extends TCPDF {

    //Page header
    public function Header() {
    }

    // Page footer
    public function Footer() {
    }
}

$arOrder = explode(",", $_REQUEST["order_wb"]);
//prent($arOrder);die;
if(!$arOrder) die("нет кодов");


//$pdf = new PDF_WB('L', 'mm', array('58','40'), true, 'UTF-8', false);
//$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, array(400, 300), true, 'UTF-8', false);
$pdf = new TCPDF('L', 'mm', array('58','43'), true, 'UTF-8', false);
//$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, array(400, 300), true, 'UTF-8', false);

// remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(0, 0, 0, 0);

// set auto page breaks false
$pdf->SetAutoPageBreak(false, 0);

// set document information

//$pdf->SetMargins(0, 0, 0);
$pdf->SetFont('dejavusans', '', 10);
//$pdf->SetHeaderMargin(0);
//$pdf->SetFooterMargin(0);

// set auto page breaks
//$pdf->SetAutoPageBreak(TRUE, 0);

// set image scale factor
//$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// add a page
$pdf->AddPage();
$cnt = count($arOrder) - 1;
foreach($arOrder as $k => $order_wb){
	if(file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/wb/{$order_wb}.svg")){
		$pdf->ImageSVG("/upload/wb/{$order_wb}.svg", -11, -1, 80, 42);
		//$pdf->ImageSVG("/upload/wb/{$order_wb}.svg", -10, -8, 80, 50);
	}


	if($k < $cnt)
		$pdf->AddPage();
}

// ---------------------------------------------------------

//Close and output PDF document
$pdf->Output();
//$pdf->Output('example_058.pdf', 'D');
