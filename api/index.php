<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
$config = is_file($configPath) ? require $configPath : require $root . '/config/config.example.php';
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Tehran');

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function cached(string $key, int $ttl, callable $fetch, string $dir): array {
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $file = $dir . '/' . hash('sha256', $key) . '.json';
    if (is_file($file) && time() - filemtime($file) < $ttl) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    $data = $fetch();
    if (is_array($data)) @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $data;
}
function tindexGet(array $config, string $path): array {
    $token = trim((string)($config['sources']['tindex_token'] ?? ''));
    if ($token === '') return ['success'=>false,'status'=>'not_configured','message'=>'توکن Tindex در تنظیمات سرور وارد نشده است.'];
    $base = rtrim((string)($config['sources']['tindex_base_url'] ?? 'https://tindex.app'), '/');
    if (!str_starts_with($path, '/api/public/')) return ['success'=>false,'status'=>'invalid_path'];
    $url = $base . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 7,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_USERAGENT => 'FundBoard/1.0 (+https://fundboard.ir)'
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false) return ['success'=>false,'status'=>'upstream_error','message'=>'ارتباط با منبع داده برقرار نشد.','detail'=>$error];
    $json = json_decode($body, true);
    if (!is_array($json)) return ['success'=>false,'status'=>'invalid_response','message'=>'پاسخ منبع داده معتبر نیست.'];
    if ($http < 200 || $http >= 300 || ($json['success'] ?? true) === false) {
        return ['success'=>false,'status'=>'upstream_rejected','http_status'=>$http,'message'=>$json['message'] ?? 'درخواست منبع داده ناموفق بود.'];
    }
    return ['success'=>true,'status'=>'live','data'=>$json['data'] ?? $json,'fetched_at'=>date(DATE_ATOM)];
}

require_once __DIR__ . '/modules.php';

$action = preg_replace('/[^a-z_]/', '', (string)($_GET['action'] ?? 'overview'));

if ($action === 'search') {
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '' || mb_strlen($q) < 2 || mb_strlen($q) > 80) respond(['success'=>false,'status'=>'invalid_parameter','message'=>'حداقل دو حرف وارد کنید.'],400);
    $hits = ['funds'=>[],'stocks'=>[]];
    try {
        $boards = tindexGet($config, '/api/public/boards');
        if (($boards['status'] ?? '') === 'live') {
            foreach ((array)$boards['data'] as $board) foreach ((array)($board['rows'] ?? []) as $row) {
                $name = (string)($row['name'] ?? $row['title'] ?? '');
                $slug = (string)($row['slug'] ?? '');
                if ($name !== '' && (mb_stripos($name,$q)!==false || mb_stripos($slug,$q)!==false)) {
                    $hits['funds'][] = ['name'=>$name,'slug'=>$slug,'price'=>$row['price']??null,'change'=>$row['change']??null,'unit'=>$row['unit']??null,'board'=>$board['key']??null];
                }
            }
        }
    } catch (Throwable $e) {}
    try {
        $stocks = fb_tsetmc('search',['query'=>$q,'limit'=>12]);
        $hits['stocks'] = $stocks['matches'] ?? [];
    } catch (Throwable $e) {}
    respond(['success'=>true,'status'=>($hits['funds']||$hits['stocks'])?'partial':'empty','query'=>$q,'results'=>$hits,'note'=>'نتایج صندوق‌ها از تابلوهای Tindex و نمادها از جست‌وجوی TSETMC گردآوری می‌شوند.']);
}
if ($action === 'profile') {
    $kind = (string)($_GET['kind'] ?? '');
    $id = trim((string)($_GET['id'] ?? ''));
    $range = (string)($_GET['range'] ?? '1m');
    if (!in_array($range,['1d','1w','1m','3m','6m','1y','all'],true) || $id === '' || strlen($id)>100) respond(['success'=>false,'status'=>'invalid_parameter','message'=>'پارامترهای پروفایل نامعتبر است.'],400);
    try {
        if ($kind === 'stock') {
            $quote = fb_tsetmc('quote',['symbol'=>$id]);
            $days = ['1d'=>2,'1w'=>7,'1m'=>30,'3m'=>90,'6m'=>180,'1y'=>365,'all'=>500][$range];
            $hist = fb_tsetmc('history',['symbol'=>$id,'days'=>$days]);
            respond(['success'=>true,'status'=>'live','kind'=>'stock','profile'=>$quote,'history'=>$hist['rows']??[],'fetched_at'=>date(DATE_ATOM)]);
        }
        if ($kind === 'fund') {
            $boards = tindexGet($config,'/api/public/boards');
            if (($boards['status']??'') !== 'live') respond($boards,502);
            $found = null;
            foreach ((array)$boards['data'] as $board) foreach ((array)($board['rows']??[]) as $row) if ((string)($row['slug']??'') === $id) {$found=$row;break 2;}
            if (!$found) respond(['success'=>false,'status'=>'not_found','message'=>'صندوق در تابلوهای متصل Tindex پیدا نشد.'],404);
            $hist = tindexGet($config,'/api/public/indicators/'.rawurlencode($id).'/history?range='.$range);
            respond(['success'=>true,'status'=>'live','kind'=>'fund','profile'=>$found,'history'=>$hist['data']['points']??[],'labels'=>$hist['data']['labels']??$hist['data']['dates']??[],'history_status'=>$hist['status']??'unavailable','fetched_at'=>date(DATE_ATOM)]);
        }
        respond(['success'=>false,'status'=>'invalid_parameter','message'=>'نوع دارایی پشتیبانی نمی‌شود.'],400);
    } catch (Throwable $e) { respond(['success'=>false,'status'=>'upstream_error','message'=>$e->getMessage()],502); }
}

