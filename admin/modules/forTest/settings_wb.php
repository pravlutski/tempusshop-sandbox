<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/modules/descGen/classes/DescriptionGenerator.php");

CModule::IncludeModule('panel_manager');

require( $_SERVER["DOCUMENT_ROOT"]."/admin/panel/engine/ozon/classes/PriceManager.php" );

$url = "https://seller.ozon.ru/api/som-stocks-bff/StockManagementV2/GetStockReport";

$ch = curl_init($url);
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
// curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
curl_setopt( $ch, CURLOPT_COOKIE, "_Secure-ETC=1d5459a9039bde22cb374f77ee0a47cc; abt_data=7.E2NltjfGIA2zEqYl8U8MXpvipJddHF1G2m7fgQxKWgqSIVt42-MrpGsBnSQtyHq4RCUDR7PzEd6cL6VBMre2L-mRte6FXgEApl7QsJj8KAzQxI1EkiT2odCRJferbTzEh9KiSpPIUTt1wMXJzmXAC_CZXdoSir6yZHLL4aYMjvqPU6_RUSyx_YGaA8U2TeHzYdabdnGsaU_8EdFxrJOeHFM1ZxmR9YdpVJv5E2lYlcVWAlEARgLScqqS706NNcXwJPB4doWtZwoMGYaEbaxtlXCD1SCQv0PyKuFPqrRrypzfNdQq_wJ4M0tjxEqcgcFlX2OnnKOAyP0H-YIBucUBxlUl20Uc_gmkoDNKr3FSiRNf_040GO-90cp2styvrblQJqB42S6Jas0ktlamgsBsDLcrIuecZ5hxJkb0A0kHkEDjQNRCyRqRjQino882nu6vHeR4hK…NMzyDISQeX-o4mgW78j7lltCMzxWugJZbIHSWU.20250116122352.20260727114703.2.j_zEZhcvZqv7m-TOqO2wFkDCu0VZbi2jsZ7qLgGO9-I.152e2876681f4ec76; __Secure-ab-group=11; __Secure-user-id=106903649; bacntid=3890960; sc_company_id=2893807; __Secure-ext_xcid=66a327b3e919842fab535440d87532d5; __Secure-sid=sc1.IT5TRPMXTNuOfm1kqgABtA.Ab9kePcvxYhohR79a8B_b_An7hGWWQYBQf9E6ALWUgwgRoyWbIehly5LwAkG_nZwA_RSaqYpRTLzHLeIDG81Nb4.PPDA5gCE3Ib4VzCeQOgfVO0oc75eHuSEWOTzLdXmt80.172fd4f6a2e2be508; client_dimensions=1920x995; x-o3-language=ru");
$resCurl = curl_exec($ch);

var_dump( $resCurl );
?>
