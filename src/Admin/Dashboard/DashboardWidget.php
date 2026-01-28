<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Dashboard;

use Floodlight\AltTextMonitor\Scan\AltEvaluator;
use Floodlight\AltTextMonitor\Scan\ContentScanner;

final class DashboardWidget {

  public function register(): void {
    \add_action('wp_dashboard_setup', [$this, 'add_widget']);
  }

  public function add_widget(): void {
    if (!\current_user_can('manage_options')) return;

    \wp_add_dashboard_widget(
      'fatm_dashboard_widget',
      'Alt Text Monitor',
      [$this, 'render']
    );
  }

  public function render(): void {
    if (!\current_user_can('manage_options')) {
      echo '—';
      return;
    }

    $rows = $this->get_recent_rows(5);

    $recent_url = \admin_url('admin.php?page=fatm');

    echo '<p style="margin:0 0 10px;">Shows the 5 most recent image alt results</p>';

    if (empty($rows)) {
      echo '<p class="description" style="margin:0 0 12px;">No images found in recent content.</p>';
      echo '<a class="button" href="' . \esc_url($recent_url) . '">View all</a>';
      return;
    }

    // small counts (from these 5 only)
    $counts = ['error' => 0, 'warning' => 0, 'ok' => 0];
    foreach ($rows as $r) {
      $sev = (string) ($r['severity'] ?? 'ok');
      if (!isset($counts[$sev])) $counts[$sev] = 0;
      $counts[$sev]++;
    }

    echo '<p style="margin:0 0 10px;">';
    echo '<strong>Errors:</strong> ' . (int) $counts['error'] . ' &nbsp; ';
    echo '<strong>Warnings:</strong> ' . (int) $counts['warning'] . ' &nbsp; ';
    echo '<strong>OK:</strong> ' . (int) $counts['ok'];
    echo '</p>';

    echo '<table class="widefat striped" style="margin:0 0 10px;">';
    echo '<thead><tr>';
    echo '<th style="width:64px;">Image</th>';
    echo '<th>Alt</th>';
    echo '<th>Action</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($rows as $row) {
      $this->render_row($row);
    }

    echo '</tbody></table>';

