<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPNotif_Post_Notifications
{
    const ACTION = 'WPNotif_POST';
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
        add_action('wpnotif_admin_update_settings', array($this, 'update_settings'));
        add_action('wpnotif_notifications_settings', array($this, 'notification_settings'));


        add_action('save_post', array($this, 'save_post'), 1000, 1);
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }


    public function save_post($post_id)
    {

        $post = get_post($post_id);

        if (empty($post) || $post->post_status != 'publish') return;

        if (empty($_POST['wpnotif_send_notifications']) || $_POST['wpnotif_send_notifications'] != 1) return;


        /*if (!wp_verify_nonce($_POST['wpnotif_notification_nonce'], 'wpnotif_notification_nonce')) {
            return;
        }*/

        $_wpnotif_newsletter_created = get_post_meta($post_id, '_wpnotif_newsletter_created', true);
        if ($_wpnotif_newsletter_created == 1) {
            return;
        }
        $is_update = false;
        if (!empty($_REQUEST['wpnotif_post_status'])) {
            $current_status = $_REQUEST['wpnotif_post_status'];
            if ($current_status == 'publish') {
                $is_update = true;
            }
        }
        update_post_meta($post_id, '_wpnotif_newsletter_created', 1);


        $settings = json_decode($this->get_settings(), true);

        $post_type = $post->post_type;

        if (isset($settings[$post_type])) {

            $notification_details = $settings[$post_type];

            if ($notification_details['enable'] == 'on') {
                $name = 'Post - ' . $post->post_title;

                if ($is_update) {
                    $message = $notification_details['update_message'];
                } else {
                    $message = $notification_details['message'];
                }
                $group_data = explode(",", $notification_details['group']);


                $route = $notification_details['route'];
                if (empty($route)) {
                    $route = 1;
                }

                if (!empty($group_data)) {
                    $this->create_post_newsletter($post_id, $name, $message, $route, $group_data);
                }
            }
        }

    }

    public function get_settings()
    {
        return stripslashes(get_option("wpnotif_post_notifications"));
    }

    public function create_post_newsletter($post_ID, $name, $message, $route = 1, $group_data = null)
    {
        if (empty($message)) {
            return;
        }

        $user_group_instance = WPNotif_UserGroups::instance();
        $wp_group_data = $user_group_instance->get_wp_user_group();

        if (!$group_data && empty($wp_group_data)) return;

        if (empty($group_data)) {
            $user_group = array(WPNotif_NewsLetter::format_group_id($wp_group_data->id));
        } else {
            $user_group = $group_data;
        }

        $newsletter_instance = WPNotif_NewsLetter::instance();
        $data = array(
            'name' => $name,
            'message' => $message,
            'post_id' => $post_ID,
            'user_group_role' => implode(",", $user_group),
            'mobile_field_type' => '1',
            'mobile_field_key' => '',
            'route' => $route,
            'status' => WPNotif_NewsLetter::pending_status,
            'execution_time' => time()
        );

        return $newsletter_instance->create_newsletter($data);

    }

    public function ajax_post_notification()
    {
        return;
        if (!current_user_can('manage_options')) {
            echo '0';
            die();
        }
    }

    public function update_settings()
    {
        if (isset($_POST['wpnotif_post_msg_details'])) {
            update_option('wpnotif_post_notifications', $_POST['wpnotif_post_msg_details']);
        }
    }

    public function notification_settings()
    {
        $post_details = $this->get_settings();


        ?>
        <div class="wpnotif_admin_head"><span><?php esc_html_e('Post Notifications', 'wpnotif'); ?></span></div>

        <table class="form-table form-switch wpnotif-post_notifications">
            <tr>
                <th scope="row"><label><?php esc_html_e('Post Type', 'wpnotif'); ?></label></th>
                <td>
                    <select id="wpnotif_post_type">
                        <?php
                        foreach ($this->get_post_types() as $post_type) {
                            echo '<option value="' . esc_attr($post_type->name) . '">' . $post_type->label . '</option>';
                        } ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php echo sprintf(esc_html__('Enable for %s', 'wpnotif'), '<span class="wpnotif-notification_post_name"></span>'); ?></label>
                </th>
                <td>
                    <div class="input-switch">
                        <input type="checkbox" id="enable_post_notifications" name="enable_post_notifications"/>
                        <label for="enable_post_notifications"></label>
                        <span class="status_text yes">On</span>
                        <span class="status_text no">Off</span>
                    </div>
                </td>
            </tr>

            <tr class="wpnotif_post_msg_row">
                <th scope="row"><label><?php esc_html_e('Route', 'wpnotif'); ?></label></th>
                <td>
                    <select id="wpnotif_post_route" name="wpnotif_post_route">
                        <option value="1"><?php esc_attr_e('SMS', 'wpnotif'); ?></option>
                        <option value="1001"><?php esc_attr_e('WhatsApp', 'wpnotif'); ?></option>
                    </select>
                </td>
            </tr>

            <tr class="wpnotif_post_msg_row">
                <th scope="row"><label><?php esc_html_e('User Groups', 'wpnotif'); ?></label></th>
                <td>
                    <?php
                    WPNotif_NewsLetter::show_usergroup_list();
                    ?>
                </td>
            </tr>

            <tr class="wpnotif_post_msg_row">
                <th scope="row"><label
                            for="wpnotif_post_msg"><?php esc_html_e('New Post Message', 'wpnotif'); ?></label></th>
                <td>
                    <div class="position-relative">
                        <textarea id="wpnotif_post_msg" name="wpnotif_post_msg"></textarea>
                        <a href="https://help.unitedover.com/wpnotif/kb/placeholders" target="_blank">
                            <span class="placeholder_list"><?php esc_html_e('Placeholder List', 'wpnotif'); ?></span>
                        </a>
                    </div>
                </td>
            </tr>
            <tr class="wpnotif_post_msg_row">
                <th scope="row"><label
                            for="wpnotif_post_update_msg"><?php esc_html_e('Post Update Message', 'wpnotif'); ?></label>
                </th>
                <td>
                    <div class="position-relative">
                        <textarea id="wpnotif_post_update_msg" name="wpnotif_post_update_msg"></textarea>
                        <a href="https://help.unitedover.com/wpnotif/kb/placeholders" target="_blank">
                            <span class="placeholder_list"><?php esc_html_e('Placeholder List', 'wpnotif'); ?></span>
                        </a>
                    </div>
                </td>
            </tr>
        </table>
        <input type="hidden" id="wpnotif_post_msg_details" name="wpnotif_post_msg_details"
               value="<?php echo esc_attr($post_details); ?>"/>
        <?php

    }

    public function get_post_types()
    {
        return get_post_types(array(), 'objects');
    }

    public function add_meta_boxes()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        global $post;

        if (empty($post->ID) || $post->post_type == 'shop_order') {
            return;
        }

        $settings = json_decode($this->get_settings(), true);

        $post_type = $post->post_type;

        if (isset($settings[$post_type])) {

            $notification_details = $settings[$post_type];

            if ($notification_details['enable'] == 'on') {
                add_meta_box(
                    'wpnotif_notify_post',
                    esc_attr__('WPNotif', 'wpnotif'),
                    array($this, 'notification_meta_box'),
                    $post->post_type,
                    'side'
                );
            }
        }

    }

    public function notification_meta_box()
    {
        global $post;
        $this->post_notification_box($post);
    }

    public function post_notification_box($post)
    {
        if (empty($post->ID)) return;

        $checked = '';
        $status = $post->post_status;
        if (empty($status) || $status == 'draft') {
            $checked = 'checked';
        }

        if (!isset($_GET['message'])) {
            delete_post_meta($post->ID, '_wpnotif_newsletter_created');
        }
        ?>
        <div class="wpnotif_post_meta_box">
            <input type="hidden" name="wpnotif_post_status" value="<?php echo esc_attr($status); ?>"/>
            <input type="hidden" name="wpnotif_notification_nonce" class="wpnotif_notification_nonce"
                   value="<?php echo esc_attr(wp_create_nonce('wpnotif_notification_nonce')); ?>"/>
            <label>
                <span class="components-checkbox-control__input-container">
                    <input id="wpnotif_send_notifications"
                           class="components-checkbox-control__input wpnotif_send_notifications"
                           type="checkbox" value="1"
                           name="wpnotif_send_notifications" <?php echo $checked; ?>/>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 24 24" width="24" height="24" role="img"
                         class="components-checkbox-control__checked" aria-hidden="true" focusable="false"><path
                                d="M15.3 5.3l-6.8 6.8-2.8-2.8-1.4 1.4 4.2 4.2 8.2-8.2"></path></svg>
                </span>

                <?php esc_html_e('Send Notification to users', 'wpnotif'); ?>
            </label>
        </div>

        <?php
    }


}