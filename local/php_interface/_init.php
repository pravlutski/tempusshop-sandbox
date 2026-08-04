<?
    include 'include/constants.php';
    include 'include/events.php';
    include 'include/classes/pricelist.php';

AddEventHandler('main', 'OnBeforeEventAdd', array('CSms4bExt', 'OnBeforeEventAdd'));
// TODO: Использовать автозагрузку классов.
class CSms4bExt
{
    public function OnBeforeEventAdd(&$event, &$lid, &$arFields) {
        if (!CModule::IncludeModule('rarus.sms4b')) {
            return;
        }

        $arNewFields = $arFields;

        if ($event === 'NEW_ONE_CLICK_BUY') {
            $orderId = $arFields['RS_ORDER_ID'];
            $dbResult = CSaleOrder::GetList(
                /*arOrder*/ array(),
                /*arFilter*/ array('ID' => $orderId),
                /*arGroupBy*/ false,
                /*arNavStartParams*/ false,
                /*arSelectFields*/ array('ACCOUNT_NUMBER')
            );
            $arOrder = $dbResult->Fetch();
            $orderNumber = $arOrder['ACCOUNT_NUMBER'];

            $arNewFields['SMS4B_ORDER_NUMBER'] = $orderNumber;
        } elseif ($event === 'SALE_ORDER_TRACKING_NUMBER') {
            $orderNumber = $arFields['ORDER_ID'];
            $dbResult = CSaleOrder::GetList(
                /*arOrder*/ array(),
                /*arFilter*/ array('ACCOUNT_NUMBER' => $orderNumber),
                /*arGroupBy*/ false,
                /*arNavStartParams*/ false,
                /*arSelectFields*/ array('ID')
            );
            $arOrder = $dbResult->Fetch();
            $orderId = $arOrder['ID'];

            $dbResult = CSaleOrderPropsValue::GetList(
                /*arOrder*/ array(),
                /*arFilter*/ array('ORDER_ID' => $orderId, 'CODE' => 'PHONE'),
                /*arGroupBy*/ false,
                /*arNavStartParams*/ false,
                /*arSelectFields*/ array('VALUE')
            );
            $arOrderPhone = $dbResult->Fetch();
            $orderPhone = $arOrderPhone['VALUE'];

            $arNewFields['SMS4B_CUSTOMER_PHONE'] = $orderPhone;
        } else {
            return;
        }

        $newEvent = $event . '_EXT';
        $CEvent = new \CEvent;
        $CEvent->Send($newEvent, $lid, $arNewFields);
    }
}
