<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_implicit_flush( true );

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"] . "/vendor/autoload.php");

use Bitrix\Main\Application,
	Bitrix\Main\Loader;
use Intervention\Image\ImageManagerStatic as Image;
use Intervention\Image\ImageManager as ImageManager;

class OZONInfographGenerator
{
	private int $offset = 400;
	private float $aspectRatio = 0.61; // width/height
	private string $savePathTest = "";

	private string $logoName = "tempus_logo";
	private array $assets = [];

	private $canvasConfig = [
		'def' => [ 'x' => 900, 'y' => 1200 ],
		'min' => [ 'x' => 700, 'y' => 900 ],
	];

	private $resizeConfig = [
		'y' => [
			'y' => ['min' => 934, 'max' => 1200],
			'x' => ['min' => null, 'max' => null],
			'resize' => ['x' => null, 'y' => 1200],
			'canvas' => ['x' => null, 'y' => 934],
			'axis' => 'height'
		],
		'x' => [
			'y' => ['min' => null, 'max' => null],
			'x' => ['min' => 577, 'max' => 742],
			'resize' => ['x' => 900, 'y' => null],
			'canvas' => ['x' => 700, 'y' => null],
			'axis' => 'width'
		],
	];
	private ?DBPanel $panel = null;

	public function __construct()
	{
		$this->savePathTest = "{$_SERVER['DOCUMENT_ROOT']}/local/cron/infograph/libozon/images/%s.png";
		$this->assets = [
			'common' => "{$_SERVER['DOCUMENT_ROOT']}/local/cron/infograph/infographic_ozon/%s.png",
			'background' => "{$_SERVER['DOCUMENT_ROOT']}/local/cron/infograph/infographic_ozon/background.png",
		];
		$this->panel = new DBPanel;
	}

  public function run():void
  {
		$this->updateStatus(
			text: 'Получение товаров',
			percent: 20,
			status: 'IN_PROCESS',
			start: date('Y.m.d G:i:s')
		);
		$items = $this->getItems();
		$this->updateStatus(text: 'Генерация изображений', percent: 50);
		try{
			$this->processItems( items: $items );
		} catch( Throwable $e ){
			$this->updateStatus(
				text: 'Ошибка генерации изображения',
				percent: 100,
				status: 'COMPLETED',
				end: date('Y.m.d G:i:s')
			);
			$error = [
				'message' => $e->getMessage(),
				'line' => $e->getLine(),
			];
			file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/libozon/criticalError.txt', print_r($error, true) );
			die;
		}

		$this->updateStatus(
			text: 'Завершено',
			percent: 100,
			status: 'COMPLETE',
			end: date('Y.m.d G:i:s')
		);
  }

  private function getItems():array
  {
    $items = [];
    $arFilter = [
      'IBLOCK_ID' => CProSet::IB_CATALOG,
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
      // 'PROPERTY_CML2_ARTICLE' => ['A-158WA-1', 'LTP-V009D-7E', 'GM-110-1A', 'MTP-1183A-2A', 'LTP-V007L-9B', 'T137.207.11.091.01']
    ];
    $arSelect = ["ID", "IBLOCK_ID", "PROPERTY_MECHANISM", "PROPERTY_GLASS", "PROPERTY_WARRANTY", "PROPERTY_INFOGRAPH_BASE", "PROPERTY_INFOOZON_IMAGE"];
    $result = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );
    while ( $row = $result->GetNext() ) {
			$filePath = CFile::GetPath( $row['PROPERTY_INFOGRAPH_BASE_VALUE'] );
      $tmp = [
				'props' => [
					'logo' => $this->logoName,
					'mechanism' => array_key_first( $row['PROPERTY_MECHANISM_VALUE'] ),
					'glass' => $row['PROPERTY_GLASS_ENUM_ID'],
					'warranty' => $row['PROPERTY_WARRANTY_ENUM_ID'],
				],
				'base_code' => $row['PROPERTY_INFOGRAPH_BASE_VALUE'],
				'base' => $_SERVER['DOCUMENT_ROOT'] . CFile::GetPath( $row['PROPERTY_INFOGRAPH_BASE_VALUE'] ),
				'infograph' => $row['PROPERTY_INFOOZON_IMAGE_VALUE'] ?? '',
      ];
			if ( empty($filePath) ){
				$tmp['base_code'] = '';
				$tmp['base'] = '';
			}

			$items[ $row['ID'] ] = $tmp;
    }

