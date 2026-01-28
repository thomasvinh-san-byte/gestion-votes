<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
$file = __DIR__ . '/events.jsonl';
$start = time();
while (true) {
  if (is_readable($file)) {
    $fh = fopen($file, 'r');
    if ($fh) {
      while (($line = fgets($fh)) !== false) {
        $evt = json_decode($line, true);
        if (!$evt) continue;
        echo 'event: ' . $evt['type'] . "\n";
        echo 'data: ' . json_encode($evt['payload']) . "\n\n";
        @ob_flush(); @flush();
      }
      fclose($fh);
    }
  }
  echo "event: ping\n"; echo "data: {}\n\n"; @ob_flush(); @flush();
  if (time() - $start > 25) break;
  sleep(1);
}
