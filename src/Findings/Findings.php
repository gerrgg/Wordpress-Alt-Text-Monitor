<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Findings;

final class Findings {
  private const OPT_PREFIX = 'fatm_findings_';

  public static function key(string $job_id): string {
    return self::OPT_PREFIX . $job_id;
  }

  public static function init(string $job_id): void {
    \update_option(self::key($job_id), [
      'created_at' => time(),
      'items' => [],
      'counts' => [
        'error' => 0,
        'warning' => 0,
        'ok' => 0,
      ],
    ], false);
  }

  public static function get(string $job_id): ?array {
    $data = \get_option(self::key($job_id), null);
    return \is_array($data) ? $data : null;
  }

  public static function add_many(string $job_id, array $rows): void {
    $data = self::get($job_id);
    if (!$data) return;

    foreach ($rows as $row) {
      $data['items'][] = $row;
      $sev = $row['severity'] ?? 'ok';
      if (!isset($data['counts'][$sev])) {
        $data['counts'][$sev] = 0;
      }
      $data['counts'][$sev]++;
    }

    \update_option(self::key($job_id), $data, false);
  }
}
