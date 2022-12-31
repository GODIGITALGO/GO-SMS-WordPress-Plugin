<?php


defined('ABSPATH') || exit;


WPNotif_Admin_Functions::instance();

final class WPNotif_Admin_Functions
{
    protected static $_instance = null;

    /**
     *  Constructor.
     */
    public function __construct()
    {
        $this->admin_ajax_hooks();
    }

    public function admin_ajax_hooks()
    {
        add_action('wp_ajax_wpnotif_user_list', array($this, 'user_list'));
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function user_list()
    {
        $this->check_access('wpnotif_user_list');

        $search = esc_attr(sanitize_text_field($_REQUEST['search']));
        $manager_roles = array();

        $data = array();

        $user_roles_opt = array();


        $user_roles = array();
        foreach (wp_roles()->roles as $rkey => $rvalue) {

            $role_name = $rvalue['name'];
            if (empty($search)) {
                if ((isset($rvalue['capabilities']['level_5']) && $rvalue['capabilities']['level_5'] == 1)) {
                    $manager_roles[] = $rkey;
                }
            } else if (stripos($rkey, $search) === false && stripos($role_name, $search) === false) {
                continue;
            }


            $user_roles[] = array(
                'id' => $rkey,
                'text' => $role_name);
        }

        $user_roles_opt['text'] = esc_attr__('User Role', 'wpnotif');
        $user_roles_opt['children'] = $user_roles;

        $data['results'][] = $user_roles_opt;


        $query = array(
            'fields' => array('ID', 'user_login'),
            'number' => 30
        );


        if (!empty($search) && strlen($search) >= 2) {
            $query['search'] = '*' . $search . '*';
            $query['search_columns'] = array(
                'user_login',
                'display_name',
                'user_email',
            );


            $find_users = new WP_User_Query($query);

            $user_opt = array();
            $users_result = array();

            foreach ($find_users->get_results() as $user) {
                $users_result[] = array(
                    'id' => 'notify_user_' . $user->ID,
                    'text' => $user->user_login);
            }

            $user_opt['text'] = esc_attr__('Users', 'wpnotif');
            $user_opt['children'] = $users_result;

            $data['results'][] = $user_opt;
        }

        wp_send_json($data);
    }

    public function check_access($action)
    {
        check_ajax_referer($action, 'nonce', true);

        if (!current_user_can('manage_options') || !is_user_logged_in()) {
            die();
        }
    }

}