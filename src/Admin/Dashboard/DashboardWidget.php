<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Dashboard;

use Floodlight\AltTextMonitor\Findings\Findings;

final class DashboardWidget {

  public function register(): void {
    add_action('wp_dashboard_setup', [$this, 'add_widget']);
  }

  public function add_widget(): void {
    if (!current_user_can('manage_options')) return;

    wp_add_dashboard_widget(
      'fatm_dashboard_widget',
      'Alt Text Monitor',
      [$this, 'render']
    );
  }

  public function render(): void {
    if (!current_user_can('manage_options')) {
      echo '—';
      return;
    }

    $job_id = (string) get_option('fatm_last_quick_job_id', '');
    $ran_at = (int) get_option('fatm_last_quick_ran_at', 0);

    $counts = ['error' => 0, 'warning' => 0, 'ok' => 0];

    if ($job_id !== '') {
      $findings = Findings::get($job_id);
      if (is_array($findings) && !empty($findings['counts']) && is_array($findings['counts'])) {
        $counts = array_merge($counts, $findings['counts']);
      }
    }

    // Flash notice (optional)
    if (!empty($_GET['fatm_quick_scan'])) {
      echo '<div class="notice notice-success inline"><p>Quick scan completed.</p></div>';
    }

    echo '<p style="margin:0 0 8px;"><strong>Quick Content Scan:</strong> last 5 modified posts</p>';

    if ($ran_at > 0) {
      echo '<p class="description" style="margin:0 0 10px;">Last run: ' . esc_html(wp_date('M j, Y g:i a', $ran_at)) . '</p>';
    } else {
      echo '<p class="description" style="margin:0 0 10px;">No quick scan has run yet.</p>';
    }

    echo '<p style="margin:0 0 12px;">';
    echo '<strong>Errors:</strong> ' . (int) $counts['error'] . ' &nbsp; ';
    echo '<strong>Warnings:</strong> ' . (int) $counts['warning'] . ' &nbsp; ';
    echo '<strong>OK:</strong> ' . (int) $counts['ok'];
    echo '</p>';

    // Quick scan button
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:8px;">';
    wp_nonce_field('fatm_quick_scan');
    echo '<input type="hidden" name="action" value="fatm_run_quick_scan" />';
    submit_button('Run Quick Content Scan', 'primary', 'submit', false);
    echo '</form>';

    // View results for quick scan
    $results_url = admin_url('admin.php?page=fatm-results');
    if ($job_id !== '') {
      $results_url = admin_url('admin.php?page=fatm-results&job=' . rawurlencode($job_id));
    }

    echo '<a class="button" href="' . esc_url($results_url) . '">View Results</a>';
  }
}
