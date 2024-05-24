{capture name=path}{l s='Waiting for payment' mod='robokassa'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}

<h2>{l s='Waiting for payment' mod='robokassa'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

<h3>{l s='Waiting for payment' mod='robokassa'}</h3>

<p>{l s='At the moment of payment is not received. Once it is received you will be able to see your order in your account' mod='robokassa'}</p>
<p>{l s='If you do not receive notification of payment please send his number' mod='robokassa'} <strong>{$ordernumber}</strong> <a href="{$link->getPageLink('contact-form.php', true)}">{l s='to support services' mod='robokassa'}</a></p>