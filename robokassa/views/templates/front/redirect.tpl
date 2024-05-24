<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset=utf-8 />
		<title>Payment</title>
	</head>
	<body>
		<form name="robokassa_form" action="{$redirect_url}" method="post" accept-charset="windows-1251">
			<input type="hidden" name="MrchLogin" value="{$robokassa_login|escape:'html':'UTF-8'}"/>
			<input type="hidden" name="OutSum" value="{$total_to_pay|escape:'html':'UTF-8'}"/>
			<input type="hidden" name="InvId" value="{$order_number|escape:'html':'UTF-8'}"/>
			<input type="hidden" name="shp_label" value="official_prestashop"/>
			{if $fiscalization}<input type="hidden" name="Receipt" value="{$receiptData}"/>{/if}
			<input type="hidden" name="SignatureValue" value="{$signature|escape:'html':'UTF-8'}"/>
			<input type="hidden" name="Email" value="{$email|escape:'html':'UTF-8'}"/>
			{if $robokassa_demo}<input type="hidden" name="IsTest" value="1"/>{/if}
			{if $OutSumCurrency}<input type="hidden" name="OutSumCurrency" value="{$OutSumCurrency|escape:'html':'UTF-8'}"/>{/if}
			<input type="submit" value="{l s='Click here to go to the payment' mod='robokassa'}"/>
		</form>
		<script>document.robokassa_form.submit();</script>
	</body>
</html>