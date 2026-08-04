<?php

/**
 * @global CMain $APPLICATION
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

if(!CModule::IncludeModule('panel.manager'))
    return;

opcache_reset();

CModule::IncludeModule("iblock");

$APPLICATION->SetTitle('Создание тега | Конфигуратор');

?>
    <link href="/admin/modules/configurator/style.css" rel="stylesheet">
    <h1 class="page-header">
        Создание тега
    </h1>
    <div class="col-sm-12 panel panel-default create-block-tags">
        <div class="col-sm-12">
            <h3 class="page-header">
                Основные параметры
            </h3>
        </div>
        <form action="process_create.php" method="post">
            <div class="col-sm-12 mb-15">
                <label for="tag_name">Название тега</label>
                <input type="text" name="tag_name" id="tag_name" class="form-control" required>
            </div>
            <div class="col-sm-12 mb-15">
                <label for="sort_order">Популярность запроса</label>
                <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
            </div>
            <div class="col-sm-12 mb-15">
                <label for="resource">Ресурс</label>
                <select name="resource" class="form-control" id="resource">
                    <option value="OZON">OZON</option>
                    <option value="WB">WB</option>
                    <option value="WordStat">WordStat</option>
                    <option value="GoogleTrends">GoogleTrends</option>
                </select>
            </div>
            <div class="col-sm-12 mb-15">
                <label for="sections">Разделы</label>
                <select name="selected_sections[]" id="sections" class="form-control" multiple>
                    <?php
                        $sections = CIBlockSection::GetList(Array("SORT" => "ASC"), Array("IBLOCK_ID" => 16));

                        while ($section = $sections->GetNext()) {
                            echo '<option value="' . $section['ID'] . '">' . $section['NAME'] . '</option>';
                        }
                    ?>
                </select>
            </div>
            <div class="col-sm-12 mb-15">
                <div id="properties_wrapper" class="mb-15">
                    <label for="property">Свойства</label>
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
                </div>
                <button type="button" id="add_property_btn" class="btn btn-primary set_period">
                    Добавить еще свойство
                </button>
            </div>
            <hr>
            <div class="col-sm-12">
                <input type="submit" value="Создать" class="btn btn-primary set_period">
                <a href="https://tempusshop.ru/admin/modules/configurator/" class="btn btn-primary">
                    Отменить
                </a>
            </div>
        </form>
    </div>
    <script>
        $(document).ready(function() {
            function addPropertyField() {
                let propertyCount = document.querySelectorAll('.property-input').length;
                let newPropertyField = document.createElement('div');
                newPropertyField.classList = 'property-input';
                newPropertyField.id = 'property-input-' + propertyCount;
                newPropertyField.innerHTML = document.getElementById('property-input-0').innerHTML;
                document.getElementById('properties_wrapper').appendChild(newPropertyField);
            }

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

            document.getElementById('add_property_btn').addEventListener('click', function() {
                addPropertyField();
            });

            function addPropertyField() {
                let propertyCount = document.querySelectorAll('.property-input').length;
                let newPropertyField = document.createElement('div');
                newPropertyField.classList = 'property-input';
                newPropertyField.id = 'property-input-' + propertyCount;
                newPropertyField.innerHTML = document.getElementById('property-input-0').innerHTML;
                document.getElementById('properties_wrapper').appendChild(newPropertyField);
            }
        });
    </script>
<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
