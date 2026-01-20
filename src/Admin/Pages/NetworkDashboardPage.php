<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Pages;

use Floodlight\AltTextMonitor\Settings\Settings;

final class NetworkDashboardPage {
  public function render(): void {
    if (!current_user_can('manage_network_options')) {
      wp_die('You do not have permission to access this page.');
    }

    $network = Settings::get_network();

    echo '<div class="wrap">';
    echo '<h1>Floodlight Alt Text Monitor (Network)</h1>';
    echo '<p>Step 1: Network admin skeleton only.</p>';

    echo '<h2>Network Default Settings (preview)</h2>';
    echo '<pre style="background:#fff;padding:12px;border:1px solid #ccd0d4;max-width:900px;overflow:auto;">';
    echo esc_html(print_r($network, true));
    echo '</pre>';

    echo '<hr />';
    echo '<p><strong>Next:</strong> Add a “scan all sites” orchestration button here.</p>';
    echo '</div>';
  }
}
