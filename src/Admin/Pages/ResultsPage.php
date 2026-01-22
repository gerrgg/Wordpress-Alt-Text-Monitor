<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Pages;

use Floodlight\AltTextMonitor\Jobs\Jobs;
use Floodlight\AltTextMonitor\Findings\Findings;

final class ResultsPage {

private function build_url(array $overrides = []): string {
  $base = \admin_url('admin.php?page=fatm-results');

  $q = [
    'job'      => isset($_GET['job']) ? \sanitize_text_field((string) $_GET['job']) : '',
    'severity' => isset($_GET['severity']) ? \sanitize_key((string) $_GET['severity']) : 'issues',
    'source'   => isset($_GET['source']) ? \sanitize_key((string) $_GET['source']) : '',
    'issue'    => isset($_GET['issue']) ? \sanitize_key((string) $_GET['issue']) : '',
    's'        => isset($_GET['s']) ? \sanitize_text_field((string) $_GET['s']) : '',
    'paged'    => isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1,
  ];


  foreach ($overrides as $k => $v) {
    $q[$k] = $v;
  }

  // Remove empties
  foreach (['job','source','issue','s'] as $k) {
    if ($q[$k] === '') unset($q[$k]);
  }


  // if you change any filter besides paged, reset paged
  if (isset($overrides['severity']) || isset($overrides['source']) || isset($overrides['issue']) || isset($overrides['s'])) {
    $q['paged'] = 1;
  }

  return $base . '&' . http_build_query($q);
}


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

    $job_id = isset($_GET['job']) ? \sanitize_text_field((string) $_GET['job']) : '';

    if ($job_id !== '') {
      // "virtual" job for display purposes
      $job = [
        'id' => $job_id,
        'type' => 'quick',
        'status' => 'completed',
      ];
    } else {
      $job = Jobs::get();
    }

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

    // ---- Filters ----
    $sev = isset($_GET['severity']) ? \sanitize_key((string) $_GET['severity']) : 'issues';
    $source_filter = isset($_GET['source']) ? \sanitize_key((string) $_GET['source']) : '';
    $issue_filter  = isset($_GET['issue']) ? \sanitize_key((string) $_GET['issue']) : '';
    $search        = isset($_GET['s']) ? \sanitize_text_field((string) $_GET['s']) : '';

    // severity filter (default: issues)
    if ($sev === 'error') {
      $items = array_values(array_filter($items, fn($r) => ($r['severity'] ?? '') === 'error'));
    } elseif ($sev === 'warning') {
      $items = array_values(array_filter($items, fn($r) => ($r['severity'] ?? '') === 'warning'));
    } elseif ($sev === 'ok') {
      $items = array_values(array_filter($items, fn($r) => ($r['severity'] ?? '') === 'ok'));
    } else {
      $items = array_values(array_filter($items, fn($r) => in_array(($r['severity'] ?? ''), ['error','warning'], true)));
    }

    // source filter
    if ($source_filter !== '') {
      $items = array_values(array_filter($items, fn($r) => ($r['source'] ?? '') === $source_filter));
    }

    // issue filter
    if ($issue_filter !== '') {
      $items = array_values(array_filter($items, function ($r) use ($issue_filter) {
        $issues = $r['issues'] ?? [];
        return is_array($issues) && in_array($issue_filter, $issues, true);
      }));
    }

    // search (post title, attachment title, field path, src)
    if ($search !== '') {
      $needle = mb_strtolower($search);
      $items = array_values(array_filter($items, function ($r) use ($needle) {
        $hay = implode(' ', array_filter([
          (string) ($r['post_title'] ?? ''),
          (string) ($r['title'] ?? ''),
          (string) ($r['field_path'] ?? ''),
          (string) ($r['img_src'] ?? ''),
          (string) ($r['file_name'] ?? ''),
          (string) ($r['alt_trimmed'] ?? ''),
        ]));
        return mb_strpos(mb_strtolower($hay), $needle) !== false;
      }));
    }

    $total_items = count($items);
    $per_page = 50;
    $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $total_pages = max(1, (int) ceil($total_items / $per_page));

    if ($paged > $total_pages) {
      $paged = $total_pages;
    }

    $items = array_slice($items, ($paged - 1) * $per_page, $per_page);



