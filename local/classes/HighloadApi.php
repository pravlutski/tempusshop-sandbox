<?php
use \Bitrix\Highloadblock\HighloadBlockTable as HL;

class HighloadApi
{
    public $HL_BLOCK_ID;
    private $entity;

    public function __construct($HLBlockID)
    {
        if(!$HLBlockID) die('Error!');
        \CModule::IncludeModule('highloadblock');
        $this->HL_BLOCK_ID = $HLBlockID;
        $this->entity = $this->getEntity($HLBlockID);
    }

    /**
     * @param $HLBlockID
     * @return bool
     * Получения экземпляра класса
     */
    protected function getEntity($HLBlockID) {
        if (empty($HLBlockID) || $HLBlockID < 1)
        {
            return false;
        }
        $hlblock = HL::getById($HLBlockID)->fetch();
        $entity = HL::compileEntity($hlblock);
        return $entity->getDataClass();
    }

    /**
     * @return mixed
     * Получение названий полей
     */
    public function getFields(){
        $hlblock = HL::getById($this->HL_BLOCK_ID)->fetch();
        $entity = HL::compileEntity($hlblock);
        return $entity->getFields();
    }

    /**
     * @return array
     * Получить элементы highload-инфоблока
     */
    public function getList($arFilter = array(), $arOrder = array('ID' => 'ASC'), $arSelect = array('*')){
        $elem = array();
        //$entity_data_class = $this->getEntity($this->HL_BLOCK_ID);
        $rsData = $this->entity::getList(array(
			'order' => $arOrder,
			'select' => $arSelect,
			'filter' => $arFilter
        ));
        while($el = $rsData->fetch()){
			$elem[] = $el;
        }
        return $elem;
    }

    /**
     * @return mixed
     * Количество элементов highload-инфоблока
     */
    public function getCount(){
		//$entity_data_class = $this->getEntity($this->HL_BLOCK_ID);
		return $this->entity::getCount();
    }

    /**
     * @return mixed
     * Добавить новый элемент в highload-инфоблок
     */
    public function add($arData){
		//$entity_data_class = $this->getEntity($this->HL_BLOCK_ID);
		return $this->entity::add($arData);
    }

    /**
     * @param $ID 
     * @return mixed
     * Удалить элемент в highload-инфоблок
     */
    public function remove($ID){
		//$entity_data_class = $this->getEntity($this->HL_BLOCK_ID);
		return $this->entity::delete($ID);
    }

    /**
     * @param $ID
     * @param $arFields
     * @return mixed
     * Обновить новый элемент в highload-инфоблок
     */
    public function update($ID, $arFields){
		//$entity_data_class = $this->getEntity($this->HL_BLOCK_ID);
		return $this->entity::update($ID, $arFields);
    }
}