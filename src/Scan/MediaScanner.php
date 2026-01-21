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
    foreach ($ids as $attachment_id) {
      if ($attachment_id <= 0) {
        continue;
      }

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
