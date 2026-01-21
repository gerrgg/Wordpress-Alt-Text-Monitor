<?php
declare(strict_types=1);

namespace Floodlight\AltTextMonitor\Settings;

final class Defaults {
  public static function network(): array {
    return [
      'scan' => [
        'post_types' => ['post', 'page'],
        'days_back' => 0,
      ],
      'rules' => [
        'missing_alt_error' => true,
        'min_alt_length' => 5,
      ],
    ];
  }

  public static function site(): array {
    return [
      'use_network_defaults' => true,
      'scan' => [
        'post_types' => ['post', 'page'],
        'days_back' => 0,
      ],
      'rules' => [
        'missing_alt_error' => true,
        'min_alt_length' => 5,
        'detect_filename' => true,
        'generic_words' => 'image,photo,picture,graphic,logo,icon,banner,untitled,placeholder',
      ],
    ];
  }
}