    echo '<a class="button button-primary" href="' . \esc_url($recent_url) . '">View all</a>';
  }

  /**
   * @return array<int,array>
   */
  private function get_recent_rows(int $take): array {
    $limit_items = 20; // same as Recent Alt Text page
    $items = $this->get_recent_items($limit_items);

    $evaluator = new AltEvaluator();
    $scanner = new ContentScanner($evaluator);

    $seen_attachment_ids = [];
    $rows = [];

    foreach ($items as $item) {
      $post_id = (int) ($item['ID'] ?? 0);
      $post_type = (string) ($item['post_type'] ?? '');
      if ($post_id <= 0 || in_array($post_id, $rows, true)) continue;

      // if ($post_type === 'attachment') {
      //   $rows[] = array_merge(
      //     ['source' => 'media'],
      //     $evaluator->evaluate_attachment($post_id, $this->rules_stub())
      //   );
      // } else {
        // Requires ContentScanner::scan_single_post() as discussed
        $rows = array_merge(
          $rows,
          $scanner->scan_single_post($post_id, $this->settings_stub())
        );
      // }


      if (count($rows) >= $take) {
        break;
      }
    }

    return array_slice($rows, 0, $take);
  }

  /**
   * @return array<int,array{ID:int,post_type:string}>
   */
  private function get_recent_items(int $limit): array {
    $public_types = \get_post_types(['public' => true], 'names');
    if (!is_array($public_types)) $public_types = ['post', 'page'];

    $content_types = array_values(array_filter($public_types, fn($t) => $t !== 'attachment'));

    $content_ids = \get_posts([
      'post_type' => $content_types,
      'post_status' => ['publish'],
      'orderby' => 'modified',
      'order' => 'DESC',
      'numberposts' => $limit,
      'fields' => 'ids',
      'suppress_filters' => true,
    ]);

    $attachment_ids = \get_posts([
      'post_type' => 'attachment',
      'post_status' => 'inherit',
      'post_mime_type' => 'image',
      'orderby' => 'modified',
      'order' => 'DESC',
      'numberposts' => $limit,
      'fields' => 'ids',
      'suppress_filters' => true,
    ]);

    $pool = [];

    foreach ($content_ids as $id) {
      $id = (int) $id;
      if ($id <= 0) continue;
      $pool[] = ['ID' => $id, 'post_type' => (string) \get_post_type($id)];
    }

    foreach ($attachment_ids as $id) {
      $id = (int) $id;
      if ($id <= 0) continue;
      $pool[] = ['ID' => $id, 'post_type' => 'attachment'];
    }

    usort($pool, function($a, $b) {
      $am = (int) \get_post_modified_time('U', true, (int)$a['ID']);
      $bm = (int) \get_post_modified_time('U', true, (int)$b['ID']);
      return $bm <=> $am;
    });

    return array_slice($pool, 0, $limit);
  }

  private function render_row(array $row): void {
    $id = (int) ($row['attachment_id'] ?? 0);
    $sev = (string) ($row['severity'] ?? 'ok');
    $alt_trimmed = (string) ($row['alt_trimmed'] ?? '');
    $issues = $row['issues'] ?? [];
    if (!is_array($issues)) $issues = [];

    $source = (string) ($row['source'] ?? '');
    $post_title = (string) ($row['post_title'] ?? '');
    $field_path = (string) ($row['field_path'] ?? '');
    $post_link = (string) ($row['post_edit_link'] ?? '');
    $edit_link = $id ? \get_edit_post_link($id, 'raw') : '';
    $img_src = (string) ($row['img_src'] ?? '');

    echo '<tr>';

    // thumb
    echo '<td>';
    if ($id > 0) {
      $thumb = \wp_get_attachment_image($id, [48, 48], true, [
        'style' => 'width:48px;height:48px;object-fit:cover;border:1px solid #ccd0d4;border-radius:4px;display:block;',
      ]);
      if ($thumb) {
        $href = $edit_link ?: '#';
        echo '<a href="' . \esc_url($href) . '">' . $thumb . '</a>';
      } else {
        echo '—';
      }
    } elseif ($img_src !== '') {
      echo '<img src="' . \esc_url($img_src) . '" alt="" style="width:48px;height:48px;object-fit:cover;border:1px solid #ccd0d4;border-radius:4px;display:block;" />';
    } else {
      echo '—';
    }
    echo '</td>';



    // alt
    echo '<td>';
    if (in_array('missing_alt', $issues, true) || $alt_trimmed === '') {
      echo '<em>(no alt)</em>';
    } else {
      echo '<code>' . \esc_html($alt_trimmed) . '</code>';
    }
    echo '</td>';

    // action
    echo '<td>';
    $action_url = '';
    $action_label = '';
    if ($source === 'acf_wysiwyg') {
      if ($post_link) { $action_url = $post_link; $action_label = 'Edit page'; }
      elseif ($edit_link) { $action_url = $edit_link; $action_label = 'Edit media'; }
    } else {
      if ($edit_link) { $action_url = $edit_link; $action_label = 'Edit media'; }
      elseif ($post_link) { $action_url = $post_link; $action_label = 'Edit page'; }
    }

    if ($action_url !== '' && $action_label !== '') {
      echo '<a href="' . \esc_url($action_url) . '">' . \esc_html($action_label) . '</a>';
    } else {
      echo '—';
    }
    echo '</td>';

    echo '</tr>';
  }

  private function settings_stub(): array {
    return [
      'scan' => [
        'post_types' => [],
      ],
      'rules' => $this->rules_stub(),
    ];
  }

  private function rules_stub(): array {
    return [
      'missing_alt_error' => true,
      'min_alt_length' => 5,
      'detect_filename' => true,
      'generic_words' => 'image,photo,picture,graphic,logo,icon,banner,untitled,placeholder',
    ];
  }
}
