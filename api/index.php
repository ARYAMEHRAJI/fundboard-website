<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$configPath = dirname(__DIR__) . '/config/config.php';
$config = is_file($configPath) ? require $configPath : require dirname(__DIR__) . '/config/config.example.php';
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Tehran');
$action = preg_replace('/[^a-z_]/', '', (string)($_GET['action'] ?? 'overview'));
$cacheDir = dirname(__DIR__) . '/storage/cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0750, true);
$cacheFile = $cacheDir . '/' . $action . '.json';
$ttl = max(15, (int)($config['cache']['ttl'] ?? 90));
if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
    readfile($cacheFile); exit;
}
$demo = [
 'overview' => [
   'status'=>'demo','updated_at'=>date(DATE_ATOM),
   'metrics'=>[
     ['key'=>'market_index','label'=>'شاخص کل بورس','value'=>null,'unit'=>'واحد','change'=>null,'source'=>'TSETMC'],
     ['key'=>'gold_18','label'=>'طلای ۱۸ عیار','value'=>null,'unit'=>'تومان','change'=>null,'source'=>'Tindex / TGJU'],
     ['key'=>'usd_free','label'=>'دلار آزاد','value'=>null,'unit'=>'تومان','change'=>null,'source'=>'Tindex / TGJU'],
     ['key'=>'bitcoin','label'=>'بیت‌کوین','value'=>null,'unit'=>'دلار','change'=>null,'source'=>'Crypto exchanges']
   ],
   'message'=>'اتصال زنده هنوز پیکربندی نشده است.'
 ],
 'funds'=>['status'=>'not_configured','items'=>[]],
 'stocks'=>['status'=>'not_configured','items'=>[]],
 'gold'=>['status'=>'not_configured','items'=>[]],
 'crypto'=>['status'=>'not_configured','items'=>[]],
 'codal'=>['status'=>'not_configured','items'=>[]]
];
/**
 * HTTP bridge contract: configured bridge endpoints should return JSON.
 * MCP stdio packages cannot be invoked by a normal PHP web request on shared hosting.
 */
$bridgeMap = [
 'stocks' => $config['sources']['tsetmc_bridge_url'] ?? '',
 'codal' => $config['sources']['codal_bridge_url'] ?? '',
 'crypto' => $config['sources']['crypto_bridge_url'] ?? ''
];
if (!empty($bridgeMap[$action])) {
    $ch = curl_init($bridgeMap[$action]);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>12,
      CURLOPT_HTTPHEADER=>['Accept: application/json','Authorization: Bearer '.($config['sources']['bridge_bearer_token'] ?? '')]
    ]);
    $body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body !== false && $status >= 200 && $status < 300) {
        json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) { file_put_contents($cacheFile, $body, LOCK_EX); echo $body; exit; }
    }
}
if ($action === 'overview' && !empty($config['sources']['tindex_token'])) {
    // Tindex integration point. Keep upstream credentials server-side.
    // Endpoint schemas can change; adapter activation is deliberately explicit.
}
$out = $demo[$action] ?? ['status'=>'unknown_action','items'=>[]];
$json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo $json;
