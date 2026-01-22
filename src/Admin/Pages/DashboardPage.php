<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Pages;

use Floodlight\AltTextMonitor\Jobs\Jobs;
use Floodlight\AltTextMonitor\Admin\Actions;
use Floodlight\AltTextMonitor\Findings\Findings;

final class DashboardPage {
  public function render(): void {
    if (!\current_user_can('manage_options')) {
      \wp_die('You do not have permission to access this page.');
    }

    $job = Jobs::get();

    $findings = null;
    if ($job && !empty($job['id'])) {
      $findings = Findings::get((string) $job['id']);
    }

    $nonce = \wp_create_nonce(Actions::nonce_action());

    $effective = \Floodlight\AltTextMonitor\Settings\Settings::get_effective();

    $settings_url = \admin_url('admin.php?page=fatm-settings');

    $scope = (string) ($effective['scan']['scope'] ?? 'all');
    $days_back = (int) ($effective['scan']['days_back'] ?? 0);
    $last_posts = (int) ($effective['scan']['last_posts'] ?? 0);

    $content_scope_label = 'All';

    if ($scope === 'days_back' && $days_back > 0) {
      $content_scope_label = 'Last ' . $days_back . ' days (modified)';
    } elseif ($scope === 'last_posts' && $last_posts > 0) {
      $content_scope_label = 'Last ' . $last_posts . ' posts (modified)';
    }


    echo '<div class="wrap">';
    echo '<h1>Floodlight Alt Text Monitor</h1>';



    echo '<h2>Scans</h2>';

    echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:8px;">';
    \wp_nonce_field(Actions::nonce_action());
    echo '<input type="hidden" name="action" value="fatm_start_scan" />';
    echo '<input type="hidden" name="scan_type" value="media" />';
    \submit_button('Run Media Scan', 'primary', 'submit', false);
    echo '</form>';

    echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:8px;">';
    \wp_nonce_field(Actions::nonce_action());
    echo '<input type="hidden" name="action" value="fatm_start_scan" />';
    echo '<input type="hidden" name="scan_type" value="content" />';
    \submit_button('Run Content Scan', 'secondary', 'submit', false);
    echo '</form>';

    $cancel_disabled = (!$job || (($job['status'] ?? '') !== 'running'));

    echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" style="display:inline-block;">';
    \wp_nonce_field(Actions::nonce_action());
    echo '<input type="hidden" name="action" value="fatm_cancel_scan" />';
    \submit_button('Cancel Scan', 'delete', 'submit', false, [
      'disabled' => $cancel_disabled,
    ]);
    echo '</form>';

        echo '<h2>Scope</h2>';
    echo '<div style="max-width:900px;background:#fff;border:1px solid #ccd0d4;padding:12px;">';

    echo '<p style="margin:0 0 6px;"><strong>Media Scanner:</strong> All</p>';
    echo '<p style="margin:0 0 10px;"><strong>Content Scanner:</strong> ' . \esc_html($content_scope_label) . '</p>';

    echo '<a class="button" href="' . \esc_url($settings_url) . '">Edit scan scope in Settings</a>';

    echo '</div>';

    echo '<h2>Status</h2>';
    echo '<div id="fatm-job" data-nonce="' . \esc_attr($nonce) . '" style="max-width:900px;background:#fff;border:1px solid #ccd0d4;padding:12px;">';


    if ($findings && isset($findings['counts'])) {
      $c = $findings['counts'];
      echo '<p style="margin:0 0 10px;">';
      echo '<strong>Counts:</strong> ';
      echo 'Errors: ' . (int)($c['error'] ?? 0) . ' | ';
      echo 'Warnings: ' . (int)($c['warning'] ?? 0) . ' | ';
      echo 'OK: ' . (int)($c['ok'] ?? 0);
      echo '</p>';
    }

    if (!$job) {
      echo '<p>No scan running.</p>';
    } else {
      $status = (string) ($job['status'] ?? '');
      $type = (string) ($job['type'] ?? '');
      $msg = (string) ($job['message'] ?? '');
      $cur = (int) ($job['progress']['current'] ?? 0);
      $tot = (int) ($job['progress']['total'] ?? 0);
      $pct = ($tot > 0) ? (int) floor(($cur / $tot) * 100) : 0;

      echo '<p style="margin:0 0 8px;">';
      echo '<strong>Status:</strong> ' . esc_html($status) . ' &mdash; ';
      echo '<strong>Type:</strong> ' . esc_html($type);
      echo '</p>';

      if ($msg !== '') {
        echo '<p style="margin:0 0 10px;">' . esc_html($msg) . '</p>';
      }

      // progress bar
      echo '<div style="background:#f0f0f1;border:1px solid #ccd0d4;border-radius:4px;overflow:hidden;height:18px;max-width:520px;">';
      echo '<div style="background:#2271b1;height:18px;width:' . esc_attr((string) $pct) . '%;"></div>';
      echo '</div>';
      echo '<p class="description" style="margin:6px 0 0;">' . (int)$cur . ' / ' . (int)$tot . ' (' . (int)$pct . '%)</p>';

      // show error if any
      $err = (string) ($job['error'] ?? '');
      if ($err !== '') {
        echo '<div class="notice notice-error" style="margin-top:12px;"><p>' . esc_html($err) . '</p></div>';
      }

      // show results link if completed
      if ($status === 'completed') {
        echo '<p style="margin-top:12px;">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=fatm-results')) . '">View Results</a>';
        echo '</p>';
      }
    }


    echo '</div>';

    echo '</div>';
  }
}
