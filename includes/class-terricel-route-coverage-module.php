<?php
/**
 * Route Coverage child module.
 *
 * @package Terricel_Route_Coverage
 */

if (!defined('ABSPATH')) {
    exit;
}

class Terricel_Route_Coverage_Module extends Terricel_Logistics_Module {

    const SCHEDULE_POST_TYPE     = 'terricel_coverage';
    const AVAILABILITY_POST_TYPE = 'terricel_driver_avail';
    const VACANCY_POST_TYPE      = 'terricel_vacancy';
    const ALERT_EMAILS_OPTION    = 'terricel_route_coverage_alert_emails';
    const STANDARD_RUN_NAMES_OPTION = 'terricel_route_coverage_standard_run_names';

    private $updating_title = false;

    public function __construct() {
        $this->id          = 'route-coverage';
        $this->name        = __('Dispatch Dashboard', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        $this->description = __('Daily route scheduling, driver availability, substitute coverage, route status tracking, and alerts for unassigned or at-risk routes.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        $this->phase       = __('Phase 1: Daily Coverage Scaffold', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        $this->capability  = 'terricel_manage_routes';
    }

    protected function register_post_types() {
        $this->register_schedule_post_type();
        $this->register_availability_post_type();
        $this->register_vacancy_post_type();
    }

    protected function register_hooks() {
        if (!is_admin()) {
            return;
        }

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::SCHEDULE_POST_TYPE, array($this, 'save_schedule_meta'));
        add_action('save_post_' . self::AVAILABILITY_POST_TYPE, array($this, 'save_availability_meta'));
        add_action('save_post_' . self::VACANCY_POST_TYPE, array($this, 'save_vacancy_meta'));
        add_action('save_post_' . Terricel_Logistics_Shared_Data::DRIVER_POST_TYPE, array($this, 'save_driver_regular_availability_meta'));
        add_action('save_post_' . Terricel_Logistics_Shared_Data::ROUTE_POST_TYPE, array($this, 'save_route_regular_schedule_meta'));
        add_action('admin_head-post.php', array($this, 'hide_title_field_for_module_records'));
        add_action('admin_head-post-new.php', array($this, 'hide_title_field_for_module_records'));
        add_action('edit_form_top', array($this, 'render_back_to_list_button'));
        add_action('restrict_manage_posts', array($this, 'render_admin_filters'));
        add_action('pre_get_posts', array($this, 'filter_admin_list_tables'));
        add_filter('views_edit-' . self::SCHEDULE_POST_TYPE, array($this, 'filter_schedule_list_views'));
        add_filter('views_edit-' . self::VACANCY_POST_TYPE, array($this, 'filter_vacancy_list_views'));
        add_action('admin_post_terricel_route_coverage_save_notifications', array($this, 'save_notification_settings'));
        add_action('admin_post_terricel_route_coverage_save_today_substitutes', array($this, 'save_today_substitutes'));
        add_action('admin_post_terricel_route_coverage_save_settings', array($this, 'save_module_settings'));
        add_action('wp_ajax_terricel_route_coverage_save_run_substitute', array($this, 'ajax_save_run_substitute'));
        add_action('wp_ajax_terricel_route_coverage_vacancy_substitutes', array($this, 'ajax_vacancy_substitutes'));
        add_action('wp_ajax_terricel_route_coverage_schedule_details', array($this, 'ajax_schedule_details'));
        add_filter('terricel_logistics_settings_tabs', array($this, 'register_parent_settings_tab'));
        add_action('terricel_logistics_settings_notices', array($this, 'render_parent_settings_notices'));
        add_action('terricel_logistics_render_settings_tab_route_coverage', array($this, 'render_parent_settings_tab'));

        add_filter('manage_' . self::SCHEDULE_POST_TYPE . '_posts_columns', array($this, 'schedule_columns'));
        add_action('manage_' . self::SCHEDULE_POST_TYPE . '_posts_custom_column', array($this, 'render_schedule_column'), 10, 2);
        add_filter('manage_' . self::AVAILABILITY_POST_TYPE . '_posts_columns', array($this, 'availability_columns'));
        add_action('manage_' . self::AVAILABILITY_POST_TYPE . '_posts_custom_column', array($this, 'render_availability_column'), 10, 2);
        add_filter('manage_' . self::VACANCY_POST_TYPE . '_posts_columns', array($this, 'vacancy_columns'));
        add_action('manage_' . self::VACANCY_POST_TYPE . '_posts_custom_column', array($this, 'render_vacancy_column'), 10, 2);
    }

    private function register_schedule_post_type() {
        register_post_type(
            self::SCHEDULE_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __('Daily Route Schedules', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    'singular_name' => __('Daily Route Schedule', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    'add_new_item'  => __('Add Daily Route Schedule', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    'edit_item'     => __('Edit Daily Route Schedule', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                ),
                'public'              => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'show_in_rest'        => false,
                'rewrite'             => false,
                'supports'            => array('title'),
                'capability_type'     => 'post',
            )
        );
    }

    private function register_availability_post_type() {
        register_post_type(
            self::AVAILABILITY_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __('Driver Availability', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    'singular_name' => __('Driver Availability', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    'add_new_item'  => __('Add Driver Availability', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    'edit_item'     => __('Edit Driver Availability', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                ),
                'public'              => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'show_in_rest'        => false,
                'rewrite'             => false,
                'supports'            => array('title'),
                'capability_type'     => 'post',
            )
        );
    }

    private function register_vacancy_post_type() {
        register_post_type(
            self::VACANCY_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __('Route Vacancies', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    'singular_name' => __('Route Vacancy', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    'add_new_item'  => __('Add Route Vacancy', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    'edit_item'     => __('Edit Route Vacancy', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                ),
                'public'              => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'show_in_rest'        => false,
                'rewrite'             => false,
                'supports'            => array('title'),
                'capability_type'     => 'post',
            )
        );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'terricel_coverage_schedule',
            __('Schedule Details', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            array($this, 'render_schedule_meta_box'),
            self::SCHEDULE_POST_TYPE,
            'normal'
        );

        add_meta_box(
            'terricel_coverage_availability',
            __('Availability Details', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            array($this, 'render_availability_meta_box'),
            self::AVAILABILITY_POST_TYPE,
            'normal'
        );

        add_meta_box(
            'terricel_coverage_vacancy',
            __('Vacancy Details', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            array($this, 'render_vacancy_meta_box'),
            self::VACANCY_POST_TYPE,
            'normal'
        );

        add_meta_box(
            'terricel_driver_regular_availability',
            __('Regular Availability', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            array($this, 'render_driver_regular_availability_meta_box'),
            Terricel_Logistics_Shared_Data::DRIVER_POST_TYPE,
            'normal'
        );

        add_meta_box(
            'terricel_route_regular_schedule',
            __('Route Schedule', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            array($this, 'render_route_regular_schedule_meta_box'),
            Terricel_Logistics_Shared_Data::ROUTE_POST_TYPE,
            'normal'
        );
    }

    public function hide_title_field_for_module_records() {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, $this->module_post_types(), true)) {
            return;
        }

        echo '<style>#titlediv{display:none!important;}</style>';
    }

    public function render_back_to_list_button($post) {
        if (!$post || !in_array($post->post_type, $this->module_post_types(), true)) {
            return;
        }

        $post_type_object = get_post_type_object($post->post_type);
        $label = $post_type_object ? $post_type_object->labels->name : __('Records', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);

        echo '<p style="margin:0 0 12px;">';
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=' . $post->post_type)) . '">&larr; ' . esc_html(sprintf(__('Back to %s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $label)) . '</a>';
        echo '</p>';
    }

    public function register_parent_settings_tab($tabs) {
        $tabs['route_coverage'] = array(
            'label'      => __('Dispatch', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'capability' => 'terricel_manage_routes',
        );

        return $tabs;
    }

    public function render_parent_settings_notices($tab) {
        if ('route_coverage' !== $tab || !isset($_GET['settings-updated'])) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Dispatch settings saved.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p></div>';
    }

    public function render_parent_settings_tab() {
        if (!current_user_can('terricel_manage_routes')) {
            wp_die(esc_html__('You do not have permission to manage Route Coverage settings.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
        }

        $run_names = self::get_standard_run_name_items();

        echo '<h2>' . esc_html__('Dispatch Settings', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('terricel_route_coverage_settings');
        echo '<input type="hidden" name="action" value="terricel_route_coverage_save_settings">';
        echo '<style>';
        echo '.terricel-standard-runs{max-width:760px;}';
        echo '.terricel-standard-runs th,.terricel-standard-runs td{vertical-align:middle;}';
        echo '.terricel-standard-run-remove-cell{width:110px;}';
        echo '</style>';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Standard Run Names', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<td>';
        echo '<table class="widefat striped terricel-standard-runs" id="terricel-standard-run-names">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Name', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th class="terricel-standard-run-remove-cell">' . esc_html__('Remove', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($run_names as $index => $run_name) {
            $this->render_standard_run_name_row($index, $run_name['id'], $run_name['name']);
        }

        echo '</tbody></table>';
        echo '<p><button type="button" class="button" id="terricel-add-standard-run-name">' . esc_html__('Add Run Name', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</button></p>';
        echo '<p class="description">' . esc_html__('These names drive the Route Schedule dropdowns and the driver schedule buttons. Each saved row keeps a stable internal ID, so renaming a run updates existing schedules instead of creating a separate new field.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</tbody></table>';
        submit_button(__('Save Dispatch Settings', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), 'primary', 'submit', false);
        echo '<script>';
        echo '(function(){';
        echo 'var table=document.getElementById("terricel-standard-run-names");';
        echo 'var button=document.getElementById("terricel-add-standard-run-name");';
        echo 'if(!table||!button){return;}';
        echo 'button.addEventListener("click",function(){';
        echo 'var tbody=table.querySelector("tbody");';
        echo 'var index=tbody.querySelectorAll("tr").length;';
        echo 'var row=document.createElement("tr");';
        echo 'row.innerHTML=\'<td><input type="hidden" name="terricel_route_coverage_standard_run_names[\'+index+\'][id]" value=""><input type="text" class="regular-text" name="terricel_route_coverage_standard_run_names[\'+index+\'][name]" value=""></td><td><label><input type="checkbox" name="terricel_route_coverage_standard_run_names[\'+index+\'][remove]" value="1"> ' . esc_js(__('Remove', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</label></td>\';';
        echo 'tbody.appendChild(row);';
        echo 'var input=row.querySelector("input[type=text]");if(input){input.focus();}';
        echo '});';
        echo '}());';
        echo '</script>';
        echo '</form>';
    }

    public function save_module_settings() {
        if (!current_user_can('terricel_manage_routes')) {
            wp_die(esc_html__('You do not have permission to manage Route Coverage settings.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
        }

        check_admin_referer('terricel_route_coverage_settings');

        $raw_run_names = isset($_POST['terricel_route_coverage_standard_run_names']) && is_array($_POST['terricel_route_coverage_standard_run_names']) ? wp_unslash($_POST['terricel_route_coverage_standard_run_names']) : array();
        update_option(self::STANDARD_RUN_NAMES_OPTION, $this->sanitize_standard_run_name_items($raw_run_names));

        wp_safe_redirect(admin_url('admin.php?page=terricel-transit-settings&tab=route_coverage&settings-updated=1'));
        exit;
    }

    public function ajax_vacancy_substitutes() {
        if (!current_user_can('terricel_manage_routes')) {
            wp_send_json_error(array('message' => __('You do not have permission to manage route coverage.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)), 403);
        }

        check_ajax_referer('terricel_route_coverage_vacancy_substitutes', 'nonce');

        $route_id = isset($_POST['route_id']) ? absint($_POST['route_id']) : 0;
        $selected_driver_id = isset($_POST['selected_driver_id']) ? absint($_POST['selected_driver_id']) : 0;
        $raw_runs = isset($_POST['runs']) && is_array($_POST['runs']) ? wp_unslash($_POST['runs']) : array();
        $runs = $this->parse_vacancy_run_values($raw_runs, $route_id);
        $options = $this->get_eligible_vacancy_substitute_options($runs, $selected_driver_id);
        $output = array();

        foreach ($options as $driver_id => $driver_name) {
            $output[] = array(
                'id'   => absint($driver_id),
                'name' => $driver_name,
            );
        }

        wp_send_json_success(
            array(
                'options'  => $output,
                'selected' => isset($options[$selected_driver_id]) ? $selected_driver_id : 0,
            )
        );
    }

    public function ajax_schedule_details() {
        if (!current_user_can('terricel_manage_routes')) {
            wp_send_json_error(array('message' => __('You do not have permission to manage route coverage.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)), 403);
        }

        check_ajax_referer('terricel_route_coverage_schedule_details', 'nonce');

        $route_id = isset($_POST['route_id']) ? absint($_POST['route_id']) : 0;
        $date = isset($_POST['date']) ? $this->sanitize_date_value(sanitize_text_field(wp_unslash($_POST['date']))) : '';

        wp_send_json_success($this->get_schedule_editor_details($route_id, $date));
    }

    private function render_standard_run_name_row($index, $run_id, $run_name) {
        echo '<tr>';
        echo '<td>';
        echo '<input type="hidden" name="terricel_route_coverage_standard_run_names[' . esc_attr($index) . '][id]" value="' . esc_attr($run_id) . '">';
        echo '<input type="text" class="regular-text" name="terricel_route_coverage_standard_run_names[' . esc_attr($index) . '][name]" value="' . esc_attr($run_name) . '">';
        echo '</td>';
        echo '<td>';
        echo '<label><input type="checkbox" name="terricel_route_coverage_standard_run_names[' . esc_attr($index) . '][remove]" value="1"> ' . esc_html__('Remove', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</label>';
        echo '</td>';
        echo '</tr>';
    }

    public function render_schedule_meta_box($post) {
        wp_nonce_field('terricel_coverage_schedule_meta', 'terricel_coverage_schedule_meta_nonce');

        $date = get_post_meta($post->ID, '_terricel_coverage_date', true);
        $route_id = (int) get_post_meta($post->ID, '_terricel_coverage_route_id', true);
        $driver_id = (int) get_post_meta($post->ID, '_terricel_coverage_driver_id', true);
        $notes = get_post_meta($post->ID, '_terricel_coverage_notes', true);
        $run_substitutes = get_post_meta($post->ID, '_terricel_coverage_run_substitutes', true);
        $run_substitutes = is_array($run_substitutes) ? $run_substitutes : array();
        $details = $this->get_schedule_editor_details($route_id, $date, 0, $run_substitutes);
        $default_driver_id = isset($details['default_driver_id']) ? absint($details['default_driver_id']) : 0;
        if ($driver_id < 1 && $route_id > 0 && !metadata_exists('post', $post->ID, '_terricel_coverage_driver_id')) {
            $driver_id = $default_driver_id;
        }

        echo '<div class="notice notice-warning inline"><p>';
        echo esc_html__('Use Daily Route Schedule only for same-day driver hot swaps, such as unassigning a route or assigning a different driver for the selected date.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . ' ';
        echo esc_html__('If a driver is out sick or will be absent, use Route Vacancies instead.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . ' ';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=' . self::VACANCY_POST_TYPE)) . '">' . esc_html__('Add Route Vacancy', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</a>';
        echo '</p></div>';
        $this->render_date_field('terricel_coverage_date', __('Coverage Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $date);
        $this->render_schedule_route_select($route_id);
        $this->render_schedule_driver_select($driver_id);
        echo '<p class="description">' . esc_html__('Route status is assigned automatically from the selected assigned driver and checked run coverage below.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
        echo '<div id="terricel-coverage-run-list"></div>';
        $this->render_textarea_field('terricel_coverage_notes', __('Dispatcher Notes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $notes);
        $this->render_schedule_editor_script($details);
    }

    public function render_availability_meta_box($post) {
        wp_nonce_field('terricel_coverage_availability_meta', 'terricel_coverage_availability_meta_nonce');

        $date = get_post_meta($post->ID, '_terricel_availability_date', true);
        $end_date = get_post_meta($post->ID, '_terricel_availability_end_date', true);
        $driver_id = (int) get_post_meta($post->ID, '_terricel_availability_driver_id', true);
        $status = get_post_meta($post->ID, '_terricel_availability_status', true);
        $can_substitute = (bool) get_post_meta($post->ID, '_terricel_availability_can_substitute', true);
        $notes = get_post_meta($post->ID, '_terricel_availability_notes', true);

        $this->render_date_field('terricel_availability_date', __('Start Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $date);
        $this->render_date_field('terricel_availability_end_date', __('Out Through Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $end_date);
        $this->render_post_select('terricel_availability_driver_id', __('Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), Terricel_Logistics_Shared_Data::DRIVER_POST_TYPE, $driver_id, __('Select a driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
        $this->render_select_field('terricel_availability_status', __('Availability Status', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $this->availability_statuses(), $status ? $status : 'available');
        $this->render_checkbox_field('terricel_availability_can_substitute', __('Available as substitute driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $can_substitute);
        $this->render_textarea_field('terricel_availability_notes', __('Availability Notes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $notes);
    }

    public function render_vacancy_meta_box($post) {
        wp_nonce_field('terricel_coverage_vacancy_meta', 'terricel_coverage_vacancy_meta_nonce');

        $date = get_post_meta($post->ID, '_terricel_vacancy_date', true);
        $end_date = get_post_meta($post->ID, '_terricel_vacancy_end_date', true);
        $driver_id = (int) get_post_meta($post->ID, '_terricel_vacancy_driver_id', true);
        $route_id = (int) get_post_meta($post->ID, '_terricel_vacancy_route_id', true);
        $assigned_driver_id = (int) get_post_meta($post->ID, '_terricel_vacancy_assigned_driver_id', true);
        $notes = get_post_meta($post->ID, '_terricel_vacancy_notes', true);
        $selected_runs = $this->get_vacancy_run_selections($post->ID);

        if ($driver_id < 1 && $route_id > 0) {
            $driver_id = (int) get_post_meta($route_id, '_terricel_route_default_driver_id', true);
        }

        if ($route_id < 1 && $driver_id > 0) {
            $route_id = (int) get_post_meta($driver_id, '_terricel_driver_default_route_id', true);
        }

        $editor_data = $this->get_vacancy_editor_data();

        echo '<style>';
        echo '.terricel-vacancy-schedule-tools{display:flex;align-items:center;gap:14px;margin:12px 0 8px;}';
        echo '.terricel-vacancy-schedule-empty{border:1px solid #ccd0d4;background:#fff;padding:10px;margin-top:8px;}';
        echo '.terricel-vacancy-schedule-table{margin-top:8px;}';
        echo '</style>';
        echo '<p class="description">' . esc_html__('Select the driver first. Module 1 will use that driver\'s default route, then you can change the route if the vacancy only applies to a different route.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
        $this->render_post_select('terricel_vacancy_driver_id', __('Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), Terricel_Logistics_Shared_Data::DRIVER_POST_TYPE, $driver_id, __('Select the driver who is out', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
        $this->render_post_select('terricel_vacancy_route_id', __('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), Terricel_Logistics_Shared_Data::ROUTE_POST_TYPE, $route_id, __('Select a route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
        $this->render_date_field('terricel_vacancy_date', __('Start Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $date);
        $this->render_date_field('terricel_vacancy_end_date', __('Last Day of Vacancy', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $end_date);
        echo '<h3>' . esc_html__('Vacant Scheduled Runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</h3>';
        echo '<p class="description">' . esc_html__('The table below is built from the selected route schedule and the vacancy date range. Select all scheduled runs, or choose only the individual runs the driver will miss.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
        echo '<div class="terricel-vacancy-schedule-tools">';
        echo '<label><input type="checkbox" id="terricel_vacancy_select_all_runs"> ' . esc_html__('Select all scheduled runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</label>';
        echo '</div>';
        echo '<div id="terricel-vacancy-schedule-empty" class="terricel-vacancy-schedule-empty">' . esc_html__('Select a driver, route, start date, and last vacant day to see scheduled runs.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</div>';
        echo '<table class="widefat striped terricel-vacancy-schedule-table" id="terricel-vacancy-schedule-table" style="display:none;">';
        echo '<thead><tr>';
        echo '<th style="width:46px;">' . esc_html__('Use', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Day', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Run Name', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Start Time', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '</tr></thead><tbody></tbody></table>';
        $candidate_runs = $this->get_vacancy_candidate_runs($route_id, $date, $end_date, get_post_meta($post->ID, '_terricel_vacancy_runs', true));
        $this->render_vacancy_substitute_driver_select($assigned_driver_id, $candidate_runs);
        $this->render_textarea_field('terricel_vacancy_notes', __('Coverage Notes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $notes);
        echo '<script>';
        echo 'window.terricelVacancyEditor=' . wp_json_encode(
            array(
                'drivers'      => $editor_data['drivers'],
                'routes'       => $editor_data['routes'],
                'selectedRuns' => $selected_runs,
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('terricel_route_coverage_vacancy_substitutes'),
                'dateLabels'   => array(
                    'empty'       => __('No scheduled runs found for this route and date range.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    'invalidDate' => __('Choose a valid start date and last vacant day.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                ),
            )
        ) . ';';
        echo '(function(){';
        echo 'var data=window.terricelVacancyEditor||{};';
        echo 'var driver=document.getElementById("terricel_vacancy_driver_id");';
        echo 'var route=document.getElementById("terricel_vacancy_route_id");';
        echo 'var start=document.getElementById("terricel_vacancy_date");';
        echo 'var end=document.getElementById("terricel_vacancy_end_date");';
        echo 'var table=document.getElementById("terricel-vacancy-schedule-table");';
        echo 'var tbody=table?table.querySelector("tbody"):null;';
        echo 'var empty=document.getElementById("terricel-vacancy-schedule-empty");';
        echo 'var selectAll=document.getElementById("terricel_vacancy_select_all_runs");';
        echo 'var substitute=document.getElementById("terricel_vacancy_assigned_driver_id");';
        echo 'var selectedRuns=data.selectedRuns||{};';
        echo 'var hasSavedSelections=Object.keys(selectedRuns).length>0;';
        echo 'var autoRoute=true;';
        echo 'var requestId=0;';
        echo 'function parseDate(value){var parts=(value||"").split("-");if(parts.length!==3){return null;}var d=new Date(Number(parts[0]),Number(parts[1])-1,Number(parts[2]));return isNaN(d.getTime())?null:d;}';
        echo 'function formatDate(d){var y=d.getFullYear();var m=String(d.getMonth()+1).padStart(2,"0");var day=String(d.getDate()).padStart(2,"0");return y+"-"+m+"-"+day;}';
        echo 'function labelDate(value){var d=parseDate(value);return d?d.toLocaleDateString(undefined,{year:"numeric",month:"short",day:"numeric"}):value;}';
        echo 'function escapeHtml(value){var div=document.createElement("div");div.textContent=value||"";return div.innerHTML;}';
        echo 'function runValue(item){return [item.date,item.day,item.run_key,item.start_time].join("||");}';
        echo 'function getRange(){var s=parseDate(start.value);var e=parseDate(end.value||start.value);if(!s||!e||e<s){return null;}var dates=[];var cursor=new Date(s.getTime());while(cursor<=e&&dates.length<370){dates.push(formatDate(cursor));cursor.setDate(cursor.getDate()+1);}return dates;}';
        echo 'function refreshSelectAll(){if(!tbody||!selectAll){return;}var checks=tbody.querySelectorAll("input[type=checkbox]");var checked=tbody.querySelectorAll("input[type=checkbox]:checked");selectAll.checked=checks.length>0&&checks.length===checked.length;selectAll.indeterminate=checked.length>0&&checked.length<checks.length;}';
        echo 'function selectedRunValues(){if(!tbody){return [];}return Array.prototype.slice.call(tbody.querySelectorAll("input[type=checkbox]:checked")).map(function(input){return input.value;});}';
        echo 'function setSubstituteOptions(options,selected){if(!substitute){return;}var current=selected||substitute.value||"0";var found=current==="0";substitute.innerHTML="";var emptyOption=document.createElement("option");emptyOption.value="0";emptyOption.textContent="' . esc_js(__('No driver assigned yet', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '";substitute.appendChild(emptyOption);(options||[]).forEach(function(option){var el=document.createElement("option");el.value=String(option.id);el.textContent=option.name;if(String(option.id)===String(current)){el.selected=true;found=true;}substitute.appendChild(el);});if(!found){substitute.value="0";}}';
        echo 'function refreshSubstitutes(){if(!substitute||!data.ajaxUrl){return;}var routeId=route?route.value:"0";var runs=selectedRunValues();if(!routeId||routeId==="0"||!runs.length){setSubstituteOptions([],substitute.value);return;}var currentRequest=++requestId;substitute.disabled=true;var body=new URLSearchParams();body.set("action","terricel_route_coverage_vacancy_substitutes");body.set("nonce",data.nonce||"");body.set("route_id",routeId);body.set("selected_driver_id",substitute.value||"0");runs.forEach(function(value){body.append("runs[]",value);});fetch(data.ajaxUrl,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:body.toString()}).then(function(response){return response.json();}).then(function(result){if(currentRequest!==requestId){return;}if(result&&result.success){setSubstituteOptions(result.data.options||[],result.data.selected||substitute.value);}}).finally(function(){if(currentRequest===requestId){substitute.disabled=false;}});}';
        echo 'function renderRows(){if(!route||!tbody||!table||!empty){return;}tbody.innerHTML="";var routeId=route.value||"0";var routeData=(data.routes||{})[routeId];var dates=getRange();if(!routeId||routeId==="0"||!dates){table.style.display="none";empty.style.display="block";empty.textContent=dates?data.dateLabels.empty:data.dateLabels.invalidDate;refreshSelectAll();refreshSubstitutes();return;}var rows=[];dates.forEach(function(date){var d=parseDate(date);var dayKeys=["sunday","monday","tuesday","wednesday","thursday","friday","saturday"];var dayKey=dayKeys[d.getDay()];var runs=routeData&&routeData.schedule&&routeData.schedule[dayKey]?routeData.schedule[dayKey]:[];runs.forEach(function(run){rows.push({date:date,day:dayKey,day_label:run.day_label,run_key:run.run_key,run_name:run.run_name,start_time:run.start_time,start_label:run.start_label});});});if(!rows.length){table.style.display="none";empty.style.display="block";empty.textContent=data.dateLabels.empty;refreshSelectAll();refreshSubstitutes();return;}rows.forEach(function(item){var value=runValue(item);var checked=hasSavedSelections?!!selectedRuns[value]:true;var tr=document.createElement("tr");tr.innerHTML="<td><input type=\\"checkbox\\" name=\\"terricel_vacancy_runs[]\\" value=\\""+escapeHtml(value)+"\\" "+(checked?"checked":"")+"></td><td>"+escapeHtml(labelDate(item.date))+"</td><td>"+escapeHtml(item.day_label)+"</td><td>"+escapeHtml(item.run_name)+"</td><td>"+escapeHtml(item.start_label)+"</td>";tbody.appendChild(tr);});table.style.display="table";empty.style.display="none";refreshSelectAll();refreshSubstitutes();}';
        echo 'if(driver){driver.addEventListener("change",function(){var item=(data.drivers||{})[driver.value];if(item&&item.default_route_id&&autoRoute&&route){route.value=String(item.default_route_id);}renderRows();});}';
        echo 'if(route){route.addEventListener("change",function(){autoRoute=false;renderRows();});}';
        echo '[start,end].forEach(function(input){if(input){input.addEventListener("change",renderRows);}});';
        echo 'if(selectAll){selectAll.addEventListener("change",function(){if(!tbody){return;}tbody.querySelectorAll("input[type=checkbox]").forEach(function(check){check.checked=selectAll.checked;});refreshSelectAll();refreshSubstitutes();});}';
        echo 'if(tbody){tbody.addEventListener("change",function(event){if(event.target.matches("input[type=checkbox]")){refreshSelectAll();refreshSubstitutes();}});}';
        echo 'renderRows();';
        echo '}());';
        echo '</script>';
    }

    public function render_driver_regular_availability_meta_box($post) {
        wp_nonce_field('terricel_driver_regular_availability_meta', 'terricel_driver_regular_availability_meta_nonce');

        $schedule = $this->get_driver_regular_availability($post->ID);
        $extra_run_availability = $this->logistics()->get_driver_extra_run_availability($post->ID);
        $days = $this->regular_schedule_days();
        $periods = $this->regular_schedule_periods();

        echo '<style>';
        echo '.terricel-regular-schedule{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:12px;margin-top:8px;}';
        echo '.terricel-regular-day{border:1px solid #ccd0d4;background:#fff;border-radius:6px;padding:12px;}';
        echo '.terricel-regular-day h3{margin:0 0 10px;font-size:14px;}';
        echo '.terricel-all-day-label{display:block;margin:0 0 10px;font-weight:600;}';
        echo '.terricel-period-grid{display:grid;gap:8px;}';
        echo '.terricel-period-input{position:absolute;opacity:0;pointer-events:none;}';
        echo '.terricel-period-button{display:flex;align-items:center;justify-content:space-between;gap:6px;border:1px solid #8c8f94;border-radius:4px;padding:8px 10px;background:#f6f7f7;color:#1d2327;cursor:pointer;}';
        echo '.terricel-period-button span:first-child{min-width:0;overflow-wrap:anywhere;}';
        echo '.terricel-period-check{visibility:hidden;font-weight:700;}';
        echo '.terricel-period-input:checked+.terricel-period-button{border-color:#2271b1;background:#2271b1;color:#fff;}';
        echo '.terricel-period-input:checked+.terricel-period-button .terricel-period-check{visibility:visible;}';
        echo '.terricel-period-input:focus+.terricel-period-button{box-shadow:0 0 0 2px #72aee6;outline:2px solid transparent;}';
        echo '@media (max-width:1199px){.terricel-regular-schedule{grid-template-columns:1fr;}.terricel-regular-day{padding:12px 14px;}}';
        echo '</style>';

        echo '<p class="description">' . esc_html__('Select the driver\'s regular weekly availability. Module 1 will use this baseline when planning driver vacancies and coverage.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
        echo '<div class="terricel-regular-schedule">';

        foreach ($days as $day_key => $day_label) {
            $selected_periods = isset($schedule[$day_key]) && is_array($schedule[$day_key]) ? $schedule[$day_key] : array();

            echo '<section class="terricel-regular-day">';
            echo '<h3>' . esc_html($day_label) . '</h3>';
            echo '<label class="terricel-all-day-label">';
            echo '<input class="terricel-all-day-input" type="checkbox"> ';
            echo esc_html__('Available all day', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
            echo '</label>';
            echo '<label class="terricel-all-day-label">';
            echo '<input type="checkbox" name="terricel_driver_extra_run_availability[]" value="' . esc_attr($day_key) . '"' . checked(!empty($extra_run_availability[$day_key]), true, false) . '> ';
            echo esc_html__('Available for extra runs beyond default routes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
            echo '</label>';
            echo '<div class="terricel-period-grid">';

            foreach ($periods as $period_key => $period_label) {
                $field_id = 'terricel_regular_' . $day_key . '_' . $period_key;

                echo '<div>';
                echo '<input class="terricel-period-input" type="checkbox" id="' . esc_attr($field_id) . '" name="terricel_driver_regular_availability[' . esc_attr($day_key) . '][]" value="' . esc_attr($period_key) . '"' . checked(in_array($period_key, $selected_periods, true), true, false) . '>';
                echo '<label class="terricel-period-button" for="' . esc_attr($field_id) . '">';
                echo '<span>' . esc_html($period_label) . '</span>';
                echo '<span class="terricel-period-check" aria-hidden="true">&#10003;</span>';
                echo '</label>';
                echo '</div>';
            }

            echo '</div>';
            echo '</section>';
        }

        echo '</div>';
        echo '<script>';
        echo '(function(){';
        echo 'document.querySelectorAll(".terricel-regular-day").forEach(function(day){';
        echo 'var allDay=day.querySelector(".terricel-all-day-input");';
        echo 'var periods=Array.prototype.slice.call(day.querySelectorAll(".terricel-period-input"));';
        echo 'if(!allDay||!periods.length){return;}';
        echo 'function syncAllDay(){var checked=periods.filter(function(input){return input.checked;}).length;allDay.checked=checked===periods.length;allDay.indeterminate=checked>0&&checked<periods.length;}';
        echo 'allDay.addEventListener("change",function(){periods.forEach(function(input){input.checked=allDay.checked;});allDay.indeterminate=false;});';
        echo 'periods.forEach(function(input){input.addEventListener("change",syncAllDay);});';
        echo 'syncAllDay();';
        echo '});';
        echo '}());';
        echo '</script>';
    }

    public function render_route_regular_schedule_meta_box($post) {
        wp_nonce_field('terricel_route_regular_schedule_meta', 'terricel_route_regular_schedule_meta_nonce');

        $schedule = $this->get_route_regular_schedule($post->ID);
        $days = $this->regular_schedule_days();
        $standard_run_names = $this->get_standard_run_name_options($schedule);
        $driver_availability = $this->get_driver_availability_map();

        echo '<style>';
        echo '.terricel-route-run-edit{display:none;}';
        echo '.terricel-route-run-confirm{display:none;margin-left:8px;color:#8a1f11;}';
        echo '.terricel-route-run-row.is-editing .terricel-route-run-display{display:none;}';
        echo '.terricel-route-run-row.is-editing .terricel-route-run-edit{display:block;}';
        echo '.terricel-route-run-row.is-confirming .terricel-route-run-confirm{display:inline;}';
        echo '.terricel-route-run-row.is-removing{opacity:.55;text-decoration:line-through;}';
        echo '.terricel-route-run-row.is-driver-unavailable td{background:#fff3cd!important;border-top:1px solid #f0ad4e;border-bottom:1px solid #f0ad4e;}';
        echo '.terricel-route-run-row.is-driver-unavailable td:first-child{border-left:4px solid #f0ad4e;}';
        echo '.terricel-route-availability-warning{border-left:4px solid #f0ad4e;background:#fff8e5;padding:10px 12px;margin:10px 0;}';
        echo '.terricel-route-new-run-warning{display:none;color:#8a5a00;margin-top:8px;}';
        echo '</style>';
        echo '<p class="description">' . esc_html__('Define the normal runs for this route. Add one run to multiple weekdays by selecting the days below before saving.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
        echo '<div id="terricel-route-availability-warning" class="terricel-route-availability-warning" style="display:none;">' . esc_html__('The selected driver is not available for the runs highlighted in orange. If this is in error, then update the driver availability, or multiple routes will need to be created in order to assign a driver to this highlighted route.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</div>';
        echo '<table class="widefat striped" style="margin-top:10px;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Day of Week', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Run Name', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Start Time', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('End Time', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Actions', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '</tr></thead><tbody>';

        $has_runs = false;

        foreach ($days as $day_key => $day_label) {
            $runs = isset($schedule[$day_key]) && is_array($schedule[$day_key]) ? $schedule[$day_key] : array();

            foreach ($runs as $index => $run) {
                $has_runs = true;
                $run_key = isset($run['run_key']) ? $run['run_key'] : '';
                $run_name = isset($run['run_name']) ? $run['run_name'] : '';
                $start_time = isset($run['start_time']) ? $run['start_time'] : '';
                $end_time = isset($run['end_time']) && $run['end_time'] ? $run['end_time'] : $this->get_default_run_end_time($start_time);

                echo '<tr class="terricel-route-run-row" data-day-key="' . esc_attr($day_key) . '" data-run-key="' . esc_attr($run_key) . '">';
                echo '<td>' . esc_html($day_label) . '</td>';
                echo '<td>';
                echo '<span class="terricel-route-run-display">' . esc_html($run_name) . '</span>';
                echo '<span class="terricel-route-run-edit">' . $this->get_run_name_select_markup('terricel_route_regular_schedule[' . $day_key . '][' . $index . '][run_key]', $run_key, $standard_run_names, 'widefat terricel-route-run-key-input') . '<input type="hidden" name="terricel_route_regular_schedule[' . esc_attr($day_key) . '][' . esc_attr($index) . '][run_name]" value="' . esc_attr($run_name) . '"></span>';
                echo '</td>';
                echo '<td>';
                echo '<span class="terricel-route-run-display">' . esc_html($this->format_time_value($start_time)) . '</span>';
                echo '<span class="terricel-route-run-edit"><input type="time" name="terricel_route_regular_schedule[' . esc_attr($day_key) . '][' . esc_attr($index) . '][start_time]" value="' . esc_attr($start_time) . '"></span>';
                echo '</td>';
                echo '<td>';
                echo '<span class="terricel-route-run-display">' . esc_html($this->format_time_value($end_time)) . '</span>';
                echo '<span class="terricel-route-run-edit"><input type="time" name="terricel_route_regular_schedule[' . esc_attr($day_key) . '][' . esc_attr($index) . '][end_time]" value="' . esc_attr($end_time) . '"></span>';
                echo '</td>';
                echo '<td>';
                echo '<a href="#" class="terricel-route-run-edit-link">' . esc_html__('Edit', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</a>';
                echo ' | ';
                echo '<a href="#" class="terricel-route-run-remove-link">' . esc_html__('Remove', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</a>';
                echo '<span class="terricel-route-run-confirm"> ';
                echo esc_html__('Are you sure?', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . ' ';
                echo '<a href="#" class="terricel-route-run-confirm-yes">' . esc_html__('Yes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</a>';
                echo '</span>';
                echo '<input class="terricel-route-run-remove-input" type="hidden" name="terricel_route_regular_schedule[' . esc_attr($day_key) . '][' . esc_attr($index) . '][remove]" value="0">';
                echo '</td>';
                echo '</tr>';
            }
        }

        if (!$has_runs) {
            echo '<tr><td colspan="5">' . esc_html__('No runs have been added yet.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</td></tr>';
        }

        echo '</tbody></table>';
        echo '<h3 style="margin-top:18px;">' . esc_html__('Add Run', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</h3>';
        echo '<p>';
        echo '<label for="terricel_route_new_run_key"><strong>' . esc_html__('Run Name', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</strong></label><br>';
        echo $this->get_run_name_select_markup('terricel_route_new_run_key', '', $standard_run_names, 'regular-text', 'terricel_route_new_run_key');
        echo '</p>';
        echo '<p>';
        echo '<label for="terricel_route_new_run_start_time"><strong>' . esc_html__('Start Time', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</strong></label><br>';
        echo '<input type="time" id="terricel_route_new_run_start_time" name="terricel_route_new_run_start_time" value="">';
        echo '</p>';
        echo '<p>';
        echo '<label for="terricel_route_new_run_end_time"><strong>' . esc_html__('End Time', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</strong></label><br>';
        echo '<input type="time" id="terricel_route_new_run_end_time" name="terricel_route_new_run_end_time" value="">';
        echo '</p>';
        echo '<fieldset>';
        echo '<legend><strong>' . esc_html__('Days to Add This Run', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</strong></legend>';

        foreach ($days as $day_key => $day_label) {
            echo '<label style="display:inline-block;margin:6px 14px 0 0;">';
            echo '<input class="terricel-route-new-run-day-input" type="checkbox" name="terricel_route_new_run_days[]" value="' . esc_attr($day_key) . '" data-day-label="' . esc_attr($day_label) . '"> ';
            echo esc_html($day_label);
            echo '</label>';
        }

        echo '</fieldset>';
        echo '<p id="terricel-route-new-run-warning" class="terricel-route-new-run-warning" data-message-template="' . esc_attr__('The selected driver is not available for this new run on: %s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '"></p>';
        echo '<p style="margin-top:14px;">';
        echo '<button type="submit" class="button button-primary" name="publish" value="1">' . esc_html__('Add Run', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</button>';
        echo '</p>';
        echo '<script>';
        echo '(function(){';
        echo 'var box=document.getElementById("terricel_route_regular_schedule");';
        echo 'if(!box){return;}';
        echo 'var availability=' . wp_json_encode($driver_availability) . ';';
        echo 'var warning=document.getElementById("terricel-route-availability-warning");';
        echo 'var newRun=document.getElementById("terricel_route_new_run_key");';
        echo 'var newStart=document.getElementById("terricel_route_new_run_start_time");';
        echo 'var newEnd=document.getElementById("terricel_route_new_run_end_time");';
        echo 'var newRunWarning=document.getElementById("terricel-route-new-run-warning");';
        echo 'function addHour(value){if(!value){return "";}var parts=value.split(":");if(parts.length!==2){return "";}var h=(parseInt(parts[0],10)+1)%24;var m=parseInt(parts[1],10);if(isNaN(h)||isNaN(m)){return "";}return String(h).padStart(2,"0")+":"+String(m).padStart(2,"0");}';
        echo 'function getDriverSelect(){return document.getElementById("terricel_route_default_driver_id");}';
        echo 'function driverCanRun(driverId,dayKey,runKey){if(!driverId||!dayKey||!runKey){return true;}var schedule=availability[String(driverId)]||{};var runs=schedule[dayKey]||[];return runs.indexOf(runKey)!==-1;}';
        echo 'function syncRouteAvailabilityWarnings(){var driverSelect=getDriverSelect();var driverId=driverSelect?driverSelect.value:"0";var hasUnavailable=false;box.querySelectorAll(".terricel-route-run-row").forEach(function(row){if(row.classList.contains("is-removing")){row.classList.remove("is-driver-unavailable");return;}var input=row.querySelector(".terricel-route-run-key-input");var runKey=input?input.value:row.getAttribute("data-run-key");var dayKey=row.getAttribute("data-day-key");var unavailable=!driverCanRun(driverId,dayKey,runKey);row.classList.toggle("is-driver-unavailable",unavailable);if(unavailable){hasUnavailable=true;}});if(warning){warning.style.display=hasUnavailable?"block":"none";}syncNewRunAvailabilityWarning();}';
        echo 'function syncNewRunAvailabilityWarning(){if(!newRun||!newRunWarning){return;}var driverSelect=getDriverSelect();var driverId=driverSelect?driverSelect.value:"0";var runKey=newRun.value;var unavailableDays=[];box.querySelectorAll(".terricel-route-new-run-day-input:checked").forEach(function(input){if(!driverCanRun(driverId,input.value,runKey)){unavailableDays.push(input.getAttribute("data-day-label")||input.value);}});if(unavailableDays.length){var template=newRunWarning.getAttribute("data-message-template")||"The selected driver is not available for this new run on: %s.";newRunWarning.textContent=template.replace("%s",unavailableDays.join(", "));newRunWarning.style.display="block";}else{newRunWarning.textContent="";newRunWarning.style.display="none";}}';
        echo 'box.addEventListener("click",function(event){';
        echo 'var edit=event.target.closest(".terricel-route-run-edit-link");';
        echo 'var remove=event.target.closest(".terricel-route-run-remove-link");';
        echo 'var yes=event.target.closest(".terricel-route-run-confirm-yes");';
        echo 'if(!edit&&!remove&&!yes){return;}';
        echo 'event.preventDefault();';
        echo 'var row=event.target.closest(".terricel-route-run-row");';
        echo 'if(!row){return;}';
        echo 'if(edit){row.classList.add("is-editing");return;}';
        echo 'if(remove){row.classList.add("is-confirming");return;}';
        echo 'if(yes){var input=row.querySelector(".terricel-route-run-remove-input");if(input){input.value="1";}row.classList.remove("is-confirming");row.classList.add("is-removing");syncRouteAvailabilityWarnings();}';
        echo '});';
        echo 'box.addEventListener("change",function(event){if(event.target.matches(".terricel-route-run-key-input,.terricel-route-new-run-day-input,#terricel_route_new_run_key")){syncRouteAvailabilityWarnings();}if(event.target===newStart&&newEnd&&!newEnd.value){newEnd.value=addHour(newStart.value);}});';
        echo 'document.addEventListener("change",function(event){if(event.target&&event.target.id==="terricel_route_default_driver_id"){syncRouteAvailabilityWarnings();}});';
        echo 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",syncRouteAvailabilityWarnings);}else{syncRouteAvailabilityWarnings();}';
        echo '}());';
        echo '</script>';
    }

    public function save_schedule_meta($post_id) {
        if (!$this->can_save($post_id, 'terricel_coverage_schedule_meta_nonce', 'terricel_coverage_schedule_meta')) {
            return;
        }

        $old_state = array(
            'date'            => get_post_meta($post_id, '_terricel_coverage_date', true),
            'route_id'        => (int) get_post_meta($post_id, '_terricel_coverage_route_id', true),
            'driver_id'       => (int) get_post_meta($post_id, '_terricel_coverage_driver_id', true),
            'substitute_driver_id' => (int) get_post_meta($post_id, '_terricel_coverage_substitute_driver_id', true),
            'run_substitutes' => get_post_meta($post_id, '_terricel_coverage_run_substitutes', true),
            'status'          => get_post_meta($post_id, '_terricel_coverage_status', true),
        );
        $old_state['run_substitutes'] = is_array($old_state['run_substitutes']) ? $old_state['run_substitutes'] : array();

        $date = $this->sanitize_date_field('terricel_coverage_date');
        $route_id = $this->sanitize_post_id('terricel_coverage_route_id');
        $driver_id = $this->sanitize_post_id('terricel_coverage_driver_id');
        $substitute_driver_id = 0;
        $notes = isset($_POST['terricel_coverage_notes']) ? sanitize_textarea_field(wp_unslash($_POST['terricel_coverage_notes'])) : '';
        $run_substitutes = $this->sanitize_schedule_run_substitute_posts($date, $route_id);

        $has_run_substitute = $this->has_any_run_substitute($run_substitutes);
        $has_uncovered_run = $this->has_uncovered_run_substitute($run_substitutes);
        $status = $has_run_substitute ? 'covered' : ($driver_id > 0 && !$has_uncovered_run ? 'scheduled' : 'unassigned');

        $this->save_meta_value($post_id, '_terricel_coverage_date', $date);
        $this->save_meta_value($post_id, '_terricel_coverage_route_id', $route_id);
        $this->save_meta_value($post_id, '_terricel_coverage_driver_id', $driver_id);
        $this->save_meta_value($post_id, '_terricel_coverage_substitute_driver_id', $substitute_driver_id);
        update_post_meta($post_id, '_terricel_coverage_run_substitutes', $run_substitutes);
        update_post_meta($post_id, '_terricel_coverage_status', $status);
        $this->save_meta_value($post_id, '_terricel_coverage_notes', $notes);
        $this->update_generated_title($post_id, $this->build_schedule_title($date, $route_id));
        $this->sync_schedule_alert($post_id, $date, $route_id, $status, $driver_id, $substitute_driver_id);
        $this->queue_schedule_affected_driver_notifications(
            $old_state,
            array(
                'date'            => $date,
                'route_id'        => $route_id,
                'driver_id'       => $driver_id,
                'substitute_driver_id' => $substitute_driver_id,
                'run_substitutes' => $run_substitutes,
                'status'          => $status,
            ),
            $notes
        );
    }

    public function save_availability_meta($post_id) {
        if (!$this->can_save($post_id, 'terricel_coverage_availability_meta_nonce', 'terricel_coverage_availability_meta')) {
            return;
        }

        $date = $this->sanitize_date_field('terricel_availability_date');
        $end_date = $this->sanitize_date_field('terricel_availability_end_date');
        $driver_id = $this->sanitize_post_id('terricel_availability_driver_id');
        $status = $this->sanitize_status('terricel_availability_status', $this->availability_statuses(), 'available');
        $can_substitute = isset($_POST['terricel_availability_can_substitute']) ? 1 : 0;
        $notes = isset($_POST['terricel_availability_notes']) ? sanitize_textarea_field(wp_unslash($_POST['terricel_availability_notes'])) : '';

        $this->save_meta_value($post_id, '_terricel_availability_date', $date);
        $this->save_meta_value($post_id, '_terricel_availability_end_date', $end_date);
        $this->save_meta_value($post_id, '_terricel_availability_driver_id', $driver_id);
        update_post_meta($post_id, '_terricel_availability_status', $status);
        update_post_meta($post_id, '_terricel_availability_can_substitute', $can_substitute);
        $this->save_meta_value($post_id, '_terricel_availability_notes', $notes);
        $this->update_generated_title($post_id, $this->build_availability_title($date, $driver_id));
    }

    public function save_vacancy_meta($post_id) {
        if (!$this->can_save($post_id, 'terricel_coverage_vacancy_meta_nonce', 'terricel_coverage_vacancy_meta')) {
            return;
        }

        $old_state = array(
            'date'               => get_post_meta($post_id, '_terricel_vacancy_date', true),
            'end_date'           => get_post_meta($post_id, '_terricel_vacancy_end_date', true),
            'driver_id'          => (int) get_post_meta($post_id, '_terricel_vacancy_driver_id', true),
            'route_id'           => (int) get_post_meta($post_id, '_terricel_vacancy_route_id', true),
            'assigned_driver_id' => (int) get_post_meta($post_id, '_terricel_vacancy_assigned_driver_id', true),
            'status'             => get_post_meta($post_id, '_terricel_vacancy_status', true),
        );

        $date = $this->sanitize_date_field('terricel_vacancy_date');
        $end_date = $this->sanitize_date_field('terricel_vacancy_end_date');
        $driver_id = $this->sanitize_post_id('terricel_vacancy_driver_id');
        $route_id = $this->sanitize_post_id('terricel_vacancy_route_id');
        if ($route_id < 1 && $driver_id > 0) {
            $route_id = (int) get_post_meta($driver_id, '_terricel_driver_default_route_id', true);
        }
        $assigned_driver_id = $this->sanitize_post_id('terricel_vacancy_assigned_driver_id');
        $status = $assigned_driver_id > 0 ? 'covered' : 'open';
        $notes = isset($_POST['terricel_vacancy_notes']) ? sanitize_textarea_field(wp_unslash($_POST['terricel_vacancy_notes'])) : '';
        $selected_runs = $this->sanitize_vacancy_run_selections_from_request($route_id);

        if ($date && $end_date && $end_date < $date) {
            $end_date = $date;
        }

        if ($assigned_driver_id > 0) {
            $eligible_substitutes = $this->get_eligible_vacancy_substitute_options($selected_runs, $assigned_driver_id);
            if (!isset($eligible_substitutes[$assigned_driver_id])) {
                $assigned_driver_id = 0;
                $status = 'open';
            }
        }

        $this->save_meta_value($post_id, '_terricel_vacancy_date', $date);
        $this->save_meta_value($post_id, '_terricel_vacancy_end_date', $end_date);
        $this->save_meta_value($post_id, '_terricel_vacancy_driver_id', $driver_id);
        $this->save_meta_value($post_id, '_terricel_vacancy_route_id', $route_id);
        $this->save_meta_value($post_id, '_terricel_vacancy_assigned_driver_id', $assigned_driver_id);
        update_post_meta($post_id, '_terricel_vacancy_status', $status);
        update_post_meta($post_id, '_terricel_vacancy_priority', 'normal');
        update_post_meta($post_id, '_terricel_vacancy_runs', $selected_runs);
        $this->save_meta_value($post_id, '_terricel_vacancy_notes', $notes);
        $this->update_generated_title($post_id, $this->build_vacancy_title($date, $driver_id));
        $this->sync_vacancy_alert($post_id, $date, $end_date, $route_id, $status, 'normal', $assigned_driver_id);
        $this->queue_vacancy_affected_driver_notifications(
            $old_state,
            array(
                'date'               => $date,
                'end_date'           => $end_date,
                'driver_id'          => $driver_id,
                'route_id'           => $route_id,
                'assigned_driver_id' => $assigned_driver_id,
                'status'             => $status,
            ),
            $notes
        );
    }

    public function save_driver_regular_availability_meta($post_id) {
        if (!isset($_POST['terricel_driver_regular_availability_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['terricel_driver_regular_availability_meta_nonce'])), 'terricel_driver_regular_availability_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $days = $this->regular_schedule_days();
        $periods = $this->regular_schedule_periods();
        $raw_schedule = isset($_POST['terricel_driver_regular_availability']) && is_array($_POST['terricel_driver_regular_availability']) ? wp_unslash($_POST['terricel_driver_regular_availability']) : array();
        $schedule = array();

        foreach ($days as $day_key => $day_label) {
            $raw_periods = isset($raw_schedule[$day_key]) && is_array($raw_schedule[$day_key]) ? $raw_schedule[$day_key] : array();
            $selected_periods = array();

            foreach ($raw_periods as $period_key) {
                $period_key = sanitize_key($period_key);

                if (isset($periods[$period_key])) {
                    $selected_periods[] = $period_key;
                }
            }

            $schedule[$day_key] = array_values(array_unique($selected_periods));
        }

        update_post_meta($post_id, '_terricel_route_coverage_regular_availability', $schedule);

        $raw_extra_days = isset($_POST['terricel_driver_extra_run_availability']) && is_array($_POST['terricel_driver_extra_run_availability'])
            ? array_map('sanitize_key', wp_unslash($_POST['terricel_driver_extra_run_availability']))
            : array();
        $extra_days = array_values(array_intersect($raw_extra_days, array_keys($days)));
        update_post_meta($post_id, '_terricel_driver_extra_run_availability', $extra_days);
    }

    public function save_route_regular_schedule_meta($post_id) {
        if (!isset($_POST['terricel_route_regular_schedule_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['terricel_route_regular_schedule_meta_nonce'])), 'terricel_route_regular_schedule_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $schedule = $this->sanitize_route_regular_schedule_from_request();
        $new_run_key = isset($_POST['terricel_route_new_run_key']) ? sanitize_key(wp_unslash($_POST['terricel_route_new_run_key'])) : '';
        $new_run_name = $this->get_run_name_label($new_run_key);
        $new_start_time = isset($_POST['terricel_route_new_run_start_time']) ? $this->sanitize_time_value(wp_unslash($_POST['terricel_route_new_run_start_time'])) : '';
        $new_end_time = isset($_POST['terricel_route_new_run_end_time']) ? $this->sanitize_time_value(wp_unslash($_POST['terricel_route_new_run_end_time'])) : '';
        $new_end_time = $new_end_time ? $new_end_time : $this->get_default_run_end_time($new_start_time);
        $new_days = isset($_POST['terricel_route_new_run_days']) && is_array($_POST['terricel_route_new_run_days']) ? array_map('sanitize_key', wp_unslash($_POST['terricel_route_new_run_days'])) : array();
        $days = $this->regular_schedule_days();

        if ($new_run_name && $new_start_time && !empty($new_days)) {
            foreach ($new_days as $day_key) {
                if (!isset($days[$day_key])) {
                    continue;
                }

                $schedule[$day_key][] = array(
                    'run_key'    => $new_run_key,
                    'run_name'   => $new_run_name,
                    'start_time' => $new_start_time,
                    'end_time'   => $new_end_time,
                );
            }
        }

        $schedule = $this->sort_route_regular_schedule($schedule);
        update_post_meta($post_id, '_terricel_route_coverage_route_schedule', $schedule);
    }

    public function render_admin_filters($post_type) {
        if (self::SCHEDULE_POST_TYPE === $post_type) {
            $selected_date = isset($_GET['terricel_coverage_date']) ? sanitize_text_field(wp_unslash($_GET['terricel_coverage_date'])) : '';
            $selected_status = isset($_GET['terricel_coverage_status']) ? sanitize_key(wp_unslash($_GET['terricel_coverage_status'])) : '';
            $this->render_date_filter('terricel_coverage_date', $selected_date, __('All dates', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
            $this->render_filter_select('terricel_coverage_status', $this->coverage_statuses(), $selected_status, __('All route statuses', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
        }

        if (self::AVAILABILITY_POST_TYPE === $post_type) {
            $selected_date = isset($_GET['terricel_availability_date']) ? sanitize_text_field(wp_unslash($_GET['terricel_availability_date'])) : '';
            $selected_status = isset($_GET['terricel_availability_status']) ? sanitize_key(wp_unslash($_GET['terricel_availability_status'])) : '';
            $this->render_date_filter('terricel_availability_date', $selected_date, __('All dates', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
            $this->render_filter_select('terricel_availability_status', $this->availability_statuses(), $selected_status, __('All availability statuses', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
        }

        if (self::VACANCY_POST_TYPE === $post_type) {
            return;
        }
    }

    public function filter_admin_list_tables($query) {
        if (!$query->is_main_query() || !is_admin()) {
            return;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
        if (!in_array($post_type, $this->module_post_types(), true)) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');

        if (self::SCHEDULE_POST_TYPE === $post_type) {
            if ($this->is_today_plus_list_view($post_type)) {
                $meta_query[] = array(
                    'key'     => '_terricel_coverage_date',
                    'value'   => current_time('Y-m-d'),
                    'compare' => '>=',
                    'type'    => 'DATE',
                );
                $query->set('meta_key', '_terricel_coverage_date');
                $query->set('orderby', 'meta_value');
                $query->set('order', 'ASC');
            }

            $this->append_meta_filter($meta_query, '_terricel_coverage_date', 'terricel_coverage_date');
            $this->append_meta_filter($meta_query, '_terricel_coverage_status', 'terricel_coverage_status');
        }

        if (self::AVAILABILITY_POST_TYPE === $post_type) {
            $this->append_meta_filter($meta_query, '_terricel_availability_date', 'terricel_availability_date');
            $this->append_meta_filter($meta_query, '_terricel_availability_status', 'terricel_availability_status');
        }

        if (self::VACANCY_POST_TYPE === $post_type && $this->is_today_plus_list_view($post_type)) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_terricel_vacancy_end_date',
                    'value'   => current_time('Y-m-d'),
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
                array(
                    'key'     => '_terricel_vacancy_date',
                    'value'   => current_time('Y-m-d'),
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            );
            $query->set('meta_key', '_terricel_vacancy_date');
            $query->set('orderby', 'meta_value');
            $query->set('order', 'ASC');
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }

    public function filter_schedule_list_views($views) {
        return $this->prepend_today_plus_list_view($views, self::SCHEDULE_POST_TYPE, $this->count_today_plus_records(self::SCHEDULE_POST_TYPE));
    }

    public function filter_vacancy_list_views($views) {
        return $this->prepend_today_plus_list_view($views, self::VACANCY_POST_TYPE, $this->count_today_plus_records(self::VACANCY_POST_TYPE));
    }

    private function prepend_today_plus_list_view($views, $post_type, $count) {
        $views = is_array($views) ? $views : array();
        $is_current = $this->is_today_plus_list_view($post_type);
        $base_url = admin_url('edit.php?post_type=' . $post_type);
        $label = sprintf(
            /* translators: %d: record count. */
            __('Today+ <span class="count">(%d)</span>', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            absint($count)
        );
        $today_view = array(
            'terricel_today_plus' => '<a href="' . esc_url($base_url) . '"' . ($is_current ? ' class="current" aria-current="page"' : '') . '>' . wp_kses_post($label) . '</a>',
        );

        if ($is_current) {
            foreach ($views as $key => $view) {
                $views[$key] = str_replace(array(' class="current"', ' class=\'current\'', ' aria-current="page"'), '', $view);
            }
        }

        foreach ($views as $key => $view) {
            if (false === strpos($view, 'terricel_list_view=')) {
                $views[$key] = preg_replace_callback(
                    '/href=(["\'])(.*?)\1/',
                    function ($matches) {
                        return 'href=' . $matches[1] . esc_url(add_query_arg('terricel_list_view', 'all', html_entity_decode($matches[2]))) . $matches[1];
                    },
                    $view,
                    1
                );
            }
        }

        return $today_view + $views;
    }

    private function is_today_plus_list_view($post_type) {
        if (!in_array($post_type, array(self::SCHEDULE_POST_TYPE, self::VACANCY_POST_TYPE), true)) {
            return false;
        }

        if (isset($_GET['terricel_list_view']) && 'all' === sanitize_key(wp_unslash($_GET['terricel_list_view']))) {
            return false;
        }

        if (isset($_GET['post_status']) && 'trash' === sanitize_key(wp_unslash($_GET['post_status']))) {
            return false;
        }

        return true;
    }

    private function count_today_plus_records($post_type) {
        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        );

        if (self::SCHEDULE_POST_TYPE === $post_type) {
            $args['meta_query'] = array(
                array(
                    'key'     => '_terricel_coverage_date',
                    'value'   => current_time('Y-m-d'),
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            );
        } elseif (self::VACANCY_POST_TYPE === $post_type) {
            $args['meta_query'] = array(
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_terricel_vacancy_end_date',
                        'value'   => current_time('Y-m-d'),
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ),
                    array(
                        'key'     => '_terricel_vacancy_date',
                        'value'   => current_time('Y-m-d'),
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ),
                ),
            );
        }

        return count(get_posts($args));
    }

    public function schedule_columns($columns) {
        return $this->insert_columns(
            $columns,
            array(
                'terricel_date'              => __('Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_route'             => __('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_driver'            => __('Assigned Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_substitute_driver' => __('Substitute', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_status'            => __('Status', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            )
        );
    }

    public function render_schedule_column($column, $post_id) {
        if ('terricel_date' === $column) {
            echo esc_html($this->format_date(get_post_meta($post_id, '_terricel_coverage_date', true)));
        }

        if ('terricel_route' === $column) {
            $this->render_linked_post((int) get_post_meta($post_id, '_terricel_coverage_route_id', true));
        }

        if ('terricel_driver' === $column) {
            $this->render_linked_post((int) get_post_meta($post_id, '_terricel_coverage_driver_id', true));
        }

        if ('terricel_substitute_driver' === $column) {
            $this->render_linked_post((int) get_post_meta($post_id, '_terricel_coverage_substitute_driver_id', true));
        }

        if ('terricel_status' === $column) {
            echo esc_html($this->get_label(get_post_meta($post_id, '_terricel_coverage_status', true), $this->coverage_statuses(), 'scheduled'));
        }
    }

    public function availability_columns($columns) {
        return $this->insert_columns(
            $columns,
            array(
                'terricel_date'           => __('Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_end_date'       => __('Out Through', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_driver'         => __('Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_status'         => __('Availability', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_can_substitute' => __('Substitute', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            )
        );
    }

    public function render_availability_column($column, $post_id) {
        if ('terricel_date' === $column) {
            echo esc_html($this->format_date(get_post_meta($post_id, '_terricel_availability_date', true)));
        }

        if ('terricel_end_date' === $column) {
            echo esc_html($this->format_date(get_post_meta($post_id, '_terricel_availability_end_date', true)));
        }

        if ('terricel_driver' === $column) {
            $this->render_linked_post((int) get_post_meta($post_id, '_terricel_availability_driver_id', true));
        }

        if ('terricel_status' === $column) {
            echo esc_html($this->get_label(get_post_meta($post_id, '_terricel_availability_status', true), $this->availability_statuses(), 'available'));
        }

        if ('terricel_can_substitute' === $column) {
            echo get_post_meta($post_id, '_terricel_availability_can_substitute', true) ? esc_html__('Yes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) : esc_html__('No', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        }
    }

    public function vacancy_columns($columns) {
        return $this->insert_columns(
            $columns,
            array(
                'terricel_date'     => __('Start Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_end_date' => __('Last Day', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_regular_driver' => __('Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_route'    => __('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'terricel_driver'   => __('Sub Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            )
        );
    }

    public function render_vacancy_column($column, $post_id) {
        if ('terricel_date' === $column) {
            echo esc_html($this->format_date(get_post_meta($post_id, '_terricel_vacancy_date', true)));
        }

        if ('terricel_end_date' === $column) {
            echo esc_html($this->format_date(get_post_meta($post_id, '_terricel_vacancy_end_date', true)));
        }

        if ('terricel_regular_driver' === $column) {
            $driver_id = (int) get_post_meta($post_id, '_terricel_vacancy_driver_id', true);
            if ($driver_id < 1) {
                $route_id = (int) get_post_meta($post_id, '_terricel_vacancy_route_id', true);
                $driver_id = $route_id > 0 ? (int) get_post_meta($route_id, '_terricel_route_default_driver_id', true) : 0;
            }

            $this->render_linked_post($driver_id);
        }

        if ('terricel_route' === $column) {
            $this->render_linked_post((int) get_post_meta($post_id, '_terricel_vacancy_route_id', true));
        }

        if ('terricel_driver' === $column) {
            $this->render_linked_post((int) get_post_meta($post_id, '_terricel_vacancy_assigned_driver_id', true));
        }

    }

    public function save_notification_settings() {
        if (!current_user_can($this->capability)) {
            wp_die(esc_html__('You do not have permission to update route coverage notifications.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
        }

        check_admin_referer('terricel_route_coverage_notifications');

        $raw = isset($_POST['terricel_route_coverage_alert_emails']) ? sanitize_textarea_field(wp_unslash($_POST['terricel_route_coverage_alert_emails'])) : '';
        $emails = $this->sanitize_email_list($raw);
        update_option(self::ALERT_EMAILS_OPTION, implode("\n", $emails));

        wp_safe_redirect(add_query_arg('terricel_route_coverage_saved', '1', admin_url('admin.php?page=terricel-transit-route-coverage')));
        exit;
    }

    public function save_today_substitutes() {
        if (!current_user_can($this->capability)) {
            wp_die(esc_html__('You do not have permission to update route coverage.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN));
        }

        check_admin_referer('terricel_route_coverage_today_substitutes');

        $date = $this->sanitize_date_field('terricel_coverage_substitute_date');
        if (!$date) {
            $date = current_time('Y-m-d');
        }

        $raw_run_substitutes = isset($_POST['terricel_route_run_substitute_driver_id']) && is_array($_POST['terricel_route_run_substitute_driver_id']) ? wp_unslash($_POST['terricel_route_run_substitute_driver_id']) : array();
        $raw_allow_any_driver = isset($_POST['terricel_route_run_allow_any_driver']) && is_array($_POST['terricel_route_run_allow_any_driver']) ? wp_unslash($_POST['terricel_route_run_allow_any_driver']) : array();
        $raw_run_substitutes = $this->sanitize_today_run_substitute_posts($date, $raw_run_substitutes, $raw_allow_any_driver);

        foreach ($raw_run_substitutes as $route_id => $run_substitutes) {
            $route_id = absint($route_id);

            if ($route_id < 1 || 'publish' !== get_post_status($route_id)) {
                continue;
            }

            $run_substitutes = is_array($run_substitutes) ? $run_substitutes : array();
            $this->save_today_substitute_schedule($date, $route_id, 0, $run_substitutes);
        }

        $raw_substitutes = isset($_POST['terricel_route_substitute_driver_id']) && is_array($_POST['terricel_route_substitute_driver_id']) ? wp_unslash($_POST['terricel_route_substitute_driver_id']) : array();

        foreach ($raw_substitutes as $route_id => $driver_id) {
            $route_id = absint($route_id);

            if (isset($raw_run_substitutes[$route_id])) {
                continue;
            }

            $driver_id = absint($driver_id);

            if ($route_id < 1 || 'publish' !== get_post_status($route_id)) {
                continue;
            }

            $this->save_today_substitute_schedule($date, $route_id, $driver_id, array());
        }

        wp_safe_redirect(add_query_arg('terricel_route_coverage_substitutes_saved', '1', admin_url('admin.php?page=terricel-transit-route-coverage')));
        exit;
    }

    public function ajax_save_run_substitute() {
        if (!current_user_can($this->capability)) {
            wp_send_json_error(array('message' => __('You do not have permission to update route coverage.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)), 403);
        }

        check_ajax_referer('terricel_route_coverage_today_substitutes');

        $date = isset($_POST['date']) ? $this->sanitize_date_value(wp_unslash($_POST['date'])) : '';
        $route_id = isset($_POST['route_id']) ? absint($_POST['route_id']) : 0;
        $run_value = isset($_POST['run_value']) ? sanitize_text_field(wp_unslash($_POST['run_value'])) : '';
        $driver_id = isset($_POST['driver_id']) ? absint($_POST['driver_id']) : 0;
        $allow_any_driver = isset($_POST['allow_any_driver']) ? absint($_POST['allow_any_driver']) : 0;

        if (!$date || $route_id < 1 || !$run_value || 'publish' !== get_post_status($route_id)) {
            wp_send_json_error(array('message' => __('The selected run could not be saved.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)), 400);
        }

        $parts = $this->logistics()->parse_run_value($run_value);
        if (!$parts || $parts['date'] !== $date) {
            wp_send_json_error(array('message' => __('The selected run does not match this dispatch date.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)), 400);
        }

        if ($driver_id > 0 && !$allow_any_driver && !$this->is_driver_available_for_dispatch_run($driver_id, $date, $route_id, $parts)) {
            wp_send_json_error(array('message' => __('That driver is no longer available for this run.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)), 400);
        }

        $schedule = $this->get_schedule_for_route_date($route_id, $date);
        $run_substitutes = $schedule ? get_post_meta($schedule->ID, '_terricel_coverage_run_substitutes', true) : array();
        $run_substitutes = is_array($run_substitutes) ? $run_substitutes : array();
        $run_substitutes[$run_value] = $driver_id;
        $run_substitutes = $this->sanitize_single_route_run_substitutes($date, $route_id, $run_substitutes);

        if ($driver_id > 0 && (!isset($run_substitutes[$run_value]) || absint($run_substitutes[$run_value]) !== $driver_id)) {
            wp_send_json_error(array('message' => __('This driver already has this run type assigned today.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)), 400);
        }

        $this->save_today_substitute_schedule($date, $route_id, 0, $run_substitutes);

        wp_send_json_success(
            array(
                'covered'      => $driver_id > 0,
                'status_label' => $driver_id > 0 ? __('Covered', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) : __('Unassigned', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            )
        );
    }

    private function sanitize_today_run_substitute_posts($date, $raw_run_substitutes, $raw_allow_any_driver = array()) {
        $flattened = array();
        $route_by_run_value = array();
        $allow_any_driver = array();

        foreach ($raw_run_substitutes as $route_id => $run_substitutes) {
            $route_id = absint($route_id);
            $run_substitutes = is_array($run_substitutes) ? $run_substitutes : array();

            foreach ($run_substitutes as $run_value => $driver_id) {
                $run_value = sanitize_text_field($run_value);
                if (!$run_value) {
                    continue;
                }

                $flattened[$run_value] = absint($driver_id);
                $route_by_run_value[$run_value] = $route_id;
            }
        }

        foreach ($raw_allow_any_driver as $route_id => $run_substitutes) {
            $route_id = absint($route_id);
            $run_substitutes = is_array($run_substitutes) ? $run_substitutes : array();

            foreach ($run_substitutes as $run_value => $flag) {
                $run_value = sanitize_text_field($run_value);
                if (!$run_value) {
                    continue;
                }

                if (!empty($flag)) {
                    $allow_any_driver[$run_value] = true;
                }
            }
        }

        $sanitized = array();
        $flattened_for_validation = array();

        foreach ($flattened as $run_value => $driver_id) {
            if (isset($allow_any_driver[$run_value])) {
                $sanitized[$run_value] = $driver_id;
                continue;
            }

            $flattened_for_validation[$run_value] = $driver_id;
        }

        $validated = $this->logistics()->sanitize_run_substitute_assignments($date, $flattened_for_validation);
        foreach ($validated as $run_value => $driver_id) {
            $sanitized[$run_value] = $driver_id;
        }

        $grouped = array();

        foreach ($sanitized as $run_value => $driver_id) {
            if (!isset($route_by_run_value[$run_value])) {
                continue;
            }

            $route_id = $route_by_run_value[$run_value];
            if (!isset($grouped[$route_id])) {
                $grouped[$route_id] = array();
            }

            $grouped[$route_id][$run_value] = $driver_id;
        }

        return $grouped;
    }

    private function sanitize_single_route_run_substitutes($date, $route_id, $run_substitutes) {
        $grouped = $this->sanitize_today_run_substitute_posts($date, array(absint($route_id) => is_array($run_substitutes) ? $run_substitutes : array()));

        return isset($grouped[$route_id]) && is_array($grouped[$route_id]) ? $grouped[$route_id] : array();
    }

    private function sanitize_schedule_run_substitute_posts($date, $route_id) {
        $raw_selected = isset($_POST['terricel_coverage_run_selected']) && is_array($_POST['terricel_coverage_run_selected'])
            ? wp_unslash($_POST['terricel_coverage_run_selected'])
            : array();
        $raw_drivers = isset($_POST['terricel_coverage_run_substitute_driver_id']) && is_array($_POST['terricel_coverage_run_substitute_driver_id'])
            ? wp_unslash($_POST['terricel_coverage_run_substitute_driver_id'])
            : array();
        $run_substitutes = array();

        foreach ($raw_selected as $run_value => $selected) {
            $run_value = sanitize_text_field($run_value);
            $parts = $this->logistics()->parse_run_value($run_value);

            if (!$parts || $parts['date'] !== $date) {
                continue;
            }

            $driver_id = isset($raw_drivers[$run_value]) ? absint($raw_drivers[$run_value]) : 0;
            if ($driver_id > 0 && !$this->is_driver_available_for_dispatch_run($driver_id, $date, $route_id, $parts)) {
                $driver_id = 0;
            }

            $run_substitutes[$run_value] = $driver_id;
        }

        return $this->sanitize_single_route_run_substitutes($date, $route_id, $run_substitutes);
    }

    private function is_driver_available_for_dispatch_run($driver_id, $date, $route_id, $parts) {
        $run_context = array(
            'route_id'   => absint($route_id),
            'run_key'    => isset($parts['run_key']) ? sanitize_key($parts['run_key']) : '',
            'start_time' => isset($parts['start_time']) ? sanitize_text_field($parts['start_time']) : '',
            'end_time'   => '',
        );

        foreach ($this->get_route_runs_for_date($route_id, $date) as $run) {
            $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';
            if (!$run_key && isset($run['run_name'])) {
                $run_key = $this->find_run_key_by_name($run['run_name']);
            }

            $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';
            if ($run_key === $run_context['run_key'] && $start_time === $run_context['start_time']) {
                $run_context['end_time'] = isset($run['end_time']) ? $this->sanitize_time_value($run['end_time']) : '';
                break;
            }
        }

        $options = $this->get_available_substitute_driver_options(
            $this->get_availability_for_date($date),
            $date,
            $this->get_schedules_for_date($date),
            $this->get_vacancies_for_date($date),
            $run_context,
            $driver_id
        );

        return isset($options[$driver_id]);
    }

    private function get_route_runs_for_date($route_id, $date) {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return array();
        }

        $day_key = strtolower(date('l', $timestamp));
        $schedule = $this->get_route_regular_schedule($route_id);
        $runs = isset($schedule[$day_key]) && is_array($schedule[$day_key]) ? $schedule[$day_key] : array();

        return $this->logistics()->apply_route_schedule_changes_to_runs($route_id, $date, $runs);
    }

    public function get_monitor_items() {
        $items = $this->get_event_monitor_items($this->name);

        foreach ($this->get_at_risk_schedules() as $schedule) {
            $route_id = (int) get_post_meta($schedule->ID, '_terricel_coverage_route_id', true);
            $status = get_post_meta($schedule->ID, '_terricel_coverage_status', true);

            $items[] = array(
                'module'                => $this->id,
                'module_label'          => $this->name,
                'priority'              => 'at-risk' === $status ? 'high' : 'urgent',
                'status'                => $status,
                'title'                 => __('Route Coverage Issue', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'message'               => sprintf(
                    __('%1$s is %2$s for %3$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $route_id ? get_the_title($route_id) : __('A route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $this->get_label($status, $this->coverage_statuses(), 'scheduled'),
                    $this->format_date(get_post_meta($schedule->ID, '_terricel_coverage_date', true))
                ),
                'requires_notification' => true,
            );
        }

        foreach ($this->get_open_vacancies() as $vacancy) {
            $route_id = (int) get_post_meta($vacancy->ID, '_terricel_vacancy_route_id', true);
            $driver_id = $this->get_vacancy_regular_driver_id($vacancy->ID);
            $start_date = get_post_meta($vacancy->ID, '_terricel_vacancy_date', true);
            $end_date = get_post_meta($vacancy->ID, '_terricel_vacancy_end_date', true);

            $items[] = array(
                'module'                => $this->id,
                'module_label'          => $this->name,
                'priority'              => 'normal',
                'status'                => 'open',
                'title'                 => __('Open Route Vacancy', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'message'               => sprintf(
                    __('%1$s is out for %2$s. Route needing coverage: %3$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $driver_id ? get_the_title($driver_id) : __('A driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $this->format_date_range($start_date, $end_date),
                    $route_id ? get_the_title($route_id) : __('A route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)
                ),
                'requires_notification' => false,
            );
        }

        return apply_filters('terricel_route_coverage_monitor_items', $items);
    }

    public function render_admin_page() {
        $today = current_time('Y-m-d');
        $view_mode = isset($_GET['terricel_dispatch_view']) ? sanitize_key(wp_unslash($_GET['terricel_dispatch_view'])) : 'daily';
        $view_mode = in_array($view_mode, array('daily', 'weekly'), true) ? $view_mode : 'daily';
        $selected_date = isset($_GET['terricel_dispatch_date']) ? $this->sanitize_date_value(sanitize_text_field(wp_unslash($_GET['terricel_dispatch_date']))) : '';
        $selected_date = $selected_date ? $selected_date : $today;
        $routes = $this->get_parent_routes();
        $drivers = $this->get_active_drivers();
        $schedules = $this->get_schedules_for_date($selected_date);
        $availability = $this->get_availability_for_date($selected_date);
        $driver_out_records = $this->get_driver_out_records($selected_date);
        $active_vacancies = $this->get_vacancies_for_date($selected_date);
        $route_rows = $this->build_dispatcher_route_rows($routes, $schedules, $driver_out_records, $active_vacancies, $selected_date);
        $scheduled_vacancies = $this->get_scheduled_driver_vacancies_for_date($selected_date);

        if (isset($_GET['terricel_route_coverage_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Route coverage notification settings saved.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p></div>';
        }

        if (isset($_GET['terricel_route_coverage_substitutes_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Today\'s substitute driver assignments saved.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p></div>';
        }

        echo '<h2>' . esc_html__('Daily Route Scheduling Dashboard', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</h2>';
        echo '<p>' . esc_html__('Module 1 uses parent-owned routes and drivers to track daily coverage, substitute availability, vacancies, and dispatcher/admin alerts.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
        $this->render_dispatch_view_controls($view_mode, $selected_date);

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:16px 0 20px;">';
        $this->render_stat_card(__('Parent Routes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), count($routes));
        $this->render_stat_card(__('Active Drivers', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), count($drivers));
        $this->render_stat_card(__('No Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), count($this->filter_route_rows_by_priority($route_rows, 'unassigned')));
        $this->render_stat_card(__('Sub Drivers', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), count($this->filter_route_rows_by_priority($route_rows, 'substitute')));
        $this->render_stat_card(__('Scheduled Vacancies', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), count($scheduled_vacancies));
        echo '</div>';

        $this->render_action_buttons();

        if ('weekly' === $view_mode) {
            $this->render_grouped_route_schedule_changes($routes, $today, $this->add_days_to_date($today, 7));
            $this->render_weekly_dispatch_view($routes, $today);
        } else {
            $this->render_regular_driver_out_snapshot($driver_out_records, $selected_date, $active_vacancies);
            $this->render_today_route_status($route_rows, $selected_date, $availability, $schedules, $active_vacancies);
            $this->render_substitute_driver_snapshot($availability);
        }

        $this->render_scheduled_driver_vacancies_snapshot($scheduled_vacancies, $selected_date);
        if ('daily' === $view_mode) {
            $this->render_daily_route_schedule_changes($routes, $selected_date);
        }
        $this->render_notification_settings();
    }

    public function get_vacant_route_count_for_date($date = '') {
        $date = $date ? $this->sanitize_date_value($date) : current_time('Y-m-d');

        if (!$date) {
            return 0;
        }

        $routes = $this->get_parent_routes();
        $schedules = $this->get_schedules_for_date($date);
        $driver_out_records = $this->get_driver_out_records($date);
        $active_vacancies = $this->get_vacancies_for_date($date);
        $route_rows = $this->build_dispatcher_route_rows($routes, $schedules, $driver_out_records, $active_vacancies, $date);

        return count($this->filter_route_rows_by_priority($route_rows, 'unassigned'));
    }

    private function render_dispatch_view_controls($view_mode, $selected_date) {
        $today = current_time('Y-m-d');
        echo '<form method="get" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin:14px 0;">';
        echo '<input type="hidden" name="page" value="terricel-transit-route-coverage">';
        echo '<p style="margin:0;"><label for="terricel_dispatch_view"><strong>' . esc_html__('View', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</strong></label><br>';
        echo '<select id="terricel_dispatch_view" name="terricel_dispatch_view">';
        echo '<option value="daily"' . selected($view_mode, 'daily', false) . '>' . esc_html__('Daily', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</option>';
        echo '<option value="weekly"' . selected($view_mode, 'weekly', false) . '>' . esc_html__('Weekly - today plus 7', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</option>';
        echo '</select></p>';
        echo '<p style="margin:0;"><label for="terricel_dispatch_date"><strong>' . esc_html__('Daily Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</strong></label><br>';
        echo '<input type="date" id="terricel_dispatch_date" name="terricel_dispatch_date" value="' . esc_attr($selected_date) . '"></p>';
        echo '<p style="margin:0;">';
        submit_button(__('Apply View', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), 'secondary', 'submit', false);
        if ('daily' === $view_mode && $selected_date !== $today) {
            echo ' <a class="button" href="' . esc_url(
                add_query_arg(
                    array(
                        'page'                   => 'terricel-transit-route-coverage',
                        'terricel_dispatch_view' => 'daily',
                        'terricel_dispatch_date' => $today,
                    ),
                    admin_url('admin.php')
                )
            ) . '">' . esc_html__('View Today', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</a>';
        }
        echo '</p>';
        echo '</form>';
    }

    private function render_weekly_dispatch_view($routes, $today) {
        echo '<h2>' . esc_html(sprintf(__('Weekly Dispatch View: %1$s through %2$s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $this->format_date($today), $this->format_date($this->add_days_to_date($today, 7)))) . '</h2>';
        echo '<p class="description">' . esc_html__('Weekly view shows today plus the next seven days. Substitute changes are still saved one day at a time inside each day section.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';

        foreach ($this->get_dispatch_week_dates($today) as $date) {
            $schedules = $this->get_schedules_for_date($date);
            $availability = $this->get_availability_for_date($date);
            $driver_out_records = $this->get_driver_out_records($date);
            $active_vacancies = $this->get_vacancies_for_date($date);
            $route_rows = $this->build_dispatcher_route_rows($routes, $schedules, $driver_out_records, $active_vacancies, $date);
            $vacant_count = $this->count_unassigned_dispatch_runs($route_rows);

            echo '<details class="postbox" style="margin:18px 0;">';
            echo '<summary style="cursor:pointer;padding:12px;font-weight:600;">';
            echo esc_html(
                sprintf(
                    _n('%1$s - %2$d vacant route', '%1$s - %2$d vacant routes', $vacant_count, TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $this->format_date($date),
                    $vacant_count
                )
            );
            echo '</summary>';
            echo '<div style="padding:0 12px 12px;">';
            $this->render_regular_driver_out_snapshot($driver_out_records, $date, $active_vacancies);
            $this->render_today_route_status($route_rows, $date, $availability, $schedules, $active_vacancies);
            echo '</div></details>';
        }
    }

    private function render_action_buttons() {
        echo '<p>';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=' . self::SCHEDULE_POST_TYPE)) . '">' . esc_html__('Add Daily Schedule', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=' . self::SCHEDULE_POST_TYPE)) . '">' . esc_html__('Manage Schedules', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('post-new.php?post_type=' . self::VACANCY_POST_TYPE)) . '">' . esc_html__('Add Route Vacancy', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=' . self::VACANCY_POST_TYPE)) . '">' . esc_html__('Manage Vacancies', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</a>';
        echo '</p>';
    }

    private function render_grouped_route_schedule_changes($routes, $start_date, $end_date) {
        $rows = $this->get_grouped_route_schedule_change_rows($routes, $start_date, $end_date);

        echo '<h2>' . esc_html__('Route Changes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('School District', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Detail', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Note', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="4">' . esc_html__('No route schedule changes for this view.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</td></tr>';
        }

        foreach ($rows as $row) {
            $date_url = admin_url(
                'admin.php?page=terricel-transit-route-coverage&terricel_dispatch_view=daily&terricel_dispatch_date=' . rawurlencode($row['date'])
            );
            echo '<tr>';
            echo '<td><a href="' . esc_url($date_url) . '">' . esc_html($this->format_date($row['date'])) . '</a></td>';
            echo '<td>' . esc_html($row['district_name']) . '</td>';
            echo '<td>' . esc_html($row['detail']) . '</td>';
            echo '<td>' . esc_html($row['note'] ? $row['note'] : __('None', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function render_daily_route_schedule_changes($routes, $date) {
        $rows = $this->get_daily_route_schedule_change_rows($routes, $date);

        echo '<h2>' . esc_html(sprintf(__('Affected Routes for %s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $this->format_date($date))) . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('District', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('School', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Change', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Affected Runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Note', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="6">' . esc_html__('No affected routes for this date.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</td></tr>';
        }

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['district_name']) . '</td>';
            echo '<td>' . esc_html($row['school_name']) . '</td>';
            echo '<td><a href="' . esc_url(get_edit_post_link($row['route_id'])) . '">' . esc_html($row['route_name']) . '</a></td>';
            echo '<td>' . esc_html($row['change_label']) . '</td>';
            echo '<td>' . esc_html($row['affected_runs']) . '</td>';
            echo '<td>' . esc_html($row['note'] ? $row['note'] : __('None', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function render_today_route_status($route_rows, $today, $availability, $schedules, $active_vacancies) {
        echo '<h2>' . esc_html(sprintf(__('Dispatcher Route List for %s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $this->format_date($today))) . '</h2>';
        echo '<p class="description">' . esc_html__('Substitute driver changes here apply to this date only. For longer substitutions, use the Manage Vacancies button above.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
        echo '<form class="terricel-dispatch-substitute-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('terricel_route_coverage_today_substitutes');
        echo '<input type="hidden" name="action" value="terricel_route_coverage_save_today_substitutes">';
        echo '<input type="hidden" name="terricel_coverage_substitute_date" value="' . esc_attr($today) . '">';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('District', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('School', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Regular Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Regular Driver Out', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Assigned Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Substitute Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Add Any Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Status', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($route_rows)) {
            echo '<tr><td colspan="9">' . esc_html__('No vacant routes or substitute-covered routes for this date.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</td></tr>';
        }

        foreach ($route_rows as $row) {
            $style = 'unassigned' === $row['priority'] ? ' style="background:#fbeaea;color:#8a1f11;font-weight:600;"' : '';

            echo '<tr' . $style . '>';
            echo '<td>' . esc_html($row['district_name']) . '</td>';
            echo '<td>' . esc_html($row['school_name']) . '</td>';
            echo '<td><a href="' . esc_url(get_edit_post_link($row['route_id'])) . '">' . esc_html($row['route_name']) . '</a></td>';
            echo '<td>' . wp_kses_post($this->get_linked_post_markup($row['regular_driver_id'])) . '</td>';
            echo '<td>' . esc_html($row['regular_driver_out']) . '</td>';
            echo '<td>' . wp_kses_post($this->get_linked_post_markup($row['assigned_driver_id'])) . '</td>';
            echo '<td>' . esc_html__('Select by run below', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</td>';
            echo '<td></td>';
            echo '<td>' . esc_html($row['status_label']) . '</td>';
            echo '</tr>';

            foreach ($row['runs'] as $run) {
                $run_style = empty($run['substitute_driver_id']) ? ' style="background:#fff5f5;color:#8a1f11;"' : '';

                echo '<tr class="terricel-dispatch-run-row"' . $run_style . '>';
                echo '<td></td>';
                echo '<td colspan="2" style="padding-left:32px;">';
                echo '<strong>' . esc_html($run['run_name']) . '</strong>';
                echo '</td>';
                echo '<td>' . esc_html($run['start_label']) . '</td>';
                echo '<td colspan="2">' . esc_html__('Run coverage', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</td>';
                echo '<td>' . $this->get_run_any_driver_toggle_markup($row['route_id'], $run['value']) . '</td>';
                $substitute_drivers = $this->get_available_substitute_driver_options(
                    $availability,
                    $today,
                    $schedules,
                    $active_vacancies,
                    array(
                        'route_id'    => $row['route_id'],
                        'run_key'     => $run['run_key'],
                        'start_time'  => $run['start_time'],
                        'end_time'    => isset($run['end_time']) ? $run['end_time'] : '',
                    ),
                    $run['substitute_driver_id']
                );
                echo '<td>' . $this->get_run_substitute_driver_select_markup($row['route_id'], $run['value'], $run['substitute_driver_id'], $substitute_drivers) . '</td>';
                echo '<td class="terricel-dispatch-run-status">' . esc_html($run['status_label']) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</form>';
        echo '<script>';
        echo '(function(){';
        echo 'var form=document.querySelector(".terricel-dispatch-substitute-form");if(!form){return;}';
        echo 'var nonce=form.querySelector("input[name=_wpnonce]");var date=form.querySelector("input[name=terricel_coverage_substitute_date]");';
        echo 'function optionValueLabelPair(options){var items=[];Object.keys(options||{}).forEach(function(key){items.push({id:String(key),name:String(options[key]||key)});});return items;}';
        echo 'function syncRunDriverOptions(select,allowAny){var available=optionValueLabelPair(JSON.parse(select.getAttribute("data-available-options")||"{}"));var all=optionValueLabelPair(JSON.parse(select.getAttribute("data-all-options")||"{}"));var allOptions=allowAny?all:available;var selectedValue=String(select.value||"0");select.innerHTML="";var none=document.createElement("option");none.value="0";none.textContent="' . esc_js(__('No substitute driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '";if(selectedValue==="0"){none.selected=true;}select.appendChild(none);allOptions.forEach(function(option){var node=document.createElement("option");node.value=String(option.id||"0");node.textContent=option.name||String(option.id||"0");if(String(option.id||"0")===selectedValue){node.selected=true;}select.appendChild(node);});}';
        echo 'function setStatus(select,message,state){var cell=select.closest("td");var status=cell?cell.querySelector(".terricel-run-substitute-save-status"):null;if(status){status.textContent=message||"";status.className="terricel-run-substitute-save-status "+(state||"");}}';
        echo 'function updateRow(select,statusLabel,covered){var row=select.closest("tr");if(!row){return;}var statusCell=row.querySelector(".terricel-dispatch-run-status");if(statusCell&&statusLabel){statusCell.textContent=statusLabel;}row.style.background=covered?"":"#fff5f5";row.style.color=covered?"":"#8a1f11";}';
        echo 'form.addEventListener("change",function(event){var target=event.target;if(!target||!target.classList){return;}if(target.classList.contains("terricel-run-any-driver-toggle")){var select=target.closest("tr")?target.closest("tr").querySelector(".terricel-run-substitute-select"):null;if(select){syncRunDriverOptions(select,target.checked);}return;}if(!target.classList.contains("terricel-run-substitute-select")){return;}var data=new FormData();data.append("action","terricel_route_coverage_save_run_substitute");data.append("_wpnonce",nonce?nonce.value:"");data.append("date",date?date.value:"");data.append("route_id",target.getAttribute("data-route-id")||"0");data.append("run_value",target.getAttribute("data-run-value")||"");data.append("driver_id",target.value||"0");var toggle=target.closest("tr")?target.closest("tr").querySelector(".terricel-run-any-driver-toggle"):null;data.append("allow_any_driver",toggle&&toggle.checked?"1":"0");target.disabled=true;setStatus(target,"Saving...","is-saving");fetch(ajaxurl,{method:"POST",credentials:"same-origin",body:data}).then(function(response){return response.json();}).then(function(response){if(!response||!response.success){throw new Error(response&&response.data&&response.data.message?response.data.message:"Unable to save.");}setStatus(target,"Saved. Refreshing...","is-saved");updateRow(target,response.data.status_label,response.data.covered);window.setTimeout(function(){window.location.reload();},300);}).catch(function(error){setStatus(target,error.message||"Unable to save.","is-error");target.disabled=false;});});';
        echo '}());';
        echo '</script>';
    }

    private function render_regular_driver_out_snapshot($driver_out_records, $today, $vacancies = array()) {
        echo '<h2>' . esc_html(sprintf(__('Regular Drivers Out for %s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $this->format_date($today))) . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Regular Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Status', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Out Through', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Time Out', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Notes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($driver_out_records) && empty($vacancies)) {
            echo '<tr><td colspan="5">' . esc_html__('No regular drivers are marked out for today.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</td></tr>';
        }

        $rendered_driver_ids = array();

        foreach ($driver_out_records as $record) {
            $driver_id = (int) get_post_meta($record->ID, '_terricel_availability_driver_id', true);
            $status = get_post_meta($record->ID, '_terricel_availability_status', true);
            $start_date = get_post_meta($record->ID, '_terricel_availability_date', true);
            $end_date = get_post_meta($record->ID, '_terricel_availability_end_date', true);
            $notes = get_post_meta($record->ID, '_terricel_availability_notes', true);
            $rendered_driver_ids[$driver_id] = true;

            echo '<tr>';
            echo '<td>' . wp_kses_post($this->get_linked_post_markup($driver_id)) . '</td>';
            echo '<td>' . esc_html($this->get_label($status, $this->availability_statuses(), 'unavailable')) . '</td>';
            echo '<td>' . esc_html($this->format_date($end_date ? $end_date : $start_date)) . '</td>';
            echo '<td>' . esc_html($this->format_out_duration($start_date, $end_date)) . '</td>';
            echo '<td>' . esc_html($notes ? $notes : __('None', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</td>';
            echo '</tr>';
        }

        foreach ($vacancies as $vacancy) {
            $driver_id = $this->get_vacancy_regular_driver_id($vacancy->ID);

            if ($driver_id > 0 && isset($rendered_driver_ids[$driver_id])) {
                continue;
            }

            $route_id = (int) get_post_meta($vacancy->ID, '_terricel_vacancy_route_id', true);
            $start_date = get_post_meta($vacancy->ID, '_terricel_vacancy_date', true);
            $end_date = get_post_meta($vacancy->ID, '_terricel_vacancy_end_date', true);
            $notes = get_post_meta($vacancy->ID, '_terricel_vacancy_notes', true);
            $route_label = $route_id > 0 ? get_the_title($route_id) : __('No route selected', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
            $note_text = trim(sprintf(__('Route: %s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $route_label) . ($notes ? ' - ' . $notes : ''));

            echo '<tr>';
            echo '<td>' . wp_kses_post($this->get_linked_post_markup($driver_id)) . '</td>';
            echo '<td>' . esc_html__('Vacancy', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</td>';
            echo '<td>' . esc_html($this->format_date($end_date ? $end_date : $start_date)) . '</td>';
            echo '<td>' . esc_html($this->format_out_duration($start_date, $end_date)) . '</td>';
            echo '<td>' . esc_html($note_text) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function render_driver_availability_snapshot($availability, $today) {
        echo '<h2>' . esc_html(sprintf(__('Driver Availability for %s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $this->format_date($today))) . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Availability', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Out Through', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Substitute', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Notes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($availability)) {
            echo '<tr><td colspan="5">' . esc_html__('No driver availability records have been entered for today.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</td></tr>';
        }

        foreach ($availability as $record) {
            $driver_id = (int) get_post_meta($record->ID, '_terricel_availability_driver_id', true);
            $status = get_post_meta($record->ID, '_terricel_availability_status', true);
            $end_date = get_post_meta($record->ID, '_terricel_availability_end_date', true);
            $can_substitute = get_post_meta($record->ID, '_terricel_availability_can_substitute', true);
            $notes = get_post_meta($record->ID, '_terricel_availability_notes', true);

            echo '<tr>';
            echo '<td>' . wp_kses_post($this->get_linked_post_markup($driver_id)) . '</td>';
            echo '<td>' . esc_html($this->get_label($status, $this->availability_statuses(), 'available')) . '</td>';
            echo '<td>' . esc_html($end_date ? $this->format_date($end_date) : __('Not set', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</td>';
            echo '<td>' . esc_html($can_substitute ? __('Yes', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) : __('No', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</td>';
            echo '<td>' . esc_html($notes ? $notes : __('None', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function render_substitute_driver_snapshot($availability) {
        $substitutes = array_filter(
            $availability,
            function ($record) {
                return get_post_meta($record->ID, '_terricel_availability_can_substitute', true) && 'available' === get_post_meta($record->ID, '_terricel_availability_status', true);
            }
        );

        echo '<h2>' . esc_html__('Substitute Driver Bench', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</h2>';

        if (empty($substitutes)) {
            echo '<p>' . esc_html__('No available substitute drivers are marked for today.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
            return;
        }

        echo '<ul>';
        foreach ($substitutes as $record) {
            $driver_id = (int) get_post_meta($record->ID, '_terricel_availability_driver_id', true);
            echo '<li>' . wp_kses_post($this->get_linked_post_markup($driver_id)) . '</li>';
        }
        echo '</ul>';
    }

    private function render_scheduled_driver_vacancies_snapshot($vacancies, $date) {
        echo '<h2>' . esc_html__('Scheduled Driver Vacancies', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Start Date', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Last Day', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '<th>' . esc_html__('Sub Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($vacancies)) {
            echo '<tr><td colspan="6">' . esc_html__('No scheduled driver vacancies affect this date.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</td></tr>';
        }

        foreach ($vacancies as $vacancy) {
            $runs_label = $this->get_vacancy_runs_label_for_date($vacancy->ID, $date);

            echo '<tr>';
            echo '<td>' . esc_html($this->format_date(get_post_meta($vacancy->ID, '_terricel_vacancy_date', true))) . '</td>';
            echo '<td>' . esc_html($this->format_date(get_post_meta($vacancy->ID, '_terricel_vacancy_end_date', true))) . '</td>';
            echo '<td>' . wp_kses_post($this->get_linked_post_markup($this->get_vacancy_regular_driver_id($vacancy->ID))) . '</td>';
            echo '<td>' . wp_kses_post($this->get_linked_post_markup((int) get_post_meta($vacancy->ID, '_terricel_vacancy_route_id', true))) . '</td>';
            echo '<td>' . esc_html($runs_label ? $runs_label : __('No runs selected', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</td>';
            echo '<td>' . wp_kses_post($this->get_linked_post_markup((int) get_post_meta($vacancy->ID, '_terricel_vacancy_assigned_driver_id', true))) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function get_vacancy_runs_label_for_date($vacancy_id, $date) {
        $runs = get_post_meta($vacancy_id, '_terricel_vacancy_runs', true);
        $runs = is_array($runs) ? $runs : array();
        $labels = array();

        foreach ($runs as $run) {
            if (!is_array($run) || $date !== ($run['date'] ?? '')) {
                continue;
            }

            $run_name = isset($run['run_name']) ? sanitize_text_field($run['run_name']) : '';
            $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';
            $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';
            $label = $run_name ? $run_name : $this->get_run_name_label($run_key);

            if ($label && $start_time) {
                $label .= ' (' . $this->format_time_value($start_time) . ')';
            }

            if ($label) {
                $labels[$label] = $label;
            }
        }

        return implode(', ', array_values($labels));
    }

    private function render_notification_settings() {
        $emails = get_option(self::ALERT_EMAILS_OPTION, '');

        echo '<h2>' . esc_html__('Dispatcher/Admin Email Alerts', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('terricel_route_coverage_notifications');
        echo '<input type="hidden" name="action" value="terricel_route_coverage_save_notifications">';
        echo '<p><label for="terricel_route_coverage_alert_emails"><strong>' . esc_html__('Alert Recipients', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</strong></label></p>';
        echo '<textarea id="terricel_route_coverage_alert_emails" name="terricel_route_coverage_alert_emails" rows="4" class="large-text" placeholder="dispatch@example.com">' . esc_textarea($emails) . '</textarea>';
        echo '<p class="description">' . esc_html__('One email per line. Alerts are queued when a schedule is unassigned or at risk, or when an open vacancy is high/urgent priority.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
        submit_button(__('Save Alert Recipients', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), 'secondary', 'submit', false);
        echo '</form>';
    }

    private function sync_schedule_alert($post_id, $date, $route_id, $status, $driver_id, $substitute_driver_id) {
        if (!in_array($status, array('unassigned', 'at-risk'), true)) {
            $this->close_alert_events($post_id, 'route-schedule-unassigned');
            $this->close_alert_events($post_id, 'route-schedule-at-risk');
            return;
        }

        $route_name = $route_id ? get_the_title($route_id) : __('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        $message = sprintf(
            __('%1$s is marked %2$s for %3$s. Assigned driver: %4$s. Substitute: %5$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            $route_name,
            $this->get_label($status, $this->coverage_statuses(), 'scheduled'),
            $this->format_date($date),
            $driver_id ? get_the_title($driver_id) : __('None', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            $substitute_driver_id ? get_the_title($substitute_driver_id) : __('None', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)
        );

        $this->close_alert_events($post_id, 'unassigned' === $status ? 'route-schedule-at-risk' : 'route-schedule-unassigned');
        $this->create_alert_event($post_id, 'route-schedule-' . $status, $status, 'at-risk' === $status ? 'high' : 'urgent', $date, $message);
        $this->queue_email_alerts(__('Route Coverage Alert', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $message);
    }

    private function sync_vacancy_alert($post_id, $date, $end_date, $route_id, $status, $priority, $assigned_driver_id) {
        $route_name = $route_id ? get_the_title($route_id) : __('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        $date_range = $this->format_date_range($date, $end_date);
        $dashboard_url = admin_url('admin.php?page=terricel-transit-route-coverage');

        if ($assigned_driver_id > 0) {
            terricel_logistics_queue_role_notification(
                $this->id,
                'sub_driver_assigned',
                __('Sub Driver Assigned to Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                sprintf(
                    __('%1$s is assigned to cover %2$s for %3$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    get_the_title($assigned_driver_id),
                    $route_name,
                    $date_range
                ),
                $dashboard_url
            );
        } elseif ('open' === $status) {
            terricel_logistics_queue_role_notification(
                $this->id,
                'new_vacant_route',
                __('New Vacant Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                sprintf(
                    __('%1$s is vacant for %2$s and has no substitute driver assigned.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $route_name,
                    $date_range
                ),
                $dashboard_url
            );
        }

        if ('open' !== $status || !in_array($priority, array('high', 'urgent'), true)) {
            $this->close_alert_events($post_id, 'route-vacancy');
            return;
        }

        $message = sprintf(
            __('%1$s has an open %2$s route vacancy for %3$s. Assigned substitute driver: %4$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            $route_name,
            $this->get_label($priority, $this->priority_levels(), 'normal'),
            $date_range,
            $assigned_driver_id ? get_the_title($assigned_driver_id) : __('None', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)
        );

        $this->create_alert_event($post_id, 'route-vacancy', 'open', $priority, $date, $message);
        $this->queue_email_alerts(__('Route Vacancy Alert', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $message);
    }

    private function create_alert_event($source_id, $event_type, $status, $priority, $date, $message) {
        global $wpdb;

        $table = $wpdb->prefix . 'terricel_events';
        $now = current_time('mysql');
        $due_at = $date ? $date . ' 00:00:00' : null;

        $wpdb->delete(
            $table,
            array(
                'module'     => $this->id,
                'event_type' => $event_type,
                'source_id'  => absint($source_id),
            ),
            array('%s', '%s', '%d')
        );

        $wpdb->insert(
            $table,
            array(
                'module'       => $this->id,
                'event_type'   => $event_type,
                'status'       => $status,
                'priority'     => $priority,
                'source_id'    => absint($source_id),
                'due_at'       => $due_at,
                'details'      => $message,
                'created_at'   => $now,
                'updated_at'   => $now,
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }

    private function close_alert_events($source_id, $event_type) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'terricel_events',
            array(
                'status'     => 'resolved',
                'updated_at' => current_time('mysql'),
            ),
            array(
                'module'     => $this->id,
                'event_type' => $event_type,
                'source_id'  => absint($source_id),
            ),
            array('%s', '%s'),
            array('%s', '%s', '%d')
        );
    }

    private function queue_email_alerts($subject, $message) {
        $emails = $this->sanitize_email_list(get_option(self::ALERT_EMAILS_OPTION, ''));
        if (empty($emails)) {
            return;
        }

        $notifications = new Terricel_Logistics_Notifications();

        foreach ($emails as $email) {
            $notifications->queue_email($this->id, $email, $subject, $message);
        }
    }

    private function queue_substitute_assignment_notification($route_id, $date, $driver_id, $run_value = '') {
        $route_name = $route_id ? get_the_title($route_id) : __('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        $driver_name = $driver_id ? get_the_title($driver_id) : __('Substitute driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        $run_parts = $run_value ? $this->logistics()->parse_run_value($run_value) : null;
        $run_label = '';

        if ($run_parts && !empty($run_parts['run_key'])) {
            $run_names = self::get_standard_run_names();
            $run_name = isset($run_names[$run_parts['run_key']]) ? $run_names[$run_parts['run_key']] : $run_parts['run_key'];
            $run_label = sprintf(__(' Run: %s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $run_name);
        }

        terricel_logistics_queue_role_notification(
            $this->id,
            'sub_driver_assigned',
            __('Sub Driver Assigned to Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            sprintf(
                __('%1$s is assigned to cover %2$s on %3$s.%4$s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                $driver_name,
                $route_name,
                $this->format_date($date),
                $run_label
            ),
            admin_url('admin.php?page=terricel-transit-route-coverage')
        );

        foreach ($this->get_user_ids_for_driver($driver_id) as $user_id) {
            terricel_logistics_queue_user_notification(
                $this->id,
                $user_id,
                __('Substitute Assignment', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                sprintf(
                    __('You are assigned to cover %1$s on %2$s.%3$s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $route_name,
                    $this->format_date($date),
                    $run_label
                ),
                admin_url('admin.php?page=terricel-driver-dashboard'),
                'driver_schedule_change'
            );
        }
    }

    private function queue_schedule_affected_driver_notifications($old_state, $new_state, $notes = '') {
        $old_state = is_array($old_state) ? $old_state : array();
        $new_state = is_array($new_state) ? $new_state : array();

        if ($this->get_schedule_notification_state_key($old_state) === $this->get_schedule_notification_state_key($new_state)) {
            return;
        }

        $date = !empty($new_state['date']) ? $new_state['date'] : (isset($old_state['date']) ? $old_state['date'] : '');
        $route_id = !empty($new_state['route_id']) ? absint($new_state['route_id']) : (isset($old_state['route_id']) ? absint($old_state['route_id']) : 0);
        $route_name = $route_id ? get_the_title($route_id) : __('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        $reason = $this->get_schedule_notification_reason($notes);
        $driver_messages = $this->get_schedule_driver_change_messages($old_state, $new_state, $route_name, $date, $reason);
        $operations_notice = $this->get_operations_schedule_notice($old_state, $new_state, $route_name, $date, $reason);

        foreach ($driver_messages as $driver_id => $messages) {
            $messages = array_unique(array_filter(array_map('sanitize_textarea_field', (array) $messages)));
            if (empty($messages)) {
                continue;
            }
            $this->queue_driver_notification(
                $driver_id,
                __('Driver Schedule Change', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                implode("\n", $messages)
            );
        }

        $this->queue_operations_schedule_change_notification(
            $operations_notice['subject'],
            $operations_notice['message'],
            $operations_notice['dedupe_key']
        );
    }

    private function get_schedule_driver_change_messages($old_state, $new_state, $route_name, $date, $reason = '') {
        $messages = array();
        $date_label = $this->format_date($date);
        $route_run_label = __('All scheduled runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);

        foreach (array('driver_id', 'substitute_driver_id') as $field) {
            $old_driver_id = isset($old_state[$field]) ? absint($old_state[$field]) : 0;
            $new_driver_id = isset($new_state[$field]) ? absint($new_state[$field]) : 0;

            if ($old_driver_id === $new_driver_id) {
                continue;
            }

            if ($old_driver_id > 0) {
                $messages[$old_driver_id][] = $this->format_driver_schedule_assignment_message('unassigned', $route_name, $route_run_label, $date_label, $reason);
            }

            if ($new_driver_id > 0) {
                $messages[$new_driver_id][] = $this->format_driver_schedule_assignment_message('assigned', $route_name, $route_run_label, $date_label, $reason);
            }
        }

        $old_run_substitutes = isset($old_state['run_substitutes']) && is_array($old_state['run_substitutes']) ? $old_state['run_substitutes'] : array();
        $new_run_substitutes = isset($new_state['run_substitutes']) && is_array($new_state['run_substitutes']) ? $new_state['run_substitutes'] : array();
        $run_values = array_unique(array_merge(array_keys($old_run_substitutes), array_keys($new_run_substitutes)));

        foreach ($run_values as $run_value) {
            $run_value = sanitize_text_field($run_value);
            if (!$run_value) {
                continue;
            }

            $old_driver_id = $this->get_effective_schedule_run_driver_id($old_state, $run_value);
            $new_driver_id = $this->get_effective_schedule_run_driver_id($new_state, $run_value);

            if ($old_driver_id === $new_driver_id) {
                continue;
            }

            $run_label = $this->get_schedule_run_notification_label($run_value);
            if ($old_driver_id > 0) {
                $messages[$old_driver_id][] = $this->format_driver_schedule_assignment_message('unassigned', $route_name, $run_label, $date_label, $reason);
            }

            if ($new_driver_id > 0) {
                $messages[$new_driver_id][] = $this->format_driver_schedule_assignment_message('assigned', $route_name, $run_label, $date_label, $reason);
            }
        }

        return $messages;
    }

    private function get_effective_schedule_run_driver_id($state, $run_value) {
        $state = is_array($state) ? $state : array();
        $run_substitutes = isset($state['run_substitutes']) && is_array($state['run_substitutes']) ? $state['run_substitutes'] : array();

        if (array_key_exists($run_value, $run_substitutes)) {
            return absint($run_substitutes[$run_value]);
        }

        if (!empty($state['substitute_driver_id'])) {
            return absint($state['substitute_driver_id']);
        }

        return !empty($state['driver_id']) ? absint($state['driver_id']) : 0;
    }

    private function get_schedule_run_notification_label($run_value) {
        $parts = $this->logistics()->parse_run_value($run_value);
        if ($parts && !empty($parts['run_key'])) {
            $run_names = self::get_standard_run_names();
            if (!empty($run_names[$parts['run_key']])) {
                return $run_names[$parts['run_key']];
            }

            return sanitize_text_field($parts['run_key']);
        }

        return __('Scheduled run', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
    }

    private function format_driver_schedule_assignment_message($action, $route_name, $run_label, $date_label, $reason = '') {
        $message = sprintf(
            'assigned' === $action
                ? __('Driver was assigned to Route %1$s - %2$s - on %3$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)
                : __('Driver was unassigned from Route %1$s - %2$s - on %3$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            $route_name,
            $run_label,
            $date_label
        );

        return $this->append_schedule_change_reason($message, $reason);
    }

    private function append_schedule_change_reason($message, $reason) {
        $reason = $this->get_schedule_notification_reason($reason);
        if (!$reason) {
            return $message;
        }

        return sprintf(
            /* translators: 1: notification message. 2: schedule change reason. */
            __('%1$s Reason: %2$s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            $message,
            $reason
        );
    }

    private function get_schedule_notification_reason($reason) {
        $reason = trim(sanitize_text_field((string) $reason));
        if (!$reason) {
            return '';
        }

        $generated_reasons = array(
            __('Dispatch assignment update', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            __('Route vacancy', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            __('Scheduled', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            __('Covered', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            __('Unassigned', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'scheduled',
            'covered',
            'unassigned',
        );

        foreach ($generated_reasons as $generated_reason) {
            if (0 === strcasecmp($reason, trim((string) $generated_reason))) {
                return '';
            }
        }

        return $reason;
    }

    private function queue_vacancy_affected_driver_notifications($old_state, $new_state, $notes = '') {
        $old_state = is_array($old_state) ? $old_state : array();
        $new_state = is_array($new_state) ? $new_state : array();

        if ($this->get_vacancy_notification_state_key($old_state) === $this->get_vacancy_notification_state_key($new_state)) {
            return;
        }

        $date = !empty($new_state['date']) ? $new_state['date'] : (isset($old_state['date']) ? $old_state['date'] : '');
        $end_date = !empty($new_state['end_date']) ? $new_state['end_date'] : (isset($old_state['end_date']) ? $old_state['end_date'] : '');
        $route_id = !empty($new_state['route_id']) ? absint($new_state['route_id']) : (isset($old_state['route_id']) ? absint($old_state['route_id']) : 0);
        $route_name = $route_id ? get_the_title($route_id) : __('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        $date_range = $this->format_date_range($date, $end_date);
        $reason = $this->get_schedule_notification_reason($notes);
        $regular_driver_ids = array(
            isset($old_state['driver_id']) ? absint($old_state['driver_id']) : 0,
            isset($new_state['driver_id']) ? absint($new_state['driver_id']) : 0,
        );

        if ($route_id > 0) {
            $regular_driver_ids[] = (int) get_post_meta($route_id, '_terricel_route_default_driver_id', true);
        }

        foreach (array_unique(array_filter(array_map('absint', $regular_driver_ids))) as $driver_id) {
            $this->queue_driver_notification(
                $driver_id,
                __('Driver Schedule Change', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                $this->format_driver_schedule_assignment_message(
                    'unassigned',
                    $route_name,
                    __('All scheduled runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $date_range,
                    $reason
                )
            );
        }

        $substitute_driver_ids = array(
            isset($old_state['assigned_driver_id']) ? absint($old_state['assigned_driver_id']) : 0,
            isset($new_state['assigned_driver_id']) ? absint($new_state['assigned_driver_id']) : 0,
        );

        foreach (array_unique(array_filter(array_map('absint', $substitute_driver_ids))) as $driver_id) {
            $assigned_now = isset($new_state['assigned_driver_id']) && absint($new_state['assigned_driver_id']) === $driver_id;
            $subject = $assigned_now ? __('Substitute Assignment', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) : __('Driver Schedule Change', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
            $message = $assigned_now
                ? $this->format_driver_schedule_assignment_message(
                    'assigned',
                    $route_name,
                    __('All scheduled runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $date_range,
                    $reason
                )
                : $this->format_driver_schedule_assignment_message(
                    'unassigned',
                    $route_name,
                    __('All scheduled runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $date_range,
                    $reason
                );

            $this->queue_driver_notification($driver_id, $subject, $message);
        }

        $this->queue_operations_schedule_change_notification(
            __('Route Vacancy Schedule Change', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            $this->append_schedule_change_reason(
                sprintf(
                    __('%1$s changed for %2$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $route_name,
                    $date_range
                ),
                $reason
            ),
            $this->get_operations_dedupe_key('vacancy', $route_id, $date_range, $reason)
        );
    }

    private function queue_driver_notification($driver_id, $subject, $message) {
        foreach ($this->get_user_ids_for_driver($driver_id) as $user_id) {
            terricel_logistics_queue_user_notification(
                $this->id,
                $user_id,
                $subject,
                $message,
                admin_url('admin.php?page=terricel-driver-dashboard'),
                'driver_schedule_change'
            );
        }
    }

    private function queue_operations_schedule_change_notification($subject, $message, $dedupe_key = '') {
        if (!function_exists('terricel_logistics_queue_user_notification')) {
            return;
        }

        $dedupe_key = $dedupe_key ? sanitize_key($dedupe_key) : $this->get_operations_dedupe_key($subject, $message);
        if ($this->operations_notification_was_recently_queued($dedupe_key)) {
            return;
        }

        $this->mark_operations_notification_queued($dedupe_key);

        foreach ($this->get_operations_notification_user_ids() as $user_id) {
            terricel_logistics_queue_user_notification(
                $this->id,
                $user_id,
                $subject,
                $message,
                admin_url('admin.php?page=terricel-transit-route-coverage'),
                'operations_schedule_change'
            );
        }
    }

    private function get_operations_schedule_notice($old_state, $new_state, $route_name, $date, $reason = '') {
        $route_id = !empty($new_state['route_id']) ? absint($new_state['route_id']) : (isset($old_state['route_id']) ? absint($old_state['route_id']) : 0);
        $bulk_notice = $this->get_bulk_schedule_change_operations_notice($route_id, $date, $reason);
        if (!empty($bulk_notice)) {
            return $bulk_notice;
        }

        if ($this->is_bulk_schedule_change_reason($reason)) {
            return array(
                'subject'    => __('Schedule Change', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'message'    => $this->append_schedule_change_reason(
                    sprintf(
                        __('Schedule change for %s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                        $this->format_date($date)
                    ),
                    $reason
                ),
                'dedupe_key' => $this->get_operations_dedupe_key('bulk-reason-schedule-change', $date, $reason),
            );
        }

        $driver_change = $this->get_primary_operations_driver_change($old_state, $new_state);
        if (!empty($driver_change)) {
            $run_label = !empty($driver_change['run_label']) ? $driver_change['run_label'] : __('All scheduled runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
            $message = $this->append_schedule_change_reason(
                $this->format_operations_driver_schedule_assignment_message($driver_change['action'], $route_name, $run_label, $this->format_date($date)),
                $reason
            );

            return array(
                'subject'    => __('Route Schedule Change', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'message'    => $message,
                'dedupe_key' => $this->get_operations_dedupe_key('route-driver-change', $route_id, $date, $driver_change['action'], $run_label, $reason),
            );
        }

        return array(
            'subject'    => __('Route Schedule Change', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'message'    => $this->append_schedule_change_reason(
                sprintf(
                    __('Route %1$s changed for %2$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $route_name,
                    $this->format_date($date)
                ),
                $reason
            ),
            'dedupe_key' => $this->get_operations_dedupe_key('route-change', $route_id, $date, $reason),
        );
    }

    private function get_bulk_schedule_change_operations_notice($route_id, $date, $reason = '') {
        $route_id = absint($route_id);
        if ($route_id < 1 || !$date || !method_exists($this->logistics(), 'get_route_schedule_changes_for_date')) {
            return array();
        }

        $changes = $this->logistics()->get_route_schedule_changes_for_date($route_id, $date);
        if (empty($changes) || !is_array($changes)) {
            return array();
        }

        $location = $this->get_route_location_labels($route_id);
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            $type = isset($change['type']) ? sanitize_key($change['type']) : '';
            $note = isset($change['note']) ? $this->get_schedule_notification_reason($change['note']) : '';
            if (!in_array($type, array('closure', 'delay', 'half_day'), true)) {
                continue;
            }

            if ($reason && $note && 0 !== strcasecmp($reason, $note)) {
                continue;
            }

            $label = $this->get_schedule_change_label($change);
            $message = sprintf(
                __('%1$s for %2$s / %3$s on %4$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                $label,
                $location['districts'],
                $location['schools'],
                $this->format_date($date)
            );
            $message = $this->append_schedule_change_reason($message, $note ? $note : $reason);

            return array(
                'subject'    => __('School Schedule Change', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'message'    => $message,
                'dedupe_key' => $this->get_operations_dedupe_key('bulk-schedule-change', $date, $location['districts'], $location['schools'], $type, $label, $note ? $note : $reason),
            );
        }

        return array();
    }

    private function is_bulk_schedule_change_reason($reason) {
        $reason = strtolower($this->get_schedule_notification_reason($reason));
        if (!$reason) {
            return false;
        }

        foreach (array('snow', 'closure', 'closed', 'delay', 'early dismissal', 'half day', 'no school', 'district', 'school') as $keyword) {
            if (false !== strpos($reason, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function get_primary_operations_driver_change($old_state, $new_state) {
        foreach (array('driver_id', 'substitute_driver_id') as $field) {
            $old_driver_id = isset($old_state[$field]) ? absint($old_state[$field]) : 0;
            $new_driver_id = isset($new_state[$field]) ? absint($new_state[$field]) : 0;

            if ($old_driver_id === $new_driver_id) {
                continue;
            }

            if ($new_driver_id > 0) {
                return array(
                    'action'    => 'assigned',
                    'run_label' => __('All scheduled runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                );
            }

            if ($old_driver_id > 0) {
                return array(
                    'action'    => 'unassigned',
                    'run_label' => __('All scheduled runs', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                );
            }
        }

        $old_run_substitutes = isset($old_state['run_substitutes']) && is_array($old_state['run_substitutes']) ? $old_state['run_substitutes'] : array();
        $new_run_substitutes = isset($new_state['run_substitutes']) && is_array($new_state['run_substitutes']) ? $new_state['run_substitutes'] : array();
        $run_values = array_unique(array_merge(array_keys($old_run_substitutes), array_keys($new_run_substitutes)));

        foreach ($run_values as $run_value) {
            $run_value = sanitize_text_field($run_value);
            if (!$run_value) {
                continue;
            }

            $old_driver_id = $this->get_effective_schedule_run_driver_id($old_state, $run_value);
            $new_driver_id = $this->get_effective_schedule_run_driver_id($new_state, $run_value);
            if ($old_driver_id === $new_driver_id) {
                continue;
            }

            return array(
                'action'    => $new_driver_id > 0 ? 'assigned' : 'unassigned',
                'run_label' => $this->get_schedule_run_notification_label($run_value),
            );
        }

        return array();
    }

    private function format_operations_driver_schedule_assignment_message($action, $route_name, $run_label, $date_label) {
        return sprintf(
            'assigned' === $action
                ? __('Driver was assigned to Route %1$s - %2$s - on %3$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)
                : __('Driver was unassigned from Route %1$s - %2$s - on %3$s.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            $route_name,
            $run_label,
            $date_label
        );
    }

    private function get_operations_dedupe_key() {
        $parts = array_map('strval', func_get_args());
        $parts = array_map('sanitize_text_field', $parts);

        return 'terricel_ops_notice_' . md5(implode('|', $parts));
    }

    private function operations_notification_was_recently_queued($dedupe_key) {
        return (bool) get_transient($dedupe_key);
    }

    private function mark_operations_notification_queued($dedupe_key) {
        set_transient($dedupe_key, 1, 5 * MINUTE_IN_SECONDS);
    }

    private function get_operations_notification_user_ids() {
        $user_ids = array();
        $role_users = get_users(
            array(
                'fields'   => array('ID'),
                'role__in' => array('administrator', 'terricel_admin', 'terricel_dispatcher'),
            )
        );

        foreach ($role_users as $user) {
            $user_ids[] = absint($user->ID);
        }

        foreach (get_users(array('fields' => array('ID'))) as $user) {
            if (user_can($user->ID, 'terricel_manage_routes')) {
                $user_ids[] = absint($user->ID);
            }
        }

        if (is_user_logged_in() && current_user_can('terricel_manage_routes')) {
            $user_ids[] = get_current_user_id();
        }

        return array_values(array_filter(array_unique(array_map('absint', $user_ids))));
    }

    private function get_schedule_state_driver_ids($state) {
        $state = is_array($state) ? $state : array();
        $driver_ids = array();

        if (!empty($state['driver_id'])) {
            $driver_ids[] = absint($state['driver_id']);
        }

        if (!empty($state['substitute_driver_id'])) {
            $driver_ids[] = absint($state['substitute_driver_id']);
        }

        foreach ((array) (isset($state['run_substitutes']) ? $state['run_substitutes'] : array()) as $driver_id) {
            if (absint($driver_id) > 0) {
                $driver_ids[] = absint($driver_id);
            }
        }

        return array_values(array_unique(array_filter($driver_ids)));
    }

    private function get_schedule_notification_state_key($state) {
        $state = is_array($state) ? $state : array();
        $run_substitutes = isset($state['run_substitutes']) && is_array($state['run_substitutes']) ? $state['run_substitutes'] : array();
        $normalized_runs = array();

        foreach ($run_substitutes as $run_value => $driver_id) {
            $normalized_runs[sanitize_text_field($run_value)] = absint($driver_id);
        }

        ksort($normalized_runs, SORT_STRING);

        return wp_json_encode(
            array(
                'date'            => isset($state['date']) ? sanitize_text_field($state['date']) : '',
                'route_id'        => isset($state['route_id']) ? absint($state['route_id']) : 0,
                'driver_id'       => isset($state['driver_id']) ? absint($state['driver_id']) : 0,
                'substitute_id'   => isset($state['substitute_driver_id']) ? absint($state['substitute_driver_id']) : 0,
                'run_substitutes' => $normalized_runs,
                'status'          => isset($state['status']) ? sanitize_key($state['status']) : '',
            )
        );
    }

    private function get_vacancy_notification_state_key($state) {
        $state = is_array($state) ? $state : array();

        return wp_json_encode(
            array(
                'date'               => isset($state['date']) ? sanitize_text_field($state['date']) : '',
                'end_date'           => isset($state['end_date']) ? sanitize_text_field($state['end_date']) : '',
                'route_id'           => isset($state['route_id']) ? absint($state['route_id']) : 0,
                'driver_id'          => isset($state['driver_id']) ? absint($state['driver_id']) : 0,
                'assigned_driver_id' => isset($state['assigned_driver_id']) ? absint($state['assigned_driver_id']) : 0,
                'status'             => isset($state['status']) ? sanitize_key($state['status']) : '',
            )
        );
    }

    private function get_user_ids_for_driver($driver_id) {
        $driver_id = absint($driver_id);
        $users = get_users(
            array(
                'fields'     => array('ID'),
                'meta_key'   => '_terricel_linked_driver_id',
                'meta_value' => $driver_id,
            )
        );
        $user_ids = array();

        foreach ($users as $user) {
            $user_ids[] = absint($user->ID);
        }

        $linked_user_id = (int) get_post_meta($driver_id, '_terricel_driver_user_id', true);
        if ($linked_user_id > 0) {
            $user_ids[] = $linked_user_id;
        }

        return array_values(array_filter(array_unique($user_ids)));
    }

    private function can_save($post_id, $nonce_name, $nonce_action) {
        if ($this->updating_title || !isset($_POST[$nonce_name]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), $nonce_action)) {
            return false;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        return current_user_can('edit_post', $post_id);
    }

    private function update_generated_title($post_id, $title) {
        if (!$title) {
            return;
        }

        $this->updating_title = true;
        wp_update_post(
            array(
                'ID'         => $post_id,
                'post_title' => $title,
                'post_name'  => sanitize_title($title),
            )
        );
        $this->updating_title = false;
    }

    private function build_schedule_title($date, $route_id) {
        return trim(($date ? $date : current_time('Y-m-d')) . ' - ' . ($route_id ? get_the_title($route_id) : __('Route Schedule', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)));
    }

    private function build_availability_title($date, $driver_id) {
        return trim(($date ? $date : current_time('Y-m-d')) . ' - ' . ($driver_id ? get_the_title($driver_id) : __('Driver Availability', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)));
    }

    private function build_vacancy_title($date, $driver_id) {
        return trim(($date ? $date : current_time('Y-m-d')) . ' - ' . ($driver_id ? get_the_title($driver_id) : __('Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . ' Vacancy');
    }

    private function save_meta_value($post_id, $meta_key, $value) {
        if ('' === $value || 0 === $value) {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        update_post_meta($post_id, $meta_key, $value);
    }

    private function logistics() {
        return terricel_logistics();
    }

    private function save_today_substitute_schedule($date, $route_id, $substitute_driver_id, $run_substitutes = array()) {
        $schedule = $this->get_schedule_for_route_date($route_id, $date);
        $regular_driver_id = (int) get_post_meta($route_id, '_terricel_route_default_driver_id', true);
        $assigned_driver_id = $schedule ? (int) get_post_meta($schedule->ID, '_terricel_coverage_driver_id', true) : $regular_driver_id;
        $old_run_substitutes = $schedule ? get_post_meta($schedule->ID, '_terricel_coverage_run_substitutes', true) : array();
        $old_run_substitutes = is_array($old_run_substitutes) ? $old_run_substitutes : array();
        $old_state = array(
            'date'                 => $schedule ? get_post_meta($schedule->ID, '_terricel_coverage_date', true) : $date,
            'route_id'             => $schedule ? (int) get_post_meta($schedule->ID, '_terricel_coverage_route_id', true) : $route_id,
            'driver_id'            => $assigned_driver_id,
            'substitute_driver_id' => $schedule ? (int) get_post_meta($schedule->ID, '_terricel_coverage_substitute_driver_id', true) : 0,
            'run_substitutes'      => $old_run_substitutes,
            'status'               => $schedule ? get_post_meta($schedule->ID, '_terricel_coverage_status', true) : ($assigned_driver_id > 0 ? 'scheduled' : 'unassigned'),
        );
        $sanitized_run_substitutes = $this->sanitize_today_run_substitutes($run_substitutes);

        if (!$assigned_driver_id && $regular_driver_id) {
            $assigned_driver_id = $regular_driver_id;
        }

        if (!$schedule && $substitute_driver_id < 1 && empty($sanitized_run_substitutes)) {
            return;
        }

        if (!$schedule) {
            $schedule_id = wp_insert_post(
                array(
                    'post_type'   => self::SCHEDULE_POST_TYPE,
                    'post_status' => 'publish',
                    'post_title'  => $this->build_schedule_title($date, $route_id),
                    'post_name'   => sanitize_title($this->build_schedule_title($date, $route_id)),
                )
            );

            if (is_wp_error($schedule_id) || $schedule_id < 1) {
                return;
            }
        } else {
            $schedule_id = $schedule->ID;
        }

        $status = ($substitute_driver_id > 0 || $this->has_any_run_substitute($sanitized_run_substitutes)) ? 'covered' : ($assigned_driver_id > 0 ? 'scheduled' : 'unassigned');

        $this->save_meta_value($schedule_id, '_terricel_coverage_date', $date);
        $this->save_meta_value($schedule_id, '_terricel_coverage_route_id', $route_id);
        $this->save_meta_value($schedule_id, '_terricel_coverage_driver_id', $assigned_driver_id);
        $this->save_meta_value($schedule_id, '_terricel_coverage_substitute_driver_id', $substitute_driver_id);
        update_post_meta($schedule_id, '_terricel_coverage_run_substitutes', $sanitized_run_substitutes);
        update_post_meta($schedule_id, '_terricel_coverage_status', $status);
        $this->update_generated_title($schedule_id, $this->build_schedule_title($date, $route_id));
        $this->sync_schedule_alert($schedule_id, $date, $route_id, $status, $assigned_driver_id, $substitute_driver_id);
        $this->queue_schedule_affected_driver_notifications(
            $old_state,
            array(
                'date'                 => $date,
                'route_id'             => $route_id,
                'driver_id'            => $assigned_driver_id,
                'substitute_driver_id' => $substitute_driver_id,
                'run_substitutes'      => $sanitized_run_substitutes,
                'status'               => $status,
            ),
            __('Dispatch assignment update', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)
        );
    }

    private function sanitize_today_run_substitutes($run_substitutes) {
        $sanitized = array();

        foreach ($run_substitutes as $run_value => $driver_id) {
            $run_value = sanitize_text_field($run_value);
            $driver_id = absint($driver_id);

            if (!$run_value) {
                continue;
            }

            $sanitized[$run_value] = $driver_id;
        }

        return $sanitized;
    }

    private function has_any_run_substitute($run_substitutes) {
        foreach ($run_substitutes as $driver_id) {
            if (absint($driver_id) > 0) {
                return true;
            }
        }

        return false;
    }

    private function has_uncovered_run_substitute($run_substitutes) {
        foreach ($run_substitutes as $driver_id) {
            if (absint($driver_id) < 1) {
                return true;
            }
        }

        return false;
    }

    private function get_schedule_for_route_date($route_id, $date) {
        $schedules = get_posts(
            array(
                'post_type'      => self::SCHEDULE_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'ID',
                'order'          => 'DESC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_terricel_coverage_route_id',
                        'value' => $route_id,
                    ),
                    array(
                        'key'   => '_terricel_coverage_date',
                        'value' => $date,
                    ),
                ),
            )
        );

        return empty($schedules) ? null : $schedules[0];
    }

    private function sanitize_post_id($field) {
        return isset($_POST[$field]) ? absint($_POST[$field]) : 0;
    }

    private function sanitize_date_field($field) {
        $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
        return $this->sanitize_date_value($value);
    }

    private function sanitize_date_value($value) {
        if (!$value) {
            return '';
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function sanitize_time_value($value) {
        $value = sanitize_text_field((string) $value);

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
    }

    private function get_default_run_end_time($start_time) {
        return $this->logistics()->get_default_run_end_time($start_time);
    }

    private function sanitize_route_regular_schedule_from_request() {
        $days = $this->regular_schedule_days();
        $raw_schedule = isset($_POST['terricel_route_regular_schedule']) && is_array($_POST['terricel_route_regular_schedule']) ? wp_unslash($_POST['terricel_route_regular_schedule']) : array();
        $schedule = array();

        foreach ($days as $day_key => $day_label) {
            $schedule[$day_key] = array();
            $raw_runs = isset($raw_schedule[$day_key]) && is_array($raw_schedule[$day_key]) ? $raw_schedule[$day_key] : array();

            foreach ($raw_runs as $raw_run) {
                if (!empty($raw_run['remove'])) {
                    continue;
                }

                $run_key = isset($raw_run['run_key']) ? sanitize_key($raw_run['run_key']) : '';

                if (!$run_key && isset($raw_run['run_name'])) {
                    $run_key = $this->find_run_key_by_name(sanitize_text_field($raw_run['run_name']));
                }

                $run_name = $this->get_run_name_label($run_key);
                if (!$run_name && isset($raw_run['run_name'])) {
                    $run_name = sanitize_text_field($raw_run['run_name']);
                }
                $start_time = isset($raw_run['start_time']) ? $this->sanitize_time_value($raw_run['start_time']) : '';
                $end_time = isset($raw_run['end_time']) ? $this->sanitize_time_value($raw_run['end_time']) : '';
                $end_time = $end_time ? $end_time : $this->get_default_run_end_time($start_time);

                if (!$run_name || !$start_time) {
                    continue;
                }

                $schedule[$day_key][] = array(
                    'run_key'    => $run_key,
                    'run_name'   => $run_name,
                    'start_time' => $start_time,
                    'end_time'   => $end_time,
                );
            }
        }

        return $schedule;
    }

    private function sort_route_regular_schedule($schedule) {
        foreach ($this->regular_schedule_days() as $day_key => $day_label) {
            if (!isset($schedule[$day_key]) || !is_array($schedule[$day_key])) {
                $schedule[$day_key] = array();
                continue;
            }

            usort(
                $schedule[$day_key],
                function ($first, $second) {
                    $time_compare = strcmp($first['start_time'], $second['start_time']);

                    if (0 !== $time_compare) {
                        return $time_compare;
                    }

                    return strnatcasecmp($first['run_name'], $second['run_name']);
                }
            );
        }

        return $schedule;
    }

    private function sanitize_status($field, $allowed, $default) {
        $status = isset($_POST[$field]) ? sanitize_key(wp_unslash($_POST[$field])) : $default;
        return isset($allowed[$status]) ? $status : $default;
    }

    private function sanitize_email_list($raw) {
        $lines = preg_split('/[\r\n,]+/', (string) $raw);
        $emails = array();

        foreach ($lines as $line) {
            $email = sanitize_email(trim($line));
            if ($email && is_email($email)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private function append_meta_filter(&$meta_query, $meta_key, $request_key) {
        if (!isset($_GET[$request_key]) || '' === $_GET[$request_key]) {
            return;
        }

        $meta_query[] = array(
            'key'   => $meta_key,
            'value' => sanitize_text_field(wp_unslash($_GET[$request_key])),
        );
    }

    private function render_date_field($name, $label, $value) {
        echo '<p><label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label></p>';
        echo '<p><input type="date" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text"></p>';
    }

    private function render_post_select($name, $label, $post_type, $selected, $empty_label) {
        echo '<p><label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label></p>';
        echo '<p><select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" class="widefat">';
        echo '<option value="0">' . esc_html($empty_label) . '</option>';

        foreach ($this->get_select_posts($post_type) as $post) {
            echo '<option value="' . esc_attr($post->ID) . '"' . selected($selected, $post->ID, false) . '>' . esc_html(get_the_title($post)) . '</option>';
        }

        echo '</select></p>';
    }

    private function render_schedule_route_select($selected_route_id) {
        echo '<p><label for="terricel_coverage_route_id"><strong>' . esc_html__('Route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</strong></label></p>';
        echo '<p><select id="terricel_coverage_route_id" name="terricel_coverage_route_id" class="widefat">';
        echo '<option value="0">' . esc_html__('Select a route', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</option>';

        foreach ($this->get_parent_routes() as $route) {
            $default_driver_id = (int) get_post_meta($route->ID, '_terricel_route_default_driver_id', true);
            echo '<option value="' . esc_attr($route->ID) . '" data-default-driver-id="' . esc_attr($default_driver_id) . '"' . selected($selected_route_id, $route->ID, false) . '>' . esc_html(get_the_title($route)) . '</option>';
        }

        echo '</select></p>';
    }

    private function render_schedule_driver_select($selected_driver_id) {
        echo '<p><label for="terricel_coverage_driver_id"><strong>' . esc_html__('Assigned Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</strong></label></p>';
        echo '<p><select id="terricel_coverage_driver_id" name="terricel_coverage_driver_id" class="widefat">';
        echo '<option value="0">' . esc_html__('No assigned driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</option>';

        foreach ($this->get_active_drivers() as $driver) {
            echo '<option value="' . esc_attr($driver->ID) . '"' . selected($selected_driver_id, $driver->ID, false) . '>' . esc_html(get_the_title($driver)) . '</option>';
        }

        echo '</select></p>';
        echo '<p class="description">' . esc_html__('This defaults from the selected route for the selected date. Choose no assigned driver to unassign the route for this date.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
    }

    private function render_schedule_editor_script($details) {
        $details = is_array($details) ? $details : array();

        echo '<script>';
        echo '(function(){';
        echo 'var dateInput=document.getElementById("terricel_coverage_date");';
        echo 'var routeSelect=document.getElementById("terricel_coverage_route_id");';
        echo 'var driverSelect=document.getElementById("terricel_coverage_driver_id");';
        echo 'var runList=document.getElementById("terricel-coverage-run-list");';
        echo 'var initialDetails=' . wp_json_encode($details) . ';';
        echo 'var nonce="' . esc_js(wp_create_nonce('terricel_route_coverage_schedule_details')) . '";';
        echo 'function optionNode(value,label,selected){var option=document.createElement("option");option.value=String(value);option.textContent=label;if(selected){option.selected=true;}return option;}';
        echo 'function renderRuns(runs){if(!runList){return;}runList.innerHTML="";runs=runs||[];var heading=document.createElement("p");heading.innerHTML="<strong>' . esc_js(__('Runs Needing Coverage', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</strong>";runList.appendChild(heading);if(!runs.length){var empty=document.createElement("p");empty.className="description";empty.textContent="' . esc_js(__('Select a route and date to choose individual runs that need coverage.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '";runList.appendChild(empty);return;}var table=document.createElement("table");table.className="widefat striped";table.innerHTML="<thead><tr><th>' . esc_js(__('Cover', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</th><th>' . esc_js(__('Run', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</th><th>' . esc_js(__('Time', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</th><th>' . esc_js(__('Covered By', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '</th></tr></thead>";var body=document.createElement("tbody");runs.forEach(function(run){var row=document.createElement("tr");var cover=document.createElement("input");cover.type="checkbox";cover.name="terricel_coverage_run_selected["+run.value+"]";cover.value="1";cover.checked=!!run.selected;var select=document.createElement("select");select.name="terricel_coverage_run_substitute_driver_id["+run.value+"]";select.style.minWidth="180px";select.appendChild(optionNode("0","' . esc_js(__('No substitute driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '",!run.substitute_driver_id));(run.substitute_options||[]).forEach(function(option){select.appendChild(optionNode(option.id,option.name,String(option.id)===String(run.substitute_driver_id||0)));});select.disabled=!cover.checked;cover.addEventListener("change",function(){select.disabled=!cover.checked;});var coverCell=document.createElement("td");coverCell.appendChild(cover);var runCell=document.createElement("td");runCell.textContent=run.run_name||"";var timeCell=document.createElement("td");timeCell.textContent=run.time_label||"";var byCell=document.createElement("td");byCell.appendChild(select);row.appendChild(coverCell);row.appendChild(runCell);row.appendChild(timeCell);row.appendChild(byCell);body.appendChild(row);});table.appendChild(body);runList.appendChild(table);var note=document.createElement("p");note.className="description";note.textContent="' . esc_js(__('Checked runs are removed from the regular driver for this date; choose a substitute now or leave blank for Dispatch Dashboard assignment.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)) . '";runList.appendChild(note);}';
        echo 'function applyDetails(details,forceDriver){details=details||{};if(driverSelect&&forceDriver){driverSelect.value=String(details.default_driver_id||"0");}renderRuns(details.runs||[]);}';
        echo 'function loadDetails(forceDriver){if(!dateInput||!routeSelect){return;}var data=new FormData();data.append("action","terricel_route_coverage_schedule_details");data.append("nonce",nonce);data.append("date",dateInput.value||"");data.append("route_id",routeSelect.value||"0");fetch(ajaxurl,{method:"POST",credentials:"same-origin",body:data}).then(function(response){return response.json();}).then(function(response){if(response&&response.success){applyDetails(response.data,forceDriver);}});}';
        echo 'if(initialDetails){applyDetails(initialDetails,false);}';
        echo 'if(routeSelect){routeSelect.addEventListener("change",function(){loadDetails(true);});}';
        echo 'if(dateInput){dateInput.addEventListener("change",function(){loadDetails(true);});}';
        echo '}());';
        echo '</script>';
    }

    private function render_vacancy_substitute_driver_select($selected_driver_id, $runs) {
        $options = $this->get_eligible_vacancy_substitute_options($runs, $selected_driver_id);

        echo '<p><label for="terricel_vacancy_assigned_driver_id"><strong>' . esc_html__('Assigned Substitute Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</strong></label></p>';
        echo '<p><select id="terricel_vacancy_assigned_driver_id" name="terricel_vacancy_assigned_driver_id" class="widefat">';
        echo '<option value="0">' . esc_html__('No driver assigned yet', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</option>';

        foreach ($options as $driver_id => $driver_name) {
            echo '<option value="' . esc_attr($driver_id) . '"' . selected($selected_driver_id, $driver_id, false) . '>' . esc_html($driver_name) . '</option>';
        }

        echo '</select></p>';
        echo '<p class="description">' . esc_html__('Only drivers eligible for every selected vacant run are listed.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</p>';
    }

    private function render_select_field($name, $label, $options, $selected) {
        echo '<p><label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label></p>';
        echo '<p><select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" class="widefat">';
        foreach ($options as $value => $label_text) {
            echo '<option value="' . esc_attr($value) . '"' . selected($selected, $value, false) . '>' . esc_html($label_text) . '</option>';
        }
        echo '</select></p>';
    }

    private function render_checkbox_field($name, $label, $checked) {
        echo '<p><label><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked($checked, true, false) . '> ' . esc_html($label) . '</label></p>';
    }

    private function render_textarea_field($name, $label, $value) {
        echo '<p><label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label></p>';
        echo '<p><textarea id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" rows="4" class="widefat">' . esc_textarea($value) . '</textarea></p>';
    }

    private function get_substitute_driver_select_markup($route_id, $selected_driver_id, $substitute_drivers) {
        $field_id = 'terricel_route_substitute_driver_id_' . absint($route_id);
        $selected_driver_id = absint($selected_driver_id);

        if ($selected_driver_id > 0 && !isset($substitute_drivers[$selected_driver_id]) && 'publish' === get_post_status($selected_driver_id)) {
            $substitute_drivers[$selected_driver_id] = get_the_title($selected_driver_id);
        }

        $markup = '<label class="screen-reader-text" for="' . esc_attr($field_id) . '">' . esc_html__('Substitute Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</label>';
        $markup .= '<select id="' . esc_attr($field_id) . '" name="terricel_route_substitute_driver_id[' . esc_attr($route_id) . ']" style="min-width:180px;">';
        $markup .= '<option value="0">' . esc_html__('No substitute driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</option>';

        foreach ($substitute_drivers as $driver_id => $driver_name) {
            $markup .= '<option value="' . esc_attr($driver_id) . '"' . selected($selected_driver_id, $driver_id, false) . '>' . esc_html($driver_name) . '</option>';
        }

        $markup .= '</select>';

        return $markup;
    }

    private function get_run_any_driver_toggle_markup($route_id, $run_value) {
        $field_id = 'terricel_route_run_add_any_driver_' . absint($route_id) . '_' . md5($run_value);

        return '<label for="' . esc_attr($field_id) . '"><input type="checkbox" class="terricel-run-any-driver-toggle" id="' . esc_attr($field_id) . '" name="terricel_route_run_allow_any_driver[' . esc_attr($route_id) . '][' . esc_attr($run_value) . ']" value="1"> ' . esc_html__('Add Any Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</label>';
    }

    private function get_run_substitute_driver_select_markup($route_id, $run_value, $selected_driver_id, $substitute_drivers) {
        $field_id = 'terricel_route_run_substitute_driver_id_' . absint($route_id) . '_' . md5($run_value);
        $selected_driver_id = absint($selected_driver_id);
        $all_driver_options = $this->get_all_active_driver_options($selected_driver_id);

        if ($selected_driver_id > 0 && !isset($substitute_drivers[$selected_driver_id]) && 'publish' === get_post_status($selected_driver_id)) {
            $substitute_drivers[$selected_driver_id] = get_the_title($selected_driver_id);
        }

        $markup = '<label class="screen-reader-text" for="' . esc_attr($field_id) . '">' . esc_html__('Substitute Driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</label>';
        $markup .= '<select class="terricel-run-substitute-select" id="' . esc_attr($field_id) . '" name="terricel_route_run_substitute_driver_id[' . esc_attr($route_id) . '][' . esc_attr($run_value) . ']" data-route-id="' . esc_attr($route_id) . '" data-run-value="' . esc_attr($run_value) . '" data-available-options="' . esc_attr(wp_json_encode($substitute_drivers)) . '" data-all-options="' . esc_attr(wp_json_encode($all_driver_options)) . '" style="min-width:180px;">';
        $markup .= '<option value="0">' . esc_html__('No substitute driver', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</option>';

        foreach ($substitute_drivers as $driver_id => $driver_name) {
            $markup .= '<option value="' . esc_attr($driver_id) . '"' . selected($selected_driver_id, $driver_id, false) . '>' . esc_html($driver_name) . '</option>';
        }

        $markup .= '</select>';
        $markup .= ' <span class="terricel-run-substitute-save-status" aria-live="polite" style="margin-left:8px;"></span>';

        return $markup;
    }

    private function get_run_name_select_markup($name, $selected, $run_names, $class = '', $id = '') {
        $id = $id ? $id : sanitize_key(str_replace(array('[', ']'), '_', $name));
        $selected = (string) $selected;
        $class_attribute = $class ? ' class="' . esc_attr($class) . '"' : '';
        $markup = '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . $class_attribute . '>';
        $markup .= '<option value="">' . esc_html__('Select a run name', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</option>';

        foreach ($run_names as $run_key => $run_name) {
            $markup .= '<option value="' . esc_attr($run_key) . '"' . selected($selected, $run_key, false) . '>' . esc_html($run_name) . '</option>';
        }

        $markup .= '</select>';

        return $markup;
    }

    private function render_date_filter($name, $selected, $label) {
        echo '<label class="screen-reader-text" for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
        echo '<input type="date" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($selected) . '"> ';
    }

    private function render_filter_select($name, $options, $selected, $empty_label) {
        echo '<label class="screen-reader-text" for="' . esc_attr($name) . '">' . esc_html($empty_label) . '</label>';
        echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">';
        echo '<option value="">' . esc_html($empty_label) . '</option>';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
    }

    private function render_stat_card($label, $value) {
        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:14px;">';
        echo '<div style="font-size:22px;font-weight:600;">' . esc_html((string) $value) . '</div>';
        echo '<div>' . esc_html($label) . '</div>';
        echo '</div>';
    }

    private function render_linked_post($post_id) {
        echo wp_kses_post($this->get_linked_post_markup($post_id));
    }

    private function get_linked_post_markup($post_id) {
        if ($post_id < 1 || 'publish' !== get_post_status($post_id)) {
            return esc_html__('None', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        }

        return '<a href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>';
    }

    private function insert_columns($columns, $insert) {
        $date = isset($columns['date']) ? $columns['date'] : null;
        unset($columns['date']);

        foreach ($insert as $key => $label) {
            $columns[$key] = $label;
        }

        if ($date) {
            $columns['date'] = $date;
        }

        return $columns;
    }

    private function get_select_posts($post_type) {
        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if (Terricel_Logistics_Shared_Data::DRIVER_POST_TYPE === $post_type) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_terricel_driver_status',
                    'value'   => 'terminated',
                    'compare' => '!=',
                ),
                array(
                    'key'     => '_terricel_driver_status',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        return get_posts($args);
    }

    private function get_parent_routes() {
        return get_posts(
            array(
                'post_type'      => Terricel_Logistics_Shared_Data::ROUTE_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 500,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
    }

    private function get_active_drivers() {
        return $this->get_select_posts(Terricel_Logistics_Shared_Data::DRIVER_POST_TYPE);
    }

    private function get_vacancy_editor_data() {
        $drivers = array();
        $routes = array();

        foreach ($this->get_active_drivers() as $driver) {
            $drivers[$driver->ID] = array(
                'name'             => get_the_title($driver),
                'default_route_id' => (int) get_post_meta($driver->ID, '_terricel_driver_default_route_id', true),
            );
        }

        foreach ($this->get_parent_routes() as $route) {
            $routes[$route->ID] = array(
                'name'     => get_the_title($route),
                'schedule' => $this->get_route_schedule_for_vacancy_editor($route->ID),
            );
        }

        return array(
            'drivers' => $drivers,
            'routes'  => $routes,
        );
    }

    private function get_schedule_editor_details($route_id, $date, $selected_substitute_id = 0, $run_substitutes = null) {
        $route_id = absint($route_id);
        $date = $this->sanitize_date_value($date);
        $default_driver_id = $route_id > 0 ? (int) get_post_meta($route_id, '_terricel_route_default_driver_id', true) : 0;

        return array(
            'default_driver_id' => $default_driver_id,
            'runs'              => $this->get_schedule_editor_run_details($route_id, $date, $run_substitutes),
        );
    }

    private function get_schedule_editor_run_details($route_id, $date, $run_substitutes = null) {
        $route_id = absint($route_id);
        $date = $this->sanitize_date_value($date);
        $timestamp = strtotime($date);

        if ($route_id < 1 || !$timestamp) {
            return array();
        }

        if (null === $run_substitutes) {
            $schedule = $this->get_schedule_for_route_date($route_id, $date);
            $run_substitutes = $schedule ? get_post_meta($schedule->ID, '_terricel_coverage_run_substitutes', true) : array();
        }

        $run_substitutes = is_array($run_substitutes) ? $run_substitutes : array();
        $day_key = strtolower(date('l', $timestamp));
        $availability = $this->get_availability_for_date($date);
        $schedules = $this->get_schedules_for_date($date);
        $vacancies = $this->get_vacancies_for_date($date);
        $rows = array();

        foreach ($this->get_route_runs_for_date($route_id, $date) as $run) {
            $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';
            $run_name = isset($run['run_name']) ? sanitize_text_field($run['run_name']) : '';
            $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';

            if (!$run_key || !$run_name || !$start_time) {
                continue;
            }

            $end_time = isset($run['end_time']) ? $this->sanitize_time_value($run['end_time']) : '';
            $value = implode('||', array($date, $day_key, $run_key, $start_time));
            $matched_driver_id = $this->get_run_substitute_driver_id($run_substitutes, $value, $date, $run_key);
            $selected_driver_id = null !== $matched_driver_id ? $matched_driver_id : 0;
            $options = $this->get_available_substitute_driver_options(
                $availability,
                $date,
                $schedules,
                $vacancies,
                array(
                    'route_id'   => $route_id,
                    'run_key'    => $run_key,
                    'start_time' => $start_time,
                    'end_time'   => $end_time,
                ),
                $selected_driver_id
            );
            $option_rows = array();

            foreach ($options as $driver_id => $driver_name) {
                $option_rows[] = array(
                    'id'   => absint($driver_id),
                    'name' => $driver_name,
                );
            }

            $rows[] = array(
                'value'                => $value,
                'run_name'             => $run_name,
                'time_label'           => $start_time ? $this->format_time_value($start_time) : __('Not set', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'selected'             => null !== $matched_driver_id,
                'substitute_driver_id' => $selected_driver_id,
                'substitute_options'   => $option_rows,
            );
        }

        return $rows;
    }

    private function get_route_schedule_for_vacancy_editor($route_id) {
        $schedule = $this->get_route_regular_schedule($route_id);
        $days = $this->regular_schedule_days();
        $output = array();

        foreach ($days as $day_key => $day_label) {
            $output[$day_key] = array();
            $runs = isset($schedule[$day_key]) && is_array($schedule[$day_key]) ? $schedule[$day_key] : array();

            foreach ($runs as $run) {
                $output[$day_key][] = array(
                    'day_label'   => $day_label,
                    'run_key'     => isset($run['run_key']) ? $run['run_key'] : '',
                    'run_name'    => isset($run['run_name']) ? $run['run_name'] : '',
                    'start_time'  => isset($run['start_time']) ? $run['start_time'] : '',
                    'end_time'    => isset($run['end_time']) ? $run['end_time'] : '',
                    'start_label' => isset($run['start_time']) ? $this->format_time_value($run['start_time']) : '',
                );
            }
        }

        return $output;
    }

    private function get_driver_regular_availability($driver_id) {
        return $this->logistics()->get_driver_regular_availability($driver_id);
    }

    private function get_driver_availability_map() {
        $availability_map = array();

        foreach ($this->get_active_drivers() as $driver) {
            $driver_id = absint($driver->ID);
            $availability_map[$driver_id] = $this->get_driver_regular_availability($driver_id);
        }

        return $availability_map;
    }

    private function get_vacancy_regular_driver_id($vacancy_id) {
        $driver_id = (int) get_post_meta($vacancy_id, '_terricel_vacancy_driver_id', true);

        if ($driver_id > 0) {
            return $driver_id;
        }

        $route_id = (int) get_post_meta($vacancy_id, '_terricel_vacancy_route_id', true);

        return $route_id > 0 ? (int) get_post_meta($route_id, '_terricel_route_default_driver_id', true) : 0;
    }

    private function get_route_regular_schedule($route_id) {
        $saved_schedule = get_post_meta($route_id, '_terricel_route_coverage_route_schedule', true);
        $saved_schedule = is_array($saved_schedule) ? $saved_schedule : array();
        $schedule = array();

        foreach ($this->regular_schedule_days() as $day_key => $day_label) {
            $runs = isset($saved_schedule[$day_key]) && is_array($saved_schedule[$day_key]) ? $saved_schedule[$day_key] : array();
            $schedule[$day_key] = array();

            foreach ($runs as $run) {
                $legacy_run_name = isset($run['run_name']) ? sanitize_text_field($run['run_name']) : '';
                $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';

                if (!$run_key && $legacy_run_name) {
                    $run_key = $this->find_run_key_by_name($legacy_run_name);
                }

                $run_name = $this->get_run_name_label($run_key);
                if (!$run_name && $legacy_run_name) {
                    $run_name = $legacy_run_name;
                }
                $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';
                $end_time = isset($run['end_time']) ? $this->sanitize_time_value($run['end_time']) : '';
                $end_time = $end_time ? $end_time : $this->get_default_run_end_time($start_time);

                if (!$run_name || !$start_time) {
                    continue;
                }

                $schedule[$day_key][] = array(
                    'run_key'    => $run_key,
                    'run_name'   => $run_name,
                    'start_time' => $start_time,
                    'end_time'   => $end_time,
                );
            }
        }

        return $this->sort_route_regular_schedule($schedule);
    }

    private function get_vacancy_run_selections($vacancy_id) {
        $saved_runs = get_post_meta($vacancy_id, '_terricel_vacancy_runs', true);
        $saved_runs = is_array($saved_runs) ? $saved_runs : array();
        $selected = array();

        foreach ($saved_runs as $run) {
            if (!is_array($run)) {
                continue;
            }

            $value = $this->get_vacancy_run_selection_value($run);
            if ($value) {
                $selected[$value] = true;
            }
        }

        return $selected;
    }

    private function get_vacancy_candidate_runs($route_id, $start_date, $end_date, $saved_runs = array()) {
        $route_id = absint($route_id);
        $start_date = $this->sanitize_date_value($start_date);
        $end_date = $this->sanitize_date_value($end_date);
        $saved_runs = is_array($saved_runs) ? $saved_runs : array();

        if (!empty($saved_runs)) {
            return $saved_runs;
        }

        if ($route_id < 1 || !$start_date) {
            return array();
        }

        if (!$end_date || $end_date < $start_date) {
            $end_date = $start_date;
        }

        $runs = array();
        $days = $this->regular_schedule_days();

        for ($timestamp = strtotime($start_date); $timestamp && $timestamp <= strtotime($end_date); $timestamp += DAY_IN_SECONDS) {
            $date = date('Y-m-d', $timestamp);
            $day_key = strtolower(date('l', $timestamp));
            $schedule = $this->get_route_regular_schedule($route_id);
            $day_runs = isset($schedule[$day_key]) && is_array($schedule[$day_key]) ? $schedule[$day_key] : array();
            $day_runs = $this->logistics()->apply_route_schedule_changes_to_runs($route_id, $date, $day_runs);

            foreach ($day_runs as $run) {
                if (!is_array($run)) {
                    continue;
                }

                $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';
                $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';

                if (!$run_key || !$start_time) {
                    continue;
                }

                $runs[] = array(
                    'date'       => $date,
                    'day'        => isset($days[$day_key]) ? $day_key : '',
                    'route_id'   => $route_id,
                    'run_key'    => $run_key,
                    'run_name'   => isset($run['run_name']) ? sanitize_text_field($run['run_name']) : $this->get_run_name_label($run_key),
                    'start_time' => $start_time,
                    'end_time'   => isset($run['end_time']) ? $this->sanitize_time_value($run['end_time']) : '',
                );
            }
        }

        return $runs;
    }

    private function parse_vacancy_run_values($raw_runs, $route_id) {
        $runs = array();
        $route_id = absint($route_id);

        foreach ((array) $raw_runs as $raw_run) {
            $parts = explode('||', sanitize_text_field($raw_run));
            if (4 !== count($parts)) {
                continue;
            }

            $date = $this->sanitize_date_value($parts[0]);
            $day_key = sanitize_key($parts[1]);
            $run_key = sanitize_key($parts[2]);
            $start_time = $this->sanitize_time_value($parts[3]);

            if (!$date || !$run_key || !$start_time || !isset($this->regular_schedule_days()[$day_key])) {
                continue;
            }

            $runs[] = array(
                'date'       => $date,
                'day'        => $day_key,
                'route_id'   => $route_id,
                'run_key'    => $run_key,
                'run_name'   => $this->get_run_name_label($run_key),
                'start_time' => $start_time,
                'end_time'   => $this->logistics()->get_default_run_end_time($start_time),
            );
        }

        return $runs;
    }

    private function get_eligible_vacancy_substitute_options($runs, $selected_driver_id = 0) {
        $runs = is_array($runs) ? $runs : array();
        $selected_driver_id = absint($selected_driver_id);
        $eligible_ids = null;

        foreach ($runs as $run) {
            if (!is_array($run)) {
                continue;
            }

            $date = isset($run['date']) ? $this->sanitize_date_value($run['date']) : '';
            $route_id = isset($run['route_id']) ? absint($run['route_id']) : 0;
            $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';
            $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';
            $end_time = isset($run['end_time']) ? $this->sanitize_time_value($run['end_time']) : '';

            if (!$date || !$route_id || !$run_key || !$start_time) {
                continue;
            }

            $options = $this->get_available_substitute_driver_options(
                $this->get_availability_for_date($date),
                $date,
                $this->get_schedules_for_date($date),
                $this->get_vacancies_for_date($date),
                array(
                    'route_id'   => $route_id,
                    'run_key'    => $run_key,
                    'start_time' => $start_time,
                    'end_time'   => $end_time,
                ),
                0
            );

            $current_ids = array_fill_keys(array_map('absint', array_keys($options)), true);
            $eligible_ids = null === $eligible_ids ? $current_ids : array_intersect_key($eligible_ids, $current_ids);
        }

        if (null === $eligible_ids) {
            return array();
        }

        $drivers = array();
        foreach (array_keys($eligible_ids) as $driver_id) {
            if ($driver_id > 0 && 'publish' === get_post_status($driver_id)) {
                $drivers[$driver_id] = get_the_title($driver_id);
            }
        }

        natcasesort($drivers);

        return $drivers;
    }

    private function sanitize_vacancy_run_selections_from_request($route_id) {
        $raw_runs = isset($_POST['terricel_vacancy_runs']) && is_array($_POST['terricel_vacancy_runs']) ? wp_unslash($_POST['terricel_vacancy_runs']) : array();
        $runs = array();
        $seen = array();

        foreach ($raw_runs as $raw_run) {
            $parts = explode('||', sanitize_text_field($raw_run));
            if (4 !== count($parts)) {
                continue;
            }

            $date = $this->sanitize_date_value($parts[0]);
            $day_key = sanitize_key($parts[1]);
            $run_key = sanitize_key($parts[2]);
            $start_time = $this->sanitize_time_value($parts[3]);

            if (!$date || !$run_key || !$start_time || !isset($this->regular_schedule_days()[$day_key])) {
                continue;
            }

            $run_name = $this->get_run_name_label($run_key);
            if (!$run_name) {
                $run_name = $run_key;
            }

            $run = array(
                'date'       => $date,
                'day'        => $day_key,
                'route_id'   => absint($route_id),
                'run_key'    => $run_key,
                'run_name'   => $run_name,
                'start_time' => $start_time,
                'end_time'   => $this->logistics()->get_default_run_end_time($start_time),
            );

            $value = $this->get_vacancy_run_selection_value($run);
            if (!$value || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $runs[] = $run;
        }

        usort(
            $runs,
            function ($first, $second) {
                $date_compare = strcmp($first['date'], $second['date']);
                if (0 !== $date_compare) {
                    return $date_compare;
                }

                $time_compare = strcmp($first['start_time'], $second['start_time']);
                if (0 !== $time_compare) {
                    return $time_compare;
                }

                return strnatcasecmp($first['run_name'], $second['run_name']);
            }
        );

        return $runs;
    }

    private function get_vacancy_run_selection_value($run) {
        $date = isset($run['date']) ? $this->sanitize_date_value($run['date']) : '';
        $day = isset($run['day']) ? sanitize_key($run['day']) : '';
        $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';
        $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';

        if (!$date || !$day || !$run_key || !$start_time) {
            return '';
        }

        return implode('||', array($date, $day, $run_key, $start_time));
    }

    private function get_standard_run_name_options($schedule = array()) {
        $run_names = self::get_standard_run_names();

        foreach ($schedule as $runs) {
            if (!is_array($runs)) {
                continue;
            }

            foreach ($runs as $run) {
                $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';
                $run_name = isset($run['run_name']) ? sanitize_text_field($run['run_name']) : '';

                if ($run_key && $run_name && !isset($run_names[$run_key])) {
                    $run_names[$run_key] = $run_name;
                }
            }
        }

        uasort(
            $run_names,
            function ($first, $second) {
                return strnatcasecmp($first, $second);
            }
        );

        return $run_names;
    }

    public static function get_standard_run_names() {
        $run_names = array();

        foreach (self::get_standard_run_name_items() as $item) {
            $run_names[$item['id']] = $item['name'];
        }

        return $run_names;
    }

    public static function get_standard_run_name_items() {
        $saved = get_option(self::STANDARD_RUN_NAMES_OPTION, null);

        if (!is_array($saved)) {
            $saved = get_option('terricel_standard_run_names', null);
        }

        if (!is_array($saved)) {
            return self::default_standard_run_name_items();
        }

        $items = array();
        $used_ids = array();

        foreach ($saved as $index => $item) {
            if (is_array($item)) {
                $name = isset($item['name']) ? sanitize_text_field($item['name']) : '';
                $id = isset($item['id']) ? sanitize_key($item['id']) : '';
            } else {
                $name = sanitize_text_field($item);
                $id = self::legacy_standard_run_name_id($name, $index);
            }

            if (!$name) {
                continue;
            }

            $id = $id ? $id : self::generate_standard_run_name_id($name, $used_ids);

            if (isset($used_ids[$id])) {
                $id = self::generate_standard_run_name_id($name, $used_ids);
            }

            $used_ids[$id] = true;
            $items[] = array(
                'id'   => $id,
                'name' => $name,
            );
        }

        return $items ? $items : self::default_standard_run_name_items();
    }

    private function sanitize_standard_run_name_items($raw_items) {
        $run_names = array();
        $used_ids = array();

        foreach ($raw_items as $raw_item) {
            if (!is_array($raw_item) || !empty($raw_item['remove'])) {
                continue;
            }

            $run_name = isset($raw_item['name']) ? sanitize_text_field(trim($raw_item['name'])) : '';

            if ($run_name) {
                $run_id = isset($raw_item['id']) ? sanitize_key($raw_item['id']) : '';
                $run_id = $run_id ? $run_id : self::generate_standard_run_name_id($run_name, $used_ids);

                if (isset($used_ids[$run_id])) {
                    $run_id = self::generate_standard_run_name_id($run_name, $used_ids);
                }

                $used_ids[$run_id] = true;
                $run_names[] = array(
                    'id'   => $run_id,
                    'name' => $run_name,
                );
            }
        }

        return $run_names;
    }

    private static function default_standard_run_name_items() {
        return array(
            array(
                'id'   => 'morning',
                'name' => __('Morning Run', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            ),
            array(
                'id'   => 'midday',
                'name' => __('Mid-day Run', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            ),
            array(
                'id'   => 'afternoon',
                'name' => __('Afternoon Run', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            ),
            array(
                'id'   => 'evening',
                'name' => __('Evening Run', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            ),
        );
    }

    private static function legacy_standard_run_name_id($name, $index) {
        $map = array(
            'morning run'   => 'morning',
            'morning'       => 'morning',
            'mid-day run'   => 'midday',
            'mid-day'       => 'midday',
            'midday run'    => 'midday',
            'midday'        => 'midday',
            'afternoon run' => 'afternoon',
            'afternoon'     => 'afternoon',
            'evening run'   => 'evening',
            'evening'       => 'evening',
        );

        $normalized = strtolower(trim($name));

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        $id = sanitize_key($name);

        return $id ? $id : 'run_' . absint($index);
    }

    private static function generate_standard_run_name_id($name, $used_ids) {
        $base = sanitize_key($name);
        $base = $base ? $base : 'run';
        $id = $base;
        $suffix = 2;

        while (isset($used_ids[$id])) {
            $id = $base . '_' . $suffix;
            $suffix++;
        }

        return $id;
    }

    private function get_run_name_label($run_key) {
        $run_names = self::get_standard_run_names();

        return isset($run_names[$run_key]) ? $run_names[$run_key] : '';
    }

    private function find_run_key_by_name($run_name) {
        foreach (self::get_standard_run_names() as $run_key => $name) {
            if (0 === strcasecmp($name, $run_name)) {
                return $run_key;
            }
        }

        return self::legacy_standard_run_name_id($run_name, 0);
    }

    private function get_schedules_for_date($date) {
        $schedules = get_posts(
            array(
                'post_type'      => self::SCHEDULE_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 500,
                'orderby'        => 'ID',
                'order'          => 'DESC',
                'meta_key'       => '_terricel_coverage_date',
                'meta_value'     => $date,
            )
        );

        return $this->dedupe_schedules_by_route_date($schedules);
    }

    private function dedupe_schedules_by_route_date($schedules) {
        $deduped = array();

        foreach ($schedules as $schedule) {
            $route_id = (int) get_post_meta($schedule->ID, '_terricel_coverage_route_id', true);
            $date = get_post_meta($schedule->ID, '_terricel_coverage_date', true);

            if ($route_id < 1 || !$date) {
                continue;
            }

            $key = $route_id . '|' . $date;
            if (!isset($deduped[$key]) || absint($schedule->ID) > absint($deduped[$key]->ID)) {
                $deduped[$key] = $schedule;
            }
        }

        usort(
            $deduped,
            function ($first, $second) {
                $first_date = get_post_meta($first->ID, '_terricel_coverage_date', true);
                $second_date = get_post_meta($second->ID, '_terricel_coverage_date', true);

                if ($first_date === $second_date) {
                    return strnatcasecmp($first->post_title, $second->post_title);
                }

                return strcmp($first_date, $second_date);
            }
        );

        return $deduped;
    }

    private function get_availability_for_date($date) {
        return get_posts(
            array(
                'post_type'      => self::AVAILABILITY_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 500,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_query'     => $this->get_date_range_meta_query('_terricel_availability_date', '_terricel_availability_end_date', $date),
            )
        );
    }

    private function get_available_substitute_driver_options($availability, $date, $schedules = array(), $vacancies = array(), $run_key = '', $selected_driver_id = 0) {
        return $this->logistics()->get_available_substitute_driver_options($date, $schedules, $vacancies, $run_key, $selected_driver_id, $availability);
    }

    private function get_all_active_driver_options($selected_driver_id = 0) {
        $options = array();
        $selected_driver_id = absint($selected_driver_id);

        foreach ($this->get_active_drivers() as $driver) {
            $options[$driver->ID] = get_the_title($driver);
        }

        if ($selected_driver_id > 0 && !isset($options[$selected_driver_id]) && 'publish' === get_post_status($selected_driver_id)) {
            $options[$selected_driver_id] = get_the_title($selected_driver_id);
        }

        return $options;
    }

    private function get_assigned_substitute_driver_ids_for_date($schedules, $vacancies) {
        $driver_ids = array();

        foreach ($schedules as $schedule) {
            $driver_id = (int) get_post_meta($schedule->ID, '_terricel_coverage_substitute_driver_id', true);

            if ($driver_id > 0) {
                $driver_ids[$driver_id] = true;
            }

            $run_substitutes = get_post_meta($schedule->ID, '_terricel_coverage_run_substitutes', true);
            $run_substitutes = is_array($run_substitutes) ? $run_substitutes : array();

            foreach ($run_substitutes as $run_driver_id) {
                $run_driver_id = absint($run_driver_id);

                if ($run_driver_id > 0) {
                    $driver_ids[$run_driver_id] = true;
                }
            }
        }

        foreach ($vacancies as $vacancy) {
            $driver_id = (int) get_post_meta($vacancy->ID, '_terricel_vacancy_assigned_driver_id', true);

            if ($driver_id > 0) {
                $driver_ids[$driver_id] = true;
            }
        }

        return $driver_ids;
    }

    private function is_driver_available_all_day_for_date($driver_id, $date) {
        return $this->logistics()->is_driver_available_all_day_for_date($driver_id, $date);
    }

    private function get_driver_out_records($date) {
        return get_posts(
            array(
                'post_type'      => self::AVAILABILITY_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 500,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    $this->get_date_range_meta_query('_terricel_availability_date', '_terricel_availability_end_date', $date),
                    array(
                        'key'     => '_terricel_availability_status',
                        'value'   => array('unavailable', 'vacation', 'sick'),
                        'compare' => 'IN',
                    ),
                ),
            )
        );
    }

    private function get_date_range_meta_query($start_key, $end_key, $date) {
        return array(
            'relation' => 'OR',
            array(
                'key'   => $start_key,
                'value' => $date,
            ),
            array(
                'relation' => 'AND',
                array(
                    'key'     => $start_key,
                    'value'   => $date,
                    'compare' => '<=',
                    'type'    => 'DATE',
                ),
                array(
                    'key'     => $end_key,
                    'value'   => $date,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
        );
    }

    private function get_dispatch_week_dates($start_date) {
        $dates = array();

        for ($offset = 0; $offset <= 7; $offset++) {
            $dates[] = $this->add_days_to_date($start_date, $offset);
        }

        return $dates;
    }

    private function add_days_to_date($date, $days) {
        $timestamp = strtotime($date . ' +' . absint($days) . ' days');
        return $timestamp ? date('Y-m-d', $timestamp) : current_time('Y-m-d');
    }

    private function get_grouped_route_schedule_change_rows($routes, $start_date, $end_date) {
        $rows = array();
        $seen = array();
        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date);

        if (!$start_timestamp || !$end_timestamp || $end_timestamp < $start_timestamp) {
            return array();
        }

        for ($timestamp = $start_timestamp; $timestamp <= $end_timestamp; $timestamp += DAY_IN_SECONDS) {
            $date = date('Y-m-d', $timestamp);

            foreach ($routes as $route) {
                $route_id = absint($route->ID);
                $changes = $this->logistics()->get_route_schedule_changes_for_date($route_id, $date);

                if (empty($changes)) {
                    continue;
                }

                $location = $this->get_route_location_labels($route_id);

                foreach ($changes as $change) {
                    if (!is_array($change)) {
                        continue;
                    }

                    $type = isset($change['type']) ? sanitize_key($change['type']) : '';
                    $note = isset($change['note']) ? sanitize_text_field($change['note']) : '';
                    $district_name = $location['districts'];
                    $detail = $this->get_schedule_change_label($change);
                    $key = sha1($date . '|' . $district_name . '|' . $type . '|' . $detail . '|' . $note);

                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $rows[] = array(
                        'date'          => $date,
                        'district_name' => $district_name,
                        'detail'        => $detail,
                        'note'          => $note,
                    );
                }
            }
        }

        usort(
            $rows,
            function ($first, $second) {
                $date_compare = strcmp($first['date'], $second['date']);
                if (0 !== $date_compare) {
                    return $date_compare;
                }

                return strnatcasecmp($first['district_name'], $second['district_name']);
            }
        );

        return $rows;
    }

    private function get_daily_route_schedule_change_rows($routes, $date) {
        $rows = array();
        $seen = array();
        $timestamp = strtotime($date);

        if (!$timestamp) {
            return array();
        }

        foreach ($routes as $route) {
            $route_id = absint($route->ID);
            $changes = $this->logistics()->get_route_schedule_changes_for_date($route_id, $date);

            if (empty($changes)) {
                continue;
            }

            $location = $this->get_route_location_labels($route_id);
            $schedule = $this->get_route_regular_schedule($route_id);
            $day_key = strtolower(date('l', $timestamp));
            $runs = isset($schedule[$day_key]) && is_array($schedule[$day_key]) ? $schedule[$day_key] : array();

            foreach ($changes as $change) {
                if (!is_array($change)) {
                    continue;
                }

                $type = isset($change['type']) ? sanitize_key($change['type']) : '';
                $note = isset($change['note']) ? sanitize_text_field($change['note']) : '';
                $key = sha1($date . '|' . $route_id . '|' . $type . '|' . wp_json_encode($change));

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $rows[] = array(
                    'date'          => $date,
                    'route_id'      => $route_id,
                    'route_name'    => get_the_title($route_id),
                    'district_name' => $location['districts'],
                    'school_name'   => $location['schools'],
                    'change_label'  => $this->get_schedule_change_label($change),
                    'affected_runs' => $this->get_schedule_change_affected_runs_label($change, $date, $runs),
                    'note'          => $note,
                );
            }
        }

        usort(
            $rows,
            function ($first, $second) {
                $district_compare = strnatcasecmp($first['district_name'], $second['district_name']);
                if (0 !== $district_compare) {
                    return $district_compare;
                }

                $school_compare = strnatcasecmp($first['school_name'], $second['school_name']);
                if (0 !== $school_compare) {
                    return $school_compare;
                }

                return strnatcasecmp($first['route_name'], $second['route_name']);
            }
        );

        return $rows;
    }

    private function build_dispatcher_route_rows($routes, $schedules, $driver_out_records, $vacancies = array(), $date = '') {
        $schedules_by_route = $this->index_posts_by_meta($schedules, '_terricel_coverage_route_id');
        $driver_out_by_id = $this->index_posts_by_meta($driver_out_records, '_terricel_availability_driver_id');
        $vacancies_by_route = $this->index_posts_by_meta($vacancies, '_terricel_vacancy_route_id');
        $date = $date ? $date : current_time('Y-m-d');
        $rows = array();

        foreach ($routes as $route) {
            $row = $this->build_dispatcher_route_row($route, $schedules_by_route, $driver_out_by_id, $vacancies_by_route, $date);

            if (in_array($row['priority'], array('unassigned', 'substitute', 'at-risk'), true)) {
                $rows[] = $row;
            }
        }

        usort($rows, array($this, 'sort_dispatcher_route_rows'));

        return $rows;
    }

    private function build_dispatcher_route_row($route, $schedules_by_route, $driver_out_by_id, $vacancies_by_route = array(), $date = '') {
        $date = $date ? $date : current_time('Y-m-d');
        $schedule = isset($schedules_by_route[$route->ID]) ? $schedules_by_route[$route->ID] : null;
        $vacancy = isset($vacancies_by_route[$route->ID]) ? $vacancies_by_route[$route->ID] : null;
        $regular_driver_id = (int) get_post_meta($route->ID, '_terricel_route_default_driver_id', true);
        $assigned_driver_id = $schedule ? (int) get_post_meta($schedule->ID, '_terricel_coverage_driver_id', true) : $regular_driver_id;
        $substitute_driver_id = $schedule ? (int) get_post_meta($schedule->ID, '_terricel_coverage_substitute_driver_id', true) : 0;
        $status = $schedule ? get_post_meta($schedule->ID, '_terricel_coverage_status', true) : 'scheduled';
        $out_record = $regular_driver_id && isset($driver_out_by_id[$regular_driver_id]) ? $driver_out_by_id[$regular_driver_id] : null;
        $route_location = $this->get_route_location_labels($route->ID);
        $runs = $this->get_dispatcher_run_rows_for_route($route->ID, $date, $schedule, $vacancy, $substitute_driver_id);

        if (!$schedule && $vacancy) {
            $assigned_driver_id = 0;
            $substitute_driver_id = (int) get_post_meta($vacancy->ID, '_terricel_vacancy_assigned_driver_id', true);
            $status = $substitute_driver_id ? 'covered' : 'unassigned';
        }

        if ($out_record && $assigned_driver_id === $regular_driver_id && !$substitute_driver_id) {
            $assigned_driver_id = 0;
        }

        $has_unassigned_runs = false;
        $has_substitute_runs = false;

        foreach ($runs as $run) {
            if (!empty($run['substitute_driver_id'])) {
                $has_substitute_runs = true;
            } else {
                $has_unassigned_runs = true;
            }
        }

        if (!$regular_driver_id && $has_unassigned_runs) {
            $priority = 'unassigned';
            $status = 'unassigned';
            $assigned_driver_id = 0;
        } elseif (!$assigned_driver_id && !$substitute_driver_id && $has_unassigned_runs) {
            $priority = 'unassigned';
            $status = 'unassigned';
        } elseif ($schedule && $has_unassigned_runs) {
            $priority = 'unassigned';
            $status = 'unassigned';
        } elseif ($has_unassigned_runs && ($out_record || $vacancy || !$assigned_driver_id)) {
            $priority = 'unassigned';
            $status = 'unassigned';
        } elseif ($substitute_driver_id || $has_substitute_runs) {
            $priority = 'substitute';
            $status = 'covered';
        } elseif ('at-risk' === $status) {
            $priority = 'at-risk';
        } else {
            $priority = 'normal';
        }

        return array(
            'priority'             => $priority,
            'district_name'        => $route_location['districts'],
            'school_name'          => $route_location['schools'],
            'route_id'             => $route->ID,
            'route_name'           => get_the_title($route),
            'regular_driver_id'    => $regular_driver_id,
            'regular_driver_out'   => $this->format_driver_out_summary($out_record),
            'assigned_driver_id'   => $assigned_driver_id,
            'substitute_driver_id' => $substitute_driver_id,
            'status_label'         => $this->get_label($status, $this->coverage_statuses(), 'scheduled'),
            'runs'                 => $runs,
        );
    }

    private function get_dispatcher_run_rows_for_route($route_id, $date, $schedule, $vacancy, $route_substitute_driver_id) {
        $day_key = strtolower(date('l', strtotime($date)));
        $route_schedule = $this->get_route_regular_schedule($route_id);
        $runs = isset($route_schedule[$day_key]) && is_array($route_schedule[$day_key]) ? $route_schedule[$day_key] : array();
        $runs = $this->logistics()->apply_route_schedule_changes_to_runs($route_id, $date, $runs);
        $scheduled_run_values = array();

        foreach ($runs as $scheduled_run) {
            $scheduled_run_key = isset($scheduled_run['run_key']) ? sanitize_key($scheduled_run['run_key']) : '';
            $scheduled_start_time = isset($scheduled_run['start_time']) ? $this->sanitize_time_value($scheduled_run['start_time']) : '';

            if ($scheduled_run_key && $scheduled_start_time) {
                $scheduled_run_values[implode('||', array($date, $day_key, $scheduled_run_key, $scheduled_start_time))] = true;
            }
        }

        if (empty($scheduled_run_values)) {
            return array();
        }

        $vacancy_runs = $vacancy ? get_post_meta($vacancy->ID, '_terricel_vacancy_runs', true) : array();
        $vacancy_runs = is_array($vacancy_runs) ? $vacancy_runs : array();
        $run_substitutes = $schedule ? get_post_meta($schedule->ID, '_terricel_coverage_run_substitutes', true) : array();
        $run_substitutes = is_array($run_substitutes) ? $run_substitutes : array();
        $output = array();

        if (!empty($run_substitutes)) {
            $runs = array_filter(
                $runs,
                function ($run) use ($date, $day_key, $run_substitutes) {
                    if (!is_array($run)) {
                        return false;
                    }

                    $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';
                    $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';
                    $value = implode('||', array($date, $day_key, $run_key, $start_time));

                    return null !== $this->get_run_substitute_driver_id($run_substitutes, $value, $date, $run_key);
                }
            );

            if (empty($runs)) {
                return array();
            }
        }

        if (!empty($vacancy_runs)) {
            $runs = array_filter(
                $vacancy_runs,
                function ($run) use ($date, $route_id, $day_key, $scheduled_run_values) {
                    if (!is_array($run) || $date !== ($run['date'] ?? '') || absint($run['route_id'] ?? 0) !== absint($route_id)) {
                        return false;
                    }

                    $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';
                    $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';
                    $value = implode('||', array($date, $day_key, $run_key, $start_time));

                    return isset($scheduled_run_values[$value]);
                }
            );

            if (empty($runs)) {
                return array();
            }
        }

        foreach ($runs as $run) {
            $run_key = isset($run['run_key']) ? sanitize_key($run['run_key']) : '';
            $run_name = isset($run['run_name']) ? sanitize_text_field($run['run_name']) : '';
            $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';
            $end_time = isset($run['end_time']) ? $this->sanitize_time_value($run['end_time']) : '';
            $end_time = $end_time ? $end_time : $this->get_default_run_end_time($start_time);

            if (!$run_key || !$run_name) {
                continue;
            }

            $value = implode('||', array($date, $day_key, $run_key, $start_time));
            $run_substitute_driver_id = $this->get_run_substitute_driver_id($run_substitutes, $value, $date, $run_key);
            $substitute_driver_id = null !== $run_substitute_driver_id ? $run_substitute_driver_id : absint($route_substitute_driver_id);

            $output[] = array(
                'value'                => $value,
                'run_key'              => $run_key,
                'run_name'             => $run_name,
                'start_time'           => $start_time,
                'end_time'             => $end_time,
                'start_label'          => $start_time ? $this->format_time_value($start_time) : __('Not set', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                'substitute_driver_id' => $substitute_driver_id,
                'status_label'         => $substitute_driver_id > 0 ? __('Covered', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) : __('Unassigned', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            );
        }

        return $output;
    }

    private function get_run_substitute_driver_id($run_substitutes, $run_value, $date, $run_key) {
        $run_substitutes = is_array($run_substitutes) ? $run_substitutes : array();
        $run_key = sanitize_key($run_key);

        if (isset($run_substitutes[$run_value])) {
            return absint($run_substitutes[$run_value]);
        }

        foreach ($run_substitutes as $saved_run_value => $driver_id) {
            $parts = $this->logistics()->parse_run_value($saved_run_value);
            if ($parts && $parts['date'] === $date && $parts['run_key'] === $run_key) {
                return absint($driver_id);
            }
        }

        return null;
    }

    private function get_route_location_labels($route_id) {
        $school_ids = $this->get_route_school_ids($route_id);
        $school_names = array();
        $district_names = array();

        foreach ($school_ids as $school_id) {
            if ('publish' !== get_post_status($school_id)) {
                continue;
            }

            $school_names[] = get_the_title($school_id);
            $district_id = (int) get_post_meta($school_id, '_terricel_school_district_id', true);

            if ($district_id > 0 && 'publish' === get_post_status($district_id)) {
                $district_names[] = get_the_title($district_id);
            }
        }

        $school_names = array_values(array_unique(array_filter($school_names)));
        $district_names = array_values(array_unique(array_filter($district_names)));

        return array(
            'schools'   => empty($school_names) ? __('Unassigned School', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) : implode(', ', $school_names),
            'districts' => empty($district_names) ? __('Unassigned District', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) : implode(', ', $district_names),
        );
    }

    private function sort_dispatcher_route_rows($first, $second) {
        $priority_order = array(
            'unassigned' => 0,
            'substitute' => 1,
            'at-risk'    => 2,
            'normal'     => 3,
        );

        $first_priority = isset($priority_order[$first['priority']]) ? $priority_order[$first['priority']] : 99;
        $second_priority = isset($priority_order[$second['priority']]) ? $priority_order[$second['priority']] : 99;

        if ($first_priority !== $second_priority) {
            return $first_priority - $second_priority;
        }

        return strnatcasecmp(
            $first['route_name'] . ' ' . $first['district_name'] . ' ' . $first['school_name'],
            $second['route_name'] . ' ' . $second['district_name'] . ' ' . $second['school_name']
        );
    }

    private function filter_route_rows_by_priority($rows, $priority) {
        return array_filter(
            $rows,
            function ($row) use ($priority) {
                return $priority === $row['priority'];
            }
        );
    }

    private function count_unassigned_dispatch_runs($rows) {
        $count = 0;

        foreach ($rows as $row) {
            if (empty($row['runs']) || !is_array($row['runs'])) {
                if ('unassigned' === $row['priority']) {
                    $count++;
                }
                continue;
            }

            foreach ($row['runs'] as $run) {
                if (empty($run['substitute_driver_id'])) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function get_schedule_change_label($change) {
        $type = isset($change['type']) ? sanitize_key($change['type']) : '';

        if ('closure' === $type) {
            return __('Closure', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        }

        if ('delay' === $type) {
            $hours = isset($change['delay_hours']) ? rtrim(rtrim((string) $change['delay_hours'], '0'), '.') : '0';
            return sprintf(
                /* translators: %s: number of delay hours. */
                __('%s hour delay', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                $hours
            );
        }

        if ('half_day' === $type) {
            return sprintf(
                /* translators: %s: early dismissal time. */
                __('Early dismissal at %s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                !empty($change['early_dismissal_time']) ? $this->format_time_value($change['early_dismissal_time']) : __('Not set', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN)
            );
        }

        return __('Schedule change', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
    }

    private function get_schedule_change_affected_runs_label($change, $date, $runs) {
        $type = isset($change['type']) ? sanitize_key($change['type']) : '';

        if ('closure' === $type) {
            return __('All affected runs cancelled; route is not vacant.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        }

        $adjusted_runs = $this->apply_single_schedule_change_to_runs($change, $runs);
        $labels = array();

        foreach ($runs as $index => $run) {
            if (!isset($adjusted_runs[$index]) || !is_array($run) || !is_array($adjusted_runs[$index])) {
                continue;
            }

            $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';
            $adjusted_start_time = isset($adjusted_runs[$index]['start_time']) ? $this->sanitize_time_value($adjusted_runs[$index]['start_time']) : '';

            if ($start_time && $adjusted_start_time && $start_time !== $adjusted_start_time) {
                $label = sprintf(
                    '%1$s: %2$s -> %3$s',
                    isset($run['run_name']) ? $run['run_name'] : __('Run', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
                    $this->format_time_value($start_time),
                    $this->format_time_value($adjusted_start_time)
                );
                $labels[$label] = $label;
            }
        }

        return $labels ? implode('; ', array_values($labels)) : __('No runs affected.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
    }

    private function apply_single_schedule_change_to_runs($change, $runs) {
        $type = isset($change['type']) ? sanitize_key($change['type']) : '';

        foreach ($runs as &$run) {
            if (!is_array($run)) {
                continue;
            }

            $start_time = isset($run['start_time']) ? $this->sanitize_time_value($run['start_time']) : '';
            if (!$start_time) {
                continue;
            }

            if ('delay' === $type && $start_time < '12:00') {
                $minutes = isset($change['delay_hours']) ? (int) round(((float) $change['delay_hours']) * 60) : 0;
                if ($minutes > 0) {
                    $run['start_time'] = $this->add_minutes_to_time($start_time, $minutes);
                }
            }

            if ('half_day' === $type) {
                $early_time = isset($change['early_dismissal_time']) ? $this->sanitize_time_value($change['early_dismissal_time']) : '';
                if ($early_time && $start_time > $early_time) {
                    $run['start_time'] = $early_time;
                }
            }
        }
        unset($run);

        return $runs;
    }

    private function add_minutes_to_time($time, $minutes) {
        $time = $this->sanitize_time_value($time);
        $timestamp = $time ? strtotime('2000-01-01 ' . $time) : false;

        return $timestamp ? date('H:i', $timestamp + ((int) $minutes * MINUTE_IN_SECONDS)) : '';
    }

    private function get_route_school_ids($route_id) {
        $school_ids = get_post_meta($route_id, '_terricel_route_school_ids', true);

        if (!is_array($school_ids)) {
            $legacy_school_id = (int) get_post_meta($route_id, '_terricel_route_school_id', true);
            $school_ids = $legacy_school_id > 0 ? array($legacy_school_id) : array();
        }

        return array_values(array_filter(array_unique(array_map('absint', $school_ids))));
    }

    private function get_at_risk_schedules() {
        return get_posts(
            array(
                'post_type'      => self::SCHEDULE_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'meta_query'     => array(
                    array(
                        'key'     => '_terricel_coverage_status',
                        'value'   => array('unassigned', 'at-risk'),
                        'compare' => 'IN',
                    ),
                ),
            )
        );
    }

    private function get_open_vacancies() {
        return get_posts(
            array(
                'post_type'      => self::VACANCY_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 100,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'     => '_terricel_vacancy_status',
                        'value'   => array('open', 'covered'),
                        'compare' => 'IN',
                    ),
                ),
            )
        );
    }

    private function get_scheduled_driver_vacancies_for_date($date) {
        return get_posts(
            array(
                'post_type'      => self::VACANCY_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 100,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_terricel_vacancy_status',
                        'value'   => array('open', 'covered'),
                        'compare' => 'IN',
                    ),
                    $this->get_date_range_meta_query('_terricel_vacancy_date', '_terricel_vacancy_end_date', $date),
                    array(
                        'key'     => '_terricel_vacancy_runs',
                        'value'   => '"' . $date . '"',
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );
    }

    private function get_vacancies_for_date($date) {
        return get_posts(
            array(
                'post_type'      => self::VACANCY_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 500,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_terricel_vacancy_status',
                        'value' => 'open',
                    ),
                    $this->get_date_range_meta_query('_terricel_vacancy_date', '_terricel_vacancy_end_date', $date),
                ),
            )
        );
    }

    private function index_posts_by_meta($posts, $meta_key) {
        $indexed = array();

        foreach ($posts as $post) {
            $indexed[(int) get_post_meta($post->ID, $meta_key, true)] = $post;
        }

        return $indexed;
    }

    private function filter_schedules_by_status($schedules, $statuses) {
        return array_filter(
            $schedules,
            function ($schedule) use ($statuses) {
                return in_array(get_post_meta($schedule->ID, '_terricel_coverage_status', true), $statuses, true);
            }
        );
    }

    private function regular_schedule_days() {
        return $this->logistics()->regular_schedule_days();
    }

    private function regular_schedule_periods() {
        return self::get_standard_run_names();
    }

    private function format_driver_out_summary($out_record) {
        if (!$out_record) {
            return __('No', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        }

        $status = get_post_meta($out_record->ID, '_terricel_availability_status', true);
        $start_date = get_post_meta($out_record->ID, '_terricel_availability_date', true);
        $end_date = get_post_meta($out_record->ID, '_terricel_availability_end_date', true);

        return sprintf(
            __('%1$s through %2$s (%3$s)', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            $this->get_label($status, $this->availability_statuses(), 'unavailable'),
            $this->format_date($end_date ? $end_date : $start_date),
            $this->format_out_duration($start_date, $end_date)
        );
    }

    private function format_out_duration($start_date, $end_date) {
        if (!$start_date) {
            return __('Unknown', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        }

        $start = DateTime::createFromFormat('Y-m-d', $start_date);
        $end = $end_date ? DateTime::createFromFormat('Y-m-d', $end_date) : $start;

        if (!$start || !$end) {
            return __('Unknown', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        }

        $days = (int) $start->diff($end)->format('%a') + 1;

        return sprintf(_n('%d day', '%d days', $days, TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN), $days);
    }

    private function format_date($date) {
        if (!$date) {
            return __('Not set', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        }

        return date_i18n(get_option('date_format'), strtotime($date));
    }

    private function format_time_value($time) {
        if (!$time) {
            return __('Not set', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        }

        $timestamp = strtotime($time);

        return $timestamp ? date_i18n(get_option('time_format'), $timestamp) : $time;
    }

    private function format_date_range($start_date, $end_date) {
        if (!$start_date && !$end_date) {
            return __('Not set', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
        }

        if (!$end_date || $end_date === $start_date) {
            return $this->format_date($start_date);
        }

        return sprintf(
            __('%1$s through %2$s', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            $this->format_date($start_date),
            $this->format_date($end_date)
        );
    }

    private function get_label($value, $options, $default) {
        $value = $value ? $value : $default;
        return isset($options[$value]) ? $options[$value] : $value;
    }

    private function module_post_types() {
        return array(
            self::SCHEDULE_POST_TYPE,
            self::AVAILABILITY_POST_TYPE,
            self::VACANCY_POST_TYPE,
        );
    }

    private function coverage_statuses() {
        return array(
            'scheduled'  => __('Scheduled', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'covered'    => __('Covered', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'unassigned' => __('Unassigned', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'at-risk'    => __('At Risk', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'cancelled'  => __('Cancelled', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
        );
    }

    private function availability_statuses() {
        return array(
            'available'   => __('Available', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'standby'     => __('Standby', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'unavailable' => __('Unavailable', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'vacation'    => __('Vacation', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'sick'        => __('Sick', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
        );
    }

    private function vacancy_statuses() {
        return array(
            'open'      => __('Open', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'covered'   => __('Covered', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'cancelled' => __('Cancelled', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
        );
    }

    private function priority_levels() {
        return array(
            'normal' => __('Normal', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'high'   => __('High', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
            'urgent' => __('Urgent', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN),
        );
    }
}
