/**
 * Plugin Name: Product Recommendation Quiz
 * Plugin URI: https://persiandigital.com
 * Description: یک سیستم هوشمند برای یافتن محصول مناسب برای مشتریان
 * Version: 1.0.0
 * Author: محمد ناصر حاجی هاشم آباد Mohammad Nasser Haji Hashemabad
 * Author URI: https://mohammadnasser.com/
 * Text Domain: product-quiz
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 *
 * @author    محمد ناصر حاجی هاشم آباد Mohammad Nasser Haji Hashemabad
 * @copyright Copyright (c) 2025, Mohammad Nasser Haji Hashemabad
 * @link      https://mohammadnasser.com/
 * @email     info@mohammadnasser.com
 * @package   Product_Quiz
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('PRODUCT_QUIZ_VERSION', '1.0.0');
define('PRODUCT_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRODUCT_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once PRODUCT_QUIZ_PLUGIN_DIR . 'includes/class-product-quiz.php';
require_once PRODUCT_QUIZ_PLUGIN_DIR . 'includes/class-product-quiz-ajax.php';
require_once PRODUCT_QUIZ_PLUGIN_DIR . 'includes/class-product-quiz-settings.php';

// Initialize the plugin
function product_quiz_init() {
    // Initialize core plugin functionality
    $plugin = new Product_Quiz();
    $plugin->init();
    
    // Initialize AJAX handler so AJAX actions are registered
    $ajax = new Product_Quiz_AJAX();

    // Initialize settings singleton
    // This will register the admin_menu hook
    Product_Quiz_Settings::get_instance();
}

// Register initialization on plugins_loaded to ensure WordPress is fully loaded
add_action('plugins_loaded', 'product_quiz_init');

// Register activation hook
register_activation_hook(__FILE__, 'product_quiz_activate');
function product_quiz_activate() {
    // Add activation tasks here
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'product_quiz_deactivate');
function product_quiz_deactivate() {
    // Add deactivation tasks here
}

// Load text domain for translations
function product_quiz_load_textdomain() {
    load_plugin_textdomain('product-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'product_quiz_load_textdomain');

// Add RTL support
function product_quiz_rtl_support() {
    if (is_rtl()) {
        wp_enqueue_style(
            'product-quiz-rtl',
            PRODUCT_QUIZ_PLUGIN_URL . 'assets/css/rtl.css',
            array('product-quiz-style'),
            PRODUCT_QUIZ_VERSION
        );
    }
}
add_action('wp_enqueue_scripts', 'product_quiz_rtl_support');

// Add Splitting library
function product_quiz_enqueue_scripts() {
    // Enqueue Splitting.js CSS
    wp_enqueue_style(
        'splitting-css',
        PRODUCT_QUIZ_PLUGIN_URL . 'assets/css/splitting.min.css',
        array(),
        PRODUCT_QUIZ_VERSION
    );
    
    // Enqueue Splitting.js script
    wp_enqueue_script(
        'splitting',
        PRODUCT_QUIZ_PLUGIN_URL . 'assets/js/splitting.min.js',
        array(),
        PRODUCT_QUIZ_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'product_quiz_enqueue_scripts'); 