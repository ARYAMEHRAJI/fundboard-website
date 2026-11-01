<?php
declare(strict_types=1);

/**
 * Native PHP adapters for public market sources.
 * No MCP runtime, Python daemon, VPS, or external worker is used.
 */
function fb_http_json(string $url, array $query = [], int $timeout = 14, array $headers = []): array {
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $allowed = ['cdn.tsetmc.com','search.codal.ir','www.codal.ir','apiv2.nobitex.ir','api.wallex.ir','api.bitpin.ir'];
    if (!in_array($host, $allowed, true)) {
        throw new RuntimeException('Upstream host is not allow-listed.');
    }
    if ($host === 'search.codal.ir' || $host === 'www.codal.ir') {
        $lockPath = dirname(__DIR__) . '/storage/codal-rate.lock';
        $lock = fopen($lockPath, 'c+');
        if ($lock) {
            flock($lock, LOCK_EX);
            $last = (float)trim((string)stream_get_contents($lock));
            $wait = 2.2 - (microtime(true) - $last);
            if ($wait > 0) usleep((int)($wait * 1000000));
            ftruncate($lock, 0); rewind($lock); fwrite($lock, (string)microtime(true)); fflush($lock);
            flock($lock, LOCK_UN); fclose($lock);
        }
    }
    if ($query) $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_HTTPHEADER=>array_merge(['Accept: application/json','Accept-Language: fa-IR,fa;q=0.9,en;q=0.8',
            'Referer: https://'.($host==='cdn.tsetmc.com'?'main.tsetmc.com':'www.codal.ir').'/'], $headers),
        CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36'
    ]);
    $body=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    if (!is_string($body)) throw new RuntimeException('خطای ارتباط با منبع داده: '.$err);
    if ($status===429) throw new RuntimeException('منبع داده محدودیت درخواست اعمال کرده؛ کمی بعد دوباره تلاش کنید.');
    if ($status<200 || $status>=300) throw new RuntimeException('منبع داده پاسخ HTTP '.$status.' برگرداند.');
    $data=json_decode($body,true);
    if (!is_array($data)) throw new RuntimeException('پاسخ منبع داده JSON معتبر نیست.');
    return $data;
}
function fb_cache_module(string $key, int $ttl, callable $callback): array {
    $dir=dirname(__DIR__).'/storage/cache';
    if (!is_dir($dir)) @mkdir($dir,0750,true);
    $file=$dir.'/module-'.hash('sha256',$key).'.json';
    if (is_file($file) && time()-filemtime($file)<$ttl) {
        $v=json_decode((string)file_get_contents($file),true); if (is_array($v)) return $v;
    }
    $v=$callback();
    @file_put_contents($file,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    return $v;
}
function fb_http_text(string $url, int $timeout=20): string {
    $host=strtolower((string)parse_url($url,PHP_URL_HOST));
    if($host!=='www.codal.ir')throw new RuntimeException('Codal page host is not allowed.');
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml','Accept-Language: fa-IR,fa;q=0.9'],CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36']);
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    if(!is_string($body)||$status<200||$status>=300)throw new RuntimeException('دریافت صفحه کدال ناموفق بود: HTTP '.$status.' '.$err);
    return $body;
}
function fb_fa(string $s): string { return trim(strtr($s,['ي'=>'ی','ك'=>'ک','ۀ'=>'ه'])); }
function fb_tsetmc(string $tool, array $p): array {
    $base='https://cdn.tsetmc.com/api';
    $symbol=fb_fa((string)($p['symbol']??$p['query']??''));
    $resolve=function() use($base,$symbol): array {
        if ($symbol==='') throw new InvalidArgumentException('نماد را وارد کنید.');
        if (ctype_digit($symbol) && strlen($symbol)>=10) return [$symbol,null];
        $d=fb_http_json($base.'/Instrument/GetInstrumentSearch/'.rawurlencode($symbol));
        $hits=$d['instrumentSearch']??[]; $norm=fn($v)=>preg_replace('/[\s.‌-]+/u','',mb_strtolower(fb_fa((string)$v)));
        foreach(['lVal18AFC','lVal30'] as $field) foreach($hits as $h) if($norm($h[$field]??'')===$norm($symbol)) return [(string)$h['insCode'],fb_fa((string)$h['lVal18AFC'])];
        if(count($hits)===1) return [(string)$hits[0]['insCode'],fb_fa((string)$hits[0]['lVal18AFC'])];
        return [null,$hits];
    };
    $pct=fn($a,$b)=>($a && $b)?round(($a-$b)/$b*100,2):null;
    $num=fn($x)=>is_numeric($x)?(0+(float)$x):null;
    if($tool==='search'){
        if($symbol==='') throw new InvalidArgumentException('نام نماد یا شرکت را وارد کنید.');
        $d=fb_http_json($base.'/Instrument/GetInstrumentSearch/'.rawurlencode($symbol));
        return ['matches'=>array_map(fn($x)=>['symbol'=>fb_fa((string)($x['lVal18AFC']??'')),'name'=>fb_fa((string)($x['lVal30']??'')),'ins_code'=>$x['insCode']??null,'market'=>fb_fa((string)($x['flowTitle']??''))],array_slice($d['instrumentSearch']??[],0,max(1,min(30,(int)($p['limit']??10)))))];
    }
    if($tool==='indices'){
        $o=fb_http_json($base.'/MarketData/GetMarketOverview/1')['marketOverview']??[];
        $idx=fb_http_json($base.'/Index/GetIndexB1LastAll/All/1')['indexB1']??[];
        $q=fb_fa((string)($p['query']??'')); if($q!=='') $idx=array_values(array_filter($idx,fn($x)=>mb_stripos(fb_fa((string)($x['lVal30']??'')),$q)!==false));
        usort($idx,fn($a,$b)=>(float)($b['xVarIdxJRfV']??0)<=>(float)($a['xVarIdxJRfV']??0));
        return ['market'=>['state'=>$o['marketStateTitle']??null,'total_index'=>$num($o['indexLastValue']??null),'total_index_change'=>$num($o['indexChange']??null),'equal_weight_index'=>$num($o['indexEqualWeightedLastValue']??null),'trades'=>$num($o['marketActivityZTotTran']??null),'volume'=>$num($o['marketActivityQTotTran']??null),'value_rial'=>$num($o['marketActivityQTotCap']??null)],'indices'=>array_map(fn($x)=>['name'=>fb_fa((string)($x['lVal30']??'')),'ins_code'=>$x['insCode']??null,'value'=>$num($x['xDrNivJIdx004']??null),'change_pct'=>$num($x['xVarIdxJRfV']??null)],array_slice($idx,0,60))];
    }
    if($tool==='movers'){
        $market=(string)($p['market']??'bourse'); $map=['bourse'=>1,'farabourse'=>2,'rights'=>4,'bonds'=>5];
        if(!isset($map[$market])) throw new InvalidArgumentException('بازار نامعتبر است.');
        $d=fb_http_json($base.'/ClosingPrice/GetMarketWatch',['market'=>0,'paperTypes[0]'=>$map[$market],'showTraded'=>'true','withBestLimits'=>'false','hEven'=>0,'RefID'=>0]);
        $rows=array_values(array_filter($d['marketwatch']??[],fn($r)=>!empty($r['pdv'])));
        $sort=(string)($p['sort_by']??'gain'); $key=['gain'=>'change','loss'=>'change','volume'=>'qtj','value'=>'qtc','trades'=>'ztt'][$sort]??'change';
        usort($rows,function($a,$b)use($key,$sort,$pct){$av=$key==='change'?($pct($a['pdv']??null,$a['py']??null)??0):($a[$key]??0);$bv=$key==='change'?($pct($b['pdv']??null,$b['py']??null)??0):($b[$key]??0);return ($sort==='loss'?$av<=>$bv:$bv<=>$av);});
        return ['unit'=>'rial','rows'=>array_map(fn($r)=>['symbol'=>fb_fa((string)($r['lva']??'')),'name'=>fb_fa((string)($r['lvc']??'')),'ins_code'=>$r['insCode']??null,'last'=>$num($r['pdv']??null),'change_pct'=>$pct($r['pdv']??null,$r['py']??null),'volume'=>$num($r['qtj']??null),'trades'=>$num($r['ztt']??null),'value_rial'=>$num($r['qtc']??null),'limit_up'=>isset($r['pMax'])&&$r['pdv']>=$r['pMax'],'limit_down'=>isset($r['pMin'])&&$r['pdv']<=$r['pMin']],array_slice($rows,0,30))];
    }
    [$ins,$ticker]=$resolve();
    if(!$ins) throw new RuntimeException('نماد پیدا نشد یا چند نتیجه مشابه دارد؛ ابتدا جست‌وجوی نماد را اجرا کنید.');
    if($tool==='quote'){
        $i=fb_http_json($base.'/Instrument/GetInstrumentInfo/'.$ins)['instrumentInfo']??[];
        $c=fb_http_json($base.'/ClosingPrice/GetClosingPriceInfo/'.$ins)['closingPriceInfo']??[];
        return ['symbol'=>$ticker??fb_fa((string)($i['lVal18AFC']??'')),'name'=>fb_fa((string)($i['lVal30']??'')),'unit'=>'rial','date'=>$c['dEven']??null,'last'=>$num($c['pDrCotVal']??null),'close'=>$num($c['pClosing']??null),'yesterday'=>$num($c['priceYesterday']??null),'change_pct'=>$pct($c['pDrCotVal']??null,$c['priceYesterday']??null),'open'=>$num($c['priceFirst']??null),'low'=>$num($c['priceMin']??null),'high'=>$num($c['priceMax']??null),'volume'=>$num($c['qTotTran5J']??null),'trades'=>$num($c['zTotTran']??null),'value_rial'=>$num($c['qTotCap']??null),'shares'=>$num($i['zTitad']??null),'sector'=>fb_fa((string)($i['sector']['lSecVal']??''))];
    }
    if($tool==='orderbook'){
        $levels=fb_http_json($base.'/BestLimits/'.$ins)['bestLimits']??[];
        return ['symbol'=>$ticker,'unit'=>'rial','levels'=>array_map(fn($b)=>['level'=>$b['number']??null,'bid_price'=>$num($b['pMeDem']??null),'bid_volume'=>$num($b['qTitMeDem']??null),'bid_orders'=>$b['zOrdMeDem']??null,'ask_price'=>$num($b['pMeOf']??null),'ask_volume'=>$num($b['qTitMeOf']??null),'ask_orders'=>$b['zOrdMeOf']??null],$levels)];
    }
    if($tool==='history'||$tool==='flow'){
        $days=max(1,min($tool==='flow'?10:50,(int)($p['days']??20)));
        $rows=fb_http_json($base.'/ClosingPrice/GetClosingPriceDailyList/'.$ins.'/'.$days)['closingPriceDaily']??[];
        if($tool==='history') return ['symbol'=>$ticker,'unit'=>'rial','rows'=>array_map(fn($r)=>['date'=>$r['dEven']??null,'open'=>$num($r['priceFirst']??null),'high'=>$num($r['priceMax']??null),'low'=>$num($r['priceMin']??null),'close'=>$num($r['pClosing']??null),'last'=>$num($r['pDrCotVal']??null),'volume'=>$num($r['qTotTran5J']??null),'trades'=>$num($r['zTotTran']??null),'value_rial'=>$num($r['qTotCap']??null)],$rows)];
        $out=[]; foreach($rows as $r){$d=(string)($r['dEven']??'');if(!$d)continue;$c=fb_http_json($base.'/ClientType/GetClientTypeHistory/'.$ins.'/'.$d)['clientType']??null;if($c)$out[]=['date'=>$d,'real_buy_volume'=>$num($c['buy_I_Volume']??null),'real_sell_volume'=>$num($c['sell_I_Volume']??null),'legal_buy_volume'=>$num($c['buy_N_Volume']??null),'legal_sell_volume'=>$num($c['sell_N_Volume']??null),'net_real_volume'=>$num(($c['buy_I_Volume']??0)-($c['sell_I_Volume']??0))];}
        return ['symbol'=>$ticker,'unit'=>'rial','rows'=>$out];
    }
    if($tool==='index_history'){
        $index=fb_fa((string)($p['index']??'شاخص کل'));$idx=fb_http_json($base.'/Index/GetIndexB1LastAll/All/1')['indexB1']??[];
        $hits=array_values(array_filter($idx,fn($x)=>mb_stripos(fb_fa((string)($x['lVal30']??'')),$index)!==false));
        if(!$hits) throw new RuntimeException('شاخص موردنظر پیدا نشد.');
        $code=$hits[0]['insCode'];$history=fb_http_json($base.'/Index/GetIndexB2History/'.$code)['indexB2']??[];
        return ['index'=>fb_fa((string)$hits[0]['lVal30']),'rows'=>array_slice($history,-max(2,min(100,(int)($p['days']??30))))];
    }
    throw new InvalidArgumentException('ابزار TSETMC ناشناخته است.');
}
function fb_codal(string $tool, array $p): array {
    $base='https://search.codal.ir/api/search';
    if($tool==='company'){
        $d=fb_http_json($base.'/v1/companies',[],25);
        $q=fb_fa((string)($p['query']??''));$norm=fn($v)=>preg_replace('/[\s‌]+/u','',mb_strtolower(fb_fa((string)$v)));
        $rows=array_values(array_filter($d,fn($x)=>$q!==''&&(mb_stripos(fb_fa((string)($x['sy']??'')),$q)!==false||mb_stripos(fb_fa((string)($x['n']??'')),$q)!==false)));
        return ['matched'=>count($rows),'companies'=>array_slice(array_map(fn($x)=>['symbol'=>fb_fa((string)($x['sy']??'')),'name'=>fb_fa((string)($x['n']??'')),'industry_code'=>$x['i']??null,'market_status'=>$x['st']??null,'publisher_type'=>$x['t']??null],$rows),0,50)];
    }
    if($tool==='categories'){
        $d=fb_http_json($base.'/v1/categories',[],20);
        return ['categories'=>$d];
    }
    if($tool==='financial_years'){
        $sym=fb_fa((string)($p['symbol']??''));if($sym==='')throw new InvalidArgumentException('نماد ناشر را وارد کنید.');
        return ['symbol'=>$sym,'year_ends'=>fb_http_json($base.'/v1/financialYears',['Symbol'=>$sym],20)];
    }
    if($tool==='search_letters'){
        $params=['Audited'=>'true','AuditorRef'=>-1,'Category'=>(int)($p['category']??-1),'Childs'=>'true','CompanyState'=>-1,'CompanyType'=>-1,'Consolidatable'=>'true','IsNotAudited'=>'false','Length'=>(int)($p['period_months']??-1),'LetterType'=>(int)($p['letter_type']??-1),'Mains'=>'true','NotAudited'=>'true','NotConsolidatable'=>'true','PageNumber'=>max(1,(int)($p['page']??1)),'Publisher'=>'false','ReportingType'=>-1,'TracingNo'=>(int)($p['tracing_no']??-1),'search'=>'true'];
        if(!empty($p['symbol']))$params['Symbol']=fb_fa((string)$p['symbol']);
        if(!empty($p['title']))$params['Subject']=fb_fa((string)$p['title']);
        if(!empty($p['from_date']))$params['FromDate']=$p['from_date'];
        if(!empty($p['to_date']))$params['ToDate']=$p['to_date'];
        $d=fb_http_json($base.'/v2/q',$params,20);
        return ['total'=>$d['Total']??0,'page'=>$params['PageNumber'],'pages'=>$d['Page']??1,'letters'=>array_map(fn($x)=>['tracing_no'=>$x['TracingNo']??null,'symbol'=>fb_fa((string)($x['Symbol']??'')),'company'=>fb_fa((string)($x['CompanyName']??'')),'title'=>fb_fa((string)($x['Title']??'')),'sent'=>$x['SentDateTime']??null,'url'=>isset($x['Url'])?'https://www.codal.ir'.$x['Url']:null,'pdf'=>!empty($x['HasPdf'])?('https://www.codal.ir'.($x['PdfUrl']??'')):null],$d['Letters']??[])];
    }
    if($tool==='letter'){
        $trace=max(1,(int)($p['tracing_no']??0)); if(!$trace)throw new InvalidArgumentException('شماره پیگیری اطلاعیه را وارد کنید.');
        $found=fb_http_json($base.'/v2/q',['Audited'=>'true','AuditorRef'=>-1,'Category'=>-1,'Childs'=>'true','CompanyState'=>-1,'CompanyType'=>-1,'Consolidatable'=>'true','IsNotAudited'=>'false','Length'=>-1,'LetterType'=>-1,'Mains'=>'true','NotAudited'=>'true','NotConsolidatable'=>'true','PageNumber'=>1,'Publisher'=>'false','ReportingType'=>-1,'TracingNo'=>$trace,'search'=>'true'],20);
        $letter=null;foreach(($found['Letters']??[]) as $row)if((int)($row['TracingNo']??0)===$trace){$letter=$row;break;}
        if(!$letter)throw new RuntimeException('اطلاعیه‌ای با این شماره پیگیری پیدا نشد.');
        $out=['tracing_no'=>$trace,'symbol'=>fb_fa((string)($letter['Symbol']??'')),'company'=>fb_fa((string)($letter['CompanyName']??'')),'title'=>fb_fa((string)($letter['Title']??'')),'sent'=>$letter['SentDateTime']??null,'url'=>isset($letter['Url'])?'https://www.codal.ir'.($letter['Url'][0]==='/'?$letter['Url']:'/'. $letter['Url']):null];
        if(!empty($letter['PdfUrl']))$out['pdf']='https://www.codal.ir'.($letter['PdfUrl'][0]==='/'?$letter['PdfUrl']:'/'. $letter['PdfUrl']);
        if(!empty($letter['ExcelUrl']))$out['excel']='https://www.codal.ir'.($letter['ExcelUrl'][0]==='/'?$letter['ExcelUrl']:'/'. $letter['ExcelUrl']);
        if(!empty($letter['Url']) && !empty($letter['HasHtml']) && str_contains((string)$letter['Url'],'Decision.aspx')){
            $page='https://www.codal.ir'.($letter['Url'][0]==='/'?$letter['Url']:'/'. $letter['Url']);
            $html=fb_http_text($page);
            if(preg_match('/var datasource = /',$html,$m,PREG_OFFSET_CAPTURE)){
                $start=$m[0][1]+strlen($m[0][0]);$json=substr($html,$start);$depth=0;$quoted=false;$escape=false;$end=0;
                for($i=0,$n=strlen($json);$i<$n;$i++){ $ch=$json[$i]; if($quoted){if($escape)$escape=false;elseif($ch==='\\\\')$escape=true;elseif($ch==='"')$quoted=false;continue;} if($ch==='"'){$quoted=true;continue;} if($ch==='{')$depth++;elseif($ch==='}'){$depth--;if($depth===0){$end=$i+1;break;}}}
                $ds=$end?json_decode(substr($json,0,$end),true):null;
                if(is_array($ds)){
                    $out['report']=$ds['title_Fa']??null;$out['period_months']=$ds['period']??null;$out['period_end']=$ds['periodEndToDate']??null;
                    $sheets=[];$tables=[];
                    foreach(($ds['sheets']??[]) as $sheet){
                        $sheets[]=['sheet'=>$sheet['id']??null,'name'=>$sheet['title_Fa']??''];
                        foreach(($sheet['tables']??[]) as $table){
                            $rows=[];foreach(($table['cells']??[]) as $cell){$seq=(int)($cell['rowSequence']??0);if(!isset($rows[$seq]))$rows[$seq]=[];$rows[$seq][(int)($cell['columnSequence']??0)]=(string)($cell['value']??'');}
                            ksort($rows);$lines=[];foreach($rows as $cells){ksort($cells);$vals=array_values($cells);while($vals && end($vals)==='')array_pop($vals);if($vals)$lines[]=implode(' | ',$vals);}
                            if($lines)$tables[]=['title'=>$table['title_Fa']??($sheet['title_Fa']??''),'rows'=>$lines];
                        }
                    }
                    $out['sheets']=$sheets;$out['tables']=$tables;
                }else{$out['note']='متن صفحه دریافت شد، اما ساختار جدول قابل‌استخراج نبود.';}
            }
        }
        return $out;
    }
    throw new InvalidArgumentException('ابزار کدال ناشناخته است.');
}
function fb_crypto(string $tool, array $p): array {
    $exchanges=[
      ['id'=>'nobitex','name'=>'نوبیتکس','unit'=>'ریال','orderbook'=>true,'trades'=>true,'candles'=>true],
      ['id'=>'wallex','name'=>'والکس','unit'=>'تومان','orderbook'=>true,'trades'=>true,'candles'=>true],
      ['id'=>'bitpin','name'=>'بیت‌پین','unit'=>'تومان','orderbook'=>true,'trades'=>true,'candles'=>true],
      ['id'=>'ramzinex','name'=>'رمزینکس','unit'=>'ریال','orderbook'=>true,'trades'=>true,'candles'=>true],
      ['id'=>'tabdeal','name'=>'تبدیل','unit'=>'تومان','orderbook'=>true,'trades'=>true,'candles'=>true],
      ['id'=>'ompfinex','name'=>'او‌ام‌پی فینکس','unit'=>'ریال','orderbook'=>true,'trades'=>false,'candles'=>true],
      ['id'=>'exir','name'=>'اکسیر','unit'=>'تومان','orderbook'=>true,'trades'=>true,'candles'=>true],
      ['id'=>'bit24','name'=>'بیت۲۴','unit'=>'تومان','orderbook'=>true,'trades'=>true,'candles'=>true],
      ['id'=>'abantether','name'=>'آبان‌تتر','unit'=>'تومان','orderbook'=>false,'trades'=>false,'candles'=>true],
      ['id'=>'kifpool','name'=>'کیف پول من','unit'=>'تومان','orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'okex','name'=>'اوکی‌اکسچنج','unit'=>'تومان','orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'tetherland','name'=>'تترلند','unit'=>'تومان','orderbook'=>false,'trades'=>false,'candles'=>false]
    ];
    if($tool==='exchanges') return ['exchanges'=>$exchanges,'note'=>'قیمت زنده در این نسخه برای نوبیتکس، والکس و بیت‌پین فعال شده است؛ سایر صرافی‌ها در فهرست شناسایی شده‌اند اما اتصال عملیاتی آن‌ها هنوز تکمیل نشده است.'];
    $coin=strtoupper(preg_replace('/[^A-Z0-9_]/','',(string)($p['coin']??'BTC')));
    if($coin==='')throw new InvalidArgumentException('نماد رمزارز را وارد کنید.');
    $quote=(string)($p['quote']??'IRT');if(!in_array($quote,['IRT','USDT'],true))throw new InvalidArgumentException('واحد قیمت نامعتبر است.');
    $getTicker=function(string $exchange)use($coin,$quote):array{
        if($exchange==='nobitex'){
            $stats=fb_http_json('https://apiv2.nobitex.ir/market/stats')['stats']??[];$suffix=$quote==='IRT'?'rls':'usdt';$v=$stats[strtolower($coin).'-'.$suffix]??null;
            if(!$v)throw new RuntimeException('بازار در نوبیتکس یافت نشد.');
            return ['exchange'=>'nobitex','name'=>'نوبیتکس','price'=>isset($v['latest'])?(float)$v['latest']/10:null,'bid'=>isset($v['bestBuy'])?(float)$v['bestBuy']/10:null,'ask'=>isset($v['bestSell'])?(float)$v['bestSell']/10:null,'high'=>isset($v['dayHigh'])?(float)$v['dayHigh']/10:null,'low'=>isset($v['dayLow'])?(float)$v['dayLow']/10:null,'change_pct'=>isset($v['dayChange'])?(float)$v['dayChange']:null,'unit'=>$quote==='IRT'?'toman':'usdt'];
        }
        if($exchange==='wallex'){
            $symbols=fb_http_json('https://api.wallex.ir/v1/markets')['result']['symbols']??[];
            foreach($symbols as $m){if(strtoupper((string)($m['baseAsset']??''))===$coin && strtoupper((string)($m['quoteAsset']??''))===($quote==='IRT'?'TMN':'USDT')){$s=$m['stats']??[];return ['exchange'=>'wallex','name'=>'والکس','price'=>isset($s['lastPrice'])?(float)$s['lastPrice']:null,'bid'=>isset($s['bidPrice'])?(float)$s['bidPrice']:null,'ask'=>isset($s['askPrice'])?(float)$s['askPrice']:null,'high'=>isset($s['24h_highPrice'])?(float)$s['24h_highPrice']:null,'low'=>isset($s['24h_lowPrice'])?(float)$s['24h_lowPrice']:null,'change_pct'=>isset($s['24h_ch'])?(float)$s['24h_ch']:null,'unit'=>$quote==='IRT'?'toman':'usdt'];}}
            throw new RuntimeException('بازار در والکس یافت نشد.');
        }
        if($exchange==='bitpin'){
            $rows=fb_http_json('https://api.bitpin.ir/api/v1/mkt/tickers/');
            foreach($rows as $t){$parts=explode('_',(string)($t['symbol']??''));if(strtoupper($parts[0]??'')===$coin && strtoupper($parts[1]??'')===$quote){return ['exchange'=>'bitpin','name'=>'بیت‌پین','price'=>isset($t['price'])?(float)$t['price']:null,'bid'=>null,'ask'=>null,'high'=>isset($t['high'])?(float)$t['high']:null,'low'=>isset($t['low'])?(float)$t['low']:null,'change_pct'=>isset($t['daily_change_price'])?(float)$t['daily_change_price']:null,'unit'=>$quote==='IRT'?'toman':'usdt'];}}
            throw new RuntimeException('بازار در بیت‌پین یافت نشد.');
        }
        throw new RuntimeException('اتصال این صرافی هنوز تکمیل نشده است.');
    };
    if($tool==='ticker'){
        $exchange=(string)($p['exchange']??'wallex');
        return $getTicker($exchange);
    }
    if($tool==='compare'){
        $rows=[];$errors=[];
        foreach(['nobitex','wallex','bitpin'] as $exchange){try{$rows[]=$getTicker($exchange);}catch(Throwable $e){$errors[$exchange]=$e->getMessage();}}
        $rows=array_values(array_filter($rows,fn($x)=>$x['price']!==null));usort($rows,fn($a,$b)=>$a['price']<=>$b['price']);
        $out=['coin'=>$coin,'quote'=>$quote,'unit'=>$quote==='IRT'?'toman':'usdt','prices'=>$rows,'errors'=>$errors];
        if(count($rows)>1){$out['cheapest']=$rows[0]['exchange'];$out['priciest']=$rows[count($rows)-1]['exchange'];$out['spread_pct']=round(($rows[count($rows)-1]['price']/$rows[0]['price']-1)*100,3);}
        return $out;
    }
    if($tool==='markets'){
        $exchange=(string)($p['exchange']??'wallex');
        if($exchange==='wallex'){$symbols=fb_http_json('https://api.wallex.ir/v1/markets')['result']['symbols']??[];$coins=[];foreach($symbols as $m)if(($m['quoteAsset']??'')===($quote==='IRT'?'TMN':'USDT'))$coins[]=$m['baseAsset'];return ['exchange'=>'wallex','quote'=>$quote,'coins'=>array_values(array_unique($coins))];}
        if($exchange==='nobitex'){$stats=fb_http_json('https://apiv2.nobitex.ir/market/stats')['stats']??[];$coins=[];foreach(array_keys($stats) as $key){[$base,$q]=array_pad(explode('-',$key,2),2,'');if($q===($quote==='IRT'?'rls':'usdt'))$coins[]=strtoupper($base);}return ['exchange'=>'nobitex','quote'=>$quote,'coins'=>$coins];}
        if($exchange==='bitpin'){$rows=fb_http_json('https://api.bitpin.ir/api/v1/mkt/tickers/');$coins=[];foreach($rows as $t){$parts=explode('_',(string)($t['symbol']??''));if(strtoupper($parts[1]??'')===$quote)$coins[]=strtoupper($parts[0]);}return ['exchange'=>'bitpin','quote'=>$quote,'coins'=>array_values(array_unique($coins))];}
        throw new RuntimeException('فهرست بازار این صرافی هنوز متصل نشده است.');
    }
    throw new RuntimeException('ابزار انتخابی رمزارز هنوز به API عمومی صرافی‌ها متصل نشده است.');
}
function fb_dispatch_module(string $module,string $tool,array $p): array {
    $ttl=$module==='codal'?3600:($module==='crypto'?30:15);
    return fb_cache_module($module.':'.$tool.':'.http_build_query($p),$ttl,function()use($module,$tool,$p){
        if($module==='tsetmc')return fb_tsetmc($tool,$p);
        if($module==='codal')return fb_codal($tool,$p);
        if($module==='crypto')return fb_crypto($tool,$p);
        throw new InvalidArgumentException('ماژول ناشناخته است.');
    });
}