$ttl = max(20, min(600, (int)($config['cache']['ttl'] ?? 90)));
$cacheDir = $root . '/storage/cache';
$fetch = static function(string $path) use ($config, $ttl, $cacheDir): array {
    return cached($path, $ttl, fn() => tindexGet($config, $path), $cacheDir);
};

if ($action === 'overview') {
    $result = $fetch('/api/public/boards');
    if (($result['status'] ?? '') !== 'live') respond($result);
    $boards = $result['data'];
    $rows = [];
    foreach ((array)$boards as $board) foreach ((array)($board['rows'] ?? []) as $row) $rows[strtolower((string)($row['slug'] ?? ''))] = $row;
    $pick = static function(array $keys) use ($rows): ?array {
        foreach ($keys as $key) if (isset($rows[strtolower($key)])) return $rows[strtolower($key)];
        return null;
    };
    $metrics = [
        ['key'=>'market_index','label'=>'شاخص کل بورس'] + ($pick(['TEDPIX','TEDPIX-INDEX']) ?? []),
        ['key'=>'gold_18','label'=>'طلای ۱۸ عیار'] + ($pick(['GOLD-18K']) ?? []),
        ['key'=>'usd_free','label'=>'دلار آزاد'] + ($pick(['USD-EXCHANGE-RATE']) ?? []),
        ['key'=>'bitcoin','label'=>'بیت‌کوین'] + ($pick(['btc']) ?? [])
    ];
    respond(['success'=>true,'status'=>'live','metrics'=>$metrics,'boards'=>$boards,'fetched_at'=>$result['fetched_at']]);
}
if ($action === 'board') {
    $key = (string)($_GET['key'] ?? '');
    if (!in_array($key, ['currency','gold','crypto','market'], true)) respond(['success'=>false,'status'=>'invalid_parameter'], 400);
    respond($fetch('/api/public/boards/' . $key));
}
if ($action === 'movers') respond($fetch('/api/public/indicators/movers'));
if ($action === 'history') {
    $slug = (string)($_GET['slug'] ?? '');
    $range = (string)($_GET['range'] ?? '1m');
    if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $slug) || !in_array($range, ['1d','1w','1m','3m'], true)) respond(['success'=>false,'status'=>'invalid_parameter'], 400);
    respond($fetch('/api/public/indicators/' . rawurlencode($slug) . '/history?range=' . $range));
}
if ($action === 'category') {
    $slug = (string)($_GET['slug'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/', $slug)) respond(['success'=>false,'status'=>'invalid_parameter'], 400);
    respond($fetch('/api/public/indicators/by-category/' . rawurlencode($slug)));
}

if ($action === 'module') {
    $module = preg_replace('/[^a-z]/', '', (string)($_GET['module'] ?? ''));
    $tool = preg_replace('/[^a-z_]/', '', (string)($_GET['tool'] ?? ''));
    if (!in_array($module, ['tsetmc','codal','crypto'], true) || $tool === '') {
        respond(['success'=>false,'status'=>'invalid_parameter','message'=>'ماژول یا ابزار نامعتبر است.'],400);
    }
    $params = $_GET;
    unset($params['action'],$params['module'],$params['tool']);
    try {
        $data = fb_dispatch_module($module,$tool,$params);
        respond(['success'=>true,'status'=>'live','module'=>$module,'tool'=>$tool,'data'=>$data,'fetched_at'=>date(DATE_ATOM)]);
    } catch (Throwable $e) {
        $status = $e instanceof InvalidArgumentException ? 400 : 502;
        respond(['success'=>false,'status'=>'upstream_error','module'=>$module,'tool'=>$tool,'message'=>$e->getMessage()],$status);
    }
}

if ($action === 'bridge') {
    $source = (string)($_GET['source'] ?? '');
    $allowed = ['funds','stocks','codal','crypto'];
    if (!in_array($source, $allowed, true)) respond(['success'=>false,'status'=>'invalid_source'], 400);
    $bridgeKeys = ['funds'=>'funds_bridge_url','stocks'=>'tsetmc_bridge_url','codal'=>'codal_bridge_url','crypto'=>'crypto_bridge_url'];
    $url = trim((string)($config['sources'][$bridgeKeys[$source]] ?? ''));
    $bridgeToken = trim((string)($config['sources']['bridge_bearer_token'] ?? ''));
    if ($bridgeToken === '') respond(['success'=>false,'status'=>'not_configured','message'=>'توکن امنیتی Bridge تنظیم نشده است.']);
    if ($url === '') respond(['success'=>false,'status'=>'not_configured','message'=>'سرویس واسط این بخش تنظیم نشده است.']);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['https'], true)) respond(['success'=>false,'status'=>'invalid_bridge_url'], 500);
    $result = cached('bridge:' . $source, $ttl, static function() use ($url, $bridgeToken): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>7, CURLOPT_TIMEOUT=>20,
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>['Accept: application/json','Authorization: Bearer '.$bridgeToken]
        ]);
        $body = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $json = is_string($body) ? json_decode($body, true) : null;
        if ($http < 200 || $http >= 300 || !is_array($json)) return ['success'=>false,'status'=>'bridge_error','message'=>'سرویس واسط پاسخ معتبر نداد.'];
        return ['success'=>true,'status'=>'live','data'=>$json['data'] ?? $json,'fetched_at'=>date(DATE_ATOM)];
    }, $cacheDir);
    respond($result);
}

respond(['success'=>false,'status'=>'unknown_action','allowed'=>['overview','board','movers','history','category','bridge']], 404);
