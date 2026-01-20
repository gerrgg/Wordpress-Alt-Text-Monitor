<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Pages;

use Floodlight\AltTextMonitor\Settings\Settings;

final class NetworkSettingsPage {
  private const NONCE_ACTION = 'fatm_save_network_settings';

  public function render(): void {
    if (!current_user_can('manage_network_options')) {
      wp_die('You do not have permission to access this page.');
    }

    $saved_notice = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      check_admin_referer(self::NONCE_ACTION);

      $rules_missing_alt_error = isset($_POST['rules_missing_alt_error']) ? (bool) $_POST['rules_missing_alt_error'] : true;
      $rules_min_alt_length = isset($_POST['rules_min_alt_length']) ? max(0, (int) $_POST['rules_min_alt_length']) : 5;

      $scan_post_types = isset($_POST['scan_post_types']) && is_array($_POST['scan_post_types'])
        ? array_values(array_map('sanitize_key', $_POST['scan_post_types']))
        : ['post', 'page'];

      $data = [
        'scan' => [
          'post_types' => $scan_post_types,
        ],
        'rules' => [
          'missing_alt_error' => $rules_missing_alt_error,
          'min_alt_length' => $rules_min_alt_length,
        ],
      ];

      Settings::set_network($data);
      $saved_notice = '<div class="notice notice-success is-dismissible"><p>Network settings saved.</p></div>';
    }

    $network = Settings::get_network();
    $all_post_types = get_post_types(['public' => true], 'objects');
    $selected = $network['scan']['post_types'] ?? ['post', 'page'];

    $missing_alt_error = (bool) (($network['rules']['missing_alt_error'] ?? true));
    $min_alt_length = (int) (($network['rules']['min_alt_length'] ?? 5));

    echo '<div class="wrap">';
    echo '<h1>Alt Text Monitor Network Settings</h1>';
    echo $saved_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    echo '<form method="post" action="">';
    wp_nonce_field(self::NONCE_ACTION);

    echo '<table class="form-table" role="presentation">';

    echo '<tr>';
    echo '<th scope="row">Scan post types (default)</th>';
    echo '<td>';
    foreach ($all_post_types as $pt) {
      $key = (string) $pt->name;
      echo '<label style="display:block;margin:2px 0;">';
      echo '<input type="checkbox" name="scan_post_types[]" value="' . esc_attr($key) . '" ' . checked(true, in_array($key, $selected, true), false) . ' />';
      echo ' ' . esc_html($pt->labels->singular_name) . ' (' . esc_html($key) . ')';
      echo '</label>';
    }
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">Rules (default)</th>';
    echo '<td>';

    echo '<label style="display:block;margin:2px 0;">';
    echo '<input type="checkbox" name="rules_missing_alt_error" value="1" ' . checked(true, $missing_alt_error, false) . ' />';
    echo ' Missing alt is an error';
    echo '</label>';

    echo '<label style="display:block;margin:10px 0 2px;">Minimum alt length (warning threshold)</label>';
    echo '<input type="number" name="rules_min_alt_length" min="0" step="1" value="' . esc_attr((string) $min_alt_length) . '" />';

    echo '</td>';
    echo '</tr>';

    echo '</table>';

    submit_button('Save Network Settings');

    echo '</form>';

    echo '<hr />';
    echo '<h2>Debug preview</h2>';
    echo '<pre style="background:#fff;padding:12px;border:1px solid #ccd0d4;max-width:900px;overflow:auto;">' . esc_html(print_r($network, true)) . '</pre>';

    echo '</div>';
  }
}
