<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;
global $DB;

$logFile = '/var/www/bitrix/data/www/tempusshop.ru/admin/test/mp.txt';
$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$imagesData = [];
foreach ($lines as $line) {
	$line = explode('@',$line);
	$imagesData[$line[0]] = explode(';',$line[1]);
}

$iblockId = 16;
$propertyArticle = 'CML2_ARTICLE';
$propertyImages = 'INFO_TOP';

// Включаем режим отладки
set_time_limit(0);
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

foreach ($imagesData as $article => $imageUrls) {
    echo "Обрабатываем артикул: {$article}<br>";

    // Ищем элемент по артикулу
    $res = CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => $iblockId,
            'PROPERTY_'.$propertyArticle => $article
        ],
        false,
        false,
        ['ID', 'IBLOCK_ID']
    );

    if ($element = $res->Fetch()) {

        $files = [];

        foreach ($imageUrls as $imageUrl) {
            echo "Загружаем изображение: {$imageUrl}<br>";

            $fileArray = CFile::MakeFileArray($imageUrl);

            if ($fileArray && !isset($fileArray['error'])) {
                echo "Файл успешно загружен во временную папку<br>";
								
                $fileId = CFile::SaveFile($fileArray, 'iblock');

                if ($fileId) {
                    echo "Файл сохранён в Битрикс с ID: {$fileId}<br>";
                    $files[] = ['VALUE' => CFile::MakeFileArray($fileId)];
                } else {
                    echo "Ошибка сохранения файла в Битрикс<br>";
                }
            } else {
                echo "Ошибка загрузки файла: " . ($fileArray['error'] ?? 'неизвестная ошибка') . "<br>";
            }
        }

        if (!empty($files)) {
            $result = CIBlockElement::SetPropertyValuesEx(
                $element['ID'],
                $iblockId,
                [$propertyImages => $files]
            );

            if ($result) {
                echo "Свойство успешно обновлено!<br><br>";
            } else {
                echo "Ошибка обновления свойства!<br><br>";
                // Дополнительная диагностика
                global $APPLICATION;
                if ($APPLICATION->GetException()) {
                    echo "Сообщение об ошибке: " . $APPLICATION->GetException()->GetString() . "<br>";
                }
            }
        } else {
            echo "Нет файлов для загрузки<br><br>";
        }
    } else {
        echo "Элемент с артикулом {$article} не найден!<br><br>";
    }
}

echo "Обработка завершена!";
