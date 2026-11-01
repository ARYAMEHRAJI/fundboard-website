<?php
declare(strict_types=1);

/** cPanel cron entry point. CLI only; no VPS or GitHub Actions required. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
$config = is_file($configFile) ? require $configFile : require $root . '/config/config.example.php';
$base = rtrim((string)($config['app']['url'] ?? ''), '/');
if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL) || parse_url($base, PHP_URL_SCHEME) !== 'https') {
    fwrite(STDERR, "Set app.url to your HTTPS domain in config/config.php\n");
    exit(1);
}
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Tehran');
$lock = fopen($root . '/storage/refresh.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { fwrite(STDOUT, "Already running; skipped.\n"); exit(0); }

$logDir = $root . '/storage/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
$targets = ['overview','board&key=currency','board&key=gold','board&key=crypto','board&key=market','movers'];
$results = [];
foreach ($targets as $target) {
    $ch = curl_init($base . '/api/?action=' . $target);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>18,
        CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_HTTPHEADER=>['Accept: application/json'], CURLOPT_USERAGENT=>'FundBoard-Cron/1.0'
    ]);
    $body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
    $json = is_string($body) ? json_decode($body, true) : null;
    $ok = $status >= 200 && $status < 300 && is_array($json) && ($json['success'] ?? false) === true;
    $results[] = ['target'=>$target,'ok'=>$ok,'http'=>$status,'status'=>$json['status'] ?? 'invalid_response','error'=>$error ?: null];
}
$line = json_encode(['time'=>date(DATE_ATOM),'results'=>$results], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;
file_put_contents($logDir . '/refresh-' . date('Y-m-d') . '.log', $line, FILE_APPEND|LOCK_EX);
foreach ($results as $r) fwrite(STDOUT, ($r['ok']?'OK ':'FAIL ') . $r['target'] . ' [' . $r['status'] . "]\n");
flock($lock, LOCK_UN); fclose($lock);
exit(count(array_filter($results, static fn(array $r): bool => !$r['ok'])) ? 2 : 0);
