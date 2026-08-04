<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
set_time_limit(0);

$APPLICATION->SetTitle("Вставка изображений в прайс-листы");

$directory = '/var/www/bitrix/data/www/tempusshop.ru/admin/test/exel/docs/';
$webPath = 'https://tempusshop.ru/admin/test/exel/docs/';


$files = glob($directory . '*.xlsx');
$fileLinks = array();
if (count($files) > 0) {
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $fileLinks = array();
    $filesToKeep = array_slice($files, 0, 20);

    foreach ($filesToKeep as $file) {
        $filename = basename($file);
        $fileLinks[$filename] = $webPath . $filename;
    }

    $filesToDelete = array_slice($files, 20);
    foreach ($filesToDelete as $file) {
        unlink($file);
    }
}
?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 20px;
        background-color: #f5f5f5;
    }

    .container {
        max-width: 600px;
        margin: 0 auto;
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h1 {
        text-align: center;
        color: #333;
    }

    .form-group {
        margin-bottom: 15px;
    }

    label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    input[type="file"],
    select,
    input[type="text"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }

    input[type="file"] {
        padding: 3px;
    }

    .file-info {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }

    button {
        background-color: #4CAF50;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        width: 100%;
    }

    button:hover {
        background-color: #45a049;
    }
    .loading {
        display: none;
        text-align: center;
        margin: 10px 0;
    }
    .notexz {
      text-align: center;
      width: 100%;
      display: flex;
      justify-content: center;
      font-size: 18px;
      margin-top: 3rem;
      text-decoration: underline;
    }
    .last-price {
      display: flex;
      flex-direction: column;
      margin-left: 5rem;
    }
    .last-price a{
      font-size: 16px;
      text-decoration: underline;
    }
</style>
<div class="row" style="margin-top: 3rem;">
  <div class="col-md-4 col-sm-12">
      <h1>Вставка изображений</h1>
      <form id="excelForm" method="POST" enctype="multipart/form-data">
          <div class="form-group">
              <label for="xlsxFile">Загрузите файл XLSX:</label>
              <input type="file" id="xlsxFile" name="pricelist" accept=".xlsx" required>
              <div class="file-info">Поддерживаются только файлы в формате .xlsx</div>
          </div>

          <div class="form-group">
              <label for="dropdown">Бренд:</label>
              <select id="dropdown" name="brand" required>
                  <option value="">-- Выберите --</option>
                  <option value="tissot">Tissot</option>
                  <option value="rado">Rado</option>
                  <option value="certina">Certina</option>
                  <option value="longines">Longines</option>
                  <option value="victorinox">Victorinox</option>
              </select>
          </div>

          <div class="form-group">
              <label for="text1">Первая строка с товаром:</label>
              <input type="text" id="text1" name="firstRow" pattern="\d*" required>
              <small class="error-message" style="color: red; display: none;">Пожалуйста, введите только цифры</small>
          </div>

          <script>
          document.getElementById('text1').addEventListener('input', function(e) {
              const value = e.target.value;
              const errorElement = e.target.nextElementSibling;

              if (!/^\d*$/.test(value)) {
                  errorElement.style.display = 'block';
                  e.target.setCustomValidity('Пожалуйста, введите только цифры');
              } else {
                  errorElement.style.display = 'none';
                  e.target.setCustomValidity('');
              }
          });
          </script>

          <div class="form-group">
              <label for="text4">Макс. кол-во обрабатываемых строк:</label>
              <input type="text" id="text4" name="maxRow" pattern="\d*" required>
              <small class="error-message" style="color: red; display: none;">Пожалуйста, введите только цифры</small>
          </div>

          <script>
          document.getElementById('text4').addEventListener('input', function(e) {
              const value = e.target.value;
              const errorElement = e.target.nextElementSibling;

              if (!/^\d*$/.test(value)) {
                  errorElement.style.display = 'block';
                  e.target.setCustomValidity('Пожалуйста, введите только цифры');
              } else {
                  errorElement.style.display = 'none';
                  e.target.setCustomValidity('');
              }
          });
          </script>

          <div class="form-group">
              <label for="text2">Буквенное обозначения столбца с артикулом</label>
              <input type="text" id="text2" name="articleColumn" required>
          </div>

          <div class="form-group">
              <label for="text3">Буквенное обозначения столбца для изображений</label>
              <input type="text" id="text3" name="imageColumn" required>
          </div>

          <div class="loading" id="loading">
             <img src="https://i.gifer.com/origin/b4/b4d657e7ef262b88eb5f7ac021edda87.gif" width="50" alt="Loading...">
             <p>Генерация файла...</p>
         </div>

          <button type="submit">Отправить</button>
      </form>
  </div>
  <div class="col-md-2 col-sm-12">
  </div>
  <div class="col-md-4 col-sm-12">
      <h1>Последние 20 обработанных прайсов</h1>
      <div class="last-price">
        <?if (empty($fileLinks)) {?>
          <span class="notexz">Файлы отсутствуют!</span>
        <?} else {
          foreach ($fileLinks as $name => $file) {?>
            <a href="<?=$file?>" target="_blank"><?=$name?></a>
        <?}
        }?>

      </div>
  </div>
</div>
<script>
$(document).ready(function() {
    $('#excelForm').on('submit', function(e) {
        e.preventDefault();

        $('#loading').show();
        $('#submitBtn').prop('disabled', true);

        $('.error').text('');

        var formData = new FormData(this);

        $.ajax({
            url: '/admin/test/exel/ajax/apply.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                $('#loading').hide();
                $('#submitBtn').prop('disabled', false);

                if (response.success) {
                    var form = $('<form>', {
                        method: 'GET',
                        action: response.downloadUrl,
                        style: 'display: none;'
                    }).appendTo('body');

                    form.submit();
                    setTimeout(function() {
                        form.remove();
                    }, 1000);

                } else {
                    if (response.errors) {
                        $.each(response.errors, function(key, value) {
                            $('#' + key + '-error').text(value);
                        });
                    }
                    alert(response.error || 'Произошла ошибка при обработке файла');
                }
            },
            error: function(xhr, status, error) {
                $('#loading').hide();
                $('#submitBtn').prop('disabled', false);

                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        alert(response.error);
                    } else {
                        alert('Ошибка сервера: ' + xhr.statusText);
                    }
                } catch (e) {
                    alert('Не удалось обработать ответ сервера. ' + error);
                }
            }
        });
    });

});
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
