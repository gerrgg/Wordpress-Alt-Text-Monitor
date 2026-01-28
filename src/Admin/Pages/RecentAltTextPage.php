<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Pages;

use Floodlight\AltTextMonitor\Scan\AltEvaluator;
use Floodlight\AltTextMonitor\Scan\ContentScanner;
use Floodlight\AltTextMonitor\Scan\MediaScanner;

final class RecentAltTextPage {

  public function render(): void {
    if (!\current_user_can('manage_options')) {
      \wp_die('You do not have permission to access this page.');
    }

    $limit = 20;

    // Build "recent 20 items" across content posts + image attachments
    $items = $this->get_recent_items($limit);

    $evaluator = new AltEvaluator();
    $content_scanner = new ContentScanner($evaluator);
    $media_scanner = new MediaScanner($evaluator);

    $rows = [];

    foreach ($items as $item) {
      $post_id = (int) ($item['ID'] ?? 0);
      $post_type = (string) ($item['post_type'] ?? '');

      if ($post_id <= 0) continue;

      // Attachment row
      if ($post_type === 'attachment') {
        $rows[] = array_merge(
          ['source' => 'media'],
          $evaluator->evaluate_attachment($post_id, $this->rules_stub())
        );
        continue;
      }

      // Content rows (scan ACF fields + WYSIWYG for just this post)
      // Add this method to ContentScanner (see section 2)
      $rows = array_merge(
        $rows,
        $content_scanner->scan_single_post($post_id, $this->settings_stub())
      );
    }

    echo '<div class="wrap">';
    echo '<h1>Recent Alt Text</h1>';
    echo '<p class="description">Automatically scans the 20 most recently modified items (content and images) on page load.</p>';

    if (empty($rows)) {
      echo '<p>No images found in the most recent items.</p>';
      echo '</div>';
      return;
    }

    $this->render_table($rows);

    echo '</div>';
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

    // Sort combined pool by modified time desc and take top $limit
    usort($pool, function($a, $b) {
      $am = (int) \get_post_modified_time('U', true, (int)$a['ID']);
      $bm = (int) \get_post_modified_time('U', true, (int)$b['ID']);
      return $bm <=> $am;
    });

    return array_slice($pool, 0, $limit);
  }

  // Minimal stubs so we don’t depend on settings pages
  private function settings_stub(): array {
    return [
      'scan' => [
        // not used for single-post scan, but keeps shape consistent
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

  private function render_table(array $items): void {
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>Source</th>';
    echo '<th>Post</th>';
    echo '<th>Field</th>';
    echo '<th>Attachment</th>';
    echo '<th>Alt Text</th>';
    echo '<th>Action</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($items as $row) {
      // You can paste your current ResultsPage row rendering here.
      // Keep it identical so the client gets the same table experience.
      echo $this->render_row($row);
    }

    echo '</tbody></table>';
  }

  private function render_row(array $row): string {
    // Keep it simple: reuse your ResultsPage markup by copy/paste.
    // Returning a string keeps this file tidy.
    ob_start();

    $id = (int) ($row['attachment_id'] ?? 0);
    $sev = (string) ($row['severity'] ?? '');
    $title = (string) ($row['title'] ?? '');
    $issues = $row['issues'] ?? [];
    if (!is_array($issues)) $issues = [];

    $why = (string) ($row['matched_rule'] ?? '');
    $alt_trimmed = (string) ($row['alt_trimmed'] ?? '');
    $alt_len = (int) ($row['alt_length'] ?? 0);
    $file_name = (string) ($row['file_name'] ?? '');
    $source = (string) ($row['source'] ?? '');
    $post_id = (int) ($row['post_id'] ?? 0);
    $post_title = (string) ($row['post_title'] ?? '');
    $post_link = (string) ($row['post_edit_link'] ?? '');
    $field_path = (string) ($row['field_path'] ?? '');
    $img_src = (string) ($row['img_src'] ?? '');
    $attachment_alt = (string) ($row['attachment_alt'] ?? '');

    $edit_link = $id ? \get_edit_post_link($id, 'raw') : '';

    $action_url = '';
    $action_label = '';
    if ($source === 'acf_wysiwyg') {
      if ($post_id && $post_link) { $action_url = $post_link; $action_label = 'Edit page'; }
      elseif ($id && $edit_link) { $action_url = $edit_link; $action_label = 'Edit media'; }
    } else {
      if ($id && $edit_link) { $action_url = $edit_link; $action_label = 'Edit media'; }
      elseif ($post_id && $post_link) { $action_url = $post_link; $action_label = 'Edit page'; }
    }

    echo '<tr>';
    echo '<td>' . esc_html($source) . '</td>';

    echo '<td>';
    if ($post_id && $post_link) echo '<a href="' . esc_url($post_link) . '" target="_blank">' . esc_html($post_title ?: ('Post #' . $post_id)) . '</a>';
    else echo '—';
    echo '</td>';

    echo '<td>' . ($field_path !== '' ? '<code>' . esc_html($field_path) . '</code>' : '—') . '</td>';

    echo '<td>';
    if ($id && $edit_link) {
      $thumb = \wp_get_attachment_image($id, [60, 60], true, [
        'style' => 'width:60px;height:60px;object-fit:cover;border:1px solid #ccd0d4;border-radius:4px;display:block;',
      ]);
      echo '<div style="display:flex;gap:10px;align-items:flex-start;">';
      if ($thumb) echo '<a href="' . esc_url($edit_link) . '">' . $thumb . '</a>';
      echo '<div>';
      echo '<a href="' . esc_url($edit_link) . '"><strong>' . esc_html($title ?: ('Attachment #' . $id)) . '</strong></a>';
      if ($file_name) echo '<div class="description">' . esc_html($file_name) . '</div>';
      echo '</div></div>';
    } elseif ($img_src !== '') {
      echo '<a href="' . esc_url($img_src) . '" target="_blank" rel="noopener">Embedded image</a>';
    } else {
      echo '—';
    }
    echo '</td>';

    echo '<td>';
    if (in_array('missing_alt', $issues, true) || $alt_trimmed === '') {
      echo '<em>(no alt text)</em>';
    } else {
      echo '<code>' . esc_html((string) ($row['alt'] ?? '')) . '</code>';
      echo '<div class="description">Trimmed: <code>' . esc_html($alt_trimmed) . '</code> — Length: ' . (int)$alt_len . '</div>';
      if ($attachment_alt !== '' && $source === 'acf_wysiwyg') {
        echo '<div class="description">Attachment alt: <code>' . esc_html($attachment_alt) . '</code></div>';
      }
    }
    echo '</td>';



    echo '<td>';
    if ($action_url !== '') {
      echo '<a class="button button-small" target="_blank" href="' . esc_url($action_url) . '">' . esc_html($action_label) . '</a>';
    } else {
      echo '—';
    }
    echo '</td>';

    echo '</tr>';

    return (string) ob_get_clean();
  }
}
