<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Settings;

final class Settings {
  private const OPT_SITE = 'fatm_settings';
  private const OPT_NETWORK = 'fatm_network_settings';

  public static function get_site(): array {
    $defaults = Defaults::site();
    $saved = get_option(self::OPT_SITE, []);
    if (!is_array($saved)) {
      $saved = [];
    }
    return self::deep_merge($defaults, $saved);
  }

  public static function set_site(array $data): void {
    update_option(self::OPT_SITE, $data, false);
  }

  public static function get_network(): array {
    $defaults = Defaults::network();
    $saved = is_multisite() ? get_site_option(self::OPT_NETWORK, []) : [];
    if (!is_array($saved)) {
      $saved = [];
    }
    return self::deep_merge($defaults, $saved);
  }

  public static function set_network(array $data): void {
    if (!is_multisite()) {
      return;
    }
    update_site_option(self::OPT_NETWORK, $data);
  }

  public static function get_effective(): array {
    $site = self::get_site();

    $use_network = (bool) ($site['use_network_defaults'] ?? false);
    if (!is_multisite() || !$use_network) {
      return $site;
    }

    $network = self::get_network();
    $network['use_network_defaults'] = true;

    return $network;
  }

  private static function deep_merge(array $base, array $overrides): array {
    foreach ($overrides as $key => $val) {
      if (is_array($val) && isset($base[$key]) && is_array($base[$key])) {
        $base[$key] = self::deep_merge($base[$key], $val);
      } else {
        $base[$key] = $val;
      }
    }
    return $base;
  }
}