    return $items;
  }

  private function processItems( array $items ):void
	{
		$skip = [];
		foreach ( $items as $id => $item )
		{
			print_r( date('G:i:s') . " Processing {$id}\n" );
			if ( empty( $item['base_code'] ) ) {
				$skip[] = $id;
				continue;
			}
			$path = $this->buildImage( $id, $item );
			if ( $path == false ) continue;

			$this->writeProperty(
				itemId: $id,
				path: $path,
				infographfileId: $item['infograph']
			);
			print_r( date('G:i:s') . "Saved for {$id}\n" );
			unlink( $path );
		}
  }

  private function buildImage( int $id, array $item ):bool|string
	{
		$canvas = Image::canvas(
			$this->canvasConfig['def']['x'],
			$this->canvasConfig['def']['y'],
			'#e6e7e2'
		);
		try{
			$this->fillWithAssets( $canvas, $item['props'] );
		}catch( Throwable $e ){
			var_dump( $e->getMessage() );
			var_dump( $id );
			var_dump( $item );
			$ar = [
				'id' => $id,
				'error' => $e->getMessage(),
			];
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/libwb/errors.txt", print_r($ar, true), FILE_APPEND);
			return false;
		}
		try{
			$image = Image::make( $item['base'] )->trim('transparent');
		}catch( Throwable $e ){
			$ar = [
				'id' => $id,
				'error' => $e->getMessage(),
			];
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/libozon/errors.txt", print_r($ar, true), FILE_APPEND);
			var_dump("Error occured with {$id}");
			return false;
		}

		$ratio = round($image->width() / $image->height(), 2);
		$key = ( $ratio >= $this->aspectRatio ) ? 'y' : 'x';
		$coef = $this->resize(
			image: $image,
			canvas: $canvas,
			params: $this->resizeConfig[$key],
			key: $key
		);

		$path = sprintf($this->savePathTest, $id);
		try{
			$canvas->insert(
				$image,
				'left',
				round($this->offset * $coef),
				0
			);
			$canvas->save( $path );
		} catch ( Throwable $e ){
			var_dump( $e->getMessage() );
			return false;
		}

		return $path;
  }

  private function resize( Intervention\Image\Image $image, Intervention\Image\Image $canvas, array $params, string $key ):float
	{
		$axis = $image->{$params['axis']}();

		if ( $axis < $params[$key]['min'] ){
			$coef = 0.78;
			$canvas->resize( $params['canvas']['x'], $params['canvas']['y'], function ( $constraint ){
					$constraint->aspectRatio();
			});
			$image->resize( $params['x']['min'], $params['y']['min'], function ( $constraint ){
					$constraint->aspectRatio();
			});
		}

		if ( $axis >= $params[$key]['min'] && $axis < $params[$key]['max'] ){
			$coef = floor( ($axis / $params[$key]['max']) * 100 ) / 100;
			$params['resize'][$key] = round($params['resize'][$key] * $coef);
			$canvas->resize( $params['resize']['x'], $params['resize']['y'], function ( $constraint ){
					$constraint->aspectRatio();
			});
		}

		if ( $axis >= $params[$key]['max'] ){
			$coef = 1;
			$image->resize( $params['x']['max'], $params['y']['max'], function ( $constraint ){
					$constraint->aspectRatio();
			});
		}

		return $coef;
	}

	private function fillWithAssets( Intervention\Image\Image $canvas, array $props ):void
	{
		foreach ( $props as $name => $prop ){
			if ( empty($prop) ) continue;
			$assetPath = sprintf($this->assets['common'], $prop);
			$image = Image::make( $assetPath );
			$canvas->insert($image, 'top-left', 0, 0);
		}
	}

	private function writeProperty( int $itemId, string $path, string $infographfileId ):void
	{
		$file = new CFile();
		$fileId_new = $file->SaveFile(
			CFile::MakeFileArray($path),
			'info_graph_image'
		);

		if ( empty($fileId_new) ){
			print_r("Cannot save file {$path}\n");
			return;
		}
		if ( !empty($infographfileId) ){
			CFile::Delete( $infographfileId );
		}
		file_put_contents(
			"/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/libozon/completed.txt",
			$itemId . PHP_EOL,
			FILE_APPEND
		);
		CIBlockElement::SetPropertyValueCode($itemId, "INFOOZON_IMAGE", array('VALUE' => $fileId_new) );
	}

	private function updateStatus( $text, $percent, $status = false, $start = false, $end = false ):void
	{
		$tmp = [
			'status' => $status,
			'status_text' => $text,
			'percent' => $percent,
			'time_start' => $start,
			'time_end' => $end,
		];

		$where[] = [ 'column' => 'code', 'operator' => '=', 'value' => 'newInfoGraph' ];

		$update = array_filter(
			array: $tmp,
			callback: fn($val) => ($val !== false)
		);

		$this->panel->update( 'ozon_agents', $update, $where );
	}
}

(new OZONInfographGenerator)->run();
 ?>
