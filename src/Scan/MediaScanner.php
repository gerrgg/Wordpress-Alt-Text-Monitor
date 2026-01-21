<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Scan;
use Floodlight\AltTextMonitor\Scan\AltEvaluator;

final class MediaScanner {
  
  /**
   * @return array{rows: array<int,array>, done: bool, next_offset: int, total: int}
  */
  
  private AltEvaluator $evaluator;

  public function __construct(?AltEvaluator $evaluator = null) {
    $this->evaluator = $evaluator ?: new AltEvaluator();
  }

  public function scan_batch(int $offset, int $limit, array $settings, array $rules): array {
    // $days_back = (int) ($settings['scan']['days_back'] ?? 0);

    $args = [
      'post_type' => 'attachment',
      'post_status' => 'inherit',
      'post_mime_type' => 'image',
      'fields' => 'ids',
      'posts_per_page' => $limit,
      'offset' => $offset,
      'orderby' => 'date',
      'order' => 'DESC',
      'no_found_rows' => false,
    ];

    // we always do a full media scan for now

    // if ($days_back > 0) {
    //   $args['date_query'] = [[
    //     'column' => 'post_date',
    //     'after' => gmdate('Y-m-d H:i:s', time() - ($days_back * DAY_IN_SECONDS)),
    //     'inclusive' => true,
    //   ]];
    // }

    $q = new \WP_Query($args);

    $ids = array_map('intval', $q->posts);
    $total = (int) $q->found_posts;

    $rows = [];
    foreach ($ids as $attachment_id) {
      if ($attachment_id <= 0) continue;

      $rows[] = array_merge(
        ['source' => 'media'],
        $this->evaluator->evaluate_attachment($attachment_id, $rules)
      );
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

}
