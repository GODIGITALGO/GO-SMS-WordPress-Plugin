<?php


if (!defined('ABSPATH')) {
    exit;
}


final class WPNotif_UserGroups
{

    protected static $_instance = null;
    public $ajax;

    /* @var WPNotif_UserGroup_Import */
    public $import;

    /* @var WPNotif_User_Functions */
    public $user_functions;

    const usergroup_table = 'wpnotif_subgroups';
    const subscribers_table = 'wpnotif_subscribers';
    const usergroup_list_table = 'wpnotif_subgroups_subscribers';

    const import_seg = 2000;
    const page = 'wpnotif-usergroups';

    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        require_once(plugin_dir_path(__FILE__) . 'import.php');
        require_once(plugin_dir_path(__FILE__) . 'user_functions.php');

        $this->import = WPNotif_UserGroup_Import::instance();

        $this->user_functions = WPNotif_User_Functions::instance();

        add_action('init', array($this, 'ajax_hooks'));
        add_action('wpnotif_menu', array($this, 'add_menu'), 30);

        add_action('wpnotif_create_db', array($this, 'wpnotif_create_db'));

        add_action('delete_user', array($this, 'user_deleted'));


        add_action('user_register', array($this, 'new_user'));

        add_action('remove_user_role', array($this, 'user_removed_from_role'), 10, 2);
        add_action('add_user_role', array($this, 'user_added_to_role'), 10, 2);
        add_action('set_user_role', array($this, 'user_added_to_role'), 10, 2);

