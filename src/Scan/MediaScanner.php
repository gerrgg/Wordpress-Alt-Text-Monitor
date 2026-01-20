<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Scan;

final class MediaScanner {
  /**
   * @return array{rows: array<int,array>, done: bool, next_offset: int, total: int}
   */
  public function scan_batch(int $offset, int $limit, array $rules): array {
    $q = new \WP_Query([
      'post_type' => 'attachment',
      'post_status' => 'inherit',
      'post_mime_type' => 'image',
      'fields' => 'ids',
      'posts_per_page' => $limit,
      'offset' => $offset,
      'orderby' => 'ID',
      'order' => 'ASC',
      'no_found_rows' => false,
    ]);

    $ids = array_map('intval', $q->posts);
    $total = (int) $q->found_posts;

    $rows = [];
    foreach ($ids as $id) {
      $rows[] = $this->evaluate_attachment($id, $rules);
    }

    $next_offset = $offset + count($ids);
    $done = ($next_offset >= $total) || empty($ids);

    return [
      'rows' => $rows,
      'done' => $done,
      'next_offset' => $next_offset,
      'total' => $total,
    ];
  }

  private function evaluate_attachment(int $attachment_id, array $rules): array {
    $alt_raw = (string) \get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    $alt = trim($alt_raw);

    $title = (string) \get_the_title($attachment_id);
    $url = (string) \wp_get_attachment_url($attachment_id);

    $missing_is_error = (bool) ($rules['missing_alt_error'] ?? true);
    $min_len = (int) ($rules['min_alt_length'] ?? 5);

    $severity = 'ok';
    $issues = [];

    if ($alt === '') {
      $severity = $missing_is_error ? 'error' : 'warning';
      $issues[] = 'missing_alt';
    } else {
      if (mb_strlen($alt) < $min_len) {
        $severity = $this->max_severity($severity, 'warning');
        $issues[] = 'alt_too_short';
      }

      if ($this->looks_like_filename($alt)) {
        $severity = $this->max_severity($severity, 'warning');
        $issues[] = 'alt_looks_like_filename';
      }

      if ($this->is_generic_alt($alt)) {
        $severity = $this->max_severity($severity, 'warning');
        $issues[] = 'alt_generic';
      }
    }

    return [
      'severity' => $severity,
      'source' => 'media',
      'attachment_id' => $attachment_id,
      'title' => $title,
      'url' => $url,
      'alt' => $alt_raw,
      'issues' => $issues,
    ];
  }

  private function max_severity(string $a, string $b): string {
    $rank = ['ok' => 0, 'warning' => 1, 'error' => 2];
    return ($rank[$b] > $rank[$a]) ? $b : $a;
  }

  private function looks_like_filename(string $alt): bool {
    $s = strtolower(trim($alt));
    // common camera / upload patterns or extension present
    if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $s)) return true;
    if (preg_match('/^(img|dsc|pxl|photo|screen)[\-_ ]?\d{2,}/i', $s)) return true;
    if (preg_match('/^[a-f0-9]{16,}$/i', $s)) return true;
    return false;
  }

  private function is_generic_alt(string $alt): bool {
    $s = strtolower(trim($alt));
    $generic = [
      'image',
      'photo',
      'picture',
      'graphic',
      'logo',
      'icon',
      'banner',
      'untitled',
      'placeholder',
    ];
    return in_array($s, $generic, true);
  }
}
