<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor;

use Floodlight\AltTextMonitor\Admin\Admin;
use Floodlight\AltTextMonitor\Support\Assets;
use Floodlight\AltTextMonitor\Admin\Actions;
use Floodlight\AltTextMonitor\Admin\Dashboard\DashboardWidget;


final class Plugin {
  private static ?Plugin $instance = null;

  private Admin $admin;
  private Assets $assets;
  private DashboardWidget $dashboard_widget;


  private Actions $actions;

  public static function instance(): Plugin {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct() {
    $this->admin = new Admin();
    $this->assets = new Assets();
    $this->actions = new Actions();
    $this->dashboard_widget = new DashboardWidget();
  }

  public function init(): void {
    add_action('admin_menu', [$this->admin, 'register_site_menu']);
    add_action('network_admin_menu', [$this->admin, 'register_network_menu']);
    add_action('admin_enqueue_scripts', [$this->assets, 'enqueue_admin_assets']);

    $this->actions->register();
    $this->dashboard_widget->register();
  }

  public static function activate(bool $network_wide): void {

  }

  public static function deactivate(bool $network_wide): void {

  }
}
