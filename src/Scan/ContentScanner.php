<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Scan;

final class ContentScanner {
  private AltEvaluator $evaluator;

  public function __construct(?AltEvaluator $evaluator = null) {
    $this->evaluator = $evaluator ?: new AltEvaluator();
  }

  /**
   * @return array{rows: array<int,array>, done: bool, next_offset: int, total: int}
   */
    public function scan_batch(int $offset, int $limit, array $settings): array {
      $post_types = $settings['scan']['post_types'] ?? ['post', 'page'];
      if (!is_array($post_types) || empty($post_types)) {
        $post_types = ['post', 'page'];
      }

      $q = new \WP_Query([
        'post_type' => $post_types,
        'post_status' => ['publish'],
        'fields' => 'ids',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => 'ID',
        'order' => 'ASC',
        'no_found_rows' => false,
      ]);

      $ids = array_map('intval', $q->posts);
      $total = (int) $q->found_posts;

      $rules = $settings['rules'] ?? [];

      $rows = [];
      foreach ($ids as $post_id) {
        $rows = array_merge($rows, $this->scan_post_acf($post_id, $rules));
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

    private function scan_post_acf(int $post_id, array $rules): array {
    if (!function_exists('get_field_objects')) {
      return [];
    }

    $field_objects = \get_field_objects($post_id);
    if (!is_array($field_objects) || empty($field_objects)) {
      return [];
    }

    $ctx = [
      'post_id' => $post_id,
      'post_title' => (string) \get_the_title($post_id),
      'post_type' => (string) \get_post_type($post_id),
      'post_edit_link' => (string) \get_edit_post_link($post_id, 'raw'),
    ];

    $rows = [];
    foreach ($field_objects as $name => $field) {
      if (!is_array($field)) continue;

      $field_name = (string) ($field['name'] ?? $name);
      $field_type = (string) ($field['type'] ?? '');
      $value = $field['value'] ?? null;

      $rows = array_merge(
        $rows,
        $this->walk_field($field, $value, $rules, $ctx, $field_name)
      );
    }

    return $rows;
  }

  /**
   * @param array $field ACF field object
   * @param mixed $value The value at this node
   * @return array<int,array>
   */
  private function walk_field(array $field, $value, array $rules, array $ctx, string $path): array {
    $type = (string) ($field['type'] ?? '');
    $rows = [];

    if ($type === 'image') {
      $attachment_id = $this->acf_to_attachment_id($value, $field);
      if ($attachment_id > 0 && $this->is_image_attachment($attachment_id)) {
        $rows[] = $this->row_for_attachment($attachment_id, $rules, $ctx, $path, 'acf_image');
      }
      return $rows;
    }

    if ($type === 'gallery') {
      if (is_array($value)) {
        foreach ($value as $i => $item) {
          $attachment_id = $this->acf_to_attachment_id($item, $field);
          if ($attachment_id > 0 && $this->is_image_attachment($attachment_id)) {
            $rows[] = $this->row_for_attachment($attachment_id, $rules, $ctx, $path . '[' . (int)$i . ']', 'acf_gallery');
          }
        }
      }
      return $rows;
    }

    if ($type === 'group') {
      // value is assoc array of subfield values
      $sub_fields = $field['sub_fields'] ?? [];
      if (is_array($sub_fields) && is_array($value)) {
        foreach ($sub_fields as $sub) {
          if (!is_array($sub)) continue;
          $sub_name = (string) ($sub['name'] ?? '');
          if ($sub_name === '') continue;

          $sub_value = $value[$sub_name] ?? null;
          $rows = array_merge($rows, $this->walk_field($sub, $sub_value, $rules, $ctx, $path . '.' . $sub_name));
        }
      }
      return $rows;
    }

    if ($type === 'repeater') {
      // value is array of rows; each row is assoc array keyed by subfield name
      $sub_fields = $field['sub_fields'] ?? [];
      if (is_array($sub_fields) && is_array($value)) {
        foreach ($value as $row_i => $row_val) {
          if (!is_array($row_val)) continue;

          foreach ($sub_fields as $sub) {
            if (!is_array($sub)) continue;
            $sub_name = (string) ($sub['name'] ?? '');
            if ($sub_name === '') continue;

            $sub_value = $row_val[$sub_name] ?? null;
            $rows = array_merge(
              $rows,
              $this->walk_field($sub, $sub_value, $rules, $ctx, $path . '[' . (int)$row_i . '].' . $sub_name)
            );
          }
        }
      }
      return $rows;
    }

    if ($type === 'flexible_content') {
      // value is array of layouts; each layout has 'acf_fc_layout' and its subfields by name
      $layouts = $field['layouts'] ?? [];
      if (!is_array($layouts) || !is_array($value)) return $rows;

      // Build lookup: layout_name => sub_fields
      $layout_map = [];
      foreach ($layouts as $layout) {
        if (!is_array($layout)) continue;
        $lname = (string) ($layout['name'] ?? '');
        if ($lname === '') continue;
        $layout_map[$lname] = $layout['sub_fields'] ?? [];
      }

      foreach ($value as $i => $layout_val) {
        if (!is_array($layout_val)) continue;
        $layout_name = (string) ($layout_val['acf_fc_layout'] ?? '');
        $sub_fields = $layout_map[$layout_name] ?? null;
        if (!is_array($sub_fields)) continue;

        foreach ($sub_fields as $sub) {
          if (!is_array($sub)) continue;
          $sub_name = (string) ($sub['name'] ?? '');
          if ($sub_name === '') continue;

          $sub_value = $layout_val[$sub_name] ?? null;
          $rows = array_merge(
            $rows,
            $this->walk_field($sub, $sub_value, $rules, $ctx, $path . '[' . (int)$i . '].' . $layout_name . '.' . $sub_name)
          );
        }
      }

      return $rows;
    }

    // Not handling WYSIWYG in this step.
    return $rows;
  }

  /**
   * Convert ACF image/gallery return formats to attachment ID.
   *
   * @param mixed $value
   */
  private function acf_to_attachment_id($value, array $field): int {
    if (is_int($value)) return $value;
    if (is_string($value) && ctype_digit($value)) return (int) $value;

    if (is_array($value)) {
      if (isset($value['ID']) && (is_int($value['ID']) || ctype_digit((string)$value['ID']))) return (int) $value['ID'];
      if (isset($value['id']) && (is_int($value['id']) || ctype_digit((string)$value['id']))) return (int) $value['id'];
    }

    // URL return format for image fields
    if (is_string($value) && $value !== '' && preg_match('#^https?://#i', $value)) {
      $id = (int) \attachment_url_to_postid($value);
      return $id > 0 ? $id : 0;
    }

    return 0;
  }

  /**
   * Walks arbitrary nested ACF values and extracts attachments from common patterns.
   *
   * @param mixed $value
   * @return array<int,array>
   */
  private function walk_value($value, array $rules, array $ctx, string $path): array {
    $rows = [];

    // Image field patterns
    // - int attachment ID
    // - array with ID / id / url / sizes
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
      $attachment_id = (int) $value;
      if ($attachment_id > 0 && $this->is_image_attachment($attachment_id)) {
        $rows[] = $this->row_for_attachment($attachment_id, $rules, $ctx, $path, 'acf_image');
      }
      return $rows;
    }

    if (is_array($value)) {
      // Gallery field patterns: array of IDs or arrays with ID
      if ($this->looks_like_gallery($value)) {
        foreach ($value as $item) {
          $attachment_id = $this->extract_attachment_id($item);
          if ($attachment_id > 0 && $this->is_image_attachment($attachment_id)) {
            $rows[] = $this->row_for_attachment($attachment_id, $rules, $ctx, $path, 'acf_gallery');
          }
        }
        return $rows;
      }

      // Generic recursion for group/repeater/flexible (nested arrays)
      foreach ($value as $k => $v) {
        $k_str = is_int($k) ? '[' . $k . ']' : (string) $k;
        $child_path = $path === '' ? $k_str : $path . '.' . $k_str;
        $rows = array_merge($rows, $this->walk_value($v, $rules, $ctx, $child_path));
      }
      return $rows;
    }

    // not supported in v1: wysiwyg HTML parsing (next phase)
    return $rows;
  }

  private function row_for_attachment(int $attachment_id, array $rules, array $ctx, string $field_path, string $source): array {
    $base = $this->evaluator->evaluate_attachment($attachment_id, $rules);

    return array_merge(
      $base,
      [
        'source' => $source,
        'field_path' => $field_path,
      ],
      $ctx
    );
  }

  private function extract_attachment_id($item): int {
    if (is_int($item)) return $item;
    if (is_string($item) && ctype_digit($item)) return (int) $item;

    if (is_array($item)) {
      if (isset($item['ID']) && (is_int($item['ID']) || ctype_digit((string) $item['ID']))) return (int) $item['ID'];
      if (isset($item['id']) && (is_int($item['id']) || ctype_digit((string) $item['id']))) return (int) $item['id'];
    }

    return 0;
  }

  private function looks_like_gallery(array $value): bool {
    // heuristic: sequential numeric keys and items are int/array
    $i = 0;
    foreach ($value as $k => $v) {
      if (!is_int($k) || $k !== $i) return false;
      if (!(is_int($v) || is_string($v) || is_array($v))) return false;
      $i++;
      if ($i >= 2) break;
    }
    return $i > 0;
  }

  private function is_image_attachment(int $attachment_id): bool {
    $mime = (string) \get_post_mime_type($attachment_id);
    return str_starts_with($mime, 'image/');
  }
}
