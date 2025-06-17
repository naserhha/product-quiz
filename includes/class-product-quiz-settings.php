<?php
if (!defined('ABSPATH')) {
    exit;
}

class Perfume_Quiz_Settings {
    private static $instance = null;
    private $settings;
    private static $admin_menu_added = false;

    public function __construct() {
        $this->init();
        // Register the admin menu items only once
        add_action('admin_menu', array($this, 'add_admin_menu'), 10);
        add_action('admin_init', array($this, 'register_settings'));
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init() {
        $this->settings = get_option('perfume_quiz_settings', array());
        if (empty($this->settings)) {
            $this->settings = $this->get_default_settings();
            update_option('perfume_quiz_settings', $this->settings);
        }
    }

    public function add_admin_menu() {
        // If we've already added the menu, don't add it again
        if (self::$admin_menu_added) {
            return;
        }
        
        // Mark as added
        self::$admin_menu_added = true;
        
        // Remove any existing menu with the same slug to avoid duplicates
        remove_menu_page('perfume-quiz-settings');
        
        // Add main menu
        $menu_hook = add_menu_page(
            'تنظیمات کوئیز عطر', // Page title
            'کوئیز عطر',          // Menu title
            'manage_options',      // Capability
            'perfume-quiz-settings', // Menu slug
            array($this, 'render_settings_page'), // Callback function
            'dashicons-filter',    // Icon
            30                     // Position
        );
        
        // Add settings submenu with the same slug as parent to avoid duplicates
        add_submenu_page(
            'perfume-quiz-settings',  // Parent slug
            'تنظیمات کوئیز عطر',     // Page title
            'تنظیمات',               // Menu title - changed to "Settings" to differentiate
            'manage_options',         // Capability
            'perfume-quiz-settings',  // Menu slug (same as parent)
            array($this, 'render_settings_page') // Callback function
        );
        
        // Add diagnostics submenu
        add_submenu_page(
            'perfume-quiz-settings',  // Parent slug
            'تشخیص و رفع خطا',       // Page title
            'تشخیص خطا',             // Menu title
            'manage_options',         // Capability
            'perfume-quiz-diagnostics', // Menu slug
            array($this, 'render_diagnostics_page') // Callback function
        );
    }

    public function register_settings() {
        register_setting(
            'perfume_quiz_settings',
            'perfume_quiz_settings',
            array($this, 'sanitize_settings')
        );
    }

    public function sanitize_settings($input) {
        if (!is_array($input) || empty($input)) {
            return array();
        }

        $sanitized = array();
        
        if (isset($input['attributes']) && is_array($input['attributes'])) {
            foreach ($input['attributes'] as $attr_key => $attr) {
                if (!empty($attr_key)) {
                    $sanitized['attributes'][$attr_key] = array(
                        'enabled' => isset($attr['enabled']) ? (bool) $attr['enabled'] : false,
                        'required' => isset($attr['required']) ? (bool) $attr['required'] : false,
                        'multiple' => isset($attr['multiple']) ? (bool) $attr['multiple'] : false,
                        'order' => isset($attr['order']) ? absint($attr['order']) : 0
                    );
                }
            }
        }

        return $sanitized;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save Settings
        if (isset($_POST['submit'])) {
            check_admin_referer('perfume_quiz_settings_nonce');
            
            $new_settings = array();
            if (isset($_POST['perfume_quiz_settings']) && is_array($_POST['perfume_quiz_settings'])) {
                $new_settings = $this->sanitize_settings($_POST['perfume_quiz_settings']);
                update_option('perfume_quiz_settings', $new_settings);
                echo '<div class="notice notice-success"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
            }
        }

        $settings = $this->get_settings();
        $attributes = wc_get_attribute_taxonomies();
        ?>
        <div class="wrap">
            <h1>تنظیمات کوئیز عطر</h1>
            
            <?php if (empty($attributes)): ?>
                <div class="notice notice-warning">
                    <p>هیچ ویژگی محصولی در ووکامرس تعریف نشده است. لطفاً ابتدا ویژگی‌های محصول را در 
                        <a href="<?php echo admin_url('edit.php?post_type=product&page=product_attributes'); ?>">تنظیمات ووکامرس</a> 
                        تعریف کنید.</p>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <?php wp_nonce_field('perfume_quiz_settings_nonce'); ?>
                    
                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php foreach ($attributes as $attribute): 
                                $attr_key = $attribute->attribute_name;
                                $attr_settings = isset($settings['attributes'][$attr_key]) ? $settings['attributes'][$attr_key] : array();
                            ?>
                                <tr>
                                    <th scope="row" colspan="2">
                                        <h3><?php echo esc_html($attribute->attribute_label); ?></h3>
                                    </th>
                                </tr>
                                <tr>
                                    <th scope="row">فعال</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="perfume_quiz_settings[attributes][<?php echo esc_attr($attr_key); ?>][enabled]" 
                                                   value="1" 
                                                   <?php checked(isset($attr_settings['enabled']) ? $attr_settings['enabled'] : false); ?>>
                                            این ویژگی در کوئیز نمایش داده شود
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">اجباری</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="perfume_quiz_settings[attributes][<?php echo esc_attr($attr_key); ?>][required]" 
                                                   value="1" 
                                                   <?php checked(isset($attr_settings['required']) ? $attr_settings['required'] : false); ?>>
                                            انتخاب این ویژگی اجباری است
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">انتخاب چندگانه</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="perfume_quiz_settings[attributes][<?php echo esc_attr($attr_key); ?>][multiple]" 
                                                   value="1" 
                                                   <?php checked(isset($attr_settings['multiple']) ? $attr_settings['multiple'] : false); ?>>
                                            امکان انتخاب چند گزینه
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">ترتیب نمایش</th>
                                    <td>
                                        <input type="number" 
                                               name="perfume_quiz_settings[attributes][<?php echo esc_attr($attr_key); ?>][order]" 
                                               value="<?php echo esc_attr(isset($attr_settings['order']) ? $attr_settings['order'] : 0); ?>" 
                                               class="small-text">
                                    </td>
                                </tr>
                                <tr><td colspan="2"><hr></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="ذخیره تنظیمات">
                    </p>
                </form>

                <div class="perfume-quiz-help">
                    <h3>راهنما</h3>
                    <ul>
                        <li><strong>فعال:</strong> این ویژگی در کوئیز نمایش داده می‌شود.</li>
                        <li><strong>اجباری:</strong> کاربر باید حتماً یک گزینه را انتخاب کند.</li>
                        <li><strong>انتخاب چندگانه:</strong> کاربر می‌تواند چند گزینه را همزمان انتخاب کند.</li>
                        <li><strong>ترتیب نمایش:</strong> ترتیب نمایش این ویژگی در کوئیز (عدد کوچکتر = اولویت بالاتر).</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .perfume-quiz-help {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .perfume-quiz-help h3 {
            margin-top: 0;
        }
        .perfume-quiz-help ul {
            list-style-type: disc;
            margin-right: 20px;
        }
        </style>
        <?php
    }

    private function get_default_settings() {
        $settings = array('attributes' => array());
        $attributes = wc_get_attribute_taxonomies();
        
        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                $settings['attributes'][$attribute->attribute_name] = array(
                    'enabled' => true,
                    'required' => false,
                    'multiple' => false,
                    'order' => 0
                );
            }
        }
        
        return $settings;
    }

