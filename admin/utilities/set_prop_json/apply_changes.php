<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager')) return;

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups) && !in_array(6, $arGroups)) {
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return;
}
//prent($_POST);
if(isset($_POST["apply_changes"])) {
    $filename_parse = $_POST["filename_parse"];
    $profile_name = $_POST["profile_name"];
    $changes = $_POST["changes"] ?? [];
    
    $updated = 0;
    $errors = [];
    $processedItems = [];
    
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
    ?>
    <h1 class="page-header">Применение изменений</h1>
    
    <div class="progress" style="height: 30px; margin: 20px 0;">
        <div id="progressBar" class="progress-bar progress-bar-striped active" 
             role="progressbar" style="width: 0%">0%</div>
    </div>
    
    <div id="statusMessages" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;"></div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        var changes = <?= json_encode($changes, JSON_UNESCAPED_UNICODE) ?>;
        var totalItems = Object.keys(changes).length;
        var processed = 0;
        var errors = [];
        
        function updateProgress() {
            var percent = Math.round((processed / totalItems) * 100);
            $('#progressBar').css('width', percent + '%').text(percent + '%');
        }
        
        function processNext() {
            var keys = Object.keys(changes);
            if (processed >= keys.length) {
                completeProcessing();
                return;
            }
            
            var elementId = keys[processed];
            var elementChanges = changes[elementId];
            
            $.ajax({
                url: '/admin/utilities/set_prop_json/apply_single.php',
                type: 'POST',
                data: {
                    element_id: elementId,
                    changes: elementChanges
                },
				dataType: "json",
                success: function(response) {
                    processed++;
                    updateProgress();
                    console.log(response);
                    if (response.success) {
                        $('#statusMessages').append(
                            '<div class="alert alert-success alert-sm">' +
                            'Товар ID: ' + elementId + ' - обновлен успешно' +
                            '</div>'
                        );
                    } else {
                        errors.push('Товар ID: ' + elementId + ' - ошибка: ' + response.error);
                        $('#statusMessages').append(
                            '<div class="alert alert-danger alert-sm">' +
                            'Товар ID: ' + elementId + ' - ошибка: ' + response.error +
                            '</div>'
                        );
                    }
                    
                    $('#statusMessages').scrollTop($('#statusMessages')[0].scrollHeight);
                    
                    setTimeout(processNext, 100);
                },
                error: function() {
                    processed++;
                    updateProgress();
                    errors.push('Товар ID: ' + elementId + ' - ошибка сети');
                    $('#statusMessages').append(
                        '<div class="alert alert-danger alert-sm">' +
                        'Товар ID: ' + elementId + ' - ошибка сети' +
                        '</div>'
                    );
                    setTimeout(processNext, 100);
                }
            });
        }
        
        function completeProcessing() {
            $('#progressBar').removeClass('active').addClass('bg-success');
            
            var html = '<div class="alert alert-info mt-3">';
            html += '<h4>Обработка завершена!</h4>';
            html += '<p>Обработано товаров: ' + processed + ' из ' + totalItems + '</p>';
            html += '<p>Успешно: ' + (processed - errors.length) + '</p>';
            html += '<p>Ошибок: ' + errors.length + '</p>';
            
            if (errors.length > 0) {
                html += '<h5>Список ошибок:</h5>';
                html += '<ul>';
                for (var i = 0; i < errors.length; i++) {
                    html += '<li>' + errors[i] + '</li>';
                }
                html += '</ul>';
            }
            
            html += '</div>';
            
            $('#statusMessages').append(html);
            
            var buttons = '<div class="mt-3">';
            buttons += '<a href="/admin/utilities/set_prop_json/" class="btn btn-primary">';
            buttons += '<i class="fa fa-arrow-left"></i> Вернуться к утилите';
            buttons += '</a> ';
            buttons += '<a href="/admin/utilities/set_prop_json/preview.php?filename_parse=<?= urlencode($filename_parse) ?>&set_profile=<?= urlencode($profile_name) ?>" class="btn btn-info">';
            buttons += '<i class="fa fa-eye"></i> Смотреть превью';
            buttons += '</a>';
            buttons += '</div>';
            
            $('#statusMessages').append(buttons);
            
            $.ajax({
                url: '/admin/utilities/set_prop_json/log_result.php',
                type: 'POST',
                data: {
                    filename: '<?= $filename_parse ?>',
                    profile: '<?= $profile_name ?>',
                    processed: processed,
                    errors: errors.length,
                    user_id: <?= $USER->GetID() ?>
                }
            });
        }
        
        processNext();
    });
    </script>
    
    <?php
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
} else {
    //LocalRedirect('/admin/utilities/set_prop_json/');
}
?>