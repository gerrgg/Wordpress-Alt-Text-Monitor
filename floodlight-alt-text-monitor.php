<?php
/**
 * Plugin Name: Floodlight Alt Text Monitor
 * Description: Scans WordPress sites for images and highlights missing or poor alt text. Multisite-ready.
 * Version: 0.1.0
 * Author: Floodlight Design
 * Network: true
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

define('FATM_VERSION', '0.1.0');
define('FATM_FILE', __FILE__);
define('FATM_PATH', plugin_dir_path(__FILE__));
define('FATM_URL', plugin_dir_url(__FILE__));

/**
 * Core
 */
require_once FATM_PATH . 'src/Plugin.php';
require_once FATM_PATH . 'src/Support/Assets.php';
require_once FATM_PATH . 'src/Findings/Findings.php';
require_once FATM_PATH . 'src/Jobs/Jobs.php';
require_once FATM_PATH . 'src/Admin/Admin.php';
require_once FATM_PATH . 'src/Admin/Actions.php';
require_once FATM_PATH . 'src/Admin/Pages/ResultsPage.php';
require_once FATM_PATH . 'src/Admin/Dashboard/DashboardWidget.php';
require_once FATM_PATH . 'src/Scan/AltEvaluator.php';
require_once FATM_PATH . 'src/Scan/MediaScanner.php';
require_once FATM_PATH . 'src/Scan/ContentScanner.php';
require_once FATM_PATH . 'src/Admin/Support/Debug.php';
require_once FATM_PATH . 'src/Cron/Cron.php';



/**
 * Settings
 */
require_once FATM_PATH . 'src/Settings/Defaults.php';
require_once FATM_PATH . 'src/Settings/Settings.php';

/**
 * Admin pages
 */
require_once FATM_PATH . 'src/Admin/Pages/DashboardPage.php';
require_once FATM_PATH . 'src/Admin/Pages/SettingsPage.php';
require_once FATM_PATH . 'src/Admin/Pages/NetworkDashboardPage.php';
require_once FATM_PATH . 'src/Admin/Pages/NetworkSettingsPage.php';


register_activation_hook(__FILE__, function ($network_wide) {
  \Floodlight\AltTextMonitor\Plugin::activate((bool) $network_wide);
});

register_deactivation_hook(__FILE__, function ($network_wide) {
  \Floodlight\AltTextMonitor\Plugin::deactivate((bool) $network_wide);
});

add_action('plugins_loaded', function () {
  \Floodlight\AltTextMonitor\Plugin::instance()->init();
});
