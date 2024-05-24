<?php
/**
* Validation controller
*/

class RobokassavalidationModuleFrontController extends ModuleFrontController
{
	public $display_header = false;
	public $display_column_left = false;
	public $display_column_right = false;
	public $display_footer = false;
	public $ssl = false;

	public function postProcess()
	{
		parent::postProcess();

		$ordernumber = Tools::getValue('InvId');
		$amount = Tools::getValue('OutSum');

		$signature = md5($amount.':'.$ordernumber.':'.Configuration::get('robokassa_password2') . ':shp_label=official_prestashop');


		if (Tools::strtoupper($signature) != Tools::getValue('SignatureValue')) {
            Robokassa::validateAnsver($this->module->l('Invalid signature ') . $signature);
        }

		if (Configuration::get('robokassa_postvalidate'))
		{
			$cart = new Cart((int)$ordernumber);
			//Проверка существования заказа
			if (!Validate::isLoadedObject($cart)) {
                Robokassa::validateAnsver($this->module->l('Cart does not exist ') . $ordernumber);
            }


			$total_to_pay = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
			//Проверка суммы заказа
			if ($amount != $total_to_pay) {
                Robokassa::validateAnsver($this->module->l('Incorrect payment summ ') . $amount);
            }

			$this->module->validateOrder((int)$cart->id, Configuration::get('PS_OS_PAYMENT'), $cart->getOrderTotal(true, Cart::BOTH),
			$this->module->displayName, null, array(), null, false, $cart->secure_key);
		}
		else
		{
			$order = new Order((int)$ordernumber);
			if (!Validate::isLoadedObject($order)) {
                Robokassa::validateAnsver($this->module->l('Order does not exis t') . $ordernumber);
            }

			$total_to_pay = number_format($order->total_paid, 2, '.', '');
			if ($amount != $total_to_pay) {
                Robokassa::validateAnsver($this->module->l('Incorrect payment summ ') . $amount);
            }

			//Смена статуса заказа
			$history = new OrderHistory();
			$history->id_order = $ordernumber;
			$history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $ordernumber);
			$history->addWithemail(true);
            Robokassa::validateAnsver($this->module->l('OK') . $ordernumber);
		}
	}
}