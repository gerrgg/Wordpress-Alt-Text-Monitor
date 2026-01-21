<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Pages;

use Floodlight\AltTextMonitor\Jobs\Jobs;
use Floodlight\AltTextMonitor\Findings\Findings;

final class ResultsPage {

private function why_label(string $why): string {
  $map = [
    'missing_alt' => 'Missing alt text',
    'alt_too_short' => 'Alt text too short',
    'alt_looks_like_filename' => 'Alt looks like a filename',
    'alt_generic' => 'Alt is too generic',
  ];

  return $map[$why] ?? $why;
}


  public function render(): void {
    if (!\current_user_can('manage_options')) {
      \wp_die('You do not have permission to access this page.');
    }

    $job = Jobs::get();

    echo '<div class="wrap">';
    echo '<h1>Alt Text Monitor Results</h1>';

    if (!$job || empty($job['id'])) {
      echo '<p>No scan has been run yet.</p>';
      echo '</div>';
      return;
    }

    $findings = Findings::get((string) $job['id']);
    if (!$findings || empty($findings['items'])) {
      echo '<p>No findings recorded for the last scan.</p>';
      echo '</div>';
      return;
    }

    $items = $findings['items'];

    // Filter by severity (default: error+warning)
    $sev = isset($_GET['severity']) ? \sanitize_key((string) $_GET['severity']) : 'issues';
    if ($sev === 'error') {
      $items = array_values(array_filter($items, fn($r) => ($r['severity'] ?? '') === 'error'));
    } elseif ($sev === 'warning') {
      $items = array_values(array_filter($items, fn($r) => ($r['severity'] ?? '') === 'warning'));
    } elseif ($sev === 'ok') {
      $items = array_values(array_filter($items, fn($r) => ($r['severity'] ?? '') === 'ok'));
    } else {
      $items = array_values(array_filter($items, fn($r) => in_array(($r['severity'] ?? ''), ['error','warning'], true)));
    }

    $counts = $findings['counts'] ?? [];
    echo '<p>';
    echo '<strong>Last job:</strong> ' . \esc_html((string) ($job['type'] ?? '')) . ' — ' . \esc_html((string) ($job['status'] ?? '')) . '<br />';
    echo '<strong>Counts:</strong> Errors: ' . (int)($counts['error'] ?? 0) . ' | Warnings: ' . (int)($counts['warning'] ?? 0) . ' | OK: ' . (int)($counts['ok'] ?? 0);
    echo '</p>';

    $base = \admin_url('admin.php?page=fatm-results');
    echo '<p>';
    echo '<a class="button" href="' . \esc_url($base . '&severity=issues') . '">Issues</a> ';
    echo '<a class="button" href="' . \esc_url($base . '&severity=error') . '">Errors</a> ';
    echo '<a class="button" href="' . \esc_url($base . '&severity=warning') . '">Warnings</a> ';
    echo '<a class="button" href="' . \esc_url($base . '&severity=ok') . '">OK</a> ';
    echo '</p>';

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>Source</th>';
    echo '<th>Post</th>';
    echo '<th>Field</th>';
    echo '<th>Severity</th>';
    echo '<th>Attachment</th>';
    echo '<th>Alt Text</th>';
    echo '<th>Why</th>';
    echo '<th>Issues</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($items as $row) {
      $id = (int) ($row['attachment_id'] ?? 0);
      $sev = (string) ($row['severity'] ?? '');
      $title = (string) ($row['title'] ?? '');
      $alt = (string) ($row['alt'] ?? '');
      $issues = $row['issues'] ?? [];
      $why = (string) ($row['matched_rule'] ?? '');
      $alt_trimmed = (string) ($row['alt_trimmed'] ?? '');
      $alt_len = (int) ($row['alt_length'] ?? 0);
      $file_name = (string) ($row['file_name'] ?? '');
      $edit_link = (string) ($row['edit_link'] ?? '');
      $source = (string) ($row['source'] ?? '');
      $post_id = (int) ($row['post_id'] ?? 0);
      $post_title = (string) ($row['post_title'] ?? '');
      $post_link = (string) ($row['post_edit_link'] ?? '');
      $field_path = (string) ($row['field_path'] ?? '');
      if (!is_array($issues)) $issues = [];

      $file_link = $id ? \wp_get_attachment_url($id) : '';

      echo '<tr>';

      echo '<td>' . esc_html($source) . '</td>';

      echo '<td>';
      if ($post_id && $post_link) {
        echo '<a href="' . esc_url($post_link) . '">' . esc_html($post_title ?: ('Post #' . $post_id)) . '</a>';
      } else {
        echo '—';
      }
      echo '</td>';

      echo '<td>';
      echo $field_path !== '' ? '<code>' . esc_html($field_path) . '</code>' : '—';
      echo '</td>';

      echo '<td><strong>' . \esc_html($sev) . '</strong></td>';

      echo '<td>';
      if ($edit_link) {
        echo '<a href="' . \esc_url($edit_link) . '">' . \esc_html($title ?: ('Attachment #' . $id)) . '</a>';
      } else {
        echo \esc_html($title ?: ('Attachment #' . $id));
      }
      if ($file_name) {
        echo '<div class="description">' . \esc_html($file_name) . '</div>';
      }
      echo '</td>';

      echo '<td>';
        if(in_array('missing_alt', $issues, true)) {
          echo '<em>(no alt text)</em><br />';
        } else {
          echo '<code>' . \esc_html((string) ($row['alt'] ?? '')) . '</code>';
          echo '<div class="description">Trimmed: <code>' . \esc_html($alt_trimmed) . '</code> — Length: ' . (int)$alt_len . '</div>';
        }
      echo '</td>';

      echo '<td>' . \esc_html($this->why_label($why)) . '</td>';

      echo '<td>' . \esc_html(implode(', ', $issues)) . '</td>';
      echo '</tr>';
    }

    if (empty($items)) {
      echo '<tr><td colspan="4">No rows for this filter.</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
  }
}
