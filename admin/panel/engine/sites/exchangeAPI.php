<?php

set_time_limit(0);
ini_set('memory_limit', '1024M');
die;
$config = [
    'source' => [
        'doc_root' => '/var/www/bitrix/data/www/tempusshop.ru/',
        'iblock_id' => 16,
        'element_id' => 121290
    ],
    'target' => [
        'doc_root' => '/var/www/bitrix/data/www/tempus.ru/',
        'iblock_id' => 12
    ]
];


try {
    $elementData = isolatedCall(
        $config['source']['doc_root'],
        'getElementData',
        [$config['source']['iblock_id'], $config['source']['element_id']]
    );

    $result = isolatedCall(
        $config['target']['doc_root'],
        'createElement',
        [$config['target']['iblock_id'], $elementData]
    );

    print_r($result);

    if ($result['success']) {
        echo "Элемент создан. ID: ".$result['id']."\n";
    }
} catch (Exception $e) {
    echo "Ошибка: ".$e->getMessage()."\n";
    exit(1);
}

function isolatedCall($docRoot, $function, $args) {
    $tempFile = tempnam(sys_get_temp_dir(), 'bitrix_');
    $code = '<?php
        $_SERVER["DOCUMENT_ROOT"] = "'.str_replace('"', '\"', $docRoot).'";
        define("BX_CRONTAB", true);
        require_once $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php";

        '.getFunctionCode().'

        try {
            $result = call_user_func_array("'.$function.'", '.var_export($args, true).');
            file_put_contents("'.$tempFile.'", json_encode($result));
        } catch (Exception $e) {
            file_put_contents("'.$tempFile.'", json_encode(["error" => $e->getMessage()]));
        }
    ';

    file_put_contents($tempFile.'.php', $code);
    exec('php -d memory_limit=1024M '.$tempFile.'.php 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        unlink($tempFile);
        unlink($tempFile.'.php');
        throw new Exception("Ошибка выполнения: ".implode("\n", $output));
    }

    $result = json_decode(file_get_contents($tempFile), true);
    unlink($tempFile);
    unlink($tempFile.'.php');

    if (isset($result['error'])) {
        throw new Exception($result['error']);
    }

    return $result;
}

function getFunctionCode() {
    return '
        function getElementData($iblockId, $elementId) {
            if (!CModule::IncludeModule("iblock")) {
                throw new Exception("Модуль iblock не доступен");
            }

            $element = CIBlockElement::GetByID($elementId)->Fetch();
            if (!$element) throw new Exception("Элемент не найден");

            // Получаем свойства
            $properties = [];
            $rsProps = CIBlockElement::GetProperty($iblockId, $elementId);
            while ($prop = $rsProps->Fetch()) {
                $properties[$prop["CODE"]] = $prop;
            }

            // Получаем разделы
            $sections = [];
            $rsSections = CIBlockElement::GetElementGroups($elementId, true);
            while ($section = $rsSections->Fetch()) {
                $sections[] = $section["ID"];
            }

            // Обрабатываем файлы
            $files = [];
            foreach (["PREVIEW_PICTURE", "DETAIL_PICTURE"] as $field) {
                if (!empty($element[$field])) {
                    $file = CFile::GetFileArray($element[$field]);
                    if ($file) {
                        $files[$field] = [
                            "path" => $_SERVER["DOCUMENT_ROOT"].$file["SRC"],
                            "name" => $file["ORIGINAL_NAME"]
                        ];
                    }
                }
            }

            return [
                "fields" => $element,
                "properties" => $properties,
                "sections" => $sections,
                "files" => $files
            ];
        }

        function createElement($iblockId, $elementData) {
            if (!CModule::IncludeModule("iblock")) {
                throw new Exception("Модуль iblock не доступен");
            }

            $el = new CIBlockElement;

            $code = $elementData["fields"]["CODE"];
            $code .= "_importTest";
            $fields = [
                "IBLOCK_ID" => $iblockId,
                "NAME" => $elementData["fields"]["NAME"],
                "CODE" => $code,
                "XML_ID" => $elementData["fields"]["XML_ID"],
                "ACTIVE" => $elementData["fields"]["ACTIVE"],
                "PREVIEW_TEXT" => $elementData["fields"]["PREVIEW_TEXT"],
                "DETAIL_TEXT" => $elementData["fields"]["DETAIL_TEXT"]
            ];

            // Копируем файлы
            foreach ($elementData["files"] as $field => $file) {
                if (file_exists($file["path"])) {
                    $fields[$field] = [
                        "name" => $file["name"],
                        "tmp_name" => $file["path"],
                        "type" => mime_content_type($file["path"])
                    ];
                }
            }


            $newId = $el->Add($fields);
            if (!$newId) throw new Exception($el->LAST_ERROR);
            /*
            // Устанавливаем свойства
            $props = [];
            foreach ($elementData["properties"] as $code => $prop) {
                $props[$code] = $prop["VALUE"];
            }
            CIBlockElement::SetPropertyValuesEx($newId, $iblockId, $props);

            // Привязываем к разделам
            if (!empty($elementData["sections"])) {
                CIBlockElement::SetElementSection($newId, $elementData["sections"]);
            }*/

            return ["success" => true, "id" => $newId];
        }

        function deleteElement($iblockId, $elementId) {
            if (!CModule::IncludeModule("iblock")) {
                throw new Exception("Модуль iblock не доступен");
            }
            return CIBlockElement::Delete($elementId);
        }
    ';
}
