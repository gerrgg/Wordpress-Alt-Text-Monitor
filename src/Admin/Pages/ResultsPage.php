<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Pages;

use Floodlight\AltTextMonitor\Jobs\Jobs;
use Floodlight\AltTextMonitor\Findings\Findings;

final class ResultsPage {
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
      if (!is_array($issues)) $issues = [];

      $edit_link = $id ? \get_edit_post_link($id, 'raw') : '';
      $file_link = $id ? \wp_get_attachment_url($id) : '';

      echo '<tr>';
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

      echo '<td>' . \esc_html($why) . '</td>';
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
