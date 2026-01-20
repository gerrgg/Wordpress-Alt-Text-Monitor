<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Support;

final class Assets {
  public function enqueue_admin_assets(string $hook_suffix): void {
    // Only load on our pages
    if (strpos($hook_suffix, 'fatm') === false) {
      return;
    }

    wp_enqueue_script(
      'fatm-admin',
      FATM_URL . 'assets/admin.js',
      [],
      FATM_VERSION,
      true
    );
  }
}