        add_action('profile_update', array($this, 'user_updated'), 10, 2);


    }

    public function user_updated($user_id, $old_user_data)
    {
        $this->import->update_wp_user_id($user_id);
    }


    public static function user_deleted($user_id)
    {
        global $wpdb;

        $uid = self::get_sub_user_id($user_id);

        if (!$uid) return;

        $list_tb = $wpdb->prefix . self::usergroup_list_table;

        $wpdb->delete($list_tb, array('uid' => $uid), array('%d'));

        self::delete_wp_subscriber($user_id);
    }

    public static function get_sub_id($phone)
    {
        global $wpdb;
        $tb = $wpdb->prefix . self::subscribers_table;

        $sql = "SELECT * FROM $tb WHERE phone = %s LIMIT 1\n";

        $row = $wpdb->get_row($wpdb->prepare($sql, array($phone)));

        return $row ? $row->id : null;
    }

    public function check_if_user_exist_in_group($group_id, $phone)
    {
        global $wpdb;

        $phone = sanitize_text_field($phone);
        $uid = self::get_sub_id($phone);

        if (empty($uid)) {
            return false;
        }

        $list_tb = $wpdb->prefix . self::usergroup_list_table;


        $sql = "SELECT * FROM $list_tb WHERE gid = %d AND uid = %d LIMIT 1\n";
        $check = $wpdb->get_row($wpdb->prepare($sql, array($group_id, $uid)));

        if (empty($check) || !$check) {
            return false;
        } else {
            return true;
        }

    }

    public static function delete_wp_subscriber($wp_id)
    {
        $details = array();
        $details['wp_id'] = $wp_id;
        self::delete_subscriber($details);
    }

    public static function delete_subscriber($details)
    {
        if (empty($details)) return;

        global $wpdb;
        $list_tb = $wpdb->prefix . self::subscribers_table;
        $wpdb->delete($list_tb, $details);
    }

    public static function get_sub_user_id($user_id)
    {
        global $wpdb;
        $tb = $wpdb->prefix . self::subscribers_table;

        $sql = "SELECT * FROM $tb WHERE wp_id = %d LIMIT 1\n";

        $row = $wpdb->get_row($wpdb->prepare($sql, array($user_id)));

        return $row ? $row->id : null;
    }

    public function get_wp_user_group($type = 'wp_users')
    {
        $group_data = $this->get_predefined_user_group_data('wp_users');
        return $this->get_usergroup($group_data, 1);
    }

    public function get_predefined_user_group_data($list_type)
    {
        return array(
            'predefined' => 1,
            'list_type' => $list_type
        );
    }

    public function new_user($user_id)
    {
        $group_data = $this->get_predefined_user_group_data('wp_users');
        $user_group = $this->get_usergroup($group_data, 1);
        if ($user_group) {
            $gid = $user_group->id;
            $this->import->import_wp_user_id($gid, $user_id);
        }
    }

    public function check_predefined_groups()
    {

        $data = array();
        $group_data = $this->get_predefined_user_group_data('wp_users');
        $predefined_wp_group = $this->get_usergroup($group_data, 1);

        $roles = $this->userrole_groups_to_be_created();
        if (empty($predefined_wp_group)) {
            $data[] = 'wp_users';
        }

        if (!empty($roles)) {
            $data = array_merge($data, array_keys($roles));
        }

        if (!empty($data)) {
            ?>
            <input class="create_predefined_group wpnotif-fixvalue" type="hidden"
                   data-groups="<?php esc_attr_e(json_encode($data)); ?>"/>
            <?php
        } else {
            update_option('wpnotif_create_usergroup_notice', 1);
        }
    }

    public function ajax_create_predefined_group()
    {
        $this->check_access();
        $group = sanitize_text_field($_REQUEST['group']);
        if (empty($group) || !isset($_REQUEST['request'])) {
            wp_send_json_error(array('message' => esc_html__('Error while creating predefined groups!', 'wpnotif')));
            die();
        }

        $users_args = array();
        $users_args['fields'] = array('ID');
        $users_args['count_total'] = true;
        if ($group != 'wp_users') {
            $users_args['role'] = $group;
        }
        $user_query = new WP_User_Query($users_args);
        $total = $user_query->get_total();

        $request = absint($_REQUEST['request']);

        $args = array();
        $args['number'] = self::import_seg;
        $args['offset'] = self::import_seg * $request;

        $create = $this->create_predefined_groups($group, $args);

        $request++;
        $data = array();
        $data['request'] = $request;

        if ($total <= $args['offset']) {
            $data['type'] = 'finished';
        } else {
            $data['type'] = 'remaining';
        }
        $data['message'] = 'done';

        wp_send_json_success($data);
    }

    public function create_predefined_groups($type, $args = array())
    {
        return $type == 'wp_users' ? $this->create_wp_user_group($args) : $this->create_userrole_group($type, $args);
    }

    public function create_wp_user_group($args = array())
    {
        $group_data = $this->get_predefined_user_group_data('wp_users');

        $predefined_groups = $this->get_usergroup($group_data, 1);


        if (empty($predefined_groups)) {
            $group_data['name'] = 'All WordPress Users';
            $group_data['description'] = 'WP Users';
            $gid = $this->create_user_group($group_data);
        } else {
            $gid = $predefined_groups->id;
        }

        return $this->import->import_all_wp_users($gid, $args);
    }


    public function userrole_groups_to_be_created()
    {
        global $wp_roles;
        $roles = $wp_roles->roles;
        $user_groups = $this->get_usergroup(array('predefined' => 1), 0);

        foreach ($user_groups as $user_group) {
            unset($roles[$user_group->list_type]);
        }
        return $roles;
    }

    public function create_userroles_groups()
    {


        $roles = $this->userrole_groups_to_be_created();
        if (empty($roles)) return;


        foreach ($roles as $role => $role_details) {
            $this->create_userrole_group($role);
        }

    }

    public function create_userrole_group($role, $args = array())
    {

        $wp_roles = wp_roles()->roles;

        $role_details = $wp_roles[$role];

        if (!$role_details) return;

        $group_data = array();
        $group_data['predefined'] = 1;
        $group_data['list_type'] = $role;


        $group = $this->get_usergroup($group_data, 1);
        if (empty($group)) {
            $group_data['name'] = $role_details['name'];
            $group_data['description'] = "User Role";
            $gid = $this->create_user_group($group_data);
        } else {
            $gid = $group->id;
        }

        $args['role'] = $role;
        return $this->import->import_all_wp_users($gid, $args);

    }


    public function user_added_to_role($user_id, $role)
    {

        $group_data = $this->get_predefined_user_group_data($role);
        $group = $this->get_usergroup($group_data, 1);
        if (!empty($group)) {
            $gid = $group->id;
            $this->import->import_wp_user_id($gid, $user_id);
        }
    }

    public function user_removed_from_role($user_id, $role)
    {
        $group_data = $this->get_predefined_user_group_data($role);
        $group = $this->get_usergroup($group_data, 1);
        if (!empty($group)) {
            $gid = $group->id;
            $this->delete_wp_user_from_group($gid, $user_id);
        }
    }

    public function wpnotif_create_db()
    {
        $this->init_db();
    }


    public function ini_default_group_ajax()
    {
        if (!current_user_can('manage_options')) return;

        add_action('wp_ajax_wpnotif_update_user_in_list', array($this, 'ajax_update_user_in_list'));

        add_action('wp_ajax_wpnotif_add_user_to_list', array($this, 'ajax_update_user_in_list'));
    }

    function ajax_update_user_in_list()
    {
        $this->check_access();
        $gid = absint($_POST['gid']);
        $uids = $_POST['uids'];
        if (!is_numeric($gid) || empty($gid)) {
            wp_send_json_error(array('message' => esc_attr__('Group not found!', 'wpnotif')));
        }

        $group_details = self::get_group_details($gid);
        $uids = explode(',', $uids);

        foreach ($uids as $uid) {
            $uid = absint($uid);
            if (empty($uid) || !is_numeric($uid)) {
                continue;
            }
            if ($_POST['type'] == 'remove') {
                $this->remove_user_from_list($group_details, $uid);
            } else {
                if ($group_details->predefined == 1) {
                    $this->make_user_active_in_list($group_details, $uid);
                } else {
                    $this->import->import_wp_user_id($group_details->id, $uid);
                }
            }
        }

        wp_send_json_success(array('message' => esc_attr__('Done', 'wpnotif')));
    }

    public function make_user_active_in_list($group_details, $uid)
    {
        $details = array();
        $details['gid'] = $group_details->id;
        $details['id'] = $uid;

        $data = array();
        $data['inactive'] = 0;
        $this->update_user_details_in_list($group_details->id, $data, $details);
    }

    public function remove_user_from_list($group_details, $uid)
    {

        $details = array();
        $details['gid'] = $group_details->id;
        $details['id'] = $uid;
        if ($group_details->predefined == 1) {
            $data = array();
            $data['inactive'] = 1;
            $this->update_user_details_in_list($group_details->id, $data, $details);
        } else {
            $this->delete_user_from_group($group_details->id, $details);
        }
    }

    function add_menu()
    {
        add_submenu_page('wpnotif-settings',
            __('User Groups', 'wpnotif'),
            __('User Groups', 'wpnotif'),
            'manage_options',
            self::page,
            array($this, 'settings_ui')
        );
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


    public function settings_ui()
    {

        $active_tab = 'usergroups';

        if (isset($_GET['tab'])) {
            $active_tab = !empty($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $active_tab;
        }


        $this->init_db();
        $this->check_predefined_groups();

        $group_details = null;
        $group_name = '';
        if (isset($_GET['gid'])) {
            $gid = filter_var($_GET['gid'], FILTER_VALIDATE_INT);
            if ($gid) {
                $group_details = self::get_group_details($gid);
                if ($group_details) {
                    $group_name = $group_details->name;
                }
            }
            if (empty($group_name)) {
                wp_safe_redirect(admin_url('admin.php?page=' . self::page));
                die();
            }
        }

        WPNotif::WPNotif_loading();
        ?>
        <div class="wpnotif-fullpage_tablayout wpnotif-newsletter_settings wpnotif_admin_conf wpnotif_admin_fields">

            <div class="wpnotif_sts_logo wpnotif_logo_tabs">
                <div class="wpnotif-details">
                    <?php WPNotif::admin_header_logo(false); ?>
                    <div class="wpnotif-tab-separator"></div>
                </div>

                <div class="wpnotif-tabs wpnotif-display_inline-block">
                    <ul class="wpnotif-tab-ul">
                        <?php if (empty($group_details) || $group_details->predefined == 1) { ?>
                            <li>
                                <a href="?page=<?php echo self::page; ?>&amp;tab=usergroups"
                                   refresh="1"
                                   class="updatetabview wpnotif-nav-tab  <?php echo $active_tab == 'usergroups' ? 'wpnotif-nav-tab-active' : ''; ?>"
                                   tab="usergroupstab">
                                    <?php
                                    esc_html_e('Predefined', 'wpnotif');
                                    if (!empty($group_name)) {
                                        echo '<span class="tree-separator">' . $group_name . '</span>';
                                    }
                                    ?>
                                </a>
                            </li>
                            <?php
                        }

                        if (empty($group_details) || $group_details->predefined == 0) { ?>
                            <li>
                                <a href="?page=<?php echo self::page; ?>&amp;tab=custom_usergroups"
                                   refresh="1"
                                   class="updatetabview wpnotif-nav-tab  <?php echo $active_tab == 'custom_usergroups' ? 'wpnotif-nav-tab-active' : ''; ?>"
                                   tab="usergroupstab">
                                    <?php esc_html_e('User Groups', 'wpnotif');
                                    if (!empty($group_name)) {
                                        echo '<span class="tree-separator">' . $group_name . '</span>';
                                    }
                                    ?>
                                </a>
                            </li>
                            <?php
                        } ?>
                    </ul>
                </div>

                <div class="wpnotif-action-buttons">
                    <?php
                    if ($active_tab == 'userlisttab') {
                        $type = !empty($_REQUEST['wp_import']) ? '1' : 0;
                        if ($group_details) {


                            if ($type != 1) {
                                $button_type = 'remove_user_from_list';
                                $button_text = __('Remove Selected', 'wpnotif');
                                ?>
                                <div class="wpnotif-button wpnotif-button_green wpnotif_select_button user-list_modify <?php echo $button_type; ?> wpnotif-hide">
                                    <?php echo esc_html($button_text); ?>
                                </div>
                                <?php
                            }
                            if ($group_details->predefined == 1 || $type == 1) {
                                $button_type = 'add_user_to_list';
                                $button_text = __('Add Selected', 'wpnotif');
                                if ($type == 1) $button_type = 'wpnotif_select_button add_user_to_list';
                                ?>
                                <div class="wpnotif-button wpnotif-button_green user-list_modify <?php echo $button_type; ?> wpnotif-hide">
                                    <?php echo esc_html($button_text); ?>
                                </div>
                                <?php
                            }
                            if ($type == 1) {
                                ?>
                                <div class="wpnotif-button wpnotif-button_green wpnotif_link"
                                     href="<?php esc_attr_e(self::create_user_group_link($group_details->id)); ?>">
                                    <?php esc_attr_e('Back', 'wpnotif'); ?>
                                </div>
                                <?php
                            } else if ($group_details->predefined != 1) {
                                ?>
                                <div class="wpnotif-button wpnotif-button_green open_modal"
                                     data-show="usergroup-import_user"
                                     data-title="<?php esc_attr_e('Import Users', 'wpnotif'); ?>"
                                     data-title-desc=""
                                >
                                    <?php esc_attr_e('Import Users', 'wpnotif'); ?>
                                </div>
                                <?php
                            }

                        }
                    } else {
                        ?>
                        <div class="wpnotif-button wpnotif-button_green open_modal"
                             data-show="newsletter-add_new"
                             data-title="<?php esc_attr_e('New User Group', 'wpnotif'); ?>">
                            <?php esc_attr_e('Add New', 'wpnotif'); ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>


            <div class="wpnotif_content">

                <?php
                if (isset($_GET['result']) && isset($_GET['gid'])) {
                    $result = base64_decode($_GET['result']);
                    if (!empty($result)) {
                        $result = explode(',', $result);
                        if (is_array($result) && count($result) == 3) {
                            ?>
                            <div class="wpnotif-import_result">
                                <?php
                                $success = $result[0];
                                $total = $result[0] + $result[1] + $result[2];
                                $result_string = sprintf(__('%s out of %s users added successfully', 'wpnotif'), $success, $total);
                                echo esc_html($result_string);
                                ?>
                            </div>
                            <?php
                        }
                    }
                }
                ?>

                <div data-tab="usergroupstab"
                     class="wpnotif_admin_in_pt usergroupstab digtabview <?php echo ($active_tab == 'custom_usergroups' || $active_tab == 'usergroups') ? 'digcurrentactive' : '" style="display:none;'; ?>">
                    <?php $this->show_groups(); ?>
                </div>

                <div data-tab="userlisttab"
                     class="wpnotif_admin_in_pt userlisttab digtabview <?php echo $active_tab == 'userlisttab' ? 'digcurrentactive' : '" style="display:none;'; ?>">
                    <?php
                    if ($group_details) {

                        $this->show_group_table($group_details);
                    }
                    ?>
                </div>

            </div>


        </div>
        <?php
        $this->modal('newsletter-add_new', 'add_new_usergroup_modal');

        $this->modal('usergroup-import_user', 'import_usergroup_modal');

        WPNotif::WPNotif_loading();
    }

    private function init_db()
    {
        global $wpdb;
        $tb = $wpdb->prefix . self::usergroup_table;
        if ($wpdb->get_var("SHOW TABLES LIKE '$tb'") != $tb) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $tb (
										id BIGINT UNSIGNED NOT NULL auto_increment,
										name TEXT NOT NULL,
										description TEXT NULL,
										predefined TINYINT DEFAULT 0 NULL,
										list_type VARCHAR(200) NULL,
		          						time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		          						PRIMARY KEY  (id),
		          						INDEX idx_id (id),
		          						INDEX idx_predefined (predefined)
	            						) $charset_collate;";

            $user_list = $wpdb->prefix . self::subscribers_table;
            $sql2 = "CREATE TABLE $user_list (
										id BIGINT UNSIGNED NOT NULL auto_increment,
										wp_id BIGINT DEFAULT 0 NULL,
										first_name TEXT NULL,
										last_name TEXT NULL,
										phone VARCHAR(35) DEFAULT 0 NULL,
										email VARCHAR(100) DEFAULT NULL,
										consent TINYINT DEFAULT 0 NULL,
										consent_time datetime NULL,
										verified TINYINT DEFAULT 0 NULL,
										activation TEXT NULL,
										country TEXT NULL,
										updated_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		          						created_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		          						PRIMARY KEY  (id),
		          						INDEX idx_wp_id (wp_id),
		          						INDEX idx_phone (phone),
		          						CONSTRAINT UC_Subscriber_Phone UNIQUE (phone),
		          						CONSTRAINT UC_Subscriber_Email UNIQUE (email)
	            						) $charset_collate;";

            $group_users = $wpdb->prefix . self::usergroup_list_table;

            $sql3 = "CREATE TABLE $group_users (
										id BIGINT UNSIGNED NOT NULL auto_increment,
										gid BIGINT NULL,
										uid BIGINT NULL,
										inactive TINYINT DEFAULT 0 NULL,
		          						time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		          						PRIMARY KEY  (id),
		          						CONSTRAINT UC_Subscriber UNIQUE (gid,uid),
		          						INDEX idx_group_id (gid)
	            						) $charset_collate;";


            $otp = $wpdb->prefix . WPNotif_User_Functions::usergroup_otp_table;
            $sql4 = "CREATE TABLE $otp (
                  id BIGINT UNSIGNED NOT NULL auto_increment,
		          countrycode MEDIUMINT(8) NOT NULL,
		          mobileno VARCHAR(20) NOT NULL,
		          otp VARCHAR(32) NOT NULL,
		          time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		          PRIMARY KEY  (id),
		          CONSTRAINT UC_Phone UNIQUE (countrycode,mobileno)
	            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta(array($sql, $sql2, $sql3, $sql4));
        }

    }


    public static function create_user_group_link($gid)
    {
        return admin_url('admin.php?page=' . self::page . '&tab=userlisttab&gid=' . $gid);
    }

    public function show_groups()
    {
        global $wpdb;
        $tb = $wpdb->prefix . self::usergroup_table;
        if ($wpdb->get_var("SHOW TABLES LIKE '$tb'") != $tb) {
            return;

        }


        $predefined = 1;
        $order = "time ASC";
        if (isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'custom_usergroups') {
            $predefined = 0;
            $order = "time DESC";
        }

        $sql = "SELECT * FROM $tb WHERE predefined = %d ORDER BY " . sanitize_sql_orderby($order);

        $usergroups = $wpdb->get_results(
            $wpdb->prepare($sql, $predefined)
        );
        ?>

        <table class="wpnotif-scheduled-newsletter_table">
            <?php
            $this->tablehead();


            foreach ($usergroups as $usergroup) {

                $data = array(
                    'id' => $usergroup->id,
                    'name' => $usergroup->name,
                    'description' => $usergroup->description,
                );

                $group_link = esc_attr(self::create_user_group_link($usergroup->id));
                ?>
                <tr id="usergroup_<?php echo $usergroup->id; ?>" class="wpnotif-action"
                    data-json="<?php echo esc_attr(json_encode($data)); ?>">
                    <td>
                        <div class="wpnotif-usergroup_name newsletter_edit"
                             data-show="newsletter-add_new"
                             data-title="<?php esc_attr_e('Edit User Group', 'wpnotif'); ?>">
                            <?php echo $usergroup->name; ?>
                        </div>
                    </td>
                    <td class="wpnotif-center_align-text">
                        <div class="wpnotif-usergroup_description newsletter_edit"
                             data-show="newsletter-add_new"
                             data-title="<?php esc_attr_e('Edit User Group', 'wpnotif'); ?>">
                            <?php echo $usergroup->description; ?>
                        </div>
                    </td>
                    <td class="wpnotif-center_align-text">
                        <div class="wpnotif-usergroup_count"><?php echo number_format($this->count_users($usergroup->id)); ?></div>
                    </td>
                    <td class="wpnotif-center_align-text">
                        <div class="wpnotif-single-action-button_container">

                            <div class="wpnotif-single-action_button wpnotif_link"
                                 href="<?php echo $group_link; ?>">
                                <div class="wpnotif-action-icon_button wpnotif-icon edit-icon"></div>
                                <?php esc_attr_e('Edit', 'wpnotif'); ?>
                            </div>
                            <div class="wpnotif-single-action_button newsletter_duplicate"
                                 data-show="newsletter-add_new"
                                 data-title="<?php esc_attr_e('New User Group', 'wpnotif'); ?>">
                                <div class="wpnotif-action-icon_button wpnotif-icon duplicate-icon"></div>
                                <?php esc_attr_e('Duplicate', 'wpnotif'); ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php
                        if ($usergroup->predefined != 1) {
                            ?>
                            <div class="wpn_shortcode">
                                <input type="text" readonly class="wpnotif_copy_shortcode"
                                       value="<?php esc_attr_e(wpnotif_create_group_shortcode($usergroup->id)); ?>"/>
                            </div>
                            <?php
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($usergroup->predefined != 1) {
                            ?>
                            <div class="wpnotif-action-icon_button wpnotif-icon delete-icon delete-usergroup"></div>
                            <?php
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
        <?php
    }

    public function show_group_table($group_details)
    {
        $data = array();
        $data[]['data'] = 'sno';
        $data[]['data'] = 'first_name';
        $data[]['data'] = 'last_name';
        $data[]['data'] = 'user_name';
        $data[]['data'] = 'phone';
        $data[]['data'] = 'time';
        $data = json_encode($data);


        $dis_sort = array(0, 3)
        ?>

        <div class="table_buttons">

            <?php
            if ($group_details->predefined == 1) {
                ?>
                <div class="filter_table">
                    <select class="filter_table_inp" id="show_user_type" name="show_user_type">
                        <option value="-1" selected><?php esc_html_e('All Users', 'wpnotif'); ?></option>
                        <option value="1"><?php esc_html_e('Removed Users', 'wpnotif'); ?></option>
                    </select>
                </div>
                <?php
            }

            $type = !empty($_REQUEST['wp_import']) ? '1' : 0;
            if ($type == 1) {
                ?>
                <div class="filter_table">
                    <select class="filter_table_inp" id="user_roles" name="user_roles">
                        <option value="-1" selected><?php esc_html_e('All Users', 'wpnotif'); ?></option>
                        <?php
                        $roles = wp_roles()->roles;

                        foreach ($roles as $role => $role_details) {
                            ?>
                            <option value="<?php esc_attr_e($role) ?>"><?php echo $role_details['name']; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <input type="hidden" name="is_wp_import" class="wpnotif-fixvalue" value="<?php echo $type; ?>"/>
                </div>

                <?php
                $dis_sort = array(0, 4);
            }
            ?>
        </div>

        <table class="wpnotif-scheduled-newsletter_table dataTable" id="wpnotif_data_table"
               data-id="<?php esc_attr_e($_GET['gid']); ?>"
               data-disable_sort="<?php esc_attr_e(json_encode($dis_sort)); ?>"
               data-action="wpnotif_show_group_users"
               data-nonce="<?php echo wp_create_nonce('wpnotif_newsletter'); ?>"
               data-coloumn="<?php esc_attr_e($data); ?>"
        >
            <thead>
            <tr>
                <th>
                    <label class="wpnotif-userlist wpnotif-checkbox empty">
                        <input type="checkbox" class="select_all" data-select="selected_user">
                    </label>
                </th>
                <th><?php esc_attr_e('First Name', 'wpnotif'); ?></th>
                <th><?php esc_attr_e('Last Name', 'wpnotif'); ?></th>
                <th><?php esc_attr_e('Username', 'wpnotif'); ?></th>
                <th><?php esc_attr_e('Phone', 'wpnotif'); ?></th>
                <th><?php esc_attr_e('Date Added', 'wpnotif'); ?></th>
            </tr>
            </thead>
        </table>
        <?php
    }

    public function show_wp_users_to_import($gid, $args)
    {
        $data = array();


        $group_users = $this->get_group_users($gid, array('only_wp' => 1, 'select' => 'wp_id'));
        $exclude = array();
        foreach ($group_users as $group_user) {
            $exclude[] = $group_user->wp_id;
        }
        $users_args = array();
        $users_args['exclude'] = $exclude;

        if (!empty($_REQUEST['user_roles']) && $_REQUEST['user_roles'] != -1) {
            $users_args['role'] = $_REQUEST['user_roles'];
        }
        if (!empty($args['search'])) {
            $users_args['search'] = '*' . $args['search'] . '*';

            $users_args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => 'first_name',
                    'value' => $args['search'],
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'last_name',
                    'value' => $args['search'],
                    'compare' => 'LIKE'
                )
            );
        }

        $request_order = $_REQUEST['order'][0];
        $coloumn = array(1 => 'first_name', 2 => 'last_name', 3 => 'user_login', 5 => 'user_registered');

        if (isset($request_order['column'])) {
            if (isset($coloumn[$request_order['column']])) {
                $users_args['orderby'] = $coloumn[$request_order['column']];
                $users_args['order'] = $request_order['dir'];
            }
        }


        $users_args['offset'] = $args['limit']['offset'];
        $users_args['number'] = $args['limit']['limit'];

        $user_query = new WP_User_Query($users_args);
        $total = $user_query->get_total();
        $data['recordsFiltered'] = $total;


        $users = array();
        foreach ($user_query->get_results() as $user_result) {

            $select = '<label class="wpnotif-userlist wpnotif-checkbox empty">';
            $select .= '<input value="' . esc_attr($user_result->ID) . '" type="checkbox" class="selected_user" name="selected_user[]">';
            $select .= '</label>';
            $details['sno'] = $select;
            $details['first_name'] = $user_result->first_name;
            $details['last_name'] = $user_result->last_name;
            $details['user_name'] = $user_result->user_login;

            $phone = self::get_user_phone($user_result->ID);

            $details['phone'] = ($phone) ? $phone : '-';

            $details['time'] = WPNotif_NewsLetter::formatDate(strtotime($user_result->user_registered));
            $users[] = $details;
        }

        $data['data'] = $users;

        $data['draw'] = absint($_REQUEST['draw']);

        $count_query = new WP_User_Query(array('fields' => array('ID'), 'excluded' => $group_users));
        $data['recordsTotal'] = $count_query->get_total();


        echo json_encode($data);
        die();

    }

    public function show_group_users()
    {
        $this->check_access();

        $coloumn = array(1 => 'first_name', 2 => 'last_name', 3 => 'phone', 4 => 'time');

        $limit = absint($_REQUEST['length']);
        $limit = !filter_var($limit, FILTER_VALIDATE_INT) ? 100 : $limit;

        $start = absint($_REQUEST['start']);
        $start = !filter_var($start, FILTER_VALIDATE_INT) ? 0 : $start;

        $request_order = $_REQUEST['order'][0];

        $gid = $_REQUEST['id'];
        $args = array();
        $args['greater'] = 0;
        $args['limit'] = array('limit' => $start + $limit, 'offset' => $start);

        $order = array('column' => 'time', 'dir' => 'desc');
        $requested_sort = array();

        if (isset($request_order['column'])) {
            if (isset($coloumn[$request_order['column']])) {
                $requested_sort['dir'] = $request_order['dir'];
                $requested_sort['column'] = $coloumn[$request_order['column']];
            }
        }

        $args['order'] = wp_parse_args($requested_sort, $order);


        if (isset($_REQUEST['search']['value'])) {
            $args['search'] = sanitize_text_field($_REQUEST['search']['value']);
        }

        $users = array();

        if (isset($_REQUEST['show_user_type']) && $_REQUEST['show_user_type'] == 1) {
            $args['inactive'] = 1;
        }

        if (!empty($_REQUEST['is_wp_import'])) {
            $this->show_wp_users_to_import($gid, $args);
            die();
        }
        $raw_users = $this->get_group_users($gid, $args);

        foreach ($raw_users as $row) {

            $user_name = '';

            if ($row->wp_id != 0) {
                $raw_user = self::get_wp_user_details($row->wp_id);
                if ($raw_user == null) continue;

                $user_name = $raw_user->user_name;
            } else {
                $raw_user = $row;
            }

            $user_phone = WPNotif_Handler::parseMobile($raw_user->phone, true);
            $user = array();

            $select = '<label class="wpnotif-userlist wpnotif-checkbox empty">';
            $select .= '<input value="' . esc_attr($row->id) . '" type="checkbox" class="selected_user" name="selected_user[]">';
            $select .= '</label>';
            $user['sno'] = $select;
            $user['first_name'] = $raw_user->first_name;
            $user['last_name'] = $raw_user->last_name;
            $user['user_name'] = $user_name;
            $user['phone'] = ($user_phone) ? $user_phone : '-';

            $user['time'] = WPNotif_NewsLetter::formatDate(strtotime($row->time));
            $users[] = $user;
        }

        $data = array();

        $data['draw'] = absint($_REQUEST['draw']);
        $data['recordsTotal'] = $this->count_users($gid);

        unset($args['limit']);
        $data['recordsFiltered'] = $this->count_users($gid, $args);

        $data['data'] = $users;

        echo json_encode($data);
        die();
    }

    public static function get_wp_user_details($user_id)
    {
        $user = get_userdata($user_id);
        if (empty($user)) {
            self::user_deleted($user_id);
            return null;
        }
        $details = new stdClass();
        $details->first_name = $user->first_name;
        $details->user_name = $user->user_login;
        $details->last_name = $user->last_name;
        $details->phone = self::get_user_phone($user_id);

        return $details;
    }

    public static function get_user_phone($user_id)
    {
        return WPNotif_Handler::get_user_phone($user_id, true);
    }

    public function tablehead()
    {
        ?>
        <thead>
        <tr>
            <th class="wpnotif-column_20 usergroup_name"><?php esc_attr_e('Name', 'wpnotif'); ?></th>
            <th class="wpnotif-column_20 usergroup_execution-date"><?php esc_attr_e('Description', 'wpnotif'); ?></th>
            <th class="wpnotif-column_20 usergroup_user-count"><?php esc_attr_e('User Count', 'wpnotif'); ?></th>
            <th class="wpnotif-column_15 usergroup_action"></th>
            <th class="wpnotif-column_15 usergroup_shortcode"></th>
            <th class="wpnotif-column_5 usergroup_delete"></th>
        </tr>
        </thead>
        <?php
    }

    public function modal($modal_class, $content)
    {
        ?>
        <div class="wpnotif-hide wpnotif-modal wpnotif_admin_conf wpnotif_admin_fields form-table <?php echo $modal_class; ?>"
             data-replace="usergroupstab">
            <div class="wpnotif-modal_overlay">
                <div class="wpnotif-modal_wrapper">
                    <div class="wpnotif-modal_box">
                        <div class="wpnotif-modal_container">
                            <?php

                            $this->$content();
                            ?>
                        </div>
                    </div>
                    <div class="wpnotif-modal_bg wpnotif-hide_modal"></div>
                </div>
            </div>
        </div>
        <?php
    }

    public function modal_header($title, $desc = '')
    {
        ?>
        <div class="wpnotif-modal_header">
            <div class="modal-title">
                <?php echo $title; ?>
            </div>
            <div class="modal-title-desc">
                <?php echo $desc; ?>
            </div>
        </div>
        <?php
    }

    public function import_usergroup_modal()
    {
        $gid = isset($_GET['gid']) ? absint($_GET['gid']) : 0;

        $this->modal_header(esc_html__('Import Users', 'wpnotif'));
        ?>
        <form enctype="multipart/form-data" method="post" autocomplete="off" data-ajax="0">
            <div class="wpnotif-modal_body wpnotif-import_modal_body">
                <div class="wpnotif-center_align modal-body_container">
                    <div class="modal-body_child modal-body_primarychild">
                        <div data-show="modal-from_data"
                             data-type="data"
                             data-title="<?php esc_attr_e('Paste Data', 'wpnotif'); ?>"
                             data-title-desc="<?php esc_attr_e('Data should be in CSV (Comma Separated Value) style, with first line containing the titles', 'wpnotif'); ?>"
                             class="wpnotif-import_option wpnotif-button wpnotif-button_green wpnotif-display_block"><?php esc_html_e('Paste Data', 'wpnotif'); ?></div>
                        <div data-show="modal-from_file"
                             data-type="file"
                             data-title="<?php esc_attr_e('Upload File', 'wpnotif'); ?>"
                             data-title-desc="<?php esc_attr_e('Data should be in CSV (Comma Separated Value) style, with first line containing the titles', 'wpnotif'); ?>"
                             class="wpnotif-import_option wpnotif-button wpnotif-button_green wpnotif-display_block"><?php esc_html_e('Upload File', 'wpnotif'); ?></div>
                        <div data-type="wp_users"
                             class="wpnotif_submit wpnotif-import_option wpnotif-button wpnotif-button_green wpnotif-display_block"><?php esc_html_e('From Existing WP Users', 'wpnotif'); ?></div>
                        <?php
                        if (empty($_REQUEST['tab']) || (!empty($_REQUEST['tab']) && $_REQUEST['tab'] !== 'userlisttab')) {
                            ?>
                            <div data-type="skip_import"
                                 class="wpnotif_submit wpnotif-import_option  wpnotif-link wpnotif-button wpnotif-display_block"><?php esc_html_e('Skip', 'wpnotif'); ?></div>
                            <?php
                        }
                        ?>
                        <input type="hidden" name="group_id">
                        <input type="hidden" name="data_type" class="data_type wpnotif-fixvalue">
                        <input type="hidden" class="wpnotif-fixvalue" name="action"
                               value="<?php echo $this->import::action; ?>">
                        <?php wp_nonce_field($this->import::action); ?>
                    </div>
                    <div class="wpnotif-full_size modal-body_child modal-from_data wpnotif-hide">
                        <?php
                        $placeholder = 'CSV data with header. For eg. &#10;firstname, lastname, countrycode, phone &#10;John, Doe, +1, 3214443366';
                        ?>
                        <textarea name="data" placeholder="<?php esc_attr_e($placeholder); ?>"></textarea>
                    </div>
                    <div class="modal-body_child modal-from_file wpnotif-hide">
                        <input type="file" name="data_file" class="wpnotif_import_input_file wpnotif-hide"
                               accept=".csv"/>
                        <div data-type="upload_file"
                             class="wpnotif-import_option wpnotif-button wpnotif-button_green wpnotif-display_block">
                            <?php esc_html_e('Select File', 'wpnotif'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wpnotif-modal_footer">
                <button class="modal-body_child wpnotif-button wpnotif-button_green modal-from_data wpnotif-import_data wpnotif-hide"
                        type="submit"><?php esc_attr_e('Import', 'wpnotif'); ?></button>
            </div>
            <input type="hidden" name="user_group_id" value="<?php esc_attr_e($gid); ?>"/>
        </form>
        <?php
    }

    public function add_new_usergroup_modal()
    {
        $this->modal_header(esc_html__('Add New', 'wpnotif'));
        ?>
        <form class="modal_form_details" data-type="user_group" data-show="usergroup-import_user" method="post"
              autocomplete="off">
            <div class="wpnotif-modal_body">
                <div class="wpnotif-newsletter_field wpnotif-usergroup_name">
                    <label><?php esc_attr_e('Name', 'wpnotif'); ?></label>
                    <input type="text" name="name" class="wpnotif-name" required/>
                </div>

                <div class="wpnotif-newsletter_field wpnotif-usergroup_description">
                    <label><?php esc_attr_e('Description', 'wpnotif'); ?></label>
                    <textarea name="description" class="wpnotif-description" required></textarea>
                </div>


            </div>
            <div class="wpnotif-modal_footer">
                <button class="wpnotif-button wpnotif-button_green newsletter_save"
                        type="submit"><?php esc_attr_e('Proceed', 'wpnotif'); ?></button>
            </div>

            <input type="hidden" class="wpnotif-fixvalue" name="action"
                   value="wpnotif_create_usergroup"/>

            <?php
            if (isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'custom_usergroups') {
                echo '<input class="wpnotif-fixvalue" type="hidden" name="tab" value="custom_usergroups"/>';
            }

            ?>
            <input type="hidden" class="wpnotif-fixvalue" name="newsletter_nonce"
                   value="<?php echo wp_create_nonce('wpnotif_newsletter'); ?>"/>
        </form>
        <?php
    }

    public function ajax_hooks()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_action('wp_ajax_wpnotif_show_group_users', array($this, 'show_group_users'));


        add_action('wp_ajax_wpnotif_create_usergroup', array($this, 'ajax_create_usergroup'));

        add_action('wp_ajax_wpnotif_delete_usergroup', array($this, 'ajax_delete_usergroup'));

        add_action('wp_ajax_wpnotif_create_predefined_group', array($this, 'ajax_create_predefined_group'));

        $this->ini_default_group_ajax();
    }

    public function ajax_delete_usergroup()
    {
        $this->check_access();


        $gids = sanitize_text_field($_POST['nids']);
        if (empty($gids)) {
            wp_send_json_error(array('message' => esc_attr__('Error! Invalid User Group', 'wpnotif')));
        }
        $gids = explode(",", $gids);
        foreach ($gids as $gid) {
            $gid = intval(preg_replace('/[^0-9.]+/', '', $gid));
            if ($gid) {
                $this->delete_usergroup($gid);
            }
        }
        wp_send_json_success(array('message' => esc_html__('Done', 'wpnotif')));
    }

    public function delete_usergroup($gid)
    {
        global $wpdb;
        $tb = $wpdb->prefix . self::usergroup_table;

        $wpdb->delete($tb, array('id' => $gid));
        $this->delete_user_from_group($gid);
    }

    public function delete_wp_user_from_group($gid, $user_id)
    {
        $uid = self::get_sub_user_id($user_id);

        if (empty($uid)) {
            return;
        }

        $data = array();
        $data['uid'] = $uid;

        $this->delete_user_from_group($gid, $data);
    }

    public function update_user_details_in_list($gid, $data, $where)
    {
        global $wpdb;
        $user_list_tb = $wpdb->prefix . self::usergroup_list_table;

        $where['gid'] = $gid;

        $wpdb->update($user_list_tb, $data, $where);
    }

    public function delete_user_from_group($gid, $data = array())
    {
        global $wpdb;
        $user_list_tb = $wpdb->prefix . self::usergroup_list_table;

        $data['gid'] = $gid;

        $wpdb->delete($user_list_tb, $data);
    }

    public function check_access()
    {
        check_ajax_referer('wpnotif_newsletter', 'newsletter_nonce', true);

        if (!current_user_can('manage_options') || !is_user_logged_in()) {
            if (WPNotif::is_doing_ajax()) {
                wp_send_json_error(array('message' => esc_attr__('Error! Unauthorized access', 'wpnotif')));
            } else {
                wp_die(esc_attr__('Error! Unauthorized access', 'wpnotif'));
            }
        }
    }

    public function ajax_create_usergroup()
    {
        $this->check_access();


        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_text_field($_POST['description']);
        if (empty($name) || empty($description)) {
            wp_send_json_error(array('message' => esc_html__('Please complete your settings', 'wpnotif')));
        }


        $group_data = array(
            'name' => $name,
            'description' => $description,
            'predefined' => 0,
            'list_type' => 'custom'
        );

        $gid = $this->create_user_group($group_data);

        ob_start();
        $data = array();
        $data['gid'] = $gid;

        $this->show_groups();
        $data['html'] = ob_get_clean();
        ob_end_clean();

        wp_send_json_success($data);
        die();
    }

    public function create_user_group($data)
    {
        global $wpdb;

        $db = $wpdb->insert($wpdb->prefix . self::usergroup_table, $data);

        if (empty($wpdb->insert_id) && !did_action('wpnotif_create_db')) {
            do_action('wpnotif_create_db');
        }
        return $wpdb->insert_id;
    }


    public function get_user_groups()
    {
        $groups = array();
        global $wpdb;
        $tb = $wpdb->prefix . self::usergroup_table;
        if ($wpdb->get_var("SHOW TABLES LIKE '$tb'") != $tb) {
            return $groups;
        }
        $usergroups = $wpdb->get_results(
            'SELECT * FROM ' . $tb . ' ORDER BY time ASC'
        );

        return $usergroups;
    }

    public static function get_group_details($group_id)
    {
        if (!is_numeric($group_id)) return null;

        global $wpdb;
        $tb = $wpdb->prefix . self::usergroup_table;

        $sql = "SELECT * FROM $tb WHERE id = %d LIMIT 1\n";

        $data = array();
        $data[] = $group_id;

        return $wpdb->get_row(
            $wpdb->prepare($sql, $data)
        );
    }

    public function get_group_users($group_id, $args = null)
    {
        if (!is_numeric($group_id)) return null;

        global $wpdb;
        $usergroup_list = $wpdb->prefix . self::usergroup_list_table;
        $subscriber_list = $wpdb->prefix . self::subscribers_table;


        $sql = "SELECT \n";

        if (isset($args['count'])) {
            $sql .= "count(*) \n";
        } else if (isset($args['select'])) {
            $sql .= esc_sql($args['select']) . " \n";
        } else {
            $sql .= "* \n";
        }

        $sql .= "FROM $subscriber_list \n";

        $sql .= "INNER JOIN $usergroup_list ON $subscriber_list.id=$usergroup_list.uid \n";
        $sql .= "WHERE (gid = %d AND inactive = %d) \n";
        $data = array();
        $data[] = $group_id;

        if (isset($args['only_wp'])) {
            $sql .= " AND (wp_id != %d) \n";
            $data[] = 0;
        }

        if (isset($args['inactive'])) {
            $data[] = 1;
        } else {
            $data[] = 0;
        }


        if (isset($args['id_greater_than'])) {
            $sql .= " AND ($usergroup_list.id > %d) \n";
            $data[] = $args['id_greater_than'];

        }

        if (!empty($args['search'])) {
            $sql .= " AND (first_name LIKE %s OR last_name LIKE %s OR phone LIKE %s)\n";
            $data[] = '%' . $args['search'] . '%';
            $data[] = '%' . $args['search'] . '%';
            $data[] = '%' . $args['search'] . '%';
        }

        if (!empty($args['order'])) {
            $order = $args['order'];

            $order_by = sanitize_sql_orderby($order['column'] . ' ' . $order['dir']);

            if ($order['column'] == 'id') {
                $order_by = $usergroup_list . '.' . $order_by;
            }
            $sql .= " ORDER BY $order_by \n";
        }

        if (!empty($args['limit'])) {
            $limit = $args['limit'];
            $sql .= " LIMIT %d, %d\n";
            $data[] = $limit['offset'];
            $data[] = $limit['limit'];
        } else if (!empty($args['only_limit'])) {
            $limit = $args['only_limit'];
            $sql .= " LIMIT %d \n";
            $data[] = $limit;
        }

        if (!empty($data)) {
            $query = $wpdb->prepare($sql, $data);
        } else {
            $query = $sql;
        }

        if (isset($args['count'])) {
            return $wpdb->get_var($query);
        } else {
            return $wpdb->get_results($query);
        }
    }

    public function count_users($group_id, $args = array())
    {
        $args['count'] = 1;
        $users = $this->get_group_users($group_id, $args);

        return $users;
    }

    private function get_usergroup($data, $limit)
    {
        return $this->get_result(self::usergroup_table, $data, $limit);
    }

    private function get_user_list_result($data, $limit)
    {
        return $this->get_result(self::usergroup_list_table, $data, $limit);
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

        $sql .= "ORDER BY id ASC";

        $query = $wpdb->prepare($sql, $values);

        if ($limit == 1) {
            return $wpdb->get_row($query);
        } else {
            return $wpdb->get_results($query);
        }
    }

}