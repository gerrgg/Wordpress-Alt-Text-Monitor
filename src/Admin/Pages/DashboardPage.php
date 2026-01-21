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
      echo '<pre style="margin:0;white-space:pre-wrap;">' . \esc_html(print_r($job, true)) . '</pre>';
    }

    echo '</div>';

    echo '</div>';
  }
}