    $counts = $findings['counts'] ?? [];
    echo '<p>';
    echo '<strong>Last job:</strong> ' . \esc_html((string) ($job['type'] ?? '')) . ' — ' . \esc_html((string) ($job['status'] ?? '')) . '<br />';
    echo '<strong>Counts:</strong> Errors: ' . (int)($counts['error'] ?? 0) . ' | Warnings: ' . (int)($counts['warning'] ?? 0) . ' | OK: ' . (int)($counts['ok'] ?? 0);
    
    echo '</p>';

    $base = \admin_url('admin.php?page=fatm-results');
    echo '<p>';
    echo '<p><strong>Severity:</strong> ';
    echo '<a class="button" href="' . esc_url($this->build_url(['severity' => 'issues'])) . '">Issues</a> ';
    echo '<a class="button" href="' . esc_url($this->build_url(['severity' => 'error'])) . '">Errors</a> ';
    echo '<a class="button" href="' . esc_url($this->build_url(['severity' => 'warning'])) . '">Warnings</a> ';
    echo '<a class="button" href="' . esc_url($this->build_url(['severity' => 'ok'])) . '">OK</a> ';
    echo '</p>';

    echo '<p><strong>Source:</strong> ';
    $source_map = [
      '' => 'All',
      'media' => 'Media',
      'acf_image' => 'ACF Image',
      'acf_gallery' => 'ACF Gallery',
      'acf_wysiwyg' => 'ACF WYSIWYG',
    ];
    foreach ($source_map as $k => $label) {
      echo '<a class="button" href="' . esc_url($this->build_url(['source' => $k])) . '">' . esc_html($label) . '</a> ';
    }
    echo '</p>';

    // echo '<p><strong>Issue:</strong> ';
    // $issue_map = [
    //   '' => 'All',
    //   'missing_alt' => 'Missing alt',
    //   'alt_too_short' => 'Too short',
    //   'alt_generic' => 'Too generic',
    //   'alt_looks_like_filename' => 'Looks like filename',
    // ];
    // foreach ($issue_map as $k => $label) {
    //   echo '<a class="button" href="' . esc_url($this->build_url(['issue' => $k])) . '">' . esc_html($label) . '</a> ';
    // }
    // echo '</p>';

    echo '<form method="get" style="margin: 12px 0;">';
    echo '<input type="hidden" name="page" value="fatm-results" />';
    if ($job_id !== '') {
      echo '<input type="hidden" name="job" value="' . esc_attr($job_id) . '" />';
    }
    echo '<input type="hidden" name="severity" value="' . esc_attr($sev) . '" />';
    if ($source_filter !== '') echo '<input type="hidden" name="source" value="' . esc_attr($source_filter) . '" />';
    if ($issue_filter !== '') echo '<input type="hidden" name="issue" value="' . esc_attr($issue_filter) . '" />';
    echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Search results…" style="min-width:280px;" /> ';
    echo '<button class="button">Search</button> ';
    echo '<a class="button" href="' . esc_url($this->build_url(['s' => ''])) . '">Clear</a>';
    echo '</form>';


    echo '<p class="description">Showing ' . (int) $total_items . ' result(s). Page ' . (int) $paged . ' of ' . (int) $total_pages . '.</p>';

    echo '<p>';
    if ($paged > 1) {
      echo '<a class="button" href="' . esc_url($this->build_url(['paged' => $paged - 1])) . '">Prev</a> ';
    }
    if ($paged < $total_pages) {
      echo '<a class="button" href="' . esc_url($this->build_url(['paged' => $paged + 1])) . '">Next</a> ';
    }
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
    echo '<th>Action</th>';
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
      $img_src = (string) ($row['img_src'] ?? '');
      $attachment_alt = (string) ($row['attachment_alt'] ?? '');

      $action_url = '';
      $action_label = '';

      if ($source === 'acf_wysiwyg') {
        if ($post_id && $post_link) {
          $action_url = $post_link;
          $action_label = 'Edit page';
        } elseif ($id > 0 && $edit_link) {
          // fallback
          $action_url = $edit_link;
          $action_label = 'Edit media';
        }
      } else {
        if ($id > 0 && $edit_link) {
          $action_url = $edit_link;
          $action_label = 'Edit media';
        } elseif ($post_id && $post_link) {
          // fallback
          $action_url = $post_link;
          $action_label = 'Edit page';
        }
      }

      if (!is_array($issues)) $issues = [];

      $file_link = $id ? \wp_get_attachment_url($id) : '';

      echo '<tr>';

      // source
      echo '<td>' . esc_html($source) . '</td>';

