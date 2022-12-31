<?php

defined('ABSPATH') || exit;

final class WPNotif_UserGroup_Import
{
    const action = 'wpnotif_import_user';
    protected static $_instance = null;

    public $valid = 0;
    public $invalid = 0;
    public $duplicate = 0;

    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        $this->admin_init();
    }

    public function admin_init()
    {
        add_action('admin_init', array($this, 'wpnotif_wp_import'));
        add_action('wp_ajax_' . self::action, array($this, 'check_import_action'));

    }

    /**
     *  Constructor.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function verify_nonce()
    {
        if (!isset($_POST['action']) || $_POST['action'] != self::action) {
            return false;
        }
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], self::action)) {
            return false;
        }
        return true;
    }

    public function wpnotif_wp_import()
    {

        if (!$this->verify_nonce()) {
            return;
        }

        $group_id = $_POST['user_group_id'];
        $import_type = $_POST['data_type'];
        $link = WPNotif_UserGroups::create_user_group_link($group_id);
        $query = array();
        if (!empty($link) && $import_type == 'wp_users' || $import_type === 'skip_import') {
            if ($import_type == 'wp_users') {
                $query['wp_import'] = true;
            }
            $link = add_query_arg($query, $link);
            wp_safe_redirect($link);
            die();
        }
    }

    public function check_import_action()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->verify_nonce()) {
            return;
        }

        $group_id = $_POST['user_group_id'];
        $import_type = $_POST['data_type'];

        $link = WPNotif_UserGroups::create_user_group_link($group_id);
        $query = array();

        if ($import_type == 'wp_users') {
            $query['wp_import'] = true;
        } else {
            $result = $this->start_import_process($group_id, $import_type);


            if (WPNotif::is_doing_ajax()) {
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                } else {
                    wp_send_json_success(array('imported' => $result, 'invalid' => $this->invalid, 'redirect' => $link));
                }
            }

            if (!is_wp_error($result) && $result > 0) {
                $query['result'] = base64_encode($result . ',' . $this->invalid . ',' . $this->duplicate);
            }
        }

        $link = add_query_arg($query, $link);

        wp_safe_redirect($link);
        die();
    }

    public function start_import_process($group_id, $import_type)
    {

        if ($import_type == 'wp_users') {
            return $this->import_all_wp_users($group_id);
        } else if ($import_type == 'upload_file' || $import_type == 'data') {
            return !empty($_POST['data']) ? $this->import_user_from_data($group_id, html_entity_decode(stripslashes($_POST['data']))) : null;
        }

        return null;
    }

    public static function generate_key()
    {
        $salt = wp_generate_password();
        return sha1($salt . uniqid(time(), true));
    }

    public function import_all_wp_users($group_id, $args = array())
    {


        $args[] = array('fields' => array('ID'));

        $users = get_users($args);

        if (empty($users)) {

            return new WP_Error('empty', esc_html__('No User found!', 'wpnotif'));
        }
        return $this->import_wp_users($group_id, $users);
    }


    public function import_wp_user_id($group_id, $user_id)
    {
        $user = get_user_by('ID', $user_id);

        if (empty($user)) return;

        $details = new stdClass();
        $details->ID = $user_id;
        $details->first_name = $user->user_firstname;
        $details->last_name = $user->user_lastname;

        return $this->import_wp_users($group_id, array($details));
    }

    public function update_wp_user_id($user_id)
    {
        $user = get_user_by('ID', $user_id);

        if (empty($user)) return;

        $data = array();
        $data['first_name'] = $user->user_firstname;
        $data['last_name'] = $user->user_lastname;
        $data['email'] = $user->user_email;
        $data['phone'] = WPNotif_Handler::get_user_phone($user_id, true);

        $where = array('wp_id' => $user_id);
        return $this->update_user($data, $where);
    }

    public function update_user($data, $where)
    {
        global $wpdb;
        $tb = $wpdb->prefix . WPNotif_UserGroups::subscribers_table;

        return $wpdb->update($tb, $data, $where);
    }

    public function import_wp_users($group_id, $users)
    {
        $import_users_list = array();

        foreach ($users as $user) {

            if(empty($user)){
                return;
            }
            $details = array();

            $user_id = $user->ID;

            $details['wp_id'] = $user_id;

            $details['first_name'] = $user->first_name;
            $details['last_name'] = $user->last_name;


            $details['email'] = $user->user_email;
            $details['phone'] = WPNotif_Handler::get_user_phone($user_id, true);

            $details['activation'] = self::generate_key();

            $import_users_list[$user_id] = $details;
        }
        return $this->process_user_import($group_id, $import_users_list, true);
    }


    public function check_update_user_import($group_id, $details)
    {

        $where = array('phone' => $details['phone'], 'wp_id' => 0);
        $user = $this->get_user($where);

        if (!$user) {
            $this->process_user_import($group_id, array($details), false);
        } else {
            $this->update_user($details, $where);
        }

        return $this->add_to_group($group_id, array($details), false);
    }

    public function process_user_import($group_id, $list, $is_wp)
    {
        $error = new WP_Error();


        if (empty($list) || !is_numeric($group_id)) {
            $error->add('empty', esc_attr__('No user found to import', 'wpnotif'));
            return $error;
        }


        $create_users = $this->insert_sub($list);


        if ($group_id == 0) {
            return $create_users;
        }

        return $this->add_to_group($group_id, $list, $is_wp);
    }

    protected function add_to_group($group_id, $list, $is_wp)
    {
        global $wpdb;
        $userlist_table = $wpdb->prefix . WPNotif_UserGroups::usergroup_list_table;
        $subscribers = $wpdb->prefix . WPNotif_UserGroups::subscribers_table;


        $sql = "INSERT IGNORE INTO $userlist_table (`gid`, `inactive` ,`uid`) \n";


        $group_id = esc_sql($group_id);
        $values = array();
        foreach ($list as $user) {
            if ($is_wp) {
                $values[] = esc_sql($user['wp_id']);
            } else {
                $values[] = esc_sql($user['phone']);
            }
        }

        $sub_sql = "SELECT $group_id, 0, id FROM $subscribers WHERE \n";

        if ($is_wp) {
            $sub_sql .= " wp_id IN \n";
        } else {
            $sub_sql .= " phone IN \n";
        }
        $sub_sql .= "(" . implode(",", $values) . ")";

        $sql .= "$sub_sql";

        return $wpdb->query($sql);
    }

    protected function insert_sub($rows)
    {


        global $wpdb;
        $tb = $wpdb->prefix . WPNotif_UserGroups::subscribers_table;

        $columns = array_keys(reset($rows));
        $columns[] = 'updated_time';

        $columnList = '`' . implode('`, `', $columns) . '`';

        $sql = "INSERT IGNORE INTO `$tb` ($columnList) VALUES\n";
        $placeholders = array();
        $data = array();


        // Build placeholders for each row, and add values to data array
        foreach ($rows as $user_phone => &$row) {
            $rowPlaceholders = array();

            $row['updated_time'] = date("Y-m-d H:i:s", time());

            foreach ($row as $key => $value) {
                $data[] = $value;
                $rowPlaceholders[] = '%s';
            }


            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        // Stitch all rows together
        $sql .= implode(",\n", $placeholders);

       // $sql .= " ON DUPLICATE KEY UPDATE ";

        return $wpdb->query($wpdb->prepare($sql, $data));
    }

    public function import_user_from_file($group_id)
    {
        if (empty($_FILES)) {
            return;
        }

        $error = new WP_Error();

        $file = $_FILES['data_file'];

        if ($file['type'] != 'text/csv') {
            $error->add('invalid_type', esc_html__('Only csv are accepted!'));
            return $error;
        }
        $data = file_get_contents($file['tmp_name']);

        return $this->import_user_from_data($group_id, $data);
    }

    public function import_user_from_data($group_id, $data)
    {
        $import_users_list = array();

        $error = new WP_Error();

        /*$data = explode(PHP_EOL, $data);
        $csv = array_map('str_getcsv', $data);*/

        $csv = json_decode($data, true);

        $header = array_shift($csv);


        $first_name = array('first_name', 'firstname', 'name');
        $last_name = array('last_name', 'lastname');
        $phone = array('phone', 'mobile');
        $country_code = array('countrycode', 'country code', 'country_code');
        $email = array('email', 'mail');

        $keys = array();
        foreach ($header as $index => $col) {
            $col = trim($col);
            if (in_array($col, $phone)) {
                $keys['phone'] = $index;
            } else if (in_array($col, $first_name)) {
                $keys['first_name'] = $index;
            } else if (in_array($col, $last_name)) {
                $keys['last_name'] = $index;
            } else if (in_array($col, $country_code)) {
                $keys['country_code'] = $index;
            } else if (in_array($col, $email)) {
                $keys['email'] = $index;
            }
        }

        if (empty($csv) || !array_key_exists('first_name', $keys) || !array_key_exists('phone', $keys)) {
            $error->add('invalid_data', esc_attr__('Data is invalid, import has failed', 'wpnotif'));
            return $error;
        }


        foreach ($csv as $user_data) {

            if (!isset($user_data[$keys['phone']])) continue;

            $user_phone = sanitize_mobile_field_wpnotif(trim($user_data[$keys['phone']]));

            if (array_key_exists('country_code', $keys)) {
                $country = trim($user_data[$keys['country_code']]);

                if (!is_numeric($country))
                    $country = WPNotif_Handler::getCountryCode($country, false);

                $user_phone = $country . $user_phone;
            }

            if (!WPNotif_NewsLetter_Handler::starts_with("+", $user_phone)) {
                $user_phone = '+' . $user_phone;
            }

            if (empty($user_phone) || !is_numeric($user_phone) || !WPNotif_Handler::parseMobile($user_phone)) {
                $this->invalid++;
                continue;
            }


            $user_details['first_name'] = isset($keys['first_name']) ? trim($user_data[$keys['first_name']]) : '';
            $user_details['last_name'] = isset($keys['last_name']) ? trim($user_data[$keys['last_name']]) : '';
            $user_details['phone'] = $user_phone;
            $user_details['activation'] = self::generate_key();

            if (isset($keys['email']) && is_email(trim($user_data[$keys['email']]))) {
                $user_details['email'] = trim($user_data[$keys['email']]);
            }

            $import_users_list[$user_phone] = $user_details;
        }


        return $this->process_user_import($group_id, $import_users_list, false);
    }

    public function add_user_to_group($group_id, $first_name, $last_name, $email, $phone, $consent)
    {
        $group_id = (int)filter_var($group_id, FILTER_SANITIZE_NUMBER_INT);

        if (empty($group_id) || !WPNotif_Handler::parseMobile($phone)) {
            return null;
        }


        $user_details = array();
        $user_details['first_name'] = $first_name;
        $user_details['last_name'] = $last_name;
        $user_details['phone'] = $phone;

        if (!empty($email) && is_email($email)) {
            $user_details['email'] = $email;
        }
        if ($consent) {
            $user_details['consent'] = 1;
            $user_details['consent_time'] = date("Y-m-d H:i:s", time());
        }
        $user_details['wp_id'] = 0;
        $user_details['activation'] = self::generate_key();

        return $this->check_update_user_import($group_id, $user_details);
    }

    public function get_user($data)
    {
        return $this->get_result(WPNotif_UserGroups::subscribers_table, $data, 1);
    }

    private function get_result($table, $data, $limit)
    {
        global $wpdb;
        $table = $wpdb->prefix . $table;


        $sql = "SELECT * FROM $table WHERE ";

        $keys = array();
        $values = array();
        foreach ($data as $key => $value) {
            $keys[] = esc_sql($key) . ' = %s';
            $values[] = $value;
        }

        $sql .= implode("AND \n", $keys);

        $sql .= " ORDER BY id ASC";

        $query = $wpdb->prepare($sql, $values);

        if ($limit == 1) {
            return $wpdb->get_row($query);
        } else {
            return $wpdb->get_results($query);
        }
    }
}