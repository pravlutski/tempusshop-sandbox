<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"] . "/vendor/autoload.php");
require("AspectRatioProcessor.php");

use Bitrix\Main\Application,
	Bitrix\Main\Loader;
use Intervention\Image\ImageManagerStatic as Image;
use Intervention\Image\ImageManager as ImageManager;

class WBInfoTest
{
  public function run():void
  {
    $ar = $this->getItems();
    $this->buildImage( $ar );
    $this->buildImage2( $ar );
  }

  private function getItems():array
  {
    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => ['A-158WA-1', 'LTP-V009D-7E', 'GA-2100-1A1', 'LTP-V001L-7B', 'A-159W-N1', 'GM-110-1A', 'MTP-1183A-2A', 'LTP-V007L-9B'],
    ];

    $arSelect = ["ID", "IBLOCK_ID", 'PROPERTY_INFOGRAPH_BASE'];

    $res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
    $items = [];
    while ( $row = $res->GetNext() ){
      if ( empty($row['PROPERTY_INFOGRAPH_BASE_VALUE']) ) continue;
      $items[] = [
        "ID" => $row['ID'],
        "BASE" => $_SERVER['DOCUMENT_ROOT'] . CFile::GetPath( $row['PROPERTY_INFOGRAPH_BASE_VALUE'] ),
      ];
    }

    // var_dump($items);
    // die;
    return $items;
  }

  private function buildImage( array $items ):void
  {
    $arp = new AspectRatioProcessor(
      im: new ImageManager(['driver' => 'gd'])
    );
    foreach ($items as $key => $item) {
      var_dump($item['ID']);
      // $canvas = Image::canvas(900, 1200, '#34ebae');
      $image = Image::make( $item['BASE'] )->trim();
      $canvas = $arp->process( $item['BASE'] );

      // $canvasWidth = 900;
      // $canvasHeight = 1200;
      $image->resize($canvas->width(), $canvas->height(), function ($constraint) {
        $constraint->aspectRatio(); // Сохраняем пропорции
      });
      // $leftMargin = ($canvasWidth - $image->width()с) / 2;
      $leftOffset = floor($canvas->width() * 0.55); // 60% ширины холста
      $coef = $this->calculateOffsetCoefficientFormula( $image->width() );
      $leftOffset = floor($canvas->width() * $coef);
      var_dump( $image->width() );
      var_dump( $coef );

      $canvas->insert($image, 'left', $leftOffset, 0);

      $canvas->save( '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/images/'.$item['ID'].'.png' );
      // die;
    }
  }

  private function buildImage2( array $items ):void
  {
    $arp = new AspectRatioProcessor(
      im: new ImageManager(['driver' => 'gd'])
    );
    foreach ($items as $key => $item) {
      var_dump($item['ID']);
      $canvas = Image::canvas(900, 1200, '#34ebae');
      // $image = Image::make( $item['BASE'] );
      $image = $this->smama(
        Image::make( $item['BASE'] )->trim('transparent')
      );
      // var_dump( $image->width() );
      // var_dump( $coef );

      $canvas->insert($image, 'left', 400, 0);

      $canvas->save( '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/images/'.$item['ID'].'_aa.png' );
      // die;
    }
  }

  private function calculateOffsetCoefficientFormula(int $width): float
  {
    // Базовая формула: коэффициент = a / (b * width + c) + d
    // Подобрано эмпирически для вашего случая

    // Вариант 1: Обратная пропорциональность
    $coefficient = 0.23 + (100 / $width);

    // Вариант 2: Логарифмическая зависимость
    // $coefficient = 1.2 - 0.1 * log($width);

    // Вариант 3: Степенная функция
    // $coefficient = 5.5 * pow($width, -0.3);

    return min(0.65, max(0.45, round($coefficient, 3)));
  }

  private function smama( $image )
  {
    $coef = $image->width() / $image->height();
		var_dump( $coef );
    if ( round($coef, 2) >= 0.66 )
    {
      $image->resize( null, 1200, function ($constraint) {
        $constraint->aspectRatio(); // Сохраняем пропорции
      });
    } else{
      $image->resize( 742, null, function ($constraint) {
        $constraint->aspectRatio(); // Сохраняем пропорции
      });
    }

    return $image;
  }
}


(new WBInfoTest)->run();

?>
