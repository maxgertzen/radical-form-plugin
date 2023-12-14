<?php

function radical_form_add_admin_menu()
{
    global $menu;
    $main_menu_slug = 'radical_admin';
    $main_menu_exists = false;

    foreach ($menu as $menu_item) {
        if ($menu_item[2] == $main_menu_slug) {
            $main_menu_exists = true;
            break;
        }
    }

    if (!$main_menu_exists) {
        $icon_url = plugin_dir_url(dirname(__FILE__)) . 'assets/icon.svg';
        add_menu_page(
            'Radical Admin',
            'Radical Admin',
            'manage_options',
            $main_menu_slug,
            'radical_admin_page',
            $icon_url,
            20
        );
    }

    add_submenu_page(
        $main_menu_slug,
        'Subscription Form',
        'Subscription Form',
        'manage_options',
        'radical_subscription_form',
        'radical_form_settings_page'
    );

    add_submenu_page(
        $main_menu_slug,
        'Logs',
        'Logs',
        'manage_options',
        'radical_logs',
        'radical_logs_page'
    );
}

function radical_admin_page()
{
?>
    <div class="wrap">
        <h1><?php echo esc_html__('Radical Admin Menu', 'radical-form'); ?></h1>
        <ul id="radical-plugin-list">
            <h2><?php echo esc_html__('Select Plugin', 'radical-form'); ?></h2>
            <li>
                <a href="<?php echo admin_url('admin.php?page=radical_subscription_form'); ?>">
                    <?php echo esc_html__('Subscription Form', 'radical-form'); ?>
                </a>
            </li>
        </ul>
    </div>
<?php
}

function radical_form_settings_page()
{
?>
    <div class="wrap">
        <h2><?php echo esc_html__('Radical Form - Settings', 'radical-form'); ?></h2>
        <form action="options.php" method="POST">
            <?php
            settings_fields('radical-plugin-page');
            do_settings_sections('radical-plugin-page');
            submit_button();
            ?>
        </form>
    </div>
<?php
}

function radical_logs_page()
{
    $last_export_log_count = get_transient('radical_last_export_log_count');
    $last_export_time = get_transient('radical_last_export_time');

    $svg_icon_url = plugin_dir_url(dirname(__FILE__)) . 'assets/excel-icon.svg';

?>
    <div class="wrap">
        <h1><?php echo esc_html__('Radical Logs', 'radical-form'); ?></h1>
        <p>
            <?php
            if ($last_export_log_count !== false && $last_export_time !== false) {
                echo sprintf(
                    esc_html__('Last export contained %d log entries on %s', 'radical-form'),
                    $last_export_log_count,
                    $last_export_time
                );
            } else {
                echo esc_html__('No export data available.', 'radical-form');
            }
            ?>
        </p>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="export_logs_to_csv">
            <button type="submit" id="radical-export-button" class="button">
                <img src="<?php echo esc_url($svg_icon_url); ?>" alt="<?php echo esc_attr__('Export Logs to CSV', 'radical-form'); ?>">
                <?php echo esc_attr__('Export Logs to CSV', 'radical-form'); ?>
            </button>
        </form>
    </div>
<?php
}

function radical_form_register_settings()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    register_setting('radical-plugin-page', 'radical_form_options', 'radical_form_options_validate');
    add_settings_section(
        'radical_form_settings_section',
        __('API Settings', 'radical-form'),
        'radical_form_section_callback',
        'radical-plugin-page'
    );

    add_settings_field(
        'radical_form_product_id',
        __('Product Id:', 'radical-form'),
        'radical_form_product_id_callback',
        'radical-plugin-page',
        'radical_form_settings_section'
    );
    add_settings_field(
        'radical_form_variation_id',
        __('Custom Price Variation Id:', 'radical-form'),
        'radical_form_variation_id_callback',
        'radical-plugin-page',
        'radical_form_settings_section'
    );
    add_settings_field(
        'radical_form_activate_form',
        __('Activate Form:', 'radical-form'),
        'radical_form_activate_form_callback',
        'radical-plugin-page',
        'radical_form_settings_section'
    );
}


function radical_form_section_callback()
{
    echo '<p>' . __('Enter your WooCommerce API credentials and the ID of the product you want to use for the form.', 'radical-form') . '</p>';
}

function radical_form_product_id_callback()
{
    $options = get_option('radical_form_options');
    echo "<input id='radical_form_options_product_id' type='text' name='radical_form_options[product_id]' value='" . esc_attr($options['product_id'] ?? '') . "' maxlength='20'/>";
}

function radical_form_variation_id_callback()
{
    $options = get_option('radical_form_options');
    echo "<input id='radical_form_variation_id' type='text' name='radical_form_options[custom_price_variation_id]' value='" . esc_attr($options['custom_price_variation_id'] ?? '') . "' maxlength='20' />";
}

function radical_form_activate_form_callback()
{
    $options = get_option('radical_form_options');
    $checked = isset($options['activate_form']) ? 'checked' : '';
    $infoText = __('This will activate the form on the page with the shortcode %s', 'radical-form');
    echo "<input id='radical_form_activate_form' type='checkbox' name='radical_form_options[activate_form]' value='1' {$checked} />";
    echo "<p style='display: inline-block; margin-left: 4px; color: grey; font-size: smaller;'>" . sprintf($infoText, '[radical_form_shortcode]') . "</p>";
}

/* Validation Function */
function radical_form_options_validate($input)
{
    $newinput = array();

    // Sanitize text fields to prevent code injection
    $newinput['consumer_key'] = sanitize_text_field($input['consumer_key']);
    $newinput['consumer_secret'] = sanitize_text_field($input['consumer_secret']);

    // Validate product_id and custom_price_variation_id as numeric and not more than 10 digits
    $newinput['product_id'] = (isset($input['product_id']) && is_numeric($input['product_id']) && strlen($input['product_id']) <= 10) ? intval($input['product_id']) : '';
    $newinput['custom_price_variation_id'] = (isset($input['custom_price_variation_id']) && is_numeric($input['custom_price_variation_id']) && strlen($input['custom_price_variation_id']) <= 10) ? intval($input['custom_price_variation_id']) : '';

    // Sanitize checkbox
    $newinput['activate_form'] = isset($input['activate_form']) ? true : false;

    return $newinput;
}
