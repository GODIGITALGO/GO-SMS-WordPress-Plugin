<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('untdovr_gateway_field_label')) {
    function untdovr_gateway_field_label($field)
    {
        return $field;
    }
}

if (!function_exists('untdovr_add_gateway')) {

    add_filter('unitedover_sms_gateways', 'untdovr_add_gateway');
    function untdovr_add_gateway($gateways)
    {
        return array_merge($gateways, untdovr_additional_gateways_list());
    }

    function untdovr_additional_gateways_list()
    {
        return array(
          
        );
    }


}