<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin;

use Floodlight\AltTextMonitor\Admin\Pages\DashboardPage;
use Floodlight\AltTextMonitor\Admin\Pages\SettingsPage;
use Floodlight\AltTextMonitor\Admin\Pages\NetworkDashboardPage;
use Floodlight\AltTextMonitor\Admin\Pages\NetworkSettingsPage;
use Floodlight\AltTextMonitor\Admin\Pages\ResultsPage;

final class Admin {
  private DashboardPage $dashboard;
  private SettingsPage $settings;
  private NetworkDashboardPage $network_dashboard;
  private NetworkSettingsPage $network_settings;
  private ResultsPage $results;

  public function __construct() {
    $this->dashboard = new DashboardPage();
    $this->settings = new SettingsPage();
    $this->network_dashboard = new NetworkDashboardPage();
    $this->network_settings = new NetworkSettingsPage();
    $this->results = new ResultsPage();
  }

  public function register_site_menu(): void {
    add_menu_page(
      'Alt Text Monitor',
      'Alt Text Monitor',
      'manage_options',
      'fatm',
      [$this->dashboard, 'render'],
      'dashicons-visibility',
      80
    );

    add_submenu_page(
      'fatm',
      'Settings',
      'Settings',
      'manage_options',
      'fatm-settings',
      [$this->settings, 'render']
    );

    add_submenu_page(
      'fatm',
      'Results',
      'Results',
      'manage_options',
      'fatm-results',
      [$this->results, 'render']
    );

  }

  public function register_network_menu(): void {
    if (!is_multisite()) {
      return;
    }

    add_menu_page(
      'Alt Text Monitor (Network)',
      'Alt Text Monitor',
      'manage_network_options',
      'fatm-network',
      [$this->network_dashboard, 'render'],
      'dashicons-visibility',
      80
    );

    add_submenu_page(
      'fatm-network',
      'Settings',
      'Settings',
      'manage_network_options',
      'fatm-network-settings',
      [$this->network_settings, 'render']
    );

    
  }
}
