<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Cron;

final class Cron {
  public const HOOK = 'fatm_daily_quick_scan';

  public function register(): void {
    \add_action(self::HOOK, [$this, 'run_daily_quick_scan']);
  }

  public static function schedule(): void {
    if (!\wp_next_scheduled(self::HOOK)) {
      // Run first time ~5 minutes from now, then daily
      \wp_schedule_event(time() + 300, 'daily', self::HOOK);
    }
  }

  public static function unschedule(): void {
    $ts = \wp_next_scheduled(self::HOOK);
    if ($ts) {
      \wp_unschedule_event($ts, self::HOOK);
    }
  }

  public function run_daily_quick_scan(): void {
    \do_action('fatm_run_quick_scan_internal');
  }
}
