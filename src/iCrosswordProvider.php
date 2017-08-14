<?php

namespace Gothick\Cruciverbal;

require_once __DIR__ . '/../vendor/autoload.php';

interface iCrosswordProvider {
  public function __construct($params = null);
  public function getPdfStreams();
}
