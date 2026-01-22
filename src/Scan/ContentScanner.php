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

      $scope = (string) ($settings['scan']['scope'] ?? 'all');
      $days_back = (int) ($settings['scan']['days_back'] ?? 0);
      $last_posts = (int) ($settings['scan']['last_posts'] ?? 0);

      $args = [
        'post_type' => $post_types,
        'post_status' => ['publish'],
        'fields' => 'ids',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'no_found_rows' => false,
      ];

      $args['orderby'] = 'modified';
      $args['order'] = 'DESC';

      if ($scope === 'days_back' && $days_back > 0) {
        $args['date_query'] = [[
          'column' => 'post_modified_gmt',
          'after' => gmdate('Y-m-d H:i:s', time() - ($days_back * DAY_IN_SECONDS)),
          'inclusive' => true,
        ]];
      }

      $q = new \WP_Query($args);

      $ids = array_map('intval', $q->posts);
      $found_total = (int) $q->found_posts;

      $total = $found_total;
      if ($scope === 'last_posts' && $last_posts > 0) {
        $total = min($found_total, $last_posts);
      }

      if ($offset >= $total) {
        return [
          'rows' => [],
          'done' => true,
          'next_offset' => $total,
          'total' => $total,
        ];
      }

      $remaining = $total - $offset;
      if (count($ids) > $remaining) {
        $ids = array_slice($ids, 0, $remaining);
      }

      $rows = [];
      foreach ($ids as $post_id) {
        if ($post_id <= 0) continue;
        $rows = array_merge($rows, $this->scan_post_acf($post_id, $settings)); 
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
      $layouts = $field['layouts'] ?? [];
      if (!is_array($layouts) || !is_array($value)) return $rows;

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

    if ($type === 'wysiwyg') {
      if (is_string($value) && $value !== '') {
        $rows = array_merge($rows, $this->scan_wysiwyg_html($value, $rules, $ctx, $path));
      }
      return $rows;
    }


    return $rows;
  }

  private function scan_wysiwyg_html(string $html, array $rules, array $ctx, string $field_path): array {
    $rows = [];

    if (stripos($html, '<img') === false) {
      return $rows;
    }

    $prev = libxml_use_internal_errors(true);
    $doc = new \DOMDocument();

    $wrapped = '<!doctype html><html><body>' . $html . '</body></html>';
    $doc->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR);

    $imgs = $doc->getElementsByTagName('img');
    $idx = 0;

    foreach ($imgs as $img) {
      $idx++;

      $alt = $img->getAttribute('alt'); // empty string if missing
      $src = $img->getAttribute('src');
      $class = $img->getAttribute('class');
      $data_id = $img->getAttribute('data-id');

      $attachment_id = $this->resolve_attachment_id($class, $data_id, $src);

      $inline_eval = $this->evaluator->evaluate_inline_alt((string) $alt, $rules);

      $row = array_merge($inline_eval, [
        'source' => 'acf_wysiwyg',
        'field_path' => $field_path . '.img[' . $idx . ']',
        'img_src' => (string) $src,
        'img_class' => (string) $class,
        'attachment_id' => (int) $attachment_id,
      ], $ctx);

      if ($attachment_id > 0 && $this->is_image_attachment($attachment_id)) {
        $att = $this->evaluator->evaluate_attachment($attachment_id, $rules);

        $row['title'] = $att['title'] ?? '';
        $row['url'] = $att['url'] ?? '';
        $row['edit_link'] = $att['edit_link'] ?? '';
        $row['file_name'] = $att['file_name'] ?? '';
        $row['mime'] = $att['mime'] ?? '';
        $row['attachment_alt'] = $att['alt'] ?? '';
      }

      $rows[] = $row;
    }

    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    return $rows;
  }

  private function resolve_attachment_id(string $class, string $data_id, string $src): int {
    if ($class !== '' && preg_match('/\bwp-image-(\d+)\b/', $class, $m)) {
      return (int) $m[1];
    }

    if ($data_id !== '' && ctype_digit($data_id)) {
      return (int) $data_id;
    }

    if ($src !== '') {
      // strip querystring
      $clean = preg_replace('/\?.*$/', '', $src);
      $id = (int) \attachment_url_to_postid((string) $clean);
      if ($id > 0) return $id;
    }

    return 0;
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

    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
      $attachment_id = (int) $value;
      if ($attachment_id > 0 && $this->is_image_attachment($attachment_id)) {
        $rows[] = $this->row_for_attachment($attachment_id, $rules, $ctx, $path, 'acf_image');
      }
      return $rows;
    }

    if (is_array($value)) {
      if ($this->looks_like_gallery($value)) {
        foreach ($value as $item) {
          $attachment_id = $this->extract_attachment_id($item);
          if ($attachment_id > 0 && $this->is_image_attachment($attachment_id)) {
            $rows[] = $this->row_for_attachment($attachment_id, $rules, $ctx, $path, 'acf_gallery');
          }
        }
        return $rows;
      }

      foreach ($value as $k => $v) {
        $k_str = is_int($k) ? '[' . $k . ']' : (string) $k;
        $child_path = $path === '' ? $k_str : $path . '.' . $k_str;
        $rows = array_merge($rows, $this->walk_value($v, $rules, $ctx, $child_path));
      }
      return $rows;
    }

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
