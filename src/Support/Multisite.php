<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Support;

final class Multisite {
  /**
   * Run a callback for each site in the network.
   * Useful later for network-wide scanning.
   *
   * @param callable $fn function(int $blog_id): void
   */
  public static function for_each_site(callable $fn): void {
    if (!is_multisite()) {
      $fn(get_current_blog_id());
      return;
    }

    $sites = get_sites([
      'number' => 0,
      'fields' => 'ids',
    ]);

    foreach ($sites as $blog_id) {
      switch_to_blog((int) $blog_id);
      try {
        $fn((int) $blog_id);
      } finally {
        restore_current_blog();
      }
    }
  }
}
