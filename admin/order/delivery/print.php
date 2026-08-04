<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");?>
<?php
global $USER;
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");

if(!CModule::IncludeModule("panel.manager") || count($_REQUEST["order"]) <= 0 || count($_REQUEST["basket"]) <= 0)
	return false;
//_POST
$order = new OrderService;
$arOrder = $order->getOrder(array(), array("ID" => $_REQUEST["order"]));
	//prent($_REQUEST["basket"]);
if(is_array($arOrder) && count($arOrder) <= 0) return;
foreach($arOrder as $key => $order){
	if($order["STATUS_ID"] != "CR")
		OrderService::setStatusOrderD7($order["ID"], "CR");
}
//prent($arOrder);die;
define("PDF_FONT_NAME_MAIN", "dejavuserifcondensed");
define("PDF_FONT_NAME_DATA", "dejavuserifcondensed");
define("PDF_FONT_SIZE_MAIN", 8);
//prent($arOrder);
//define("K_PATH_IMAGES", $_SERVER["DOCUMENT_ROOT"] . "/images/");
//define("PDF_HEADER_LOGO", "pdf_logo.png");
//define("PDF_HEADER_LOGO_WIDTH", 50);
//define("PDF_HEADER_TITLE", "");
//define("PDF_HEADER_STRING", "ИП Рудак Г.Ю. 2013-2017. тел. 8 (029) 344-99-66, 8 (033) 354-99-66 Пн-Вс 10:00 до 20:00");
require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/tcpdf/tcpdf.php');

// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF {
	public $USER_NAME;
/*	public function Header() {
		if (count($this->pages) === 1) { // Do this only on the first page
			$html .= '<p>Your header here</p>';
		}

		$this->writeHTML($html, true, false, false, false, '');
	}*/
    // Page footer
	public function Footer() {
		$cur_y = $this->y;
		$this->SetTextColorArray($this->footer_text_color);
		//set style for cell border
		$line_width = (0.85 / $this->k);
		$this->SetLineStyle(array('width' => $line_width, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $this->footer_line_color));
		//print document barcode
		$barcode = $this->getBarcode();
		if (!empty($barcode)) {
			$this->Ln($line_width);
			$barcode_width = round(($this->w - $this->original_lMargin - $this->original_rMargin) / 3);
			$style = array(
				'position' => $this->rtl?'R':'L',
				'align' => $this->rtl?'R':'L',
				'stretch' => false,
				'fitwidth' => true,
				'cellfitalign' => '',
				'border' => false,
				'padding' => 0,
				'fgcolor' => array(0,0,0),
				'bgcolor' => false,
				'text' => false
			);
			$this->write1DBarcode($barcode, 'C128', '', $cur_y + $line_width, '', (($this->footer_margin / 3) - $line_width), 0.3, $style, '');
		}
		$w_page = isset($this->l['w_page']) ? $this->l['w_page'].' ' : '';
		if (empty($this->pagegroups)) {
			$pagenumtxt = $w_page.$this->getAliasNumPage().' / '.$this->getAliasNbPages();
		} else {
			$pagenumtxt = $w_page.$this->getPageNumGroupAlias().' / '.$this->getPageGroupAlias();
		}
		//$pagenumtxt = $this->USER_NAME." - ".$pagenumtxt;
		$this->SetY($cur_y);
		//Print page number
		if ($this->getRTL()) {
			$this->SetX($this->original_rMargin);
			$this->Cell(0, 0, $pagenumtxt, 'T', 0, 'L');

		} else {
			$this->SetX($this->original_lMargin);
			$this->Cell(0, 0, $this->getAliasRightShift().$pagenumtxt, 'T', 0, 'R');
			$this->SetX(10);
			$this->Cell(0, 0, $this->USER_NAME." ".date("d-m-Y H:i"), 'T', 0, 'L');
		}
	}
}

// create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->USER_NAME = $USER->GetLogin();
$pdf->SetPrintHeader(false);
//$pdf->setHeaderData('',0,'','',array(0,0,0), array(255,255,255) );
// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Tempus');
$pdf->SetTitle('Доставка');
$pdf->SetSubject('Гарантия');
$pdf->SetKeywords('Гарантия');


// set default header data
//$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
//$pdf->setCellPaddings(0,0,10,0);
// set header and footer fonts
//$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));


// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, 8, PDF_MARGIN_RIGHT);
//$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);


// set auto page breaks
//$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/tcpdf/lang/rus.php')) {
    require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/tcpdf/lang/rus.php');

    $pdf->setLanguageArray($l);
}

// set font
$pdf->SetFont('dejavuserifcondensed', '', 10);
$pdf->setCellHeightRatio(1.6);
// add a page
$pdf->AddPage();
// create some HTML content
//$html = "<p><span style=\"text-align:rigth;font-size: 10px;line-height:12px;margin:0;padding:0;\">Приложение №1 <br>к договору №11 <br>от «23» июня 2014 г.</span>";
//$html .= "<span style=\"text-align:left;font-size: 10px;line-height:10px;margin:0;padding:0;\">".date("d-m-Y h:i")."</span></p>";