    public function get_settings() {
        return $this->settings;
    }

    public static function get_enabled_attributes() {
        $settings = self::get_instance()->get_settings();
        $enabled_attributes = array();

        if (!empty($settings['attributes'])) {
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            $attribute_labels = array();
            
            // Create a map of attribute names to labels
            foreach ($attribute_taxonomies as $tax) {
                $attribute_labels[$tax->attribute_name] = $tax->attribute_label;
            }

            foreach ($settings['attributes'] as $key => $attr) {
                if (isset($attr['enabled']) && $attr['enabled']) {
                    if (isset($attribute_labels[$key])) {
                        $enabled_attributes[$key] = array(
                            'name' => $key,
                            'label' => $attribute_labels[$key],
                            'required' => isset($attr['required']) ? $attr['required'] : false,
                            'multiple' => isset($attr['multiple']) ? $attr['multiple'] : false,
                            'order' => isset($attr['order']) ? $attr['order'] : 0
                        );
                    }
                }
            }
        }

        // Sort by order
        if (!empty($enabled_attributes)) {
            uasort($enabled_attributes, function($a, $b) {
                return ($a['order'] ?? 0) - ($b['order'] ?? 0);
            });
        }

        error_log('Enabled attributes: ' . print_r($enabled_attributes, true));
        return $enabled_attributes;
    }

