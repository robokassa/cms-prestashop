<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Robokassa extends PaymentModule
{
    private $html = '';
    private $post_errors = array();

    public static $sno = array(
        1 => array('id' => 'osn', 'name' => 'Общая СН'),
        2 => array('id' => 'usn_income', 'name' => 'Упрощенная СН (доходы)'),
        3 => array('id' => 'usn_income_outcome', 'name' => 'Упрощенная СН (доходы минус расходы)'),
        4 => array('id' => 'esn', 'name' => 'Единый сельскохозяйственный налог'),
        5 => array('id' => 'patent', 'name' => 'Патентная СН'),
    );

    public static $payment_method = array(
        1 => array('id' => 'full_prepayment', 'name' => 'Предоплата 100%'),
        2 => array('id' => 'prepayment', 'name' => 'Частичная предоплата'),
        3 => array('id' => 'advance', 'name' => 'Аванс'),
        4 => array('id' => 'full_payment', 'name' => 'Полный расчёт'),
        5 => array('id' => 'partial_payment', 'name' => 'Частичный расчёт и кредит'),
        6 => array('id' => 'credit', 'name' => 'Передача в кредит'),
        7 => array('id' => 'credit_payment', 'name' => 'Оплата кредита'),
    );

    public static $payment_object = array(
        1 => array('id' => 'commodity', 'name' => 'Товар'),
        2 => array('id' => 'excise', 'name' => 'Подакцизный товар'),
        3 => array('id' => 'job', 'name' => 'Работа'),
        4 => array('id' => 'service', 'name' => 'Услуга'),
    );

    public static $tax = array(
    1 => array('id' => 'none', 'name' => 'Без НДС'),
    2 => array('id' => 'vat0', 'name' => 'НДС по ставке 0%'),
    3 => array('id' => 'vat10', 'name' => 'НДС чека по ставке 10%'),
    4 => array('id' => 'vat110', 'name' => 'НДС чека по расчетной ставке 10/110'),
    5 => array('id' => 'vat20', 'name' => 'НДС чека по ставке 20%'),
    6 => array('id' => 'vat120', 'name' => 'НДС чека по расчетной ставке 20/120'),
    7 => array('id' => 'vat5', 'name' => 'НДС по ставке 5%'),
    8 => array('id' => 'vat105', 'name' => 'НДС чека по расчетной ставке 5/105'),
    9 => array('id' => 'vat7', 'name' => 'НДС по ставке 7%'),
    10 => array('id' => 'vat107', 'name' => 'НДС чека по расчетной ставке 7/107'),
);

    public static $country = array(
        1 => array('id' => 'RU', 'name' => 'Россия'),
        2 => array('id' => 'KZ', 'name' => 'Казахстан'),
    );

    public function __construct()
    {
        $this->name = 'robokassa';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Robokassa';
        $this->need_instance = 1;

        $this->bootstrap = true;

        $this->controllers = array('redirect', 'success', 'validation');

        //Привязвать к валюте
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('Robokassa');
        $this->description = $this->l('Service to receive payments by plastic cards, in every e-currency, using mobile commerce');
    }

    public function install()
    {
        return (parent::install()
            && Configuration::updateValue('robokassa_demo', '1')
            && Configuration::updateValue('robokassa_fiscalization', '1')
            && Configuration::updateValue('robokassa_hash_algo', 'md5')
            && $this->registerHook('displayOrderDetail')
            && $this->registerHook('displayPayment')
            && $this->registerHook('displayPaymentReturn')
            && $this->registerHook('paymentOptions')
            && $this->addPLStatuses()
        );
    }

    public function uninstall()
    {
        return (parent::uninstall()
            && Configuration::deleteByName('robokassa_login')
            && Configuration::deleteByName('robokassa_password1')
            && Configuration::deleteByName('robokassa_password2')
            && Configuration::deleteByName('robokassa_demo')
            && Configuration::deleteByName('robokassa_fiscalization')
            && Configuration::deleteByName('robokassa_postvalidate')
            && Configuration::deleteByName('robokassa_hash_algo')
        );
    }

    public function hookPaymentOptions()
    {

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText($this->l('Robokassa payment'))
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', [], true));
/*            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'));*/

        $payment_options = [
            $newOption,
        ];
        return $payment_options;
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitrobokassa')) {
            $this->postValidation();
            if (!count($this->post_errors))
                $this->postProcess();
            else
                foreach ($this->post_errors as $err)
                    $this->html .= $this->displayError($err);
        }
        $this->html .= $this->renderForm();
        return $this->html;
    }

    public function renderForm()
    {
        $this->fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
                'image' => _PS_ADMIN_IMG_ . 'information.png'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Merchant login'),
                    'name' => 'robokassa_login',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Password #1'),
                    'name' => 'robokassa_password1',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Password #2'),
                    'name' => 'robokassa_password2',
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('country'),
                    'name' => 'robokassa_country',
                    'options' => array(
                        'query' => self::$country,
                        'id' => 'id',
                        'name' => 'name',
                    ),
                    'value_default' => 'RU',
                    'validation' => 'isString',
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Demo mode'),
                    'desc' => $this->l('Set this mode to disabled for switch to production mode'),
                    'name' => 'robokassa_demo',
                    'values' => array(
                        array(
                            'id' => 'robokassa_demo_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'robokassa_demo_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Fiscalization'),
                    'name' => 'robokassa_fiscalization',
                    'values' => array(
                        array(
                            'id' => 'robokassa_fiscalization_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'robokassa_fiscalization_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('sno'),
                    'name' => 'robokassa_sno',
                    'options' => array(
                        'query' => self::$sno,
                        'id' => 'id',
                        'name' => 'name',
                    ),
                    'value_default' => 'osn',
                    'validation' => 'isString',
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('payment_method'),
                    'name' => 'robokassa_payment_method',
                    'options' => array(
                        'query' => self::$payment_method,
                        'id' => 'id',
                        'name' => 'name',
                    ),
                    'value_default' => 'full_prepayment',
                    'validation' => 'isString',
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('payment_object'),
                    'name' => 'robokassa_payment_object',
                    'options' => array(
                        'query' => self::$payment_object,
                        'id' => 'id',
                        'name' => 'name',
                    ),
                    'value_default' => 'commodity',
                    'validation' => 'isString',
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('tax'),
                    'name' => 'robokassa_tax',
                    'options' => array(
                        'query' => self::$tax,
                        'id' => 'id',
                        'name' => 'name',
                    ),
                    'value_default' => 'none',
                    'validation' => 'isString',
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Order after payment'),
                    'name' => 'robokassa_postvalidate',
                    'desc' => $this->l('Create order after receive payment notification'),
                    'values' => array(
                        array(
                            'id' => 'robokassa_postvalidate_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'robokassa_postvalidate_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                ),
            ),
            'submit' => array(
                'name' => 'submitrobokassa',
                'title' => $this->l('Save')
            ),
        );
        $this->fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Merchant configuration information'),
                'image' => _PS_ADMIN_IMG_ . 'information.png'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Result URL'),
                    'desc' => $this->l('Used for payment notification.'),
                    'name' => 'url1',
                    'size' => 120,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Success URL'),
                    'desc' => $this->l('URL to be used for query in case of successful payment.'),
                    'name' => 'url2',
                    'size' => 120,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Fail URL'),
                    'desc' => $this->l('URL to be used for query in case of failed payment.'),
                    'name' => 'url3',
                    'size' => 120,
                )
            )
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitrobokassa';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name .
            '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );
        return $helper->generateForm($this->fields_form);
    }

    public function getConfigFieldsValues()
    {
        $fields_values = array();
        $fields_values['robokassa_login'] = Configuration::get('robokassa_login');
        $fields_values['robokassa_password1'] = Configuration::get('robokassa_password1');
        $fields_values['robokassa_password2'] = Configuration::get('robokassa_password2');
        $fields_values['robokassa_demo'] = Configuration::get('robokassa_demo');
        $fields_values['robokassa_fiscalization'] = Configuration::get('robokassa_fiscalization');
        $fields_values['robokassa_postvalidate'] = Configuration::get('robokassa_postvalidate');
        $fields_values['robokassa_hash_algo'] = Configuration::get('robokassa_hash_algo');
        $fields_values['robokassa_sno'] = Configuration::get('robokassa_sno');
        $fields_values['robokassa_payment_method'] = Configuration::get('robokassa_payment_method');
        $fields_values['robokassa_payment_object'] = Configuration::get('robokassa_payment_object');
        $fields_values['robokassa_tax'] = Configuration::get('robokassa_tax');
        $fields_values['robokassa_country'] = Configuration::get('robokassa_country');


        ///////////////////////////////////////////////////////////
        $usessl = (bool)Configuration::get('PS_SSL_ENABLED');
        ///////////////////////////////////////////////////////////
        $fields_values['url1'] = $this->context->link->getModuleLink('robokassa', 'validation', array(), $usessl);
        $fields_values['url2'] = $this->context->link->getModuleLink('robokassa', 'success', array(), $usessl);
        $fields_values['url3'] = $this->context->link->getPageLink('history.php', $usessl, null, null);

        return $fields_values;
    }

    private function postValidation()
    {
        if (Tools::getValue('robokassa_login') && (!Validate::isString(Tools::getValue('robokassa_login'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('Merchant login');
        if (Tools::getValue('robokassa_password1') && (!Validate::isString(Tools::getValue('robokassa_password1'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('Password #1');
        if (Tools::getValue('robokassa_password2') && (!Validate::isString(Tools::getValue('robokassa_password2'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('Password #2');
        if (Tools::getValue('robokassa_demo') && (!Validate::isBool(Tools::getValue('robokassa_demo'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('Demo mode');
        if (Tools::getValue('robokassa_hash_algo') && (!Validate::isString(Tools::getValue('robokassa_hash_algo'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('hashing algorhythm');
        if (Tools::getValue('robokassa_fiscalization') && (!Validate::isString(Tools::getValue('robokassa_fiscalization'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('Fiscalization');
        if (Tools::getValue('robokassa_sno') && (!Validate::isString(Tools::getValue('robokassa_sno'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('sno');
        if (Tools::getValue('robokassa_payment_method') && (!Validate::isString(Tools::getValue('robokassa_payment_method'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('payment_method');
        if (Tools::getValue('robokassa_payment_object') && (!Validate::isString(Tools::getValue('robokassa_payment_object'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('payment_object');
        if (Tools::getValue('robokassa_tax') && (!Validate::isString(Tools::getValue('robokassa_tax'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('tax');
        if (Tools::getValue('robokassa_country') && (!Validate::isString(Tools::getValue('robokassa_country'))))
            $this->post_errors[] = $this->l('Invalid') . ' ' . $this->l('country');
    }

    private function postProcess()
    {
        Configuration::updateValue('robokassa_login', Tools::getValue('robokassa_login'));
        Configuration::updateValue('robokassa_password1', Tools::getValue('robokassa_password1'));
        Configuration::updateValue('robokassa_password2', Tools::getValue('robokassa_password2'));
        Configuration::updateValue('robokassa_demo', Tools::getValue('robokassa_demo'));
        Configuration::updateValue('robokassa_fiscalization', Tools::getValue('robokassa_fiscalization'));
        Configuration::updateValue('robokassa_postvalidate', Tools::getValue('robokassa_postvalidate'));
        Configuration::updateValue('robokassa_hash_algo', Tools::getValue('robokassa_hash_algo'));
        Configuration::updateValue('robokassa_sno', Tools::getValue('robokassa_sno'));
        Configuration::updateValue('robokassa_payment_method', Tools::getValue('robokassa_payment_method'));
        Configuration::updateValue('robokassa_payment_object', Tools::getValue('robokassa_payment_object'));
        Configuration::updateValue('robokassa_tax', Tools::getValue('robokassa_tax'));
        Configuration::updateValue('robokassa_country', Tools::getValue('robokassa_country'));
        $this->html .= $this->displayConfirmation($this->l('Settings updated.'));
    }

    /*Проверка валюты
    * @return bool
    */
    private function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**Отображение ответа валидации уведомления
     * @return html
     */
    public static function validateAnsver($message)
    {
        Logger::addLog('robokassa: '.$message);
        die($message);
    }
}
