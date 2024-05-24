<?php
/**
* Success controller
*/

class RobokassasuccessModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_footer = false;
    public $ssl = false;

	public function initContent()
	{
		parent::initContent();

		$ordernumber = Tools::getValue('InvId');
		$this->context->smarty->assign('ordernumber', $ordernumber);

        if (Configuration::get('robokassa_postvalidate')) {
            if (!$ordernumber) {
                Robokassa::validateAnsver($this->module->l('Cart number is not set') . $ordernumber);
            }

            $cart = new Cart((int)$ordernumber);
            if (!Validate::isLoadedObject($cart)) {
                Robokassa::validateAnsver($this->module->l('Cart does not exist'));
            }

            if (!($ordernumber = Order::getOrderByCartId($cart->id))) {
                $this->setTemplate('module:robokassa/views/templates/front/waitingPayment.tpl');
                return;
            }
        }


		if (!$ordernumber) {
            Robokassa::validateAnsver($this->module->l('Order number is not set ') . $ordernumber);
        }

		$order = new Order((int)$ordernumber);
		if (!Validate::isLoadedObject($order)) {
            Robokassa::validateAnsver($this->module->l('Order does not exist') . $ordernumber);
        }

		$customer = new Customer((int)$order->id_customer);


/*		if ($customer->id != $this->context->cookie->id_customer) {
            Robokassa::validateAnsver($this->module->l('You are not logged in ' . $customer->id . ' 2:' . $this->context->cookie->id_customer));
        }*/

		if ($order->hasBeenPaid()) {
            Tools::redirectLink(__PS_BASE_URI__ . 'order-confirmation.php?key=' . $customer->secure_key . '&id_cart=' . $order->id_cart .
                '&id_module=' . $this->module->id . '&id_order=' . $order->id);
        } else {
            $this->setTemplate('module:robokassa/views/templates/front/waitingPayment.tpl');
        }
	}
}