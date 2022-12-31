<?php


if (!defined('ABSPATH')) {
    exit;
}


function wpn_doCurl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $data = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $data;
}
function wpnotif_install_addons($plugin, $slug)
{
    wpnotif_modify_addons($plugin, $slug, 1);
}

function wpnotif_modify_addons($plugin, $slug, $type)
{
    if (!current_user_can('manage_options')) {
        die();
    }

    include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
    include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');


    if ($type == -1) {

        deactivate_plugins($plugin);
        wp_ajax_delete_plugin();
        die();
    } else {

        $digpc = get_option('wpnotif_purchasecode');
        if (empty($digpc)) {
            wpnotif_send_error(array('errorMessage' => __('Please enter a valid purchase code', 'wpnotif')));
            die();
        }

        $status = array(
            'install' => 'plugin',
            'slug' => sanitize_key(wp_unslash($slug)),
        );

        if (!current_user_can('install_plugins')) {
            $status['errorMessage'] = __('Sorry, you are not allowed to install plugins on this site.');
            wpnotif_send_error($status);
        }

        if (is_wp_error(validate_plugin($plugin))) {
            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);


            $checkPurchase = wpn_doCurl('https://bridge.unitedover.com/updates/?action=get_metadata&slug=' . $slug . '&license_key=' . $digpc . '&request_site=' . network_home_url());

            if (!isset($checkPurchase['download_url'])) {
                $status['errorMessage'] = __('Error while verifying your addon license.', 'wpnotif');
                wpnotif_send_error($status);
            }

            $result = $upgrader->install('https://bridge.unitedover.com/updates/?action=download&slug=' . $slug . '&license_key=' . $digpc . '&request_site=' . wpn_network_home_url());

            if (is_wp_error($result)) {
                $status['errorCode'] = $result->get_error_code();
                $status['errorMessage'] = $result->get_error_message();
                wpnotif_send_error($status);
            } elseif (is_wp_error($skin->result)) {
                $status['errorCode'] = $skin->result->get_error_code();
                $status['errorMessage'] = $skin->result->get_error_message();
                wpnotif_send_error($status);
            } elseif ($skin->get_errors()->get_error_code()) {
                $status['errorMessage'] = $skin->get_error_messages();
                wpnotif_send_error($status);
            } elseif (is_null($result)) {
                global $wp_filesystem;

                $status['errorCode'] = 'unable_to_connect_to_filesystem';
                $status['errorMessage'] = __('Unable to connect to the filesystem. Please confirm your credentials.');

                // Pass through the error from WP_Filesystem if one was raised.
                if ($wp_filesystem instanceof WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code()) {
                    $status['errorMessage'] = esc_html($wp_filesystem->errors->get_error_message());
                }

                wpnotif_send_error($status);
            }


        }

        if ($type == 10) {
            wp_ajax_update_plugin();
        } else {
            $result = activate_plugin($plugin);
            if (is_wp_error($result)) {
                $status['errorCode'] = $result->get_error_code();
                $status['errorMessage'] = $result->get_error_message();
                wpnotif_send_error($status);
            }
            wpnotif_send_success($status);
        }

    }


}


function wpnotif_send_error($status)
{
    if (wpn_is_doing_ajax()) {
        wp_send_json_error($status);
    } else {
        wp_die($status['errorMessage']);
    }
}

function wpnotif_send_success($status)
{
    if (wpn_is_doing_ajax()) {

    } else {

    }
}


function wpn_is_doing_ajax()
{
    return defined('DOING_AJAX') && DOING_AJAX ? true : false;
}