<?php
/**
* Redirect controller.
*/

class RobokassaredirectModuleFrontController extends ModuleFrontController
{
	public $display_header = false;
	public $display_column_left = false;
	public $display_column_right = false;
	public $display_footer = false;
	public $ssl = true;

	public function initContent()
	{
		if ($id_cart = Tools::getValue('id_cart'))
		{
			$my_cart = new Cart($id_cart);
			if (!Validate::isLoadedObject($my_cart)) {
                $my_cart = $this->context->cart;
            }
		} else {
            $my_cart = $this->context->cart;
        }

		if ($ordernumber = Order::getOrderByCartId($my_cart->id))
		{
			$order = new Order((int)$ordernumber);
			if ($order->hasBeenPaid())
			{
				Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$order->secure_key.'&id_cart='.$order->id_cart.
					'&id_module='.$this->module->id.'&id_order='.$order->id);
				return;
			}
		}

        $currency = new Currency($my_cart->id_currency);

		$total_to_pay = number_format($my_cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');

		if ($postvalidate = Configuration::get('robokassa_postvalidate')) {
            $order_number = $my_cart->id;
        } else {
            if (!($order_number = Order::getOrderByCartId($my_cart->id))) {
                $order_status = Configuration::get('PL_OS_WAITPAYMENT');

                if (!$order_status || !OrderState::existsInDatabase($order_status, 'order_state')) {
                    $orderState = new OrderState();
                    $orderState->name = array((int)Configuration::get('PS_LANG_DEFAULT') => 'Ожидание оплаты');


                    if ($orderState->add()) {
                        Configuration::updateValue('PL_OS_WAITPAYMENT', $orderState->id);
                        $order_status = $orderState->id;
                    } else {
                        die('Ошибка: Не удалось создать статус заказа.');
                    }
                }

                $this->module->validateOrder(
                    (int)$my_cart->id,
                    $order_status,
                    $my_cart->getOrderTotal(true, Cart::BOTH),
                    $this->module->displayName,
                    null,
                    array(),
                    null,
                    false,
                    $my_cart->secure_key
                );
                $order_number = $this->module->currentOrder;
            }
        }

        $receiptData = $this->getReceiptData($my_cart);
        $redirect_url = $this->getRedirectUrl();
        $receipt_part = $this->getReceiptPart($receiptData);
        $signature = $this->generateSignature($total_to_pay, $order_number, $receipt_part);

		$customer = new Customer($my_cart->id_customer);

		$this->context->smarty->assign(array(
			'robokassa_login' => Configuration::get('robokassa_login'),
			'robokassa_demo' => Configuration::get('robokassa_demo'),
			'signature' => Tools::strtoupper($signature),
            'fiscalization' => Configuration::get('robokassa_fiscalization'),
            'redirect_url' => $redirect_url,
			'email' => $customer->email,
            'receiptData' => urlencode(json_encode($receiptData)),
			'OutSumCurrency' => ($currency->iso_code == 'RUB' ? false : $currency->iso_code),
			'postvalidate' => $postvalidate,
			'order_number' => $order_number,
			'total_to_pay' => $total_to_pay,
             'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));

        return $this->setTemplate('module:robokassa/views/templates/front/redirect.tpl');
    }


    private function getReceiptData($cart)
    {
        $receiptData = array();

        $receiptData['sno'] = Configuration::get('robokassa_sno');

        $products = $cart->getProducts();
        foreach ($products as $product) {
            $item = array(
                'name'    => $product['name'],
                'quantity' => floatval($product['quantity']),
                'cost'   => sprintf('%0.2F', $product['total_wt']),
                'payment_method'   => Configuration::get('robokassa_payment_method'),
                'payment_object'   => Configuration::get('robokassa_payment_object'),
                'tax'      => Configuration::get('robokassa_tax'),
            );
            $features_list = Product::getFrontFeaturesStatic(Context::getContext()->language->id, $product['id_product']);
            foreach ($features_list as $features) {
                if ($features['name'] === 'spic') {
                    $item['spic'] = $features['value'];
                } elseif ($features['name'] === 'packagecode') {
                    $item['packagecode'] = $features['value'];
                }
            }

            $receiptData['items'][] = $item;
        }
        $deliveryCost = $cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        if ($deliveryCost > 0) {
            $ship_item = array(
                'name'    => $this->l('Delivery'),
                'quantity' => 1,
                'sum'    => sprintf('%0.2F', $deliveryCost),
                'payment_method'   => Configuration::get('robokassa_payment_method'),
                'payment_object'   => Configuration::get('robokassa_payment_object'),
                'tax'      => Configuration::get('robokassa_tax'),
            );

            $receiptData['items'][] = $ship_item;
        }

        return $receiptData;
    }

    private function getReceiptPart($receiptData)
    {
        $fiscalization = Configuration::get('robokassa_fiscalization');
        if ($fiscalization == '1') {
            return urlencode(json_encode($receiptData));
        }

        return '';
    }

    private function getRedirectUrl()
    {
        $country = Configuration::get('robokassa_country');

        if ($country == 'KZ') {
            return 'https://auth.robokassa.kz/Merchant/Index.aspx';
        } else {
            return 'https://auth.robokassa.ru/Merchant/Index.aspx';
        }
    }

    private function generateSignature($total_to_pay, $order_number, $receipt_part)
    {
        $signature_base = Configuration::get('robokassa_login') . ':' .
            $total_to_pay . ':' .
            $order_number;

        if ($receipt_part) {
            $signature_base .= ':' . $receipt_part;
        }

        $signature_base .= ':' . Configuration::get('robokassa_password1') . ':shp_label=official_prestashop';

        return md5($signature_base);
    }

}