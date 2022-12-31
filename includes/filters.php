<?php
defined('ABSPATH') || exit;


add_filter('wpnotif_filter_message', 'wpnotif_add_tracking_details', 10, 2);


function wpnotif_add_tracking_details($msg, $order)
{

    $tracking_link_string = '';

    if (class_exists('YITH_Tracking_Data')) {
        $values = array();
        $yith_placeholders = apply_filters('ywsn_sms_placeholders', array(), $order);
        /* foreach ($yith_placeholders as $key => $value) {
             $values['{'.$key.'}'] = $value;
         }
         $msg = strtr($msg, $values);*/

        if (isset($yith_placeholders['{tracking_url}'])) {
            $tracking_link_string = $yith_placeholders['{tracking_url}'];
        }

    } else {

        try {
            $tracking_links = wpnotif_get_wc_tracking_links($order);
        } catch (Exception $e) {
            $tracking_links = array();
        }
        if (!empty($tracking_links)) {
            $tracking_link_string = implode(',', $tracking_links);
        }
    }

    $msg = str_replace('{{wc-tracking-link}}', $tracking_link_string, $msg);

    return $msg;
}

function wpnotif_get_wc_tracking_links($order)
{
    $tracking_links = array();

    try {
        if (class_exists('WC_Shipment_Tracking_Actions')) {
            $st = WC_Shipment_Tracking_Actions::get_instance();
            $order_number = $order->get_id();

            if (!empty($order_number)) {
                $tracking_items = $st->get_tracking_items($order_number);
                if (!empty($tracking_items)) {
                    $tracking_links = array();
                    foreach ($tracking_items as $tracking_item) {
                        $formated_item = $st->get_formatted_tracking_item($order_number, $tracking_item);
                        $tracking_links[] = htmlspecialchars_decode($formated_item['formatted_tracking_link']);
                        break;
                    }

                }
            }
        } else if (class_exists('WC_Advanced_Shipment_Tracking_Actions')) {
            if (function_exists('ast_get_tracking_items')) {
                $order_id = $order->get_id();
                $tracking_items = ast_get_tracking_items($order_id);
                foreach ($tracking_items as $tracking_item) {
                    $tracking_number = $tracking_item['tracking_number'];
                    $tracking_provider = $tracking_item['formatted_tracking_provider'];
                    $tracking_url = $tracking_item['formatted_tracking_link'];

                    $tracking_links[] = htmlspecialchars_decode($tracking_url);
                }
            }
        } else {
            return array();
        }
    } catch (Exception $e) {
        return $tracking_links;
    }

    return $tracking_links;
}

function wpn_split_message_template($message)
{
    $sms_attr = '##SMS##';
    $whatsapp_attr = '##WHATSAPP##';

    $whatsapp_pos = stripos($message, $whatsapp_attr);
    $sms_pos = stripos($message, $sms_attr);
    if ($sms_pos === false || $whatsapp_pos === false) {
        return $message;
    }

    $sms_attr_length = strlen($sms_attr);
    $whatsapp_attr_length = strlen($whatsapp_attr);

    $sms = substr($message, $sms_pos + $sms_attr_length, $whatsapp_pos - $sms_attr_length);
    $whatsapp = substr($message, $whatsapp_pos + $whatsapp_attr_length);

    $data = array(
        'sms' => trim($sms),
        'whatsapp' => trim($whatsapp)
    );
    return $data;
}


if (!function_exists('wpn_parse_message_template')) {
    function wpn_parse_message_template($message, $template_ids)
    {
        $value_separator = ':';
        $use_arrow = strpos($message, '=>') !== false;
        if ($use_arrow) {
            $value_separator = '=>';
        }

        $message_values = explode("\n", $message);
        $params = array();
        $template = array();
        $last_id = false;
        foreach ($message_values as $attr) {
            if (empty($attr)) continue;
            $obj = explode($value_separator, $attr, 2);
            if (sizeof($obj) !== 2) {
                /*continuation of previous variable*/
                if (!$last_id) {
                    continue;
                }
                if (in_array($last_id, $template_ids)) {
                    $template[$last_id] = $template[$last_id] . PHP_EOL . $attr;
                } else {
                    $params[$last_id] = $params[$last_id] . PHP_EOL . $attr;
                }
            } else {
                $id = trim($obj[0]);
                $value = trim($obj[1]);
                $last_id = $id;
                if (in_array($id, $template_ids)) {
                    $template[$id] = $value;
                } else {
                    $params[$id] = $value;
                }
            }
        }

        if (empty($template) && empty($params)) {
            return $message;
        }
        return array(
            'template' => $template,
            'params' => $params
        );
    }
}
