<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Dashboard;

final class DashboardWidget {

  public function register(): void {
    \add_action('wp_dashboard_setup', [$this, 'add_widget']);
  }

  public function add_widget(): void {
    if (!\current_user_can('manage_options')) {
      return;
    }

    \wp_add_dashboard_widget(
      'fatm_dashboard_widget',
      'Alt Text Monitor',
      [$this, 'render']
    );
  }

  public function render(): void {
    if (!\current_user_can('manage_options')) {
      echo '—';
      return;
    }

    echo '<p><strong>Floodlight Alt Text Monitor</strong></p>';
    echo '<p class="description">Dashboard widget boilerplate.</p>';

    echo '<p style="margin-top:10px;">';
    echo '<a class="button button-primary" href="' . \esc_url(\admin_url('admin.php?page=fatm')) . '">Open Monitor</a> ';
    echo '<a class="button" href="' . \esc_url(\admin_url('admin.php?page=fatm-results')) . '">View Results</a>';
    echo '</p>';
  }
}