    public function update_settings($new_settings) {
        $this->settings = wp_parse_args($new_settings, $this->get_default_settings());
        update_option('perfume_quiz_settings', $this->settings);
        error_log('Settings updated: ' . print_r($this->settings, true));
    }

    public function render_diagnostics_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $plugin_version = PERFUME_QUIZ_VERSION;
        $wp_version = get_bloginfo('version');
        $php_version = phpversion();
        $wc_version = defined('WC_VERSION') ? WC_VERSION : 'WooCommerce not active';
        $active_theme = wp_get_theme()->get('Name');
        
        // Get WooCommerce attribute taxonomies
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        
        // Get active plugins
        $active_plugins = get_option('active_plugins');
        $plugin_list = array();
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $plugin_list[] = $plugin_data['Name'] . ' ' . $plugin_data['Version'];
        }
        
        // Check if shortcode is used in any page
        $shortcode_pages = array();
        $pages = get_posts(array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => -1,
            's' => '[perfume_quiz]'
        ));
        
        foreach ($pages as $page) {
            $shortcode_pages[] = array(
                'title' => $page->post_title,
                'id' => $page->ID,
                'url' => get_permalink($page->ID)
            );
        }
        
        // Get settings
        $settings = $this->get_settings();
        
        // Check if AJAX endpoints are working
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('perfume_quiz_nonce');
        ?>
        <div class="wrap">
            <h1>تشخیص و رفع خطا کوئیز عطر</h1>
            
            <h2>اطلاعات سیستم</h2>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <th>نسخه افزونه:</th>
                        <td><?php echo esc_html($plugin_version); ?></td>
                    </tr>
                    <tr>
                        <th>نسخه وردپرس:</th>
                        <td><?php echo esc_html($wp_version); ?></td>
                    </tr>
                    <tr>
                        <th>نسخه PHP:</th>
                        <td><?php echo esc_html($php_version); ?></td>
                    </tr>
                    <tr>
                        <th>نسخه ووکامرس:</th>
                        <td><?php echo esc_html($wc_version); ?></td>
                    </tr>
                    <tr>
                        <th>قالب فعال:</th>
                        <td><?php echo esc_html($active_theme); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h2>وضعیت افزونه</h2>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <th>صفحات با شورتکد:</th>
                        <td>
                            <?php if (empty($shortcode_pages)): ?>
                                <span style="color: red;">هیچ صفحه‌ای شورتکد [perfume_quiz] را استفاده نمی‌کند.</span>
                            <?php else: ?>
                                <?php foreach ($shortcode_pages as $page): ?>
                                    <div>
                                        <a href="<?php echo esc_url($page['url']); ?>" target="_blank">
                                            <?php echo esc_html($page['title']); ?> (ID: <?php echo esc_html($page['id']); ?>)
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ویژگی‌های فعال:</th>
                        <td>
                            <?php 
                            if (empty($settings['attributes'])) {
                                echo '<span style="color: red;">هیچ ویژگی فعالی وجود ندارد.</span>';
                            } else {
                                $enabled_count = 0;
                                foreach ($settings['attributes'] as $attr_key => $attr) {
                                    if (isset($attr['enabled']) && $attr['enabled']) {
                                        $enabled_count++;
                                        echo esc_html($attr_key) . ' ';
                                        echo isset($attr['required']) && $attr['required'] ? '<span style="color: orange;">(اجباری)</span>' : '';
                                        echo isset($attr['multiple']) && $attr['multiple'] ? '<span style="color: blue;">(چندگانه)</span>' : '';
                                        echo '<br>';
                                    }
                                }
                                if ($enabled_count == 0) {
                                    echo '<span style="color: red;">هیچ ویژگی فعالی وجود ندارد.</span>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h2>ویژگی‌های ووکامرس</h2>
            <?php if (empty($attribute_taxonomies)): ?>
                <div class="notice notice-error">
                    <p>هیچ ویژگی محصولی در ووکامرس تعریف نشده است. لطفاً ابتدا ویژگی‌های محصول را در 
                        <a href="<?php echo admin_url('edit.php?post_type=product&page=product_attributes'); ?>">تنظیمات ووکامرس</a> 
                        تعریف کنید.</p>
                </div>
            <?php else: ?>
                <table class="widefat" style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th>نام ویژگی</th>
                            <th>اسلاگ</th>
                            <th>وضعیت در کوئیز</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attribute_taxonomies as $attribute): 
                            $attr_key = $attribute->attribute_name;
                            $attr_status = isset($settings['attributes'][$attr_key]['enabled']) && $settings['attributes'][$attr_key]['enabled'] ? 'فعال' : 'غیرفعال';
                            $attr_color = $attr_status == 'فعال' ? 'green' : 'red';
                        ?>
                            <tr>
                                <td><?php echo esc_html($attribute->attribute_label); ?></td>
                                <td>pa_<?php echo esc_html($attr_key); ?></td>
                                <td style="color: <?php echo $attr_color; ?>"><?php echo esc_html($attr_status); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <h2>افزونه‌های فعال</h2>
            <ul style="background: #f8f8f8; padding: 10px 15px; border: 1px solid #ddd;">
                <?php foreach ($plugin_list as $plugin): ?>
                    <li><?php echo esc_html($plugin); ?></li>
                <?php endforeach; ?>
            </ul>
            
            <h2>آزمایش AJAX</h2>
            <p>برای بررسی عملکرد AJAX، روی دکمه زیر کلیک کنید:</p>
            <button id="test-ajax" class="button button-primary">آزمایش AJAX</button>
            <div id="ajax-result" style="margin-top: 10px; padding: 10px; background: #f8f8f8; display: none;"></div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#test-ajax').on('click', function() {
                    var result = $('#ajax-result');
                    result.html('در حال بررسی...').show();
                    
                    $.ajax({
                        url: '<?php echo esc_url($ajax_url); ?>',
                        type: 'POST',
                        data: {
                            action: 'perfume_quiz_submit',
                            nonce: '<?php echo esc_js($nonce); ?>',
                            answers: {
                                'product_cat': ['perfume']
                            }
                        },
                        success: function(response) {
                            if (response.success) {
                                result.html('<div style="color: green;">AJAX با موفقیت کار می‌کند!</div>');
                                result.append('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                            } else {
                                result.html('<div style="color: red;">خطا در پاسخ AJAX:</div>');
                                result.append('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                            }
                        },
                        error: function(xhr, status, error) {
                            result.html('<div style="color: red;">خطای AJAX: ' + error + '</div>');
                            result.append('<div>وضعیت: ' + status + '</div>');
                            result.append('<div>پاسخ: ' + xhr.responseText + '</div>');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
} 