<?php

/**
 * @global $APPLICATION
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

if(!CModule::IncludeModule('panel.manager'))
    return;

opcache_reset();

CModule::IncludeModule("iblock");

global $DB; // Подключаем глобальный объект базы данных

$tag_id = $_GET["id"]; // Пример ID тега, для которого хотим получить информацию
$tag = [];

$strSql = "SELECT * FROM `ci_configurator_tags` WHERE `id` = " . intval($tag_id);
$res = $DB->Query($strSql);
if ($row = $res->Fetch()) {
    $tag = $row;
}

$APPLICATION->SetTitle('Редактирование тега | Конфигуратор');

?>
    <link href="/admin/modules/configurator/style.css" rel="stylesheet">
    <h1 class="page-header">Редактирование тега: <?= $tag['tag_name'] ?></h1>
    <div class="col-sm-12 panel panel-default create-block-tags">
        <div class="col-sm-12">
            <h3 class="page-header">Основные параметры</h3>
        </div>
        <form action="process_edit.php" method="post">
            <input type="hidden" name="tag_id" value="<?= $tag_id ?>"> <!-- Передаем ID тега для обработки -->
            <div class="col-sm-12 mb-15">
                <label>Дата и время создания:</label>
                <span><?=$tag["created_at"]?></span>
            </div>
            <div class="col-sm-12 mb-15">
                <label>Дата и время последнего обновления:</label>
                <span><?=$tag["updated_at"]?></span>
            </div>
            <div class="col-sm-12 mb-15">
                <label for="sort_order">Популярность запроса:</label>
                <span><?=$tag['sort_order'];?></span>
            </div>
            <div class="col-sm-12 mb-15">
                <label for="sort_order">Ресурс:</label>
                <span><?=$tag['resource'];?></span>
            </div>
            <div class="col-sm-12 mb-15">
                <label for="tag_name">Название тега</label>
                <input type="text" name="tag_name" id="tag_name" class="form-control" value="<?= $tag['tag_name'] ?>" required>
            </div>
            <div class="col-sm-12 mb-15">
                <label for="sections">Разделы</label>
                <select name="selected_sections[]" id="sections" class="form-control" multiple>
                    <?php
                        $selected_sections = json_decode($tag['sections_json']);

                        if(!is_array($selected_sections)) {
                            $selected_sections = [];
                        }

                        $sections = CIBlockSection::GetList(Array("SORT" => "ASC"), Array("IBLOCK_ID" => 16, "DEPTH_LEVEL" => 3), false, ["IBLOCK_ID", "ID", "NAME"]);

                        while ($section = $sections->GetNext()) {
                            $selected = (in_array($section['ID'], $selected_sections)) ? "selected" : "";
                            echo '<option value="' . $section['ID'] . '" ' . $selected . '>' . $section['NAME'] . '</option>';
                        }
                    ?>
                </select>
            </div>
            <div class="col-sm-12 mb-15">
                <div id="properties_wrapper" class="mb-15">
                    <label for="property">Свойства</label>
                    <?php if($tag['properties_json']):?>
                        <?php
                        $tag_properties = json_decode($tag['properties_json']); // Распарсить JSON свойств тега ?>
                        <?php foreach ($tag_properties as $index => $property): ?>
                            <div class="property-input d-flex" id="property-input-<?= $index ?>">
                                <select name="selected_properties[]" class="property-id form-control select_w" id="property">
                                    <?php
                                    $properties = CIBlockProperty::GetList(["SORT" => "ASC"], ["ACTIVE" => "Y", "IBLOCK_ID" => 16]);
                                    while ($prop_fields = $properties->GetNext()) {
                                        $selected = ($property[0] == $prop_fields['CODE']) ? "selected" : "";
                                        $data_type = isset($prop_fields["LINK_IBLOCK_ID"]) ? ' data-iblock-link="'.$prop_fields["LINK_IBLOCK_ID"].'"' : '';
                                        echo '<option data-type="' . $prop_fields['PROPERTY_TYPE'] . '"'. $data_type .' value="' . $prop_fields['CODE'] . '" ' . $selected . '>' . $prop_fields['NAME'] . '</option>';
                                    }
                                    ?>
                                </select>
                                <span class="property-value">
                                    <?php
                                    $value = $property[1];

                                    if (is_numeric($value)) {
                                        echo '<input type="text" name="property_value[]" value="' . $value . '" placeholder="Значение" class="form-control">';
                                    } else {
                                        echo '<select name="property_value[]" class="form-control">';
                                        echo '<option value="' . $value . '" selected>' . $value . '</option>';
                                        echo '</select>';
                                    }
                                    ?>
                                </span>
                                <button class="btn btn-danger delete-property-btn">Удалить</button> <!-- Кнопка "Удалить" -->
                            </div>
                        <?php endforeach; ?>
                    <?php else:?>
                        <div class="property-input d-flex" id="property-input-0">
                            <select name="selected_properties[]" class="property-id form-control select_w" id="property">
                                <?php
                                    $properties = CIBlockProperty::GetList(["SORT" => "ASC"], ["ACTIVE" => "Y", "IBLOCK_ID" => 16]);

                                    while ($prop_fields = $properties->GetNext()) {

                                        $data_type = isset($prop_fields["LINK_IBLOCK_ID"]) ? ' data-iblock-link="'.$prop_fields["LINK_IBLOCK_ID"].'"' : '';

                                        echo '<option data-type="' . $prop_fields['PROPERTY_TYPE'] . '"'. $data_type .' value="' . $prop_fields['CODE'] . '">' . $prop_fields['NAME'] . '</option>';
                                    }
                                ?>
                            </select>
                            <span class="property-value">
                                <input type="text" name="property_value[]" placeholder="Значение" class="form-control">
                            </span>
                        </div>
                    <?php endif;?>
                </div>
                <button type="button" id="add_property_btn" class="btn btn-primary set_period">
                    Добавить еще свойство
                </button>
            </div>
            <hr>
            <div class="col-sm-12 mt-30">
                <input type="submit" value="Сохранить" class="btn btn-primary set_period">
                <a href="https://tempusshop.ru/admin/modules/configurator/" class="btn btn-primary">
                    Отменить
                </a>
            </div>
        </form>
    </div>
    <script>
        $(document).ready(function() {
            // Загрузка значений свойств при открытии страницы
            $('.property-id').each(function() {
                let propertySelect = $(this);
                let propertyType = propertySelect.find('option:selected').attr('data-type');
                let propertyValue = propertySelect.val();
                let propertyInputWrapper = propertySelect.next('.property-value');
                if (propertyType === 'L') {
                    let propertyId = propertySelect.val();
                    let propertyValueId = propertyInputWrapper.find('input[name="property_value[]"]').val(); // текущий выбранный ID

                    $.ajax({
                        method: "POST",
                        url: "get_property_values.php",
                        data: { propertyId: propertyId, selectedValueId: propertyValueId }
                    }).done(function(msg) {
                        let data = JSON.parse(msg);
                        let selectBox = $('<select class="form-control" name="property_value[]"></select>');

                        $.each(data, function(index, item) {
                            let selected = '';

                            if (item.ID == propertyValueId) {
                                selected = ' selected';
                            }

                            selectBox.append('<option value="' + item.ID + '"' + selected + '>' + item.VALUE + '</option>');
                        });

                        propertyInputWrapper.empty().append(selectBox);
                    });
                } else if(propertyType === 'E') {
                    let propertyValueId = propertyInputWrapper.find('input[name="property_value[]"]').val();

                    $.ajax({
                        method: "POST",
                        url: "get_elements.php",
                        data: {
                            iblockId: propertySelect.find('option:selected').attr('data-iblock-link'),
                            selectedElementId: propertyValueId
                        }
                    }).done(function(msg) {
                        propertyInputWrapper.html(msg);
                    });
                } else {
                    if ($.isNumeric(propertyValue)) {
                        $.ajax({
                            method: "POST",
                            url: "get_property_value_by_id.php",
                            data: { propertyId: propertyValue }
                        }).done(function(msg) {
                            propertyInputWrapper.html(msg);
                        });
                    }
                }
            });

            // Функция проверки типа свойства и обработки значений
            $('#properties_wrapper').on('change', '.property-id', function(e) {
                let propertySelect = $(this);
                let propertyType = propertySelect.find('option:selected').attr('data-type');
                let propertyValue = propertySelect.val();
                let propertyInputWrapper = propertySelect.next('.property-value');
                console.log(propertyType);
                if (propertyType === 'L') {
                    let propertyId = propertySelect.val();

                    $.ajax({
                        method: "POST",
                        url: "get_property_values.php",
                        data: { propertyId: propertyId }
                    }).done(function(msg) {
                        let data = JSON.parse(msg);
                        let selectBox = $('<select class="form-control" name="property_value[]"></select>');

                        $.each(data, function(index, item) {
                            selectBox.append('<option value="' + item.ID + '">' + item.VALUE + '</option>');
                        });

                        propertyInputWrapper.empty().append(selectBox);
                    });
                } else if (propertyType === 'E') {
                    let selectedElementId = propertyInputWrapper.find('input[name=PROPERTY_' + propertyValue + ']').val();
                    $.ajax({
                        method: "POST",
                        url: "get_elements.php",
                        data: { iblockId: propertySelect.find('option:selected').attr('data-iblock-link'), selectedElementId: selectedElementId }
                    }).done(function(msg) {
                        propertyInputWrapper.html(msg);
                    });
                } else {
                    let propertyValue = propertySelect.val();

                    if ($.isNumeric(propertyValue)) {
                        $.ajax({
                            method: "POST",
                            url: "get_property_value_by_id.php",
                            data: { propertyId: propertyValue }
                        }).done(function(msg) {
                            propertyInputWrapper.html(msg);
                        });
                    } else {
                        propertyInputWrapper.html('<input type="text" name="property_value[]" value="" placeholder="Значение" class="form-control">');
                    }
                }
            });


            // Обработчик клика на кнопку "Удалить" для удаления свойства и пересчета индексов
            $('#properties_wrapper').on('click', '.delete-property-btn', function() {
                $(this).closest('.property-input').remove();
                $('.property-input').each(function(index) {
                    $(this).attr('id', 'property-input-' + index);
                });
            });

            // Функция добавления нового поля свойства
            $('#add_property_btn').on('click', function() {
                addPropertyField();
                console.log("Функция добавления нового поля свойства");
            });

            function addPropertyField() {
                let propertyCount = $('.property-input').length;
                let newPropertyField = $('#property-input-0').clone();
                newPropertyField.attr('id', 'property-input-' + propertyCount);
                newPropertyField.find('.form-control').val(''); // Очистка значений полей input
                $('#properties_wrapper').append(newPropertyField);
            }

            $(document).ready(function() {
                // Загрузка инфоблоков при открытии страницы
                $.ajax({
                    method: "GET",
                    url: "get_iblocks.php"
                }).done(function(msg) {
                    $("#iblock").html(msg);
                });
            });

            $('#iblock').on('change', function() {
                let selectedIblock = $(this).val();
                $.ajax({
                    method: "POST",
                    url: "get_elements.php",
                    data: { iblockId: selectedIblock }
                }).done(function(msg) {
                    $("#elements").html(msg);
                });
            });

        });
    </script>
<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