$html = "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
<tr>
<td width=\"80%\"><br><br><span style=\"text-align:left;font-size: 10px;line-height:12px;\">".date("d") . ' ' . getNameMonth(date("n")) . ' ' . date("Y")."</span></td>
<td width=\"20%\" style=\"text-align: left;\"><span style=\"text-align:rigth;font-size: 10px;line-height:12px;\">Приложение №1 <br>к договору №11 <br>от «23» июня 2014 г.</span></td>
</tr>
</table>";

$html .= "<h1 style=\"text-align:center;font-size: 16px;\">АКТ<br><span style=\"text-align:center;font-size: 14px;\">приема-передачи товара</span></h1>
<p style=\"text-align:justify;font-size: 10px;line-height:14px;\">ООО «Роялтайм», в лице директора Шевцова Андрея Анатольевича, действующего на основании устава, именуемое в дальнейшем Заказчик, с одной стороны и ".$_POST["selcourier"].", именуемый в дальнейшем Подрядчик, с другой стороны (в дальнейшем вместе именуемые «Стороны» и по отдельности «Сторона»), составили настоящий Акт о нижеследующем: </p>
<p style=\"text-align:justify;font-size: 10px;line-height:14px;\">1. В соответствии с п. 1.2.1 Договора между Сторонами № 11 от «23» июня 2014 года Заказчик передает, а Подрядчик принимает Товар для транспортировки и реализации следующего ассортимента и количества:</p><br>
<table border=\"1\" cellpadding=\"5\" cellspacing=\"0\" style=\"width: 100%;\">
<thead><tr style=\"font-size: 9px;\">
<th style=\"text-align:center;width: 5%;\">№</th>
<th style=\"text-align:center;width: 55%;\">Наименование</th>
<th style=\"text-align:center;width: 10%;\">Кол-во</th>
<th style=\"text-align:center;width: 15%;\">Цена, включая НДС</th>
<th style=\"text-align:center;width: 15%;\">Сумма, включая НДС</th>
</tr></thead><tbody>";

$i = 1;
$total = $cnt = 0;
//prent($arOrder);
foreach($arOrder as $arItem){
	foreach($arItem["BASKET"] as $key => $arBasket){
		if(in_array($arBasket["ID"], $_REQUEST["basket"])){
			$html .= "<tr>
						<td style=\"text-align:center;width: 5%;\">{$i}</td>
						<td style=\"text-align:left;width: 55%;\">{$arBasket["NAME"]}</td>
						<td style=\"text-align:center;width: 10%;\">{$arBasket["QUANTITY"]}</td>
						<td style=\"text-align:left;width: 15%;\">".number_format($arBasket["PRICE"], 2, ',', ' ')."</td>
						<td style=\"text-align:left;width: 15%;\">".number_format($arBasket["PRICE"] * $arBasket["QUANTITY"], 2, ',', ' ')."</td>
					</tr>";
			$i++;
			$total += round($arBasket["PRICE"], 2);
			$cnt += $arBasket["QUANTITY"];
		}
	}
}
$html .= "<tr>
				<td align=\"left\" colspan=\"2\">Итого:</td>
				<td align=\"center\">{$cnt}</td>
				<td align=\"center\"></td>
				<td align=\"left\">".number_format($total, 2, ',', ' ')."</td>
			</tr>
		</tbody>
	</table>";
$html .= "<p style=\"text-align:justify;font-size: 10px;line-height:14px;\">Стоимость Товара поставленного в соответствии с условиями Договора составляет ".number_format($total, 2, '.', ' ')." руб. (".num2str($total).") с учетом НДС.</p>";
$html .= "<p style=\"text-align:justify;font-size: 10px;line-height:14px;\">2. Настоящий Акт составлен в двух экземплярах, имеющих равную юридическую силу, по одному экземпляру для каждой из Сторон и является неотъемлемой частью Договора между Сторонами.</p>";

$html .= '<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
<td width="50%">Уполномоченный представитель заказчика</td>
<td width="50%" style="text-align: left;">Подрядчик</td>
</tr>
<tr>
<td width="50%"></td>
<td width="50%" style="text-align: left;"></td>
</tr>
<tr>
<td width="50%">______________________________ '.$_POST["selagent"].'</td>
<td width="50%" style="text-align: left;">____________________________ '.$_POST["selcourier"].'</td>
</tr>
</table>';

// output the HTML content
//$pdf->writeHTML($html, true, false, true, false, '');
$pdf->writeHTML($html, true, 0, true, true);
// reset pointer to the last page

//Close and output PDF document
$pdf->Output('example_007.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>
