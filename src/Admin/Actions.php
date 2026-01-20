<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin;

use Floodlight\AltTextMonitor\Jobs\Jobs;

use Floodlight\AltTextMonitor\Settings\Settings;
use Floodlight\AltTextMonitor\Findings\Findings;
use Floodlight\AltTextMonitor\Scan\MediaScanner;


final class Actions {
  private const NONCE_ACTION = 'fatm_actions';

  public function register(): void {
    add_action('admin_post_fatm_start_scan', [$this, 'start_scan']);
    add_action('admin_post_fatm_cancel_scan', [$this, 'cancel_scan']);
    add_action('wp_ajax_fatm_run_step', [$this, 'run_step']);
  }

  public function start_scan(): void {
    if (!current_user_can('manage_options')) {
      wp_die('Forbidden');
    }
    check_admin_referer(self::NONCE_ACTION);

    $type = isset($_POST['scan_type']) ? sanitize_key((string) $_POST['scan_type']) : '';
    if (!in_array($type, ['media', 'content'], true)) {
      wp_die('Invalid scan type');
    }

    Jobs::start($type);

    wp_safe_redirect($this->return_url());
    exit;
  }

  public function cancel_scan(): void {
    if (!current_user_can('manage_options')) {
      wp_die('Forbidden');
    }
    check_admin_referer(self::NONCE_ACTION);

    Jobs::cancel();

    wp_safe_redirect($this->return_url());
    exit;
  }

  public function run_step(): void {
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    check_ajax_referer(self::NONCE_ACTION);

    $job = Jobs::get();
    if (!$job) {
      wp_send_json_success(['job' => null]);
    }

    if (($job['status'] ?? '') !== 'running') {
      wp_send_json_success(['job' => $job]);
    }

    $effective = Settings::get_effective();
    $rules = $effective['rules'] ?? [];

    $batch_size = 25;

    // initialize findings on first step
    if (($job['progress']['current'] ?? 0) === 0 && empty($job['findings_initialized'])) {
      Findings::init((string) $job['id']);
      Jobs::update(['findings_initialized' => true]);
    }

    if (($job['type'] ?? '') !== 'media') {
      // Content scan not implemented yet; leave placeholder
      $job = Jobs::update([
        'status' => 'error',
        'message' => 'Content scan not implemented yet.',
        'error' => 'Implement in Step 4.',
      ]);
      \wp_send_json_success(['job' => $job]);
    }

    $offset = (int) ($job['cursor']['offset'] ?? 0);

    $scanner = new MediaScanner();
    $res = $scanner->scan_batch($offset, $batch_size, $rules);

    // Store findings
    Findings::add_many((string) $job['id'], $res['rows']);

    $job_patch = [
      'cursor' => [
        'offset' => $res['next_offset'],
      ],
      'progress' => [
        'current' => $res['next_offset'],
        'total' => $res['total'],
      ],
      'message' => 'Scanning Media Library…',
    ];

    if ($res['done']) {
      $job_patch['status'] = 'completed';
      $job_patch['message'] = 'Media scan completed.';
    }

    $job = Jobs::update($job_patch);

    \wp_send_json_success(['job' => $job]);
  }

  private function return_url(): string {
    // Return to dashboard
    return admin_url('admin.php?page=fatm');
  }

  public static function nonce_action(): string {
    return self::NONCE_ACTION;
  }
}
