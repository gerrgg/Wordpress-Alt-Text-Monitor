<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Admin\Support;

final class Debug {
  public static function json_details(string $label, $data): void {
    echo '<details style="margin: 10px 0;">';
    echo '<summary style="cursor:pointer;"><strong>' . esc_html($label) . '</strong></summary>';
    echo '<pre style="background:#fff;padding:12px;border:1px solid #ccd0d4;max-width:900px;overflow:auto;">'
      . esc_html(wp_json_encode($data, JSON_PRETTY_PRINT))
      . '</pre>';
    echo '</details>';
  }

  public static function section(string $label, callable $render_inner): void {
    echo '<details style="margin: 10px 0;">';
    echo '<summary style="cursor:pointer;"><strong>' . esc_html($label) . '</strong></summary>';
    echo '<div style="margin-top:10px;">';
    $render_inner();
    echo '</div>';
    echo '</details>';
  }
}
