<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Jobs;

final class Jobs {
  private const OPT_CURRENT_JOB = 'fatm_current_job';

  public static function get(): ?array {
    $job = get_option(self::OPT_CURRENT_JOB, null);
    return is_array($job) ? $job : null;
  }

  public static function set(array $job): void {
    update_option(self::OPT_CURRENT_JOB, $job, false);
  }

  public static function clear(): void {
    delete_option(self::OPT_CURRENT_JOB);
  }

  public static function start(string $type): array {
    $job = [
      'id' => wp_generate_uuid4(),
      'type' => $type, // 'media' | 'content'
      'status' => 'running', // running|completed|cancelled|error
      'created_at' => time(),
      'updated_at' => time(),
      'progress' => [
        'current' => 0,
        'total' => 100, // placeholder until scanners report real totals
      ],
      'message' => 'Job started.',
      'error' => '',
    ];

    self::set($job);
    return $job;
  }

  public static function update(array $patch): ?array {
    $job = self::get();
    if (!$job) return null;

    $job = array_replace_recursive($job, $patch);
    $job['updated_at'] = time();

    self::set($job);
    return $job;
  }

  public static function cancel(): ?array {
    $job = self::get();
    if (!$job) return null;

    $job['status'] = 'cancelled';
    $job['message'] = 'Job cancelled.';
    $job['updated_at'] = time();

    self::set($job);
    return $job;
  }
}
