<?php

defined('ABSPATH') || exit;


WPNotif_Gateway::instance();

class WPNotif_Gateway
{
    protected static $_instance = null;


    /**
     *  Constructor.
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        require_once 'gateway_list.php';
        require_once plugin_dir_path(__DIR__) . 'gateways/app/handler.php';
        require_once plugin_dir_path(__DIR__) . 'gateways/wa.php';

        add_filter('wpnotif_sms_gateways', array($this, 'add_gateways'), 100);

        add_filter('wpnotif_group_gateways_list', array($this, 'group_gateways_list'), 100);


    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function group_gateways_list($gateways)
    {
        $groups = array();

        $groups['starting_group'] = array();

        $default_group = __('Alphabetical Order');
        foreach ($gateways as $key => $gateway) {
            $group = isset($gateway['group']) ? $gateway['group'] : $default_group;

            $groups[$group][$key] = $gateway;
        }


        ksort($groups[$default_group]);
        return $groups;
    }


    public function add_gateways($smsgateways)
    {

        $custom_gateway = $this->custom_gateway();
        $sms_gateway = $this->sms_gateway();

        return array_merge($smsgateways, $custom_gateway, $sms_gateway);
    }


    public function sms_gateway()
    {
        $src = admin_url('admin-ajax.php');

        $data = array('nonce' => wp_create_nonce('wpnotif_qrcode'), 'action' => 'wpnotif_get_qrcode');
        $big_src = add_query_arg($data, $src);

        $data['preview'] = 1;
        $src = add_query_arg($data, $src);

        $key = WPNotif_App_Handler::instance()->get_data();

        return array(
           
        );
    }

    public function custom_gateway()
    {

        $placeholder = 'to:{to}, message:{message}, sender:{sender_id}, template id:{template_id}';
        $desc = '<i>' . __('Enter Parameters separated by "," and values by ":"') . '</i><br />';
        $desc .= 'To : {to}<br /> Message : {message}<br /> Sender ID : {sender_id}<br /> Template ID: {template_id}';
        $apiroute = 'https://sms.godigitalda.com/api/v3/sms/send?recipient={to}&sender_id={sender_id}&type=plain&message={message}';

        return array(
            'custom_gateway' => array(
                'value' => 900,
                'group' => esc_attr__('GO SMS'),
                'label' => esc_attr__('GO SMS'),
                'inputs' => array(
                    __('SMS Gateway URL') => array('text' => true, 'name' => 'gateway_url', 'placeholder' => $apiroute),
                    __('HTTP Header') => array('textarea' => true, 'name' => 'http_header', 'rows' => 3, 'optional' => 1, 'desc' => esc_attr__('Add developer API Token ex: (1|QdMP8VdBLrUmkwLe530...)')),
                    __('HTTP Method') => array('select' => true, 'name' => 'http_method', 'options' => array('GET' => 'GET', 'POST' => 'POST')),
                    __('Gateway Parameters') => array('textarea' => true, 'name' => 'gateway_attributes', 'rows' => 6, 'desc' => $desc, 'placeholder' => $placeholder),
                    __('Send as Body Data') => array('select' => true, 'name' => 'send_body_data', 'options' => array('No' => 0, 'Yes' => 1)),
                    __('Encode Message') => array('select' => true, 'name' => 'encode_message', 'options' => array(__('No') => 0, __('URL Encode') => 1, __('URL Raw Encode') => 3, __('Convert To Unicode') => 2)),
                    __('Phone Number') => array('select' => true, 'name' => 'phone_number', 'options' => array(__('with only country code') => 2, __('with + and country code') => 1, __('without country code') => 3)),
                    __('Sender ID') => array('text' => true, 'name' => 'sender_id', 'optional' => 1, 'desc' => esc_attr__('O ID do remetente deve ser o mesmo que o da plataforma, caso contrário, ocorrerá um erro')),
                ),
            ),
        );
    }
}