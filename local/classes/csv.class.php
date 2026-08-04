<?php

/**
 * Класс для работы с csv-файлами 
 * @author дизайн студия ox2.ru  
 */
class CSV {

    private $_csv_file = null;
	public $error = null;

    /**
     * @param string $csv_file  - путь до csv-файла
     */
    public function __construct($csv_file) {
		setlocale(LC_CTYPE, "ru_RU.CP1251");
        if(file_exists($csv_file)){ //Если файл существует
			$this->_csv_file = $csv_file; //Записываем путь к файлу в переменную
		}else{
			$this->error = "no_found";
			//echo "no_found";
			//throw new Exception("Файл \"$csv_file\" не найден");
		}// throw new Exception("Файл \"$csv_file\" не найден"); //Если файл не найден то вызываем исключение
    }

    public function setCSV(Array $csv) {
        $handle = fopen($this->_csv_file, "a"); //Открываем csv для до-записи, если указать w, то  ифномация которая была в csv будет затерта

        foreach ($csv as $value) { //Проходим массив
            fputcsv($handle, explode(";", $value), ";"); //Записываем, 3-ий параметр - разделитель поля
        }
        fclose($handle); //Закрываем
    }

    /**
     * Метод для чтения из csv-файла. Возвращает массив с данными из csv
     * @return array;
     */
    public function getCSV() {
        $handle = fopen($this->_csv_file, "r"); //Открываем csv для чтения
		
        $array_line_full = array(); //Массив будет хранить данные из csv
        while (($line = fgetcsv($handle, 0, ";")) !== FALSE) { //Проходим весь csv-файл, и читаем построчно. 3-ий параметр разделитель поля
            //prent($line);
			//$array_line_full[] = ((!defined("BX_UTF")) ? $GLOBALS["APPLICATION"]->ConvertCharset(htmlspecialchars($line), SITE_CHARSET, 'UTF-8') : htmlspecialchars($line));
			//$array_line_full[] = iconv('windows-1251', 'utf-8', $line);
			$array_line_full[] = $line; //Записываем строчки в массив
        }
        fclose($handle); //Закрываем файл
        return $array_line_full; //Возвращаем прочтенные данные
    }

}

?>