      // post link
      echo '<td>';
      if ($post_id && $post_link) {
        echo '<a href="' . esc_url($post_link) . '" target="_blank">' . esc_html($post_title ?: ('Post #' . $post_id)) . '</a>';
      } else {
        echo '—';
      }
      echo '</td>';

      // field path
      echo '<td>';
      echo $field_path !== '' ? '<code>' . esc_html($field_path) . '</code>' : '—';
      echo '</td>';

      // severity
      echo '<td><strong>' . \esc_html($sev) . '</strong></td>';

      // enhanced attachment display
      echo '<td>';
      if ($id > 0 && $edit_link) {
        $thumb = \wp_get_attachment_image($id, [60, 60], true, [
          'style' => 'width:60px;height:60px;object-fit:cover;border:1px solid #ccd0d4;border-radius:4px;display:block;',
        ]);

        echo '<div style="display:flex;gap:10px;align-items:flex-start;">';

        // thumbnail (clickable)
        if ($thumb) {
          echo '<a style="background: #999; border-radius: 4px;" href="' . \esc_url($edit_link) . '" style="flex:0 0 auto;">' . $thumb . '</a>';
        }

        // text meta
        echo '<div style="min-width:0;">';
        // echo '<a href="' . \esc_url($edit_link) . '"><strong>' . \esc_html($title ?: ('Attachment #' . $id)) . '</strong></a>';

        if ($file_name) {
          // echo '<div class="description" style="margin-top:2px;">' . \esc_html($file_name) . '</div>';
        }

        echo '</div>'; // meta
        echo '</div>'; // flex wrapper

      } elseif ($img_src !== '') {
        echo '<div style="display:flex;gap:10px;align-items:flex-start;">';

        // external/embedded thumbnail preview
        echo '<a href="' . esc_url($img_src) . '" target="_blank" rel="noopener" style="flex:0 0 auto;">';
        echo '<img src="' . esc_url($img_src) . '" alt="" style="width:60px;height:60px;object-fit:cover;border:1px solid #ccd0d4;border-radius:4px;display:block;" />';
        echo '</a>';

        echo '<div>';
        echo '<strong>Embedded image</strong>';
        echo '<div class="description"><a href="' . esc_url($img_src) . '" target="_blank" rel="noopener">View src</a></div>';
        echo '</div>';

        echo '</div>';

      } else {
        echo '—';
      }


      echo '</td>';

      // alt text
      echo '<td>';
        if (in_array('missing_alt', $issues, true)) {
          echo '<em>(no alt text)</em><br />';
        } else {
          echo '<code>' . \esc_html((string) ($row['alt'] ?? '')) . '</code>';
          echo '<div class="description">Trimmed: <code>' . \esc_html($alt_trimmed) . '</code> — Length: ' . (int)$alt_len . '</div>';

          // show attachment alt vs inline alt
          if ($attachment_alt !== '' && $source === 'acf_wysiwyg') {
            echo '<div class="description">Attachment alt: <code>' . esc_html($attachment_alt) . '</code></div>';
          }
        }
      echo '</td>';

      // why
      echo '<td>' . \esc_html($this->why_label($why)) . '</td>';

      // issues
      echo '<td>' . \esc_html(implode(', ', $issues)) . '</td>';

      // action
      echo '<td>';
      if ($action_url !== '') {
        echo '<a class="button button-small" target="_blank" href="' . esc_url($action_url) . '">' . esc_html($action_label) . '</a>';
      } else {
        echo '—';
      }
      echo '</td>';

      echo '</tr>';
    }

    if (empty($items)) {
      echo '<tr><td colspan="9">No rows for this filter.</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
  }

  // public function render(): void {
  //   if (!\current_user_can('manage_options')) {
  //     \wp_die('You do not have permission to access this page.');
  //   }

  //   $job = Jobs::get();

  //   echo '<div class="wrap">';
  //   echo '<h1>Alt Text Monitor Results</h1>';

  //   if (!$job || empty($job['id'])) {
  //     echo '<p>No scan has been run yet.</p>';
  //     echo '</div>';
  //     return;
  //   }

  //   $findings = Findings::get((string) $job['id']);
  //   if (!$findings || empty($findings['items'])) {
  //     echo '<p>No findings recorded for the last scan.</p>';
  //     echo '</div>';
  //     return;
  //   }

  //   $items = $findings['items'];

  //   foreach($items as $item){
  //     echo '<pre style="background: #fff;padding:12px;border:1px solid #ccd0d4;max-width:900px;overflow:auto;">';
  //     print_r($item);
  //     echo '</pre>';
  //   }

  // }
}
