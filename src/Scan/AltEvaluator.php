<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Scan;

final class AltEvaluator {
  public function evaluate_attachment(int $attachment_id, array $rules): array {
    $alt_raw = (string) \get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    $alt_trimmed = trim($alt_raw);

    $title = (string) \get_the_title($attachment_id);
    $url = (string) \wp_get_attachment_url($attachment_id);

    $file_path = (string) \get_attached_file($attachment_id);
    $file_name = $file_path ? basename($file_path) : '';
    $mime = (string) \get_post_mime_type($attachment_id);

    $edit_link = (string) \get_edit_post_link($attachment_id, 'raw');

    $missing_is_error = (bool) ($rules['missing_alt_error'] ?? true);
    $min_len = (int) ($rules['min_alt_length'] ?? 5);
    $detect_filename = (bool) ($rules['detect_filename'] ?? true);

    $generic_words = $this->parse_csv_words((string) ($rules['generic_words'] ?? ''));

    $severity = 'ok';
    $issues = [];
    $matched_rule = '';

    if ($alt_trimmed === '') {
      $severity = $missing_is_error ? 'error' : 'warning';
      $issues[] = 'missing_alt';
      $matched_rule = 'missing_alt';
    } else {
      if (\mb_strlen($alt_trimmed) < $min_len) {
        $severity = $this->max_severity($severity, 'warning');
        $issues[] = 'alt_too_short';
        $matched_rule = $matched_rule ?: 'alt_too_short';
      }

      if ($detect_filename && $this->looks_like_filename($alt_trimmed)) {
        $severity = $this->max_severity($severity, 'warning');
        $issues[] = 'alt_looks_like_filename';
        $matched_rule = $matched_rule ?: 'alt_looks_like_filename';
      }

      if (!empty($generic_words) && $this->is_generic_alt($alt_trimmed, $generic_words)) {
        $severity = $this->max_severity($severity, 'warning');
        $issues[] = 'alt_generic';
        $matched_rule = $matched_rule ?: 'alt_generic';
      }
    }

    return [
      'severity' => $severity,
      'attachment_id' => $attachment_id,
      'title' => $title,
      'url' => $url,
      'edit_link' => $edit_link,
      'alt' => $alt_raw,
      'alt_trimmed' => $alt_trimmed,
      'alt_length' => \mb_strlen($alt_trimmed),
      'file_name' => $file_name,
      'mime' => $mime,
      'matched_rule' => $matched_rule,
      'issues' => $issues,
    ];
  }

  private function max_severity(string $a, string $b): string {
    $rank = ['ok' => 0, 'warning' => 1, 'error' => 2];
    return ($rank[$b] > $rank[$a]) ? $b : $a;
  }

  private function looks_like_filename(string $alt): bool {
    $s = strtolower(trim($alt));
    if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $s)) return true;
    if (preg_match('/^(img|dsc|pxl|photo|screen)[\-_ ]?\d{2,}/i', $s)) return true;
    if (preg_match('/^[a-f0-9]{16,}$/i', $s)) return true;
    return false;
  }

  private function is_generic_alt(string $alt, array $generic_words): bool {
    $s = strtolower(trim($alt));
    return in_array($s, $generic_words, true);
  }

  private function parse_csv_words(string $csv): array {
    $parts = array_map('trim', explode(',', strtolower($csv)));
    $parts = array_values(array_filter($parts, fn($s) => $s !== ''));
    return array_values(array_unique($parts));
  }
}
