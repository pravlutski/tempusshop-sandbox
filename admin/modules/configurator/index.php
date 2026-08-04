<?php

/**
 * @global CMain $APPLICATION
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

if (!CModule::IncludeModule('panel.manager')) {
    return;
}

opcache_reset();

CModule::IncludeModule("iblock");

$APPLICATION->SetTitle('Конфигуратор');

global $DB;

$strSql = "SELECT * FROM `ci_configurator_tags` ORDER BY `sort_order`";

$tags = $DB->Query($strSql, false);

?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<link href="/admin/modules/configurator/style.css" rel="stylesheet">
<h1 class="page-header">
    Список тегов
</h1>

<div class="row">
    <div class="col-sm-12 mb-sm-3 block-create-tags">
        <a href="create" class="btn btn-primary inline">
            Создать тег
        </a>
        <a href="create_multipe_tags" class="btn btn-primary inline">
            Массовое создание тегов
        </a>
        <button class="btn btn-danger inline delete-selected-tags">Удалить выбранные теги</button>
    </div>
    <div class="col-sm-12">
        <table class="table tablesorter">
            <thead>
                <tr>
                    <th>
                        <input type="checkbox" id="checkAllTags"/>
                    </th>
                    <th class="filter-1">
                        <span>Название</span>
                    </th>
                    <th>
                        <span>
                            Популярность запроса
                        </span>
                    </th>
                    <th>
                        <span>
                            Ресурс
                        </span>
                    </th>
                    <th>
                        <span>
                            Заполненные свойства
                        </span>
                    </th>
                    <th>
                        <span>
                            Действия
                        </span>
                    </th>
                    <th>
                        <span>
                            Дата обновления
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $tags->Fetch()): ?>
                    <tr data-tag-id="<?= $row['id']; ?>">
                        <td>
                            <input type="checkbox" class="tag-checkbox"/>
                        </td>
                        <td>
                            <a href="https://tempusshop.ru/admin/modules/configurator/edit?id=<?= $row['id']; ?>">
                                <?=$row['tag_name'];?>
                            </a>
                        </td>
                        <td>
                            <?=$row['sort_order'];?>
                        </td>
                        <td>
                            <?=$row['resource'];?>
                        </td>
                        <td>
                            <?=$row['properties_json'] ? "Да" : "Нет";?>
                        </td>
                        <td>
                            <a href="https://tempusshop.ru/admin/modules/configurator/edit?id=<?=$row['id'];?>"
                                class="btn btn-primary inline">
                                Редактировать
                            </a>
                            <button type="submit" class="btn btn-danger inline remove-task delete-selected-btn"
                                    data-id="<?=$row['id'];?>">
                                Удалить
                            </button>
                        </td>
                        <td>
                            <?=$row['updated_at'];?>
                        </td>
                    </tr>
                <?php endwhile;?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://tempusshop.ru/admin/modules/configurator/sort.js"></script>
<script type="text/javascript">
    $(document).ready(function() {
        // Выбрать все теги с помощью одного чекбокса
        $('#checkAllTags').click(function() {
            $('.tag-checkbox').prop('checked', this.checked);
        });

        // Удаление выбранных тегов
        $('.delete-selected-tags').click(function() {
            let tagIds = [];
            $('input.tag-checkbox:checked').each(function() {
                tagIds.push($(this).closest('tr').data('tag-id'));
            });

            if (tagIds.length > 0) {
                if (confirm("Вы уверены, что хотите удалить выбранные теги?")) {
                    $.ajax({
                        method: "POST",
                        url: "delete_el.php",
                        data: { elementsId: tagIds }
                    }).done(function(msg) {
                        location.reload();
                    });
                }
            } else {
                alert("Выберите теги для удаления");
            }
        });

        // удаление выбранного элемента по кнопке
        $('.delete-selected-btn').click(function (){
            let tagIds = [];
            tagIds.push($(this).data('id'));

            if(confirm("Вы уверены, что хотите удалить выбранный тег?")) {
                $.ajax({
                   method: "POST",
                   url: "delete_el.php",
                   data: { elementsId: tagIds }
                }).done(function (msg) {
                    location.reload();
                });
            }
        });

        $("table").tablesorter({
            widgets: ['zebra'],
            headers: {
                0: {
                    sorter:false
                },
                4: {
                    sorter:false
                }
            }
        });
    });
</script>
<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
