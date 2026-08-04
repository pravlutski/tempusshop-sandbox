<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");?>
<?php
global $USER;
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");

if(!CModule::IncludeModule("panel.manager"))
	die("Непредвиденная ошибка");

$arResult = array();
foreach($_POST["item"] as $arItem){
	$price = (float) str_replace(array(" ", ","), array("", "."), $arItem["price"]);
	$price_taxi = (float) str_replace(array(" ", ","), array("", "."), $arItem["price_taxi"]);
	if(strlen($arItem["name"]) > 0 && ($price > 0 || $price_taxi > 0)){
		$arResult["ITEMS"][] = array(
			"name" => $arItem["name"],
			"price" => $price,
			"price_taxi" => $price_taxi,
			"sum" => $price - $price_taxi,
		);
	}
}
foreach($_POST["item_return"] as $arItem){
	$price = (float) str_replace(array(" ", ","), array("", "."), $arItem["price"]);
	if(strlen($arItem["name"]) > 0 && $price > 0){
		$arResult["ITEMS_RETURN"][] = array(
			"name" => $arItem["name"],
			"price" => $price,
		);
	}
}

if(count($arResult["ITEMS"]) <= 0 && count($arResult["ITEMS_RETURN"]) <= 0)
	die("Нет данных для вывода");
$arResult["PRICE_ALL"] = $arResult["PRICE_TAXI_ALL"] = $arResult["SUM_ALL"] = 0;
foreach($arResult["ITEMS"] as $arItem){
	$arResult["PRICE_ALL"] += $arItem["price"];
	$arResult["PRICE_TAXI_ALL"] += $arItem["price_taxi"];
	$arResult["SUM_ALL"] += $arItem["sum"];
}
//prent($arResult);
define("PDF_FONT_NAME_MAIN", "dejavuserifcondensed");
define("PDF_FONT_NAME_DATA", "dejavuserifcondensed");
define("PDF_FONT_SIZE_MAIN", 8);

//define("K_PATH_IMAGES", $_SERVER["DOCUMENT_ROOT"] . "/images/");
//define("PDF_HEADER_LOGO", "pdf_logo.png");
//define("PDF_HEADER_LOGO_WIDTH", 50);
//define("PDF_HEADER_TITLE", "");
//define("PDF_HEADER_STRING", "ИП Рудак Г.Ю. 2013-2017. тел. 8 (029) 344-99-66, 8 (033) 354-99-66 Пн-Вс 10:00 до 20:00");
require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/tcpdf/tcpdf.php');

// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF {
	public $USER_NAME;
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

$html = "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
<tr>
<td width=\"80%\"><br><br><span style=\"text-align:left;font-size: 10px;line-height:12px;\">".date("d") . ' ' . getNameMonth(date("n")) . ' ' . date("Y")."</span></td>
<td width=\"20%\" style=\"text-align: left;\"><span style=\"text-align:rigth;font-size: 10px;line-height:12px;\">Приложение №2 <br>к договору №12 <br>от «23» июня 2014 г.</span></td>
</tr>
</table>";

$html .= "<h1 style=\"text-align:center;font-size: 16px;\">АКТ<br><span style=\"text-align:center;font-size: 14px;\">приема­передачи вырученных наличных денежных средств</span></h1>
<p style=\"text-align:justify;font-size: 12px;line-height:14px;\">Настоящим актом подтверждается факт передачи вырученных наличных денежных
средств от подрядчика ".$_POST["selcourier"].", работающего по договору подряда № 11 от 23 июня 2014г. уполномоченным представителем Заказчика в размере <i>".num2str($arResult["PRICE_ALL"])."</i></p>
<p style=\"text-align:left;font-size: 12px;line-height:14px;\">Расчет суммы:</p>
<table border=\"1\" cellpadding=\"5\" cellspacing=\"0\" style=\"width: 100%;\">
<thead><tr style=\"font-size: 9px;\">
<th style=\"text-align:center;width: 5%;\">№</th>
<th style=\"text-align:center;width: 35%;\">Наименование <br>услуги</th>
<th style=\"text-align:center;width: 20%;\">Вырученные ДС</th>
<th style=\"text-align:center;width: 20%;\">Стоимость услуг <br>подрядчика</th>
<th style=\"text-align:center;width: 20%;\">Сумма</th>
</tr></thead><tbody>";

$i = 1;

foreach($arResult["ITEMS"] as $arItem){
	$html .= "<tr>
				<td style=\"text-align:center;width: 5%;\">{$i}</td>
				<td style=\"text-align:center;width: 35%;\">{$arItem["name"]}</td>
				<td style=\"text-align:center;width: 20%;\">".number_format($arItem["price"], 2, '.', ' ')."</td>
				<td style=\"text-align:center;width: 20%;\">".number_format($arItem["price_taxi"], 2, '.', ' ')."</td>
				<td style=\"text-align:center;width: 20%;\">".number_format($arItem["sum"], 2, '.', ' ')."</td>
			</tr>";
	$i++;
}
$html .= "<tr>
				<td align=\"left\" colspan=\"2\">Итого:</td>
				<td align=\"center\">".number_format($arResult["PRICE_ALL"], 2, '.', ' ')."</td>
				<td align=\"center\">".number_format($arResult["PRICE_TAXI_ALL"], 2, '.', ' ')."</td>
				<td align=\"center\">".number_format($arResult["SUM_ALL"], 2, '.', ' ')."</td>
			</tr>
		</tbody>
	</table>";
if(count($arResult["ITEMS_RETURN"]) > 0){
	$html .= "<p style=\"text-align:center;font-size: 12px;line-height:14px;\">Возврат товара заказчику</p>";
	$html .= "<table border=\"1\" cellpadding=\"5\" cellspacing=\"0\" style=\"width: 100%;\">
	<thead><tr style=\"font-size: 9px;\">
	<th style=\"text-align:center;width: 5%;\">№</th>
	<th style=\"text-align:center;width: 45%;\">Наименование</th>
	<th style=\"text-align:center;width: 10%;\">Количество</th>
	<th style=\"text-align:center;width: 20%;\">Цена, включая <br>НДС</th>
	<th style=\"text-align:center;width: 20%;\">Сумма,<br>включая НДС</th>
	</tr></thead><tbody>";
	$i = 1;
	$sum = 0;
	foreach($arResult["ITEMS_RETURN"] as $arItem){
		$html .= "<tr>
					<td style=\"text-align:center;width: 5%;\">{$i}</td>
					<td style=\"text-align:center;width: 45%;\">{$arItem["name"]}</td>
					<td style=\"text-align:center;width: 10%;\">1</td>
					<td style=\"text-align:center;width: 20%;\">".number_format($arItem["price"], 2, '.', ' ')."</td>
					<td style=\"text-align:center;width: 20%;\">".number_format($arItem["price"], 2, '.', ' ')."</td>
				</tr>";
		$i++;
		$sum += $arItem["price"];
	}
	$html .= "<tr>
					<td align=\"left\" colspan=\"3\">Итого:</td>
					<td align=\"center\">".number_format($sum, 2, '.', ' ')."</td>
					<td align=\"center\">".number_format($sum, 2, '.', ' ')."</td>
				</tr>
			</tbody>
		</table>";
}
$html .= '<br><br><table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
<td width="50%">Денежные средства передал</td>
<td width="50%" style="text-align: left;">Денежные средства принял
</td>
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
$pdf->lastPage();

$pdf->setCellHeightRatio(1.1);

//Close and output PDF document
$pdf->Output('example_006.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>