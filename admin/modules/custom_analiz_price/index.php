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

$APPLICATION->SetTitle('Ручной анализ цен');

global $DB;

class customPrice
{
	public static function getList()
	{
		global $DB;

		$strSql = "SELECT * FROM `ci_custom_analiz_price`";

		return $DB->Query($strSql, false);
	}

	public static function getPurchasePrice($article, $priceId): float|string
	{
		global $DB;
		$price = '';
		$strSql = "SELECT * FROM `ci_price` WHERE `model` = '{$article}' AND `active_{$priceId}` = 'Y'";
		$el = $DB->Query($strSql, false);

		while ($row = $el->Fetch()) {
			$price = round(floatval($row['price']));
		}

		return $price;
	}

	public static function getSetPrice($article, $priceId): float|string
	{
		global $DB;
		$price = '';
		$strSql = "SELECT * FROM `ci_price_catalog` WHERE `model` = '{$article}'";
		$el = $DB->Query($strSql, false);

		while ($row = $el->Fetch()) {
			$val = "price_{$priceId}";
			$price = round(floatval($row[$val]));
		}

		return $price;
	}

	public static function getPrice($article, $priceId, $markup): float|int
	{
		$purPrice = self::getPurchasePrice($article, $priceId);

		if (!is_numeric($purPrice) || !is_numeric($markup)) {
			// Обработка ситуации, когда $purPrice или $markup не являются числами
			return 0;
		}

		$price = 0;

		if($priceId === 'wb') {
			$price = $purPrice * $markup;
		} else {
			$price = $purPrice * $markup;
		}

		return round(floatval($price));
	}
}

$customPrice = new customPrice();

$price_elements = $customPrice->getList();

?>
<link href="/admin/modules/configurator/style.css" rel="stylesheet">
<h1 class="page-header">
    Ручной анализ цен
</h1>

<div class="row">
    <div class="col-sm-12 mb-sm-3 block-create-tags">
        <a href="create" class="btn btn-primary inline">
            Создать профиль
        </a>
        <a href="create_multipe_tags" class="btn btn-primary inline">
            Массовое создание профилей
        </a>
        <button class="btn btn-danger inline delete-selected-tags">Удалить выбранные профили</button>
    </div>
    <div class="col-sm-12">
        <table class="table tablesorter">
            <thead>
                <tr>
                    <th>
                        <input type="checkbox" id="checkAllTags"/>
                    </th>
                    <th class="filter-1">
                        <span>Артикул</span>
                    </th>
                    <th>
                        <span>
                            Ставка
                        </span>
                    </th>
                    <th>
                        <span>
                            По прайсу
                        </span>
                    </th>
                    <th>
                        <span>
                            Цена на текущий момент
                        </span>
                    </th>
					<th>
                        <span>
                            Цена которая должна быть
                        </span>
					</th>
					<th>
						<span>
							Тип цены
						</span>
					</th>
                    <th>
                        <span>
                            Действия
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $price_elements->Fetch()): ?>
                    <tr data-tag-id="<?= $row['id']; ?>">
                        <td>
                            <input type="checkbox" class="tag-checkbox"/>
                        </td>
                        <td>
                            <a href="/admin/modules/custom_analiz_price/edit?id=<?= $row['id']; ?>">
                                <?=$row['article'];?>
                            </a>
                        </td>
                        <td>
                            <?=$row['markup'];?>
                        </td>
                        <td>
	                        <?=$customPrice->getPurchasePrice($row['article'], $row['price_id'])?>
                        </td>
						<td>
		                    <?=$customPrice->getSetPrice($row['article'], $row['price_id'])?>
						</td>
						<td>
		                    <?=$customPrice->getPrice($row['article'], $row['price_id'], $row['markup']);?>
						</td>
						<td>
							<?=$row['price_id'];?>
						</td>
                        <td>
                            <a href="/admin/modules/custom_analiz_price/edit?id=<?=$row['id'];?>"
                                class="btn btn-primary inline">
                                Редактировать
                            </a>
                            <button type="submit" class="btn btn-danger inline remove-task delete-selected-btn"
                                    data-id="<?=$row['id'];?>">
                                Удалить
                            </button>
                        </td>
                    </tr>
                <?php endwhile;?>
            </tbody>
        </table>
    </div>
</div>
<script src="/admin/modules/custom_analiz_price/sort.js"></script>
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
