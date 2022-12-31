<?php

defined('ABSPATH') || exit;

add_filter('wpnotif_whatsapp_gateway_inputs', 'wpnotif_whatsapp_custom_gateway_inputs');

function wpnotif_whatsapp_custom_gateway_inputs($wa_inputs)
{
    $wa_key = 12;
    $name_prefix = 'wa_custom_';
    $placeholder = 'to:{to}, message:{message}, sender:{sender_id}, template id:{template_id}';
    $desc = '<i>' . __('Enter Parameters separated by "," and values by ":"') . '</i><br />';
    $desc .= 'To : {to}<br /> Message : {message}<br /> Sender ID : {sender_id}<br /> Template ID: {template_id}';

    $inputs = array(
        __('WhatsApp Gateway URL') => array('text' => true, 'name' => 'gateway_url', 'placeholder' => 'https://www.example.com/whatsapp/send'),
        __('HTTP Header') => array('textarea' => true, 'name' => 'http_header', 'rows' => 3, 'optional' => 1, 'desc' => esc_attr__('Headers separated by ","')),
        __('HTTP Method') => array('select' => true, 'name' => 'http_method', 'options' => array('GET' => 'GET', 'POST' => 'POST')),
        __('Gateway Parameters') => array('textarea' => true, 'name' => 'gateway_attributes', 'rows' => 6, 'desc' => $desc, 'placeholder' => $placeholder),
        __('Send as Body Data') => array('select' => true, 'name' => 'send_body_data', 'options' => array('No' => 0, 'Yes' => 1)),
        __('Encode Message') => array('select' => true, 'name' => 'encode_message', 'options' => array(__('No') => 0, __('URL Encode') => 1, __('URL Raw Encode') => 3, __('Convert To Unicode') => 2)),
        __('Phone Number') => array('select' => true, 'name' => 'phone_number', 'options' => array(__('with only country code') => 2, __('with + and country code') => 1, __('without country code') => 3)),
        __('Sender ID') => array('text' => true, 'name' => 'sender_id', 'optional' => 1),
    );

    foreach ($inputs as $label => $input) {
        $input['label'] = $label;
        $input['name'] = $name_prefix . $input['name'];
        $input['value'] = $wa_key;
        $input['show_if'] = 'whatsapp_gateway_' . $wa_key;

        $wa_inputs[] = $input;
    }

    return $wa_inputs;
}