<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Pages;

use Floodlight\AltTextMonitor\Settings\Settings;

final class SettingsPage {
  private const NONCE_ACTION = 'fatm_save_site_settings';

  public function render(): void {
    if (!\current_user_can('manage_options')) {
      \wp_die('You do not have permission to access this page.');
    }

    $saved_notice = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      \check_admin_referer(self::NONCE_ACTION);

      $use_network_defaults = isset($_POST['use_network_defaults']) ? (bool) $_POST['use_network_defaults'] : false;

      // site rules
      $rules_missing_alt_error = !empty($_POST['rules_missing_alt_error']);
      $rules_min_alt_length = isset($_POST['rules_min_alt_length']) ? max(0, (int) $_POST['rules_min_alt_length']) : 5;
      $detect_filename = !empty($_POST['rules_detect_filename']);
      $generic_words = isset($_POST['rules_generic_words']) ? sanitize_text_field((string) $_POST['rules_generic_words']) : '';

      $scan_post_types = isset($_POST['scan_post_types']) && is_array($_POST['scan_post_types'])
        ? array_values(array_map('sanitize_key', $_POST['scan_post_types']))
        : ['post', 'page'];

      $scope = isset($_POST['scan_scope']) ? sanitize_key((string) $_POST['scan_scope']) : 'all';
      if (!in_array($scope, ['all','days_back','last_posts'], true)) {
        $scope = 'all';
      }

      $scan_days_back = isset($_POST['scan_days_back']) ? max(0, (int) $_POST['scan_days_back']) : 0;
      $scan_last_posts = isset($_POST['scan_last_posts']) ? max(0, (int) $_POST['scan_last_posts']) : 0;


      $data = [
        'use_network_defaults' => $use_network_defaults,
        'scan' => [
          'post_types' => $scan_post_types,
          'scope' => $scope,
          'days_back' => $scan_days_back,
          'last_posts' => $scan_last_posts,
        ],
        'rules' => [
          'missing_alt_error' => $rules_missing_alt_error,
          'min_alt_length' => $rules_min_alt_length,
          'detect_filename' => $detect_filename,
          'generic_words' => $generic_words,
        ],
      ];

      Settings::set_site($data);
      $saved_notice = '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $site = Settings::get_site();
    $network = Settings::get_network();
    $effective = Settings::get_effective();

    $all_post_types = get_post_types(['public' => true], 'objects');
    $site_use_network = (bool) ($site['use_network_defaults'] ?? false);

    $missing_alt_error = (bool) (($site['rules']['missing_alt_error'] ?? true));
    $min_alt_length = (int) (($site['rules']['min_alt_length'] ?? 5));
    $detect_filename_val = (bool) (($site['rules']['detect_filename'] ?? true));
    $generic_words_val = (string) (($site['rules']['generic_words'] ?? ''));
    $scope_val = (string) ($site['scan']['scope'] ?? 'all');
    $days_back = (int) ($site['scan']['days_back'] ?? 0);
    $last_posts = (int) ($site['scan']['last_posts'] ?? 0);

    echo '<div class="wrap">';
    echo '<h1>Alt Text Monitor Settings</h1>';
    echo $saved_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    echo '<form method="post" action="">';
    wp_nonce_field(self::NONCE_ACTION);

    echo '<table class="form-table" role="presentation">';

    echo '<tr>';
    echo '<th scope="row">Use network defaults</th>';
    echo '<td>';
    echo '<label>';
    echo '<input type="checkbox" name="use_network_defaults" value="1" ' . checked(true, $site_use_network, false) . ' />';
    echo ' Inherit network settings (site can still store overrides, but effective settings will follow the network)';
    echo '</label>';
    echo '<p class="description">Recommended for consistency across sites.</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">Scan post types</th>';
    echo '<td>';
    $selected = $site['scan']['post_types'] ?? ['post', 'page'];
    foreach ($all_post_types as $pt) {
      $key = (string) $pt->name;
      echo '<label style="display:block;margin:2px 0;">';
      echo '<input type="checkbox" name="scan_post_types[]" value="' . esc_attr($key) . '" ' . checked(true, in_array($key, $selected, true), false) . ' />';
      echo ' ' . esc_html($pt->labels->singular_name) . ' (' . esc_html($key) . ')';
      echo '</label>';
    }
    echo '<p class="description">Used to limit which posts are included in Content Scans.</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">Content scan scope</th>';
    echo '<td>';

    echo '<label style="display:block;margin:3px 0;">';
    echo '<input type="radio" name="scan_scope" value="all" ' . checked('all', $scope_val, false) . ' /> ';
    echo 'All posts';
    echo '</label>';

    echo '<label style="display:block;margin:3px 0;">';
    echo '<input type="radio" name="scan_scope" value="days_back" ' . checked('days_back', $scope_val, false) . ' /> ';
    echo 'Posts modified in the last ';
    echo '<input type="number" name="scan_days_back" min="0" step="1" value="' . esc_attr((string) $days_back) . '" style="width:90px;" /> days';
    echo '</label>';

    echo '<label style="display:block;margin:3px 0;">';
    echo '<input type="radio" name="scan_scope" value="last_posts" ' . checked('last_posts', $scope_val, false) . ' /> ';
    echo 'Last ';
    echo '<input type="number" name="scan_last_posts" min="0" step="1" value="' . esc_attr((string) $last_posts) . '" style="width:120px;" /> posts (by last modified date)';
    echo '</label>';

    echo '<p class="description">Tip: "Last X posts" is best for large sites and keeps scans fast.</p>';

    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">Rules</th>';
    echo '<td>';

    echo '<label style="display:block;margin:2px 0;">';
    echo '<input type="hidden" name="rules_missing_alt_error" value="0" />';
    echo '<input type="checkbox" name="rules_missing_alt_error" value="1" ' . checked(true, $missing_alt_error, false) . ' />';

    echo ' Missing alt is an error';
    echo '</label>';

    echo '<label style="display:block;margin:12px 0 2px;">Generic alt words (comma-separated)</label>';
    echo '<input type="text" name="rules_generic_words" style="width:420px;max-width:100%;" value="' . esc_attr($generic_words_val) . '" />';
    echo '<p class="description">Example: image, photo, logo</p>';

    echo '<label style="display:block;margin:10px 0 2px;">Filename detection</label>';
    echo '<label>';
    echo '<input type="hidden" name="rules_detect_filename" value="0" />';
    echo '<input type="checkbox" name="rules_detect_filename" value="1" ' . checked(true, $detect_filename_val, false) . ' />';

    echo ' Flag alts that look like filenames or camera names (IMG_1234, DSC_…, .jpg, hex strings)';
    echo '</label>';


    echo '<label style="display:block;margin:10px 0 2px;">Minimum alt length (warning threshold)</label>';
    echo '<input type="number" name="rules_min_alt_length" min="0" step="1" value="' . esc_attr((string) $min_alt_length) . '" />';


    echo '</td>';
    echo '</tr>';

    echo '</table>';

    submit_button('Save Settings');

    echo '</form>';

    echo '<hr />';
    echo '<h2>Debug preview</h2>';
    echo '<h3>Site</h3>';
    echo '<pre style="background: #fff;padding:12px;border:1px solid #ccd0d4;max-width:900px;overflow:auto;">' . esc_html(wp_json_encode($site, JSON_PRETTY_PRINT)) . '</pre>';
    echo '<h3>Network</h3>';
    echo '<pre style="background: #fff;padding:12px;border:1px solid #ccd0d4;max-width:900px;overflow:auto;">' . esc_html(wp_json_encode($network, JSON_PRETTY_PRINT)) . '</pre>';
    echo '<h3>Effective</h3>';
    echo '<pre style="background: #fff;padding:12px;border:1px solid #ccd0d4;max-width:900px;overflow:auto;">' . esc_html(wp_json_encode($effective, JSON_PRETTY_PRINT)) . '</pre>';

    echo '</div>';
  }
}
