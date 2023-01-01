<?php


if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'user_groups.php';


final class WPNotif_NewsLetter
{
    const running_status = 'running';
    const draft_status = 'draft';
    const pending_status = 'scheduled';
    const queued_status = 'queued';

    const paused_status = 'paused';

    const stopped_status = 'stopped';
    const completed_status = 'completed';
    const page = 'wpnotif-newsletter';
    protected static $_instance = null;
    public $ajax;
    public $usergroups;
    private $newsletter_table = 'wpnotif_newsletter';
    private $newsletter_send_history_table = 'wpnotif_newsletter_send_history';
    private $newsletter_history_table = 'wpnotif_newsletter_history';

    public function __construct()
    {
        $this->init_hooks();
        $this->usergroups = WPNotif_UserGroups::instance();
    }

    private function init_hooks()
    {
        $this->usergroups = WPNotif_UserGroups::instance();

        add_action('init', array($this, 'ajax_hooks'));


        add_action('wpnotif_create_db', array($this, 'wpnotif_create_db'));
        add_action('wpnotif_menu', array($this, 'add_menu'), 20);
        add_action('wpnotif_enqueue_scripts', array($this, 'enqueue_scripts'));

    }

    public function admin_footer_show_time($content){

        $content  = '<span class="wpnotif-hide">'.esc_html__('System Time','wpnotif').': ';

        $time = time();
        $content .= '<span class="wpnotif-footer_time" data-time="'.$time.'"></span>';

        $content .= '</span>';
        return $content;
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

    public static function cron_methods(){
        return array(
                'wp_cron' => array('label'=>esc_attr__('WordPress Cron Job','wpnotif')),
                'wpn_cron' => array('label'=>esc_attr__('WPNotif Cron Job','wpnotif')),
                'wpn_bg' => array('label'=>esc_attr__('WPNotif Background Task','wpnotif'))
        );
    }
    public static function show_settings()
    {

        $settings = get_option('wpnotif_newsletter_settings');
        $sending_frequency = !empty($settings['sending_frequency']) ? $settings['sending_frequency'] : 5;
        $selected_method = !empty($settings['cron_method']) ? $settings['cron_method'] : 'wp_cron';

        ?>
        <div class="wpnotif_admin_head"><span><?php esc_html_e('Newsletter', 'wpnotif'); ?></span></div>
            <table class="form-table form-switch">
                <tr>
                    <th scope="row"><label><?php esc_html_e('Sending Frequency', 'wpnotif'); ?></label></th>
                    <td>
                        <div class="wpnotif_field_floating_text">
                            <input type="number" name="sending_frequency"
                                   value="<?php echo esc_attr($sending_frequency); ?>"
                                   max="100" size="3" step="1" min="0"/>
                            <span class="floating_text"><?php esc_attr_e('Sms per Minute', 'wpnotif'); ?></span>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label><?php esc_html_e('Cron Method', 'wpnotif'); ?></label></th>
                    <td>
                        <select id="wpnotif_cron_method" name="cron_method">
                        <?php
                         foreach(self::cron_methods() as $key=>$cron_method){
                             $sel = '';
                             if($selected_method ==$key){
                                 $sel = 'selected';
                             }
                             echo '<option value="'.$key.'" '.$sel.'>'.$cron_method['label'].'</option>';
                         }

                         // wget -qO-  &> /dev/null
                         ?>
                         <option value="disabled" <?php if($selected_method =='disabled'){
                                 echo 'selected';
                             }?> ><?php esc_attr_e('Disable','wpnotif');?></option>
                        </select>
                        <p class="wpnotif_desc wpnotif_cron_desc wpnotif_wpn_cron">
                            php <?php echo WPNotif::get_directory_path().'wpnotif-cron.php';?>
                        </p>
                        <p class="wpnotif_desc wpnotif_cron_desc wpnotif_cron_desc_all">
                       
                        </p>
                        <p class="wpnotif_desc wpnotif_cron_disabled" style="display:none;">
                        <?php esc_attr_e('Newsletters and Pending payment notifications will probably not work if you disable cron.','wpnotif');?>
                        </p>
                    </td>
                </tr>


            </table>

        <?php

    }

    public static function get_formated_usergroup_list($predefined = false){
        $group_list = self::get_usergroup_list();
        $groups = array();

        $groups[] = array('label' => esc_attr__('None', 'wpnotif'), 'value' => 0);

        foreach ($group_list as $list) {
            if (!$predefined && $list->predefined == 1) {
                continue;
            }
            $groups[] = array(
                'label' => $list->name,
                'value' => self::format_group_id($list->id),
            );
        }
        return $groups;
    }

    public static function get_usergroup_list(){
        return WPNotif_UserGroups::instance()->get_user_groups();
    }

    public static function format_group_id($gid){
        return 'ug_' . esc_attr__($gid);
    }

    public static function enqueue_scripts()
    {
        if (!isset($_GET['page'])) {
            return;
        }
        if (empty($_GET['page']) || ('wpnotif-newsletter' !== $_GET['page'] && 'wpnotif-usergroups' !== $_GET['page'])) {
            return;
        }

        wp_enqueue_style('wpnotif-newsletter', WPNotif::get_dir('/assets/css/newsletter.min.css'), array(), WPNotif::get_version(), 'all');


        wp_enqueue_script('datepicker', WPNotif::get_dir('/assets/js/datepicker.min.js'), array(
            'jquery'
        ), null);

        wp_enqueue_script('datepicker-lang', WPNotif::get_dir('/assets/js/i18n/datepicker.en.js'), array('datepicker'), null);
        wp_enqueue_style('datepicker', WPNotif::get_dir('/assets/css/datepicker.min.css'), array(), null, 'all');


        wp_enqueue_script('papaparse', WPNotif::get_dir('/assets/js/papaparse.min.js'), array(), '5.0.2');


        wp_enqueue_script('datatables', 'https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.20/af-2.3.4/b-1.6.1/b-colvis-1.6.1/b-flash-1.6.1/b-html5-1.6.1/b-print-1.6.1/cr-1.5.2/fc-3.3.0/fh-3.1.6/kt-2.5.1/r-2.2.3/rg-1.1.1/rr-1.2.6/sc-2.0.1/sl-1.3.1/datatables.min.js', array(
            'jquery'
        ), null);

        wp_register_script('wpnotif-newsletter', WPNotif::get_dir('/assets/js/newsletter.js'), array(
            'jquery',
            'datepicker',
            'datatables',
            'papaparse'
        ), WPNotif::get_version(), true);

        $settings_array = array(
            "Error" => esc_attr__("Error! Please try again later", "wpnotif"),
            "PleasecompleteyourSettings" => esc_attr__("Please complete your Settings", 'wpnotif'),
            "ajax_url" => admin_url('admin-ajax.php'),
            "nonce" => wp_create_nonce('wpnotif_newsletter'),
            "creatingPredefinedGroups" => esc_attr__('Please wait, creating predefined groups. This is a one time process.','wpnotif'),
            "error_retrying" => esc_attr__("An error occurred while sending request to server, Retrying in 3 seconds!", "wpnotif"),
            "duplicate_import_failed"=>esc_attr__("Data contains duplicate entries, import has failed.",'wpnotif'),
            "partial_import"=>esc_attr__("Partial data imported, {duplicate} duplicate and {invalid} invalid entries.",'wpnotif')
        );
        wp_localize_script('wpnotif-newsletter', 'nl', $settings_array);

        wp_enqueue_script('wpnotif-newsletter');
    }

    public function wpnotif_create_db()
    {
        $this->init_db();
    }

    private function init_db()
    {
        global $wpdb;


        $newsletter_send_history_table = $wpdb->prefix . $this->newsletter_send_history_table;
        $newsletter_history_table = $wpdb->prefix . $this->newsletter_history_table;

        $newsletter_table = $wpdb->prefix . $this->newsletter_table;
        if ($wpdb->get_var("SHOW TABLES LIKE '$newsletter_table'") != $newsletter_table) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $newsletter_table (
										id BIGINT UNSIGNED NOT NULL auto_increment,
										name TEXT NOT NULL,
		          						status VARCHAR(200) NOT NULL,
										rid BIGINT NULL,
		          						progress BIGINT DEFAULT 0 NOT NULL,
		          						total_users BIGINT DEFAULT 0 NOT NULL,
		          						message TEXT NOT NULL,
		          						user_group_role TEXT NOT NULL,
		          						mobile_field_type VARCHAR(200) NOT NULL,
		          						mobile_field_key VARCHAR(200) NOT NULL,
		          						post_id BIGINT NULL,
		          						repeat_newsletter TINYINT DEFAULT 0 NULL,
		          						repeat_time TEXT NULL,
		          						execution_time BIGINT UNSIGNED NULL,
		          						route INT DEFAULT 1 NOT NULL,
		          						frequency INT DEFAULT 0 NULL,
		          						time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		          						PRIMARY KEY  (id),
		          						INDEX idx_rid (rid),
		          						INDEX idx_status (status),
		          						INDEX idx_execution_time (execution_time)
	            						) $charset_collate;";

            $sql2 = "CREATE TABLE $newsletter_history_table (
										id BIGINT UNSIGNED NOT NULL auto_increment,
										nid BIGINT NOT NULL,
										status VARCHAR(200) NULL,
										current_group TEXT NULL,
										completed_group TEXT NULL,
		          						start_time datetime DEFAULT CURRENT_TIMESTAMP NULL,
		          						end_time datetime NULL,
		          						sno BIGINT DEFAULT 0 NOT NULL,
		          						newsletter_details LONGTEXT NULL,
		          						PRIMARY KEY  (id),
		          						INDEX idx_nid (nid),
		          						INDEX idx_status (status)
	            						) $charset_collate;";

            $sql3 = "CREATE TABLE $newsletter_send_history_table (
										id BIGINT UNSIGNED NOT NULL auto_increment,
										sno BIGINT NOT NULL,
										rid BIGINT NOT NULL,
										type TEXT NULL,
										status TEXT NULL,
										phone TEXT NOT NULL,
										wp_id BIGINT UNSIGNED NULL,
										route INT DEFAULT 0 NULL,
		          						time datetime DEFAULT CURRENT_TIMESTAMP NULL,
		          						PRIMARY KEY  (id),
		          						INDEX idx_rid (rid)
	            						) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta(array($sql, $sql2, $sql3));
        }

    }

   

    public function settings_ui()
    {

        add_action('update_footer',array($this,'admin_footer_show_time'));

        $active_tab = 'newsletter';

        if (isset($_GET['tab'])) {
            $active_tab = !empty($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $active_tab;
        }
        $this->init_db();


        if (isset($_GET['rid'])) {
            $rid = filter_var($_GET['rid'], FILTER_VALIDATE_INT);
            $history = $this->get_running_instance_by_id($rid);

            if (empty($rid) || empty($history)) {
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
                        <li><a href="?page=<?php echo self::page; ?>&amp;tab=newsletter"
                               class="updatetabview wpnotif-nav-tab  <?php echo $active_tab == 'newsletter' ? 'wpnotif-nav-tab-active' : ''; ?>"
                               tab="newslettertab"><?php esc_html_e('Scheduled Newsletter', 'wpnotif'); ?></a></li>

                        <li><a href="?page=<?php echo self::page; ?>&amp;tab=history"
                               refresh="1"
                               class="updatetabview wpnotif-nav-tab  <?php echo $active_tab == 'history' ? 'wpnotif-nav-tab-active' : ''; ?>"
                               tab="historytab"><?php esc_html_e('History', 'wpnotif'); ?></a>
                        </li>

                    </ul>
                </div>

                <div class="wpnotif-action-buttons">
                    <div class="wpnotif-button wpnotif-button_green newsletter-bulk_delete newsletter-hide_icon"><?php esc_attr_e('Delete Selected', 'wpnotif'); ?></div>
                    <div class="wpnotif-button wpnotif-button_green open_modal"
                         data-show="newsletter-add_new"
                         data-title="<?php esc_attr_e('Add New Newsletter', 'wpnotif'); ?>"><?php esc_attr_e('Add New', 'wpnotif'); ?></div>
                </div>
            </div>

            <div class="wpnotif_content">
                <div data-tab="newslettertab"
                     class="wpnotif_admin_in_pt newslettertab digtabview <?php echo $active_tab == 'newsletter' ? 'digcurrentactive' : '" style="display:none;'; ?>">
                    <?php $this->show_newsletter(); ?>
                </div>
                <div data-tab="historytab"
                     class="wpnotif_admin_in_pt historytab digtabview <?php echo $active_tab == 'history' ? 'digcurrentactive' : '" style="display:none;'; ?>">
                    <?php
                    if ($active_tab == 'history') {
                        $this->show_history();
                    }
                    ?>
                </div>

                <div data-tab="runninghistorytab"
                     class="wpnotif_admin_in_pt runninghistorytab digtabview <?php echo $active_tab == 'runninghistorytab' ? 'digcurrentactive' : '" style="display:none;'; ?>">
                    <?php if ($active_tab == 'runninghistorytab') $this->show_running_history_layout($rid); ?>
                </div>
            </div>


        </div>
        <?php
        $this->newsletter_modal('newsletter-add_new', 'add_new_newsletter_modal');

        WPNotif::WPNotif_loading();
    }

    public function get_running_instance_by_id($id)
    {
        $data = array();
        $data['id'] = $id;
        return $this->get_result($this->newsletter_history_table, $data, 1);
    }

    private function get_result($table, $data, $limit)
    {
        global $wpdb;
        $table = $wpdb->prefix . $table;


        $sql = "SELECT * FROM $table WHERE ";

        $keys = array();
        $values = array();
        if(isset($data['less_equal'])){
            $less_equal = $data['less_equal'];
            $keys[] = esc_sql($less_equal['key']) . ' <= %s';
            $values[] = $less_equal['value'];
            unset($data['less_equal']);
        }

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

    public function update_newsletter_usercount($newsletter){
        $count = $this->count_newsletter_user($newsletter);
        $this->update_newsletter_by_id($newsletter->id,array('total_users'=>$count));

    }
    public function count_newsletter_user($newsletter){
        if(empty($newsletter) || empty($newsletter->user_group_role)) return 0;


        $count = 0;
        $user_groups = explode(',', $newsletter->user_group_role);

        foreach($user_groups as $user_group){
        if (WPNotif_NewsLetter_Handler::starts_with('ug', $user_group)) {
                $gid = str_replace('ug_', '', $user_group);
                $count = $count + $this->usergroups->count_users($gid);

            }
        }

        return $count;
    }

    public function get_newsletters(){
        global $wpdb;
        $tb = $wpdb->prefix . $this->newsletter_table;
        if ($wpdb->get_var("SHOW TABLES LIKE '$tb'") != $tb) {
            return array();
        }
        return $wpdb->get_results(
            'SELECT * FROM ' . $tb . ' ORDER BY time DESC'
        );
    }


    public function show_newsletter()
    {



        WPNotif_NewsLetter_Handler::instance()->check_newsletter_status();

        $newsletters = $this->get_newsletters();

        ?>

        <table class="wpnotif-scheduled-newsletter_table wpnotif_newsletter_list">
            <?php
            $this->newsletter_tablehead();
            $button_conditions = array(
                'edit' => array(self::paused_status, self::draft_status, self::pending_status),
                'view' => array(self::completed_status, self::stopped_status, self::running_status),
                'duplicate' => array(self::paused_status, self::draft_status, self::pending_status, self::stopped_status, self::completed_status),
                'pause' => array(self::running_status),
                'stop' => array(self::running_status),
                'run' => array(self::paused_status, self::draft_status, self::pending_status),
            );

            $current_time = time();
            foreach ($newsletters as $newsletter) {

                $repeat_time = '';
                $repeat_period = '';
                if(!empty($newsletter->repeat_time)){
                    $repeat = explode('-',$newsletter->repeat_time);
                    $repeat_time = $repeat[0];
                    $repeat_period = $repeat[1];
                }
                $data = array(
                    'name' => $newsletter->name,
                    'user_group' => $newsletter->user_group_role,
                    'mobile_field_type' => $newsletter->mobile_field_type,
                    'mobile_field_key' => $newsletter->mobile_field_key,
                    'message' => $newsletter->message,
                    'schedule' =>  self::formatJSDate($newsletter->execution_time),
                    'post_id' => $newsletter->post_id,
                    'newsletter_repeat' => $newsletter->repeat_newsletter,
                    'repeat_time' => $repeat_time,
                    'repeat_period' => $repeat_period,
                    'route' => $newsletter->route,
                );


                $newsletter_time = $newsletter->execution_time;
                if ($newsletter_time < 0 || empty($newsletter_time)) {
                    $newsletter_time = '-';
                } else {
                    $newsletter_time = self::formatDate($newsletter_time);
                }
                $status = $newsletter->status;

                if (in_array($status, $button_conditions['view']) || $status == self::paused_status) {
                    $running_instance = $this->get_instance(array('nid' => $newsletter->id, 'status' => $status));
                }

                $progress = 0;
                if($status==self::completed_status){
                    $data['schedule'] = '';

                    $progress = 100;
                }else if($status==self::pending_status){
                    $progress = 0;
                }else if($status==self::running_status){
                    $total_users = $this->count_newsletter_user($newsletter);
                    $progress = 100 * $newsletter->progress/$total_users;
                }else if(!empty($running_instance)){
                    if(empty($newsletter->total_users)){
                        $progress = 0;
                    }else{
                        $progress = 100 * $newsletter->progress/$newsletter->total_users;
                    }
                }

                $progress = min(100,round($progress,2));

                ?>
                <tr id="newsletter_<?php echo $newsletter->id; ?>" data-current-state="<?php echo esc_attr($newsletter->status);?>">
                    <td>
                        <label class="wpnotif-newsletter_name wpnotif-checkbox">
                            <input type="checkbox" name="selected_newsletter[]">
                            <?php echo $newsletter->name; ?>
                        </label>
                    </td>
                    <td>
                        <div class="wpnotif-newsletter_exec_time"><?php echo $newsletter_time; ?></div>
                    </td>
                    <td>
                    <div class="wpnotif-newsletter_status_container">
                        <div class="wpnotif-newsletter_status_progress" style="width: <?php echo $progress;?>%"></div>
                        <div class="wpnotif-newsletter_status">
                            <?php
                                if($status==self::running_status){
                                    echo $progress.'%';
                                }else if($status==self::pending_status && $current_time > $newsletter->execution_time){
                                    echo self::queued_status;
                                }else {
                                    echo $status;
                                }
                            ?>
                        </div>

                    </div>
                    </td>
                    <td>
                        <div class="wpnotif-single-action-button_container wpnotif-action"
                             data-json="<?php echo esc_attr(json_encode($data)); ?>">

                            <?php if (in_array($status, $button_conditions['view'])) {
                                if(!empty($running_instance)){
                                $running_link = $this->create_instance_history_link($running_instance->id);
                                ?>
                                <div class="wpnotif-single-action_button wpnotif_link"
                                     href="<?php esc_attr_e($running_link); ?>">
                                    <div class="wpnotif-action-icon_button wpnotif-icon view-icon"></div>
                                    <?php esc_attr_e('View', 'wpnotif'); ?>
                                </div>
                                <?php
                                }
                            }
                            if (in_array($status, $button_conditions['edit'])) {
                                ?>

                                <div class="wpnotif-single-action_button newsletter_edit"
                                     data-show="newsletter-add_new"
                                     data-title="<?php esc_attr_e('Edit Newsletter', 'wpnotif'); ?>">
                                    <div class="wpnotif-action-icon_button wpnotif-icon edit-icon"></div>
                                    <?php esc_attr_e('Edit', 'wpnotif'); ?>
                                </div>
                                <?php
                            }

                            if (in_array($status, $button_conditions['pause'])) {

                                ?>
                                <div class="wpnotif-single-action_button newsletter_change_state"
                                     data-state="pause"
                                     data-action="wpnotif_newsletter_change_state"
                                     data-nonce="<?php echo wp_create_nonce('change_state_newsletter_' . $newsletter->id); ?>">
                                    <div class="wpnotif-action-icon_button wpnotif-icon pause-icon"></div>
                                    <?php esc_attr_e('Pause', 'wpnotif'); ?>
                                </div>
                                <?php
                            }

                            if (in_array($status, $button_conditions['stop'])) {

                                ?>
                                <div class="wpnotif-single-action_button newsletter_change_state"
                                     data-state="stop"
                                     data-action="wpnotif_newsletter_change_state"
                                     data-nonce="<?php echo wp_create_nonce('change_state_newsletter_' . $newsletter->id); ?>">
                                    <div class="wpnotif-action-icon_button wpnotif-icon stop-icon"></div>
                                    <?php esc_attr_e('Stop', 'wpnotif'); ?>
                                </div>
                                <?php
                            }

                            if (in_array($status, $button_conditions['duplicate'])) {

                                ?>
                                <div class="wpnotif-single-action_button newsletter_duplicate"
                                     data-show="newsletter-add_new"
                                     data-title="<?php esc_attr_e('Add New Newsletter', 'wpnotif'); ?>">
                                    <div class="wpnotif-action-icon_button wpnotif-icon duplicate-icon"></div>
                                    <?php esc_attr_e('Duplicate', 'wpnotif'); ?>
                                </div>
                                <?php
                            }
                            if (in_array($status, $button_conditions['run'])) {


                                $state = $status == self::paused_status ? 'resume' : 'run';

                                ?>
                                <div class="wpnotif-single-action_button newsletter_change_state"
                                     data-state="<?php echo esc_attr($state); ?>"
                                     data-action="wpnotif_newsletter_change_state"
                                     data-nonce="<?php echo wp_create_nonce('run_newsletter_' . $newsletter->id); ?>">
                                    <div class="wpnotif-action-icon_button wpnotif-icon run-now-icon"></div>
                                    <?php
                                    if ($state == 'resume') {
                                        esc_attr_e('Resume', 'wpnotif');
                                    } else {
                                        esc_attr_e('Run Now', 'wpnotif');
                                    }
                                    ?>
                                </div>
                                <?php

                            }

                            ?>
                        </div>
                    </td>
                    <td>
                        <?php
                        if ($status != self::running_status) {
                            ?>
                            <div class="wpnotif-action-icon_button wpnotif-icon delete-icon delete-newsletter"></div>
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

    public function newsletter_tablehead()
    {
        ?>
        <thead>
        <tr>
            <th class="wpnotif-column_35 newsletter_name"><?php esc_attr_e('Name', 'wpnotif'); ?></th>
            <th class="wpnotif-column_15 newsletter_execution-date"><?php esc_attr_e('Execution Date', 'wpnotif'); ?></th>
            <th class="wpnotif-column_15 newsletter_status"><?php esc_attr_e('Status', 'wpnotif'); ?></th>
            <th class="wpnotif-column_35 newsletter_action"></th>
            <th class="wpnotif-column_5 newsletter_delete"></th>
        </tr>
        </thead>
        <?php
    }

     public static function formatJSDate($time, $seconds = false)
    {
        $format = 'M d, Y h:i A';

        return $time==0 ? '-' : date($format, $time);
    }

    public static function formatDate($time, $seconds = false)
    {
        if($seconds){
            $format = 'h:i:s A, d M Y';
        }else{
            $format = 'h:i A, d M Y';
        }

        return $time==0 ? '-' : date($format, $time);
    }

    public function get_instance($data)
    {
        return $this->get_result($this->newsletter_history_table, $data, 1);
    }

    public function create_instance_history_link($id)
    {
        return admin_url('admin.php?page=' . self::page . '&tab=runninghistorytab&rid=' . $id);
    }

    public function show_history()
    {
        $data = array();
        $data[]['data'] = 'name';
        $data[]['data'] = 'status';
        $data[]['data'] = 'start_time';
        $data[]['data'] = 'end_time';
        $data = json_encode($data);


        ?>
        <table class="wpnotif-scheduled-newsletter_table dataTable"
               id="wpnotif_data_table"
               data-id="0"
               data-disable_sort="<?php esc_attr_e(json_encode(array(0))); ?>"
               data-disable_search="true"
               data-action="wpnotif_show_newsletter_history"
               data-nonce="<?php echo wp_create_nonce('wpnotif_newsletter'); ?>"
               data-coloumn="<?php esc_attr_e($data); ?>"
        >
            <thead>
            <tr>
                <th class="newsletter_name"><?php esc_attr_e('Name', 'wpnotif'); ?></th>
                <th class="newsletter_status"><?php esc_attr_e('Status', 'wpnotif'); ?></th>
                <th class="newsletter_start-date"><?php esc_attr_e('Start Time', 'wpnotif'); ?></th>
                <th class="newsletter_end-date"><?php esc_attr_e('End Time', 'wpnotif'); ?></th>
            </tr>
            </thead>
        </table>
        <?php
    }

    public function show_running_history_layout($rid)
    {
        $data = array();
        $data[]['data'] = 'sno';
        $data[]['data'] = 'phone';
        $data[]['data'] = 'status';
        $data[]['data'] = 'time';
        $data = json_encode($data);
        ?>
        <table class="wpnotif-scheduled-newsletter_table dataTable"
               id="wpnotif_data_table"
               data-id="<?php esc_attr_e($rid); ?>>"
               data-action="wpnotif_show_newsletter_running_history"
               data-nonce="<?php echo wp_create_nonce('wpnotif_newsletter'); ?>"
               data-coloumn="<?php esc_attr_e($data); ?>"
        >
            <thead>
            <tr>
                <th class="newsletter_id"><?php esc_attr_e('SR.', 'wpnotif'); ?></th>
                <th class="newsletter_phone"><?php esc_attr_e('Phone', 'wpnotif'); ?></th>
                <th class="newsletter_status"><?php esc_attr_e('Status', 'wpnotif'); ?></th>
                <th class="newsletter_time"><?php esc_attr_e('Time', 'wpnotif'); ?></th>
            </tr>
            </thead>
        </table>
        <?php
    }

    public function newsletter_modal($modal_class, $content)
    {
        ?>
        <div class="wpnotif-hide wpnotif-modal wpnotif_admin_conf wpnotif_admin_fields form-table <?php echo $modal_class; ?>"
             data-replace="newslettertab">
            <div class="wpnotif-modal_overlay">
                <div class="wpnotif-modal_wrapper">
                    <div class="wpnotif-modal_box">
                    <div class="wpnotif-modal_container">
                        <?php
                        $this->$content();
                        ?>
                        </div>
                    <div class="wpnotif-modal_bg wpnotif-hide_modal"></div>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_history()
    {
        $this->check_access();

        $table = $this->newsletter_history_table;


        $coloumn = array(0 => 'start_time', 1 => 'status', 2 => 'start_time', 3 => 'end_time');


        $args = $this->get_args($coloumn, 'status');
        $history = array();

        $where = array('key'=>'status','type'=>'IN','value'=>array(self::stopped_status,self::completed_status));

        $raw_data = $this->get_data($table, $where, $args);


        $newsletter_list = array();
        foreach ($raw_data as $row) {
            $entry = array();

            $nid = $row->nid;
            if (!array_key_exists($nid, $newsletter_list)) {
                $newsletter = $this->get_newspaper_by_id($nid);
                if(!empty($newsletter)){
                    $newsletter_list[$nid] = $newsletter->name;
                }else{
                    $details = json_decode($row->newsletter_details);
                    $newsletter_list[$nid] = $details->name;
                }
            }
            $entry['name'] = '<div class="wpnotif_link" href="' . esc_attr($this->create_instance_history_link($row->id)) . '">' . $newsletter_list[$nid] . '</div>';

            $entry['status'] = $row->status;
            $entry['start_time'] = self::formatDate(strtotime($row->start_time));
            $entry['end_time'] = self::formatDate(strtotime($row->end_time));

            $history[] = $entry;
        }

        $data = array();

        $data['draw'] = absint($_REQUEST['draw']);
        $data['recordsTotal'] = $this->count_data($table);

        unset($args['limit']);
        $data['recordsFiltered'] = $this->count_data($table, $where, $args);

        $data['data'] = $history;

        echo json_encode($data);
        die();
    }

    public function check_access($action = 'wpnotif_newsletter')
    {
        check_ajax_referer($action, 'newsletter_nonce', true);

        if (!current_user_can('manage_options') || !is_user_logged_in()) {
            if (WPNotif::is_doing_ajax()) {
                wp_send_json_error(array('message' => esc_attr__('Error! Unauthorized access', 'wpnotif')));
            } else {
                wp_die(esc_attr__('Error! Unauthorized access', 'wpnotif'));
            }
        }
    }

    private function get_args($coloumn, $search_args)
    {
        $limit = absint($_REQUEST['length']);
        $limit = !filter_var($limit, FILTER_VALIDATE_INT) ? 100 : $limit;

        $start = absint($_REQUEST['start']);
        $start = !filter_var($start, FILTER_VALIDATE_INT) ? 0 : $start;

        $request_order = $_REQUEST['order'][0];


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
            $args['search_arg'] = $search_args;
        }
        return $args;
    }

    public function get_data($table, $where = null, $args = null)
    {
        global $wpdb;
        $tb = $wpdb->prefix . $table;

        $sql = "SELECT \n";

        if(isset($args['count'])) {
            $sql .= "count(*) \n";
        }else{
            $sql .= "* \n";
        }

        $sql .= "FROM $tb \n";


        $data = array();

        if ($where != null) {
            if(!empty($where['type']) && $where['type']=='IN'){
                $values = array_map(function($v) {
                    return "'" . esc_sql($v) . "'";
                    }, $where['value']);

                $sql .= "WHERE (" . $where['key'] . " IN (".implode(",", $values)."))";

            }else{
                $sql .= "WHERE (" . $where['key'] . " = %s)";
                $data[] = $where['value'];
            }
        }

        if (!empty($args['search'])) {
            $sql .= " AND (" . $args['search_arg'] . " LIKE %s)\n";
            $data[] = '%' . $args['search'] . '%';
        }

        if (!empty($args['order'])) {
            $order = $args['order'];
            $order_by = sanitize_sql_orderby($order['column'].' '.$order['dir']);

            $sql .= " ORDER BY $order_by \n";
        }

        if (!empty($args['limit'])) {
            $sql .= " LIMIT %d, %d\n";
            $limit = $args['limit'];
            $data[] = $limit['offset'];
            $data[] = $limit['limit'];
        }

        if(!empty($data)){
            $query = $wpdb->prepare($sql, $data);
        }else{
            $query = $sql;
        }

        if (isset($args['count'])) {
            return $wpdb->get_var($query);
        } else {
            return $wpdb->get_results($query);
        }
    }

    public function get_newspaper_by_id($id)
    {
        $data = array();
        $data['id'] = $id;
        return $this->get_result($this->newsletter_table, $data, 1);
    }

    public function count_data($table, $where = null, $args = array())
    {
        $args['count'] = 1;
        $data = $this->get_data($table, $where, $args);
        return $data;
    }

    public function ajax_running_history()
    {
        $this->check_access();

        $table = $this->newsletter_send_history_table;
        $coloumn = array(0 => 'sno', 1 => 'phone', 2 => 'status',3 => 'time');

        $args = $this->get_args($coloumn, 'phone');
        $history = array();

        $where = array('key' => 'rid', 'value' => absint($_REQUEST['id']));
        $raw_data = $this->get_data($table, $where, $args);


        foreach ($raw_data as $row) {

            $entry['sno'] = $row->sno;
            $entry['phone'] = $row->phone;
            $entry['status'] = $row->status;
            $entry['time'] = self::formatDate(strtotime($row->time));

            $history[] = $entry;
        }

        $data = array();

        $data['draw'] = absint($_REQUEST['draw']);
        $data['recordsTotal'] = $this->count_data($table, $where);

        unset($args['limit']);
        $data['recordsFiltered'] = $this->count_data($table, $where, $args);

        $data['data'] = $history;

        echo json_encode($data);
        die();
    }

    public function update_settings()
    {

        $option_slug = 'wpnotif_newsletter_settings';

        $values = array();
        $keys = array('sending_frequency','cron_method');
        foreach ($keys as $key) {
            $values[$key] = sanitize_text_field($_POST[$key]);
        }

        if($_POST['cron_method']!='wpn_bg'){
             delete_option('_wpnotif_bg_last_execution_time');
        }

        update_option($option_slug, $values);

    }

    public function add_new_newsletter_modal()
    {
        $this->modal_header(esc_html__('Add New Newsletter', 'wpnotif'));

        ?>

        <form class="modal_form_details" data-type="newsletter" method="post" autocomplete="off">
            <div class="wpnotif-modal_body">
                <div class="wpnotif-newsletter_field wpnotif-newsletter_name">
                    <label><?php esc_attr_e('Name', 'wpnotif'); ?></label>
                    <input type="text" name="name" class="wpnotif-name" required/>
                </div>

                <div class="wpnotif-newsletter_field wpnotif-newsletter_user-group">
                    <label><?php esc_attr_e('User Group', 'wpnotif'); ?></label>
                    <?php
                    self::show_usergroup_list();
                    ?>
                </div>
                <div class="wpnotif-newsletter_field wpnotif-newsletter_route">
                    <label><?php esc_attr_e('Route', 'wpnotif'); ?></label>
                    <select name="route" class="wpnotif-route" required>
                        <option value="1"><?php esc_attr_e('SMS', 'wpnotif'); ?></option>
                        <option value="1001"><?php esc_attr_e('WhatsApp', 'wpnotif'); ?></option>
                    </select>
                </div>
                <div class="wpnotif-newsletter_field wpnotif-newsletter_mobile-field">
                    <label><?php esc_attr_e('Phone Number Field', 'wpnotif'); ?></label>
                    <select name="mobile_field_type" class="wpnotif-mobile_field_type" required>
                        <option value="1"><?php esc_attr_e('Auto', 'wpnotif'); ?></option>
                        <option value="-1"><?php esc_attr_e('Custom Meta Key', 'wpnotif'); ?></option>
                    </select>
                    <input class="wpnotif-newsletter_mobile_number-key wpnotif-mobile_field_key" type="text"
                           name="mobile_field_key"/>
                </div>

                <div class="wpnotif-newsletter_field wpnotif-newsletter_message-field">
                    <label for="wpnotif-newsletter_message"><?php esc_attr_e('Message', 'wpnotif'); ?></label>
                    <textarea id="wpnotif-newsletter_message" class="wpnotif-message" name="message"
                              required></textarea>
                </div>
                <div class="wpnotif-newsletter_field wpnotif-newsletter_schedule">
                    <label><?php esc_attr_e('Schedule', 'wpnotif'); ?></label>
                    <input type="text" name="schedule" class="wpnotif-schedule"/>
                </div>

                <div class="wpnotif-newsletter_field wpnotif-newsletter_repeat wpnotif-input-inline-small">


                <label class="wpnotif-newsletter_name wpnotif-checkbox">
                            <input type="checkbox" name="newsletter_repeat" class="wpnotif-newsletter_repeat">
                            &nbsp;<?php esc_attr_e('Repeat it every', 'wpnotif'); ?>
                            </label>&nbsp




                <input type="text" name="repeat_time" class="wpnotif-repeat_time" size="3" value="1"/>
                <select name="repeat_period" class="wpnotif-repeat_period">
                <option value="day"><?php esc_attr_e('Days','wpnotif');?></option>
                <option value="week"><?php esc_attr_e('Weeks','wpnotif');?></option>
                <option value="month"><?php esc_attr_e('Months','wpnotif');?></option>
                <option value="year"><?php esc_attr_e('Years','wpnotif');?></option>
                </select>

                </div>

            </div>
            <div class="wpnotif-modal_footer">
                <button class="wpnotif-button wpnotif-button_green newsletter_save"
                        type="submit"><?php esc_attr_e('Save', 'wpnotif'); ?></button>
            </div>
            <input type="hidden" class="wpnotif-fixvalue" name="action"
                   value="wpnotif_create_newsletter"/>
            <input type="hidden" name="post_id" class="wpnotif-post_id" value="0" />
            <input type="hidden" class="wpnotif-fixvalue" name="newsletter_nonce"
                   value="<?php echo wp_create_nonce('wpnotif_newsletter'); ?>"/>
        </form>
        <?php
    }

    public function modal_header($title)
    {
        ?>
        <div class="wpnotif-modal_header">
            <div class="modal-title">
                <?php echo $title; ?>
            </div>
        </div>
        <?php
    }

    public static function show_usergroup_list(){
        ?>
        <select name="user_group[]" class="wpnotif-user_group wpnotif_multiselect_enable"
                            multiple="multiple">
                        <?php

                        $user_groups = self::get_usergroup_list();

                        $predefined_groups = array();
                        $user_defined_groups = array();
                        foreach ($user_groups as $user_group) {
                            $gid = $user_group->id;
                            $group_name = $user_group->name;
                            $option = '<option value="'.self::format_group_id($gid).'">' . esc_html($group_name) . '</option>';
                            if($user_group->predefined==1){
                                $predefined_groups[]= $option;
                            }else{
                                $user_defined_groups[]= $option;
                            }
                        }

                        echo '<optgroup label="' . esc_html__('Predefined Groups', 'wpnotif') . '">';
                        foreach($predefined_groups as $option){
                            echo $option;
                        }
                        echo '</optgroup>';

                        echo '<optgroup label="' . esc_html__('User Groups', 'wpnotif') . '">';
                        foreach($user_defined_groups as $option){
                            echo $option;
                        }
                        echo '</optgroup>';

                        ?>
        </select>

        <?php
    }

    public function ajax_hooks()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_action('wp_ajax_wpnotif_create_newsletter', array($this, 'ajax_create_newsletter'));


        add_action('wp_ajax_wpnotif_refresh_newsletter', array($this, 'ajax_refresh_newsletter'));

        add_action('wp_ajax_wpnotif_show_newsletter_history', array($this, 'ajax_history'));

        add_action('wp_ajax_wpnotif_show_newsletter_running_history', array($this, 'ajax_running_history'));


        add_action('wp_ajax_wpnotif_delete_newsletter', array($this, 'ajax_delete_newsletter'));

        add_action('wpnotif_admin_update_settings', array($this, 'update_settings'));

        add_action('wp_ajax_wpnotif_newsletter_change_state', array($this, 'ajax_change_state'));

    }

    public function ajax_change_state()
    {
        $nid = intval(preg_replace('/[^0-9.]+/', '', $_POST['nid']));

        if (empty($nid)) {
            wp_send_json_error(array('message' => esc_attr__('Error! Invalid Newsletter', 'wpnotif')));
        }
        $state = $_POST['state'];

        $current_state = $_POST['current_state'];

        $newsletter = $this->get_newspaper_by_id($nid);
        if($newsletter->status!=$current_state){
            wp_send_json_error(array('message' => esc_attr__('Error, please refresh your page!', 'wpnotif')));
        }

        if ($state == 'run' || $state == 'resume') {
            $this->check_access('run_newsletter_' . $nid);

            $new_instance = true;
            if ($state == 'resume') $new_instance = false;

            $run_newsletter = $this->run_newsletter($nid, $new_instance, false);
            $status = __('running', 'wpnotif');
        } else {
            $this->check_access('change_state_newsletter_' . $nid);

            if ($state == 'pause') {
                $change_status = self::paused_status;
                $status = __('Paused', 'wpnotif');
            } else {
                $change_status = self::stopped_status;
                $status = __('stopped', 'wpnotif');
            }
            $this->change_status_newsletter($nid, $change_status);
        }

        if (!empty($run_newsletter) && is_wp_error($run_newsletter)) {
            wp_send_json_error(array('message' => $run_newsletter->get_error_message()));
        } else {

            ob_start();
            $data = array();
            $data['message'] = sprintf(__('Newsletter is now %s!', 'wpnotif'), $status);
            $this->show_newsletter();
            $data['html'] = ob_get_clean();
            ob_end_clean();

            wp_send_json_success($data);
        }
    }

    public function run_newsletter($nid, $new_instance = true, $force = false)
    {

        $error = new WP_Error();

        $newsletter = $this->get_newspaper_by_id($nid);

        if (!$newsletter) {
            $error->add('not_found', esc_attr__('Newsletter not found!', 'wpnotif'));
            return $error;
        }
        if (empty($newsletter->message) || empty($newsletter->user_group_role)) {
            $error->add('incomplete_details', esc_attr__('Please fill in all newsletter details to run it!', 'wpnotif'));
            return $error;
        }

        if (!$force) {
            $check_running = $this->get_active_newsletter();
            if ($check_running) {
                $error->add('already_running', esc_attr__('Please stop previous newsletter before running new one!', 'wpnotif'));
                return $error;
            }
        } else {
            $this->stop_all_newsletter();
        }

        $data = array();

        if ($new_instance) {
            $rid = $this->create_running_instance($nid);
            $data['progress'] = 0;
        } else {
            $running_instance = $this->get_instance(array('status' => self::paused_status, 'nid' => $nid));
            if (empty($running_instance)) {
                $error->add('error_resuming', esc_attr__('Error while resuming newsletter!', 'wpnotif'));
                return $error;
            }
            $rid = $running_instance->id;
            $this->update_running_instance_by_id($rid,array('status'=>self::running_status));
        }

        $data['status'] = self::running_status;
        $data['rid'] = $rid;
        $data['execution_time'] = time();

        return $this->update_newsletter($data, array('id' => $nid));
    }

    public function get_active_newsletter()
    {
        return $this->get_status(self::running_status);
    }

    public function get_status($status)
    {
        $data = array('status' => $status);
        return $this->get_newspaper($data);
    }

    private function get_newspaper($data)
    {
        return $this->get_result($this->newsletter_table, $data, 1);
    }

    public function stop_all_newsletter()
    {
        $data = array('status' => self::stopped_status);
        $where = array('status' => self::running_status);
        $this->update_newsletter($data, $where);

        $data['current_group'] = '';
        $data['completed_group'] = '';
        $data['end_time'] = date("Y-m-d H:i:s", time());
        $this->update_running_instance($data, $where);
    }

    public function update_newsletter_by_id($nid, $data)
    {
        return $this->set_value($this->newsletter_table, $data, array('id'=>$nid));
    }

    public function update_newsletter($data, $where)
    {
        $update = $this->set_value($this->newsletter_table, $data, $where);

        return $update;
    }

    private function set_value($table, $data, $where)
    {
        global $wpdb;
        $table = $wpdb->prefix . $table;
        return $wpdb->update($table, $data, $where);
    }

    public function update_running_instance($data, $where)
    {
        return $this->set_value($this->newsletter_history_table, $data, $where);
    }

    private function create_running_instance($newsletter_id)
    {
        global $wpdb;
        $data = array();
        $data['start_time'] = date("Y-m-d H:i:s", time());
        $data['status'] = self::running_status;
        $data['nid'] = $newsletter_id;

        $this->insert($this->newsletter_history_table, $data);

        return $wpdb->insert_id;
    }

    private function insert($table, $data)
    {
        return $this->bulk_insert($table, array($data));
    }

    private function bulk_insert($table, $rows)
    {
        global $wpdb;
        $table = $wpdb->prefix . $table;

        $columns = array_keys(reset($rows));

        $columnList = '`' . implode('`, `', $columns) . '`';

        $sql = "INSERT INTO `$table` ($columnList) VALUES\n";
        $placeholders = array();
        $data = array();

        // Build placeholders for each row, and add values to data array
        foreach ($rows as $row) {
            $rowPlaceholders = array();

            foreach ($row as $key => $value) {
                $data[] = $value;
                $rowPlaceholders[] = '%s';
            }

            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        // Stitch all rows together
        $sql .= implode(",\n", $placeholders);


        return $wpdb->query($wpdb->prepare($sql, $data));
    }

    public function change_status_newsletter($nid, $status)
    {
        $error = new WP_Error();
        $newsletter = $this->get_newspaper_by_id($nid);

        if (!$newsletter) {
            $error->add('not_found', esc_attr__('Newsletter not found!', 'wpnotif'));
            return $error;
        }

        $newsletter = $this->get_newspaper_by_id($nid);

        $this->update_instance_status($newsletter->rid, $status);

        return $this->update_newsletter_status($nid, $status);
    }

    public function update_instance_status($rid, $status)
    {
        $where = array('id' => $rid);
        $data = array('status' => $status);

        if($status==self::stopped_status){
            $data['end_time'] =self::database_time();
        }

        return $this->update_running_instance($data, $where);
    }

    public function update_newsletter_status($nid, $status)
    {
        $where = array('id' => $nid);
        $data = array('status' => $status);
        return $this->update_newsletter($data, $where);
    }

    public function get_running_instance($data = array())
    {
        $data['status'] = self::running_status;

        return $this->get_result($this->newsletter_history_table, $data, 1);
    }

    public function ajax_delete_newsletter()
    {
        $this->check_access();


        $nids = sanitize_text_field($_POST['nids']);
        if (empty($nids)) {
            wp_send_json_error(array('message' => esc_attr__('Error! Invalid Newsletter', 'wpnotif')));
        }
        $nids = explode(",", $nids);
        $this->delete_newsletter($nids);
        wp_send_json_success(array('message' => esc_html__('Done', 'wpnotif')));
    }


    public function delete_newsletter($nids){
        if(!is_array($nids)){
            $nids = array($nids);
        }

        global $wpdb;
        $tb = $wpdb->prefix . $this->newsletter_table;
         foreach ($nids as $nid) {
            $nid = intval(preg_replace('/[^0-9.]+/', '', $nid));
            $newsletter = $this->get_newspaper_by_id($nid);
            if ($nid && !empty($newsletter)) {
                $wpdb->delete($tb, array('id' => $nid), array('%d'));

                $details = array(
                        'name'=>$newsletter->name
                );
                $data = array('status' => self::stopped_status,'end_time' => self::database_time());
                $data['newsletter_details'] = json_encode($details);


                $this->update_running_instance_by_id($nid, $data);
            }

        }
    }

    public static function database_time(){
        return date("Y-m-d H:i:s", time());
    }

    public function update_running_instance_by_id($rid, $data)
    {
        $where = array('id' => $rid);

        return $this->set_value($this->newsletter_history_table, $data, $where);
    }

    public function ajax_create_newsletter()
    {
        $this->check_access();

        global $wpdb;

        $user_group = array();

        foreach ($_POST['user_group'] as $role) {
            $user_group[] = sanitize_text_field($role);
        }
        $name = sanitize_text_field($_POST['name']);
        $mobile_type = sanitize_text_field($_POST['mobile_field_type']);
        $mobile_key = sanitize_text_field($_POST['mobile_field_key']);
        $message = $_POST['message'];
        $schedule = sanitize_text_field($_POST['schedule']);
        $route = sanitize_text_field($_POST['route']);

        if (empty($name) || empty($mobile_type) || empty($message) || (empty($mobile_key) && $mobile_type == -1)) {
            wp_send_json_error(array('message' => esc_html__('Please complete your settings', 'wpnotif')));
        }

        $repeat = sanitize_text_field($_POST['newsletter_repeat']);
        $repeat_time = '';
        if($repeat=='on'){
            $repeat = 1;
            $repeat_time =  intval(preg_replace('/[^0-9.]+/', '',$_POST['repeat_time'])) .'-'.sanitize_text_field($_POST['repeat_period']);
        }else{
            $repeat = 0;
            $repeat_time = '';
        }

        $nid = 0;
        if (isset($_POST['edit_id'])) {
            $nid = intval(preg_replace('/[^0-9.]+/', '', $_POST['edit_id']));
        }

        if (empty($schedule) || empty($message) || empty($user_group)) {
            $status = self::draft_status;
        } else {
            $status = self::pending_status;
        }

        $data = array(
            'name' => $name,
            'message' => $message,
            'user_group_role' => implode(",", $user_group),
            'mobile_field_type' => $mobile_type,
            'mobile_field_key' => $mobile_key,
            'repeat_newsletter'=>$repeat,
            'repeat_time'=>$repeat_time,
            'route'=>$route
        );



        if (!empty($schedule) && !empty(strtotime($schedule))) {
            $data['execution_time'] = strtotime($schedule);
        }

        if ($nid && $nid > 0) {
            $db = $this->update_newsletter($data, array('id' => $nid));

        } else {
            $data['status'] = $status;
            $data['time'] = date("Y-m-d H:i:s", time());
            $db = $this->create_newsletter($data);

            $nid = $wpdb->insert_id;

        }

        if(!empty($nid)){
            $newsletter = $this->get_newspaper_by_id($nid);
            $this->update_newsletter_usercount($newsletter);
        }

        ob_start();
        $data = array();

        $this->show_newsletter();

        $data['html'] = ob_get_clean();
        ob_end_clean();

        wp_send_json_success($data);
        die();

    }

    public function ajax_refresh_newsletter(){
        $this->check_access();

        ob_start();
        $data = array();

        $this->show_newsletter();

        $data['html'] = ob_get_clean();
        ob_end_clean();

        wp_send_json_success($data);
        die();

    }


    public function create_newsletter($data)
    {
        return $this->insert($this->newsletter_table, $data);
    }

    public function get_scheduled_newspaper()
    {
        $data = array('status'=>self::pending_status);

        $data['less_equal'] = array('key'=>'execution_time',
        'value'=>time(),'date'=>1);



        return $this->get_result($this->newsletter_table, $data, 1);
    }

    public function add_running_history($data)
    {
        $this->bulk_insert($this->newsletter_send_history_table, array($data));
    }

    public function get_running_instance_phone_history($data)
    {
        return $this->get_result($this->newsletter_send_history_table, $data, 1);
    }


}