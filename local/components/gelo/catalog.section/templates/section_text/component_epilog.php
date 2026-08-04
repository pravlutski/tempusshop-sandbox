<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $templateData */
/** @var @global CMain $APPLICATION */
use Bitrix\Main\Loader;?>

<? if ($_SERVER['HTTP_HOST'] === 'tempusshop.ru') {
	if ($APPLICATION->GetCurPage() == '/catalog/watches/casio/') {?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Casio).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/casio/baby-g/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Casio-Baby_G).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/casio/casio_collections/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Casio-Casio_Collections).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/casio/edifice/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Casio-Edifice).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/casio/g_shock/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Casio-G_Shock).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/casio/casio_protrek/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Casio-Casio_Protrek).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/casio/casio_sheen/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Casio-Casio_Sheen).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/casio/casio_radio_controlled/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Casio-Radio_Controlled).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/casio/sports/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Casio-Sports).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/casio/casio_vintage/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Casio-Casio_Vintage).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/orient/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Orient).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/seiko/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Seiko).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/vostok/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(Vostok).php';?>
	<? } elseif ($APPLICATION->GetCurPage() == '/catalog/watches/emporio_armani/') { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/special_text(EmporioArmani).php';?>
	<? }elseif (($APPLICATION->GetCurPage() !== '/catalog/sale/')/*&&(strpos($APPLICATION->GetCurPage(),'/catalog/accessories/')!=0)*/&& ($APPLICATION->GetCurPage() !== '/catalog/gift_certificates/')) { ?>
		<?include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/regular_text.php';?>
	<?}
	if ($APPLICATION->GetCurPage() == '/catalog/gift_certificates/') {?>
		<div classs="section-text" style="padding-left:30px; padding-top: 40px;">
			<p>Приобрести сертификат любого номинала вы можете на нашем сайте.</p>
			<h2>Добавьте нужный вам номинал в корзину и оплатите его</h2>
			<p>Электронный подарочный сертификат предоставляется в виде индивидуального промокода. После оформления заказа вам на почту придет письмо, в котором будет указан выбранный номинал и сам промокод.</p>
			<p>Чтобы использовать подарочный сертификат, необходимо в корзине в поле «Купон для скидки» указать промокод, затем оплатить покупку</p>
			<h2>Условия использования подарочного сертификата</h2>
			<ul>
				<li>сертификат действует в течение 3 месяцев с момента его покупки;</li>
				<li>сертификат не восстанавливается после утери или кражи;</li>
				<li>сертификат не обменивается на деньги;</li>
				<li>оставшаяся сумма, не использованная при покупке, не сгорает и ее можно использовать в течение действия сертификата;</li>
				<li>если стоимость покупки превышает номинал сертификата, покупатель должен оплатить разницу.</li>
			</ul>
		</div>
	<?}
}?>

<? if ($_SERVER['HTTP_HOST'] === 'www.tempus.by') {
	
	include $_SERVER['DOCUMENT_ROOT'].'/include/template_text/regular_text.php';
	
	if ($APPLICATION->GetCurPage() == '/catalog/gift_certificates/') {?>
		<div classs="section-text" style="padding-left:30px; padding-top: 40px;">
			<p>Приобрести сертификат любого номинала вы можете в нашем розничном магазине или на сайте.</p>
			<p>В случае его приобретения на сайте <b>добавьте нужный вам номинал в корзину и оплатите любым удобным для вас способом.</b> </p>
			<p>Электронный подарочный сертификат предоставляется в виде индивидуального промокода. После оформления заказа вам на почту придет письмо, в котором будет указан выбранный номинал и сам промокод.</p>
			<p>Чтобы использовать подарочный сертификат, необходимо в корзине в поле «Купон для скидки» указать промокод, затем оплатить покупку.</p>
			<h2>Условия использования подарочного сертификата</h2>
			<ul>
				<li>сертификат действует в течение 3 месяцев с момента его покупки;</li>
				<li>сертификат не восстанавливается после утери или кражи;</li>
				<li>сертификат не обменивается на деньги;</li>
				<li>оставшаяся сумма, не использованная при покупке, не сгорает и ее можно использовать в течение действия сертификата;</li>
				<li>если стоимость покупки превышает номинал сертификата, покупатель должен оплатить разницу;</li>
				<li>воспользоваться бумажным сертификатам можно только в розничном магазине.</li>
			</ul>
		</div>
	<?}
}?>
