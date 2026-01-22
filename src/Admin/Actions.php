<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin;

use Floodlight\AltTextMonitor\Jobs\Jobs;

use Floodlight\AltTextMonitor\Settings\Settings;
use Floodlight\AltTextMonitor\Findings\Findings;
use Floodlight\AltTextMonitor\Scan\MediaScanner;
use Floodlight\AltTextMonitor\Scan\ContentScanner;
use Floodlight\AltTextMonitor\Scan\AltEvaluator;



final class Actions {
  private const NONCE_ACTION = 'fatm_actions';

  public function register(): void {
    add_action('admin_post_fatm_start_scan', [$this, 'start_scan']);
    add_action('admin_post_fatm_cancel_scan', [$this, 'cancel_scan']);
    add_action('wp_ajax_fatm_run_step', [$this, 'run_step']);

    add_action('admin_post_fatm_run_quick_scan', [$this, 'run_quick_scan']);
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
    $batch_size = 10;
    $batch_size_media = 25;

    if (($job['progress']['current'] ?? 0) === 0 && empty($job['findings_initialized'])) {
      Findings::init((string) $job['id']);
      Jobs::update(['findings_initialized' => true]);
    }

    $type = (string) ($job['type'] ?? '');
    $offset = (int) ($job['cursor']['offset'] ?? 0);

    $evaluator = new AltEvaluator();

    if ($type === 'media') {
      $scanner = new MediaScanner($evaluator);
      $res = $scanner->scan_batch($offset, $batch_size_media, $effective, $effective['rules'] ?? []);
      Findings::add_many((string) $job['id'], $res['rows']);

      $job_patch = [
        'cursor' => ['offset' => $res['next_offset']],
        'progress' => ['current' => $res['next_offset'], 'total' => $res['total']],
        'message' => 'Scanning Media Library…',
      ];

      if ($res['done']) {
        $job_patch['status'] = 'completed';
        $job_patch['message'] = 'Media scan completed.';
      }

      $job = Jobs::update($job_patch);
      \wp_send_json_success(['job' => $job]);
    }

    if ($type === 'content') {
      $scanner = new ContentScanner($evaluator);
      $res = $scanner->scan_batch($offset, $batch_size, $effective);
      Findings::add_many((string) $job['id'], $res['rows']);

      $job_patch = [
        'cursor' => ['offset' => $res['next_offset']],
        'progress' => ['current' => $res['next_offset'], 'total' => $res['total']],
        'message' => 'Scanning content (ACF image/gallery)…',
      ];

      if ($res['done']) {
        $job_patch['status'] = 'completed';
        $job_patch['message'] = 'Content scan completed (ACF image/gallery).';
      }

      $job = Jobs::update($job_patch);
      \wp_send_json_success(['job' => $job]);
    }

    $job = Jobs::update([
      'status' => 'error',
      'message' => 'Unknown job type.',
      'error' => 'Invalid type: ' . $type,
    ]);

    \wp_send_json_success(['job' => $job]);
  }

  public function run_quick_scan(): void {
    if (!current_user_can('manage_options')) {
      wp_die('Forbidden');
    }

    check_admin_referer('fatm_quick_scan');

    // Build settings: force content scope to last 5 posts
    $settings = Settings::get_effective();
    $settings['scan']['scope'] = 'last_posts';
    $settings['scan']['last_posts'] = 5;

    $job_id = wp_generate_uuid4();

    Findings::init($job_id);

    $evaluator = new AltEvaluator();
    $scanner = new ContentScanner($evaluator);

    $offset = 0;
    $limit = 10; // small batch; last_posts=5 caps total anyway

    do {
      $res = $scanner->scan_batch($offset, $limit, $settings);
      Findings::add_many($job_id, $res['rows'] ?? []);
      $offset = (int) ($res['next_offset'] ?? ($offset + $limit));
      $done = (bool) ($res['done'] ?? true);
    } while (!$done);

    update_option('fatm_last_quick_job_id', $job_id, false);
    update_option('fatm_last_quick_ran_at', time(), false);

    wp_safe_redirect(admin_url('index.php?fatm_quick_scan=1'));
    exit;
  }


  private function return_url(): string {
    // Return to dashboard
    return admin_url('admin.php?page=fatm');
  }

  public static function nonce_action(): string {
    return self::NONCE_ACTION;
  }
}
