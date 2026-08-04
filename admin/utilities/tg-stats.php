<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Статистика Telegram');?>
<?$APPLICATION->SetPageProperty("page_h1", "Статистика Telegram");?>
<?
global $DB;
global $USER;
$cabinetArr = array('IP','TI');
$CurDB = new DBPanel();
$arGroups = $USER->GetUserGroupArray();
$result = $CurDB->query("SELECT * FROM tg_stats ORDER BY datetime DESC");
$rows = $CurDB->fetchAll($result);
$stats = array();
foreach ($rows as $row) {
    $stats[] = $row;
}
?>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<div class="tg-stats-container">
    <div class="filters-section" style="margin-bottom: 20px;">
        <div class="row">
            <div class="col-md-3">
                <label for="min">Дата от:</label>
                <input type="date" id="min" name="min" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="max">Дата до:</label>
                <input type="date" id="max" name="max" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="ipFilter">IP адрес:</label>
                <input type="text" id="ipFilter" name="ipFilter" class="form-control">
            </div>
            <div class="col-md-3">
                <label style="visibility: hidden;">Кнопки</label><br>
                <button id="resetFilters" class="btn btn-secondary">Сбросить фильтры</button>
            </div>
        </div>
    </div>
    <table id="tgStatsTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Дата и время</th>
                <th>User Agent</th>
                <th>IP адрес</th>
                <th>Метод запроса</th>
                <th>Referer</th>
                <th>URI запроса</th>
            </tr>
        </thead>
        <tbody>
            <?foreach ($stats as $item):?>
            <tr>
                <td><?=htmlspecialcharsbx($item['id'])?></td>
                <td data-order="<?=strtotime($item['datetime'])?>">
                    <?=date('d.m.Y H:i:s', strtotime($item['datetime']))?>
                </td>
                <td title="<?=htmlspecialcharsbx($item['user_agent'])?>">
                    <?=mb_strlen($item['user_agent']) > 50 ? mb_substr($item['user_agent'], 0, 50).'...' : htmlspecialcharsbx($item['user_agent'])?>
                </td>
                <td><?=htmlspecialcharsbx($item['ip_address'])?></td>
                <td><?=htmlspecialcharsbx($item['request_method'])?></td>
                <td title="<?=htmlspecialcharsbx($item['referer'])?>">
                    <?=mb_strlen($item['referer']) > 50 ? mb_substr($item['referer'], 0, 50).'...' : htmlspecialcharsbx($item['referer'])?>
                </td>
                <td title="<?=htmlspecialcharsbx($item['request_uri'])?>">
                    <?=mb_strlen($item['request_uri']) > 50 ? mb_substr($item['request_uri'], 0, 50).'...' : htmlspecialcharsbx($item['request_uri'])?>
                </td>
            </tr>
            <?endforeach;?>
        </tbody>
    </table>
</div>
<script type="text/javascript" src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#tgStatsTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ru.json"
        },
        "pageLength": 25,
        "order": [[1, "desc"]],
        "dom": 'Bfrtip',
        "buttons": [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
    function filterByDate() {
        var min = $('#min').val();
        var max = $('#max').val();
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (!min && !max) {
                return true;
            }
            var row = table.row(dataIndex).node();
            var dateTimestamp = parseInt($(row).find('td:eq(1)').attr('data-order'));
            var rowDate = new Date(dateTimestamp * 1000);
            var rowDateStr = rowDate.toISOString().split('T')[0];
            if (min && !max) {
                return rowDateStr >= min;
            } else if (!min && max) {
                return rowDateStr <= max;
            } else if (min && max) {
                return rowDateStr >= min && rowDateStr <= max;
            }
            return true;
        });
    }
    $('#min, #max').on('change', function() {
        $.fn.dataTable.ext.search.pop();
        filterByDate();
        table.draw();
    });
    $('#ipFilter').on('keyup', function() {
        table.column(3).search(this.value).draw();
    });
    $('#resetFilters').on('click', function() {
        $('#min').val('');
        $('#max').val('');
        $('#ipFilter').val('');
        $.fn.dataTable.ext.search = [];
        table.search('').columns().search('').draw();
    });
});
</script>
<style>
.tg-stats-container {
    margin: 20px 0;
}
.date-filter {
    margin-bottom: 10px;
}
#tgStatsTable {
    margin-top: 20px;
    font-size: 14px;
}
.dataTables_wrapper {
    position: relative;
}
.dt-buttons {
    margin-bottom: 10px;
}
#tgStatsTable th {
    background-color: #f8f9fa;
    font-weight: bold;
    cursor: pointer;
}
#tgStatsTable th:hover {
    background-color: #e9ecef;
}
#tgStatsTable td {
    vertical-align: top;
    padding: 8px;
}
@media (max-width: 768px) {
    .filters-section .col-md-3 {
        margin-bottom: 15px;
    }
    .dt-buttons {
        text-align: center;
    }
    .dt-button {
        margin: 2px;
        font-size: 12px;
        padding: 4px 8px;
    }
    #tgStatsTable {
        font-size: 12px;
    }
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
