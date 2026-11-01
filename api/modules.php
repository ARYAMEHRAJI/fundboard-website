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
    $lockPath=dirname(__DIR__).'/storage/codal-rate.lock';$lock=fopen($lockPath,'c+');
    if($lock){flock($lock,LOCK_EX);$last=(float)trim((string)stream_get_contents($lock));$wait=2.2-(microtime(true)-$last);if($wait>0)usleep((int)($wait*1000000));ftruncate($lock,0);rewind($lock);fwrite($lock,(string)microtime(true));fflush($lock);flock($lock,LOCK_UN);fclose($lock);}
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
    if($tool==='etf_screener'){
        $category=(string)($p['category']??'ETF');
        $allowed=['ETF','MostTradedETF','PClosingTopETF','PClosingBtmETF','CommodityFund','MostVisited'];
        if(!in_array($category,$allowed,true))throw new InvalidArgumentException('دسته‌بندی غربالگر ETF نامعتبر است.');
        $flow=max(0,min(4,(int)($p['flow']??0)));$top=max(1,min(100,(int)($p['limit']??50)));
        $d=fb_http_json($base.'/ClosingPrice/GetTradeTop/'.$category.'/'.$flow.'/'.$top,[],18);
        $items=$d['tradeTop']??[];$rows=[];
        foreach($items as $x){$inst=$x['instrument']??[];$rows[]=['ins_code'=>$x['insCode']??$inst['insCode']??null,'symbol'=>fb_fa((string)($inst['lVal18AFC']??$x['lVal18AFC']??'')),'name'=>fb_fa((string)($inst['lVal30']??$x['lVal30']??'')),'last'=>$num($x['pDrCotVal']??null),'close'=>$num($x['pClosing']??null),'change_pct'=>$num($x['priceChange']??$x['priceChangePercent']??null),'volume'=>$num($x['qTotTran5J']??null),'value_rial'=>$num($x['qTotCap']??null),'raw'=>$x];}
        return ['category'=>$category,'flow'=>$flow,'rows'=>$rows,'count'=>count($rows)];
    }
    if($tool==='market_map'){
        $market=max(0,min(4,(int)($p['market']??0)));
        $size=max(100,min(3000,(int)($p['limit']??1500)));
        $sector=max(0,(int)($p['sector']??0));
        $type=max(1,min(4,(int)($p['type']??1)));
        $d=fb_http_json($base.'/ClosingPrice/GetMarketMap?market='.$market.'&size='.$size.'&sector='.$sector.'&typeSelected='.$type.'&hEven=0',[],20);
        $items=[];
        foreach($d as $x){
            $items[]=['ins_code'=>$x['insCode']??null,'symbol'=>fb_fa((string)($x['lVal18AFC']??'')),'name'=>fb_fa((string)($x['lVal30']??'')),'sector'=>fb_fa((string)($x['lSecVal']??'')),'close'=>$num($x['pClosing']??null),'last'=>$num($x['pDrCotVal']??null),'change_pct'=>$num($x['priceChangePercent']??$x['percent']??null),'volume'=>$num($x['qTotTran5J']??null),'value_rial'=>$num($x['qTotCap']??null),'color'=>$x['color']??null];
        }
        return ['items'=>$items,'count'=>count($items),'market'=>$market,'type'=>$type,'source'=>'TSETMC Market Map'];
    }
    if($tool==='fund_list'){
        $types=[4=>'درآمد ثابت',5=>'کالایی',6=>'سهامی',7=>'مختلط',11=>'بازارگردانی',12=>'سرمایه‌گذاری خطرپذیر',13=>'پروژه‌ای',14=>'املاک و مستغلات',16=>'خصوصی',17=>'سایر'];
        $out=[];$errors=[];
        $all=null;
        try{$all=fb_http_json($base.'/Fund/GetFunds/0',[],12)['funds']??null;}catch(Throwable $e){$errors['all']=$e->getMessage();}
        if(is_array($all)&&count($all)){
            foreach($all as $x){
                $type=(int)($x['type']??$x['fundType']??$x['fundTypeId']??0);
                $label=$types[$type]??(string)($x['typeTitle']??$x['fundTypeTitle']??'صندوق');
                $name=(string)($x['name']??$x['fundName']??$x['lVal30']??$x['title']??$x['nameFa']??'');
                $symbol=(string)($x['symbol']??$x['lVal18AFC']??$x['ticker']??'');
                $ins=(string)($x['insCode']??$x['inscode']??$x['instrumentCode']??'');
                if($name===''&&$symbol==='')continue;
                $out[]=['name'=>fb_fa($name),'symbol'=>fb_fa($symbol),'ins_code'=>$ins,'reg_no'=>$x['regNo']??$x['regno']??$x['registrationNo']??null,'type'=>$type,'type_title'=>$label,'nav'=>$x['nav']??$x['NAV']??null,'aum'=>$x['aUM']??$x['aum']??$x['AUM']??null];
            }
        }else{
            $fallbackTypes=[4,5,6,7,14];
            foreach($fallbackTypes as $type){
                try{
                    $d=fb_http_json($base.'/Fund/GetFunds/'.$type,[],10);
                    foreach(($d['funds']??[]) as $x){
                        $name=(string)($x['name']??$x['fundName']??$x['lVal30']??$x['title']??$x['nameFa']??'');
                        $symbol=(string)($x['symbol']??$x['lVal18AFC']??$x['ticker']??'');
                        $ins=(string)($x['insCode']??$x['inscode']??$x['instrumentCode']??'');
                        if($name===''&&$symbol==='')continue;
                        $out[]=['name'=>fb_fa($name),'symbol'=>fb_fa($symbol),'ins_code'=>$ins,'reg_no'=>$x['regNo']??$x['regno']??$x['registrationNo']??null,'type'=>$type,'type_title'=>$types[$type],'nav'=>$x['nav']??$x['NAV']??null,'aum'=>$x['aUM']??$x['aum']??$x['AUM']??null];
                    }
                }catch(Throwable $e){$errors[$type]=$e->getMessage();}
            }
        }
        $unique=[];foreach($out as $x){$key=$x['ins_code']?:($x['symbol'].'|'.$x['name']);if($key!=='')$unique[$key]=$x;}
        return ['funds'=>array_values($unique),'count'=>count($unique),'types'=>$types,'partial_errors'=>$errors,'source'=>'TSETMC Fund API'];
    }
    if($tool==='search'){
        if($symbol==='') throw new InvalidArgumentException('نام نماد یا شرکت را وارد کنید.');
        $d=fb_http_json($base.'/Instrument/GetInstrumentSearch/'.rawurlencode($symbol));
        return ['matches'=>array_map(fn($x)=>['symbol'=>fb_fa((string)($x['lVal18AFC']??'')),'name'=>fb_fa((string)($x['lVal30']??'')),'ins_code'=>$x['insCode']??null,'market'=>fb_fa((string)($x['flowTitle']??'')),'asset_type'=>(str_starts_with(strtoupper((string)($x['cIsin']??'')),'IRT1')||preg_match('/صندوق|ETF|fund/i',fb_fa((string)(($x['flowTitle']??'').' '.($x['cgrValCotTitle']??''))))?'fund':'stock')],array_slice($d['instrumentSearch']??[],0,max(1,min(30,(int)($p['limit']??10)))))];
    }
    if($tool==='fund_profile'){
        [$ins,$ticker]=$resolve();
        if(!$ins)throw new RuntimeException('نماد صندوق در TSETMC پیدا نشد.');
        $quote=[];$etf=[];$last=[];$regNo=(string)($p['reg_no']??'');$detail=[];
        try{$quote=fb_http_json($base.'/Instrument/GetInstrumentInfo/'.$ins)['instrumentInfo']??[];}catch(Throwable $e){}
        try{$etf=fb_http_json($base.'/Fund/GetETFByInsCode/'.$ins)['etf']??[];}catch(Throwable $e){}
        try{
            $detailSet=fb_http_json($base.'/Fund/GetFundInDetail/0',[],18)['fund']??[];
            $detailRows=is_array($detailSet)&&array_is_list($detailSet)?$detailSet:[$detailSet];
            foreach($detailRows as $row){
                $rowReg=(string)($row['regNo']??$row['regno']??$row['no']??'');
                $rowIns=(string)($row['insCode']??$row['inscode']??'');
                if(($regNo!==''&&$rowReg===$regNo)||$rowIns===$ins){$detail=$row;break;}
            }
            if(!$detail){$detailSet=fb_http_json($base.'/Fund/GetFundInDetail/1',[],18)['fund']??[];$detailRows=is_array($detailSet)&&array_is_list($detailSet)?$detailSet:[$detailSet];foreach($detailRows as $row){$rowReg=(string)($row['regNo']??$row['regno']??$row['no']??'');$rowIns=(string)($row['insCode']??$row['inscode']??'');if(($regNo!==''&&$rowReg===$regNo)||$rowIns===$ins){$detail=$row;break;}}}
        }catch(Throwable $e){}
        try{$last=fb_http_json($base.'/ClosingPrice/GetClosingPriceInfo/'.$ins)['closingPriceInfo']??[];}catch(Throwable $e){}
        $lastPrice=$num($last['pDrCotVal']??null);
        $nav=$num($etf['pRedTran']??$quote['nav']??null);
        return ['ins_code'=>$ins,'symbol'=>$ticker,'name'=>fb_fa((string)($quote['lVal30']??'')),'sector'=>fb_fa((string)($quote['sector']['lSecVal']??'')),'unit'=>'rial','quote'=>['last'=>$lastPrice,'close'=>$num($last['pClosing']??null),'change_pct'=>$pct($last['pDrCotVal']??null,$last['priceYesterday']??null)],'nav'=>['redemption'=>$nav,'subscription'=>$num($etf['pSubTran']??null),'date'=>$etf['deven']??null,'time'=>$etf['hEven']??null],'premium_pct'=>($lastPrice&&$nav)?round(($lastPrice/$nav-1)*100,3):null,'instrument_info'=>$quote,'fund_detail'=>$detail,'etf_data'=>$etf,'data_scope'=>'TSETMC fund/ETF endpoints'];
    }
    if(in_array($tool,['statistics','shareholders','messages','price_adjustments','trades','intraday','trade_volume','status','option_chain'],true)){
        [$ins,$ticker]=$resolve();
        if(!$ins)throw new RuntimeException('نماد در TSETMC پیدا نشد.');
        if($tool==='statistics'){$d=fb_http_json($base.'/MarketData/GetInstrumentStatistic/'.$ins,[],18);return ['symbol'=>$ticker,'items'=>$d['instrumentStatistic']??[]];}
        if($tool==='shareholders'){$d=fb_http_json($base.'/Shareholder/GetInstrumentShareHolderLast/'.$ins,[],18);return ['symbol'=>$ticker,'holders'=>$d['shareHolder']??[]];}
        if($tool==='messages'){$d=fb_http_json($base.'/Msg/GetMsgByInsCode/'.$ins,[],18);return ['symbol'=>$ticker,'messages'=>$d['msg']??[]];}
        if($tool==='price_adjustments'){$d=fb_http_json($base.'/ClosingPrice/GetPriceAdjustList/'.$ins,[],18);return ['symbol'=>$ticker,'events'=>$d['priceAdjust']??[]];}
        if($tool==='trades'){$d=fb_http_json($base.'/Trade/GetTrade/'.$ins,[],18);return ['symbol'=>$ticker,'trades'=>$d['trade']??[]];}
        if($tool==='intraday'){$d=fb_http_json($base.'/Trade/GetTradeIntraDay/'.$ins,[],18);return ['symbol'=>$ticker,'trades'=>$d['tradeIntraDay']??[]];}
        if($tool==='trade_volume'){$d=fb_http_json($base.'/Trade/GetTradeVolume/'.$ins,[],18);return ['symbol'=>$ticker,'distribution'=>$d['tradeVolume']??[]];}
        if($tool==='status'){$date=(string)($p['date']??date('Ymd'));if(!preg_match('/^\\d{8}$/',$date))throw new InvalidArgumentException('تاریخ باید به شکل YYYYMMDD باشد.');$d=fb_http_json($base.'/MarketData/GetInstrumentState/'.$ins.'/'.$date,[],18);return ['symbol'=>$ticker,'state'=>$d['instrumentState']??[]];}
        if($tool==='option_chain'){$info=fb_http_json($base.'/Instrument/GetInstrumentInfo/'.$ins,[],18)['instrumentInfo']??[];$instrumentId=(string)($info['instrumentID']??'');if($instrumentId==='')throw new RuntimeException('شناسه پایه برای دریافت زنجیره اختیار پیدا نشد.');$d=fb_http_json($base.'/Instrument/GetInstrumentOptionByInstrumentID/'.rawurlencode($instrumentId),[],18);return ['symbol'=>$ticker,'instrument_id'=>$instrumentId,'response'=>$d];}
    }
    if($tool==='options_market'){
        $d=fb_http_json($base.'/Instrument/GetInstrumentOptionMarketWatch',[],20);
        return ['response'=>$d,'source'=>'TSETMC option market watch'];
    }
    if($tool==='market_overview'){
        $flow=max(0,min(4,(int)($p['flow']??1)));$d=fb_http_json($base.'/MarketData/GetMarketOverview/'.$flow,[],18);return ['overview'=>$d['marketOverview']??[]];
    }
    if($tool==='index_overview'){
        $flow=max(1,min(2,(int)($p['flow']??1)));$d=fb_http_json($base.'/Index/GetIndexB1LastAll/SelectedIndexes/'.$flow,[],18);return ['indices'=>$d['indexB1']??[]];
    }
    if($tool==='sectors'){
        $d=fb_http_json($base.'/MarketData/GetSectorsSummary',[],18);return ['sectors'=>$d['sectorSummeries']??$d['sectorSummaries']??[]];
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
    if($tool==='index_history'){
        $index=fb_fa((string)($p['index']??'شاخص کل'));$idx=fb_http_json($base.'/Index/GetIndexB1LastAll/All/1')['indexB1']??[];
        $hits=array_values(array_filter($idx,fn($x)=>mb_stripos(fb_fa((string)($x['lVal30']??'')),$index)!==false));
        if(!$hits) throw new RuntimeException('شاخص موردنظر پیدا نشد.');
        $code=$hits[0]['insCode'];$history=fb_http_json($base.'/Index/GetIndexB2History/'.$code)['indexB2']??[];
        return ['index'=>fb_fa((string)$hits[0]['lVal30']),'rows'=>array_slice($history,-max(2,min(100,(int)($p['days']??30))))];
    }
    [$ins,$ticker]=$resolve();
    if(!$ins) throw new RuntimeException('نماد پیدا نشد یا چند نتیجه مشابه دارد؛ ابتدا جست‌وجوی نماد را اجرا کنید.');
    if($tool==='quote'){
        $i=fb_http_json($base.'/Instrument/GetInstrumentInfo/'.$ins)['instrumentInfo']??[];
        $c=fb_http_json($base.'/ClosingPrice/GetClosingPriceInfo/'.$ins)['closingPriceInfo']??[];
        return ['symbol'=>$ticker??fb_fa((string)($i['lVal18AFC']??'')),'name'=>fb_fa((string)($i['lVal30']??'')),'unit'=>'rial','date'=>$c['dEven']??null,'last'=>$num($c['pDrCotVal']??null),'close'=>$num($c['pClosing']??null),'yesterday'=>$num($c['priceYesterday']??null),'change_pct'=>$pct($c['pDrCotVal']??null,$c['priceYesterday']??null),'open'=>$num($c['priceFirst']??null),'low'=>$num($c['priceMin']??null),'high'=>$num($c['priceMax']??null),'volume'=>$num($c['qTotTran5J']??null),'trades'=>$num($c['zTotTran']??null),'value_rial'=>$num($c['qTotCap']??null),'shares'=>$num($i['zTitad']??null),'sector'=>fb_fa((string)($i['sector']['lSecVal']??'')),'eps'=>$num((is_array($i['eps']??null)?($i['eps']['epsValue']??$i['eps']['estimatedEPS']??null):($i['eps']??null))),'pe'=>$num($i['pe']??(is_array($i['eps']??null)?($i['eps']['pe']??null):null)),'sector_pe'=>$num(is_array($i['eps']??null)?($i['eps']['sectorPE']??null):null),'free_float_pct'=>$num($i['kAjCapValCpsIdx']??null),'year_low'=>$num($i['minYear']??null),'year_high'=>$num($i['maxYear']??null),'base_volume'=>$num($i['baseVol']??null)];
    }
    if($tool==='orderbook'){
        $levels=fb_http_json($base.'/BestLimits/'.$ins)['bestLimits']??[];
        return ['symbol'=>$ticker,'unit'=>'rial','levels'=>array_map(fn($b)=>['level'=>$b['number']??null,'bid_price'=>$num($b['pMeDem']??null),'bid_volume'=>$num($b['qTitMeDem']??null),'bid_orders'=>$b['zOrdMeDem']??null,'ask_price'=>$num($b['pMeOf']??null),'ask_volume'=>$num($b['qTitMeOf']??null),'ask_orders'=>$b['zOrdMeOf']??null],$levels)];
    }
    if($tool==='history'){
        $days=max(1,min(500,(int)($p['days']??20)));
        $rows=fb_http_json($base.'/ClosingPrice/GetClosingPriceDailyList/'.$ins.'/'.$days)['closingPriceDaily']??[];
        return ['symbol'=>$ticker,'unit'=>'rial','rows'=>array_map(fn($r)=>['date'=>$r['dEven']??null,'open'=>$num($r['priceFirst']??null),'high'=>$num($r['priceMax']??null),'low'=>$num($r['priceMin']??null),'close'=>$num($r['pClosing']??null),'last'=>$num($r['pDrCotVal']??null),'volume'=>$num($r['qTotTran5J']??null),'trades'=>$num($r['zTotTran']??null),'value_rial'=>$num($r['qTotCap']??null)],$rows)];
    }
    if($tool==='flow'){
        $days=max(1,min(500,(int)($p['days']??30)));
        $all=fb_http_json($base.'/ClientType/GetClientTypeHistory/'.$ins,[],20)['clientType']??[];
        $rows=array_map(fn($c)=>['date'=>$c['dEven']??null,'real_buy_volume'=>$num($c['buy_I_Volume']??null),'real_sell_volume'=>$num($c['sell_I_Volume']??null),'legal_buy_volume'=>$num($c['buy_N_Volume']??null),'legal_sell_volume'=>$num($c['sell_N_Volume']??null),'net_real_volume'=>$num(($c['buy_I_Volume']??0)-($c['sell_I_Volume']??0)),'real_buy_value'=>$num($c['buy_I_Value']??null),'real_sell_value'=>$num($c['sell_I_Value']??null),'net_real_value'=>$num(($c['buy_I_Value']??0)-($c['sell_I_Value']??0))],$all);
        usort($rows,fn($a,$b)=>(int)$a['date']<=>(int)$b['date']);
        return ['symbol'=>$ticker,'unit'=>'rial','rows'=>array_slice($rows,-$days)];
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
                for($i=0,$n=strlen($json);$i<$n;$i++){ $ch=$json[$i]; if($quoted){if($escape)$escape=false;elseif($ch==='\\')$escape=true;elseif($ch==='"')$quoted=false;continue;} if($ch==='"'){$quoted=true;continue;} if($ch==='{')$depth++;elseif($ch==='}'){$depth--;if($depth===0){$end=$i+1;break;}}}
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
      ['id'=>'nobitex','name'=>'نوبیتکس','unit'=>'ریال','ticker'=>true,'orderbook'=>true,'trades'=>true,'candles'=>false],
      ['id'=>'wallex','name'=>'والکس','unit'=>'تومان','ticker'=>true,'orderbook'=>true,'trades'=>true,'candles'=>true],
      ['id'=>'bitpin','name'=>'بیت‌پین','unit'=>'تومان','ticker'=>true,'orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'ramzinex','name'=>'رمزینکس','unit'=>'ریال','ticker'=>false,'orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'tabdeal','name'=>'تبدیل','unit'=>'تومان','ticker'=>false,'orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'ompfinex','name'=>'او‌ام‌پی فینکس','unit'=>'ریال','ticker'=>false,'orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'exir','name'=>'اکسیر','unit'=>'تومان','ticker'=>false,'orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'bit24','name'=>'بیت۲۴','unit'=>'تومان','ticker'=>false,'orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'abantether','name'=>'آبان‌تتر','unit'=>'تومان','ticker'=>false,'orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'kifpool','name'=>'کیف پول من','unit'=>'تومان','ticker'=>false,'orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'okex','name'=>'اوکی‌اکسچنج','unit'=>'تومان','ticker'=>false,'orderbook'=>false,'trades'=>false,'candles'=>false],
      ['id'=>'tetherland','name'=>'تترلند','unit'=>'تومان','ticker'=>false,'orderbook'=>false,'trades'=>false,'candles'=>false]
    ];
    if($tool==='exchanges') return ['exchanges'=>$exchanges,'note'=>'تیکر و فهرست بازارها برای نوبیتکس، والکس و بیت‌پین فعال است؛ دفتر سفارش و معاملات برای نوبیتکس و والکس و کندل تاریخی فعلاً فقط برای والکس متصل است. سایر صرافی‌ها صرفاً در فهرست شناخته‌شده‌اند.'];
    $coin=strtoupper(preg_replace('/[^A-Z0-9_]/','',(string)($p['coin']??'BTC')));
    if($coin==='')throw new InvalidArgumentException('نماد رمزارز را وارد کنید.');
    $quote=(string)($p['quote']??'IRT');if(!in_array($quote,['IRT','USDT'],true))throw new InvalidArgumentException('واحد قیمت نامعتبر است.');
    $getTicker=function(string $exchange)use($coin,$quote):array{
        if($exchange==='nobitex'){
            $stats=fb_http_json('https://apiv2.nobitex.ir/market/stats')['stats']??[];$suffix=$quote==='IRT'?'rls':'usdt';$v=$stats[strtolower($coin).'-'.$suffix]??null;
            if(!$v)throw new RuntimeException('بازار در نوبیتکس یافت نشد.');
            $scale=$quote==='IRT'?10:1;
            return ['exchange'=>'nobitex','name'=>'نوبیتکس','price'=>isset($v['latest'])?(float)$v['latest']/$scale:null,'bid'=>isset($v['bestBuy'])?(float)$v['bestBuy']/$scale:null,'ask'=>isset($v['bestSell'])?(float)$v['bestSell']/$scale:null,'high'=>isset($v['dayHigh'])?(float)$v['dayHigh']/$scale:null,'low'=>isset($v['dayLow'])?(float)$v['dayLow']/$scale:null,'change_pct'=>isset($v['dayChange'])?(float)$v['dayChange']:null,'unit'=>$quote==='IRT'?'toman':'usdt'];
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
    $exchange=(string)($p['exchange']??'wallex');
    $marketSymbol = function(string $ex) use($coin,$quote): string {
        if($ex==='nobitex') return $coin.($quote==='IRT'?'IRT':'USDT');
        if($ex==='wallex') {
            $markets=fb_http_json('https://api.wallex.ir/v1/markets')['result']['symbols']??[];
            foreach($markets as $m) if(strtoupper((string)($m['baseAsset']??''))===$coin && strtoupper((string)($m['quoteAsset']??''))===($quote==='IRT'?'TMN':'USDT')) return (string)($m['symbol']??'');
        }
        return '';
    };
    if($tool==='orderbook'){
        $symbol=$marketSymbol($exchange);
        if($exchange==='nobitex'){
            if(!$symbol)throw new RuntimeException('بازار در نوبیتکس یافت نشد.');
            $d=fb_http_json('https://apiv2.nobitex.ir/v3/orderbook/'.$symbol);
            $scale=$quote==='IRT'?10:1;
            $norm=fn($rows)=>array_map(fn($x)=>['price'=>(float)($x[0]??0)/$scale,'quantity'=>(float)($x[1]??0)],array_slice($rows??[],0,50));
            return ['exchange'=>$exchange,'symbol'=>$symbol,'unit'=>$quote==='IRT'?'toman':'usdt','asks'=>$norm($d['asks']??[]),'bids'=>$norm($d['bids']??[])];
        }
        if($exchange==='wallex'){
            if(!$symbol)throw new RuntimeException('بازار در والکس یافت نشد.');
            $d=fb_http_json('https://api.wallex.ir/v1/depth',['symbol'=>$symbol])['result']??[];
            return ['exchange'=>$exchange,'symbol'=>$symbol,'unit'=>$quote==='IRT'?'toman':'usdt','asks'=>array_slice($d['ask']??[],0,50),'bids'=>array_slice($d['bid']??[],0,50)];
        }
        throw new RuntimeException('دفتر سفارش این صرافی هنوز متصل نشده است.');
    }
    if($tool==='trades'){
        $symbol=$marketSymbol($exchange);
        if($exchange==='nobitex'){
            if(!$symbol)throw new RuntimeException('بازار در نوبیتکس یافت نشد.');
            $d=fb_http_json('https://apiv2.nobitex.ir/v2/trades/'.$symbol);
            $scale=$quote==='IRT'?10:1;
            return ['exchange'=>$exchange,'symbol'=>$symbol,'unit'=>$quote==='IRT'?'toman':'usdt','trades'=>array_map(fn($x)=>['time'=>$x['time']??null,'price'=>isset($x['price'])?(float)$x['price']/$scale:null,'quantity'=>(float)($x['volume']??0),'side'=>$x['type']??null],array_slice($d['trades']??[],0,max(1,min(100,(int)($p['limit']??30)))))];
        }
        if($exchange==='wallex'){
            if(!$symbol)throw new RuntimeException('بازار در والکس یافت نشد.');
            $d=fb_http_json('https://api.wallex.ir/v1/trades',['symbol'=>$symbol])['result']['latestTrades']??[];
            return ['exchange'=>$exchange,'symbol'=>$symbol,'unit'=>$quote==='IRT'?'toman':'usdt','trades'=>array_slice($d,0,max(1,min(100,(int)($p['limit']??30))))];
        }
        throw new RuntimeException('معاملات اخیر این صرافی هنوز متصل نشده است.');
    }
    if($tool==='candles'){
        if($exchange!=='wallex')throw new RuntimeException('در این نسخه کندل تاریخی فقط برای والکس متصل شده است.');
        $symbol=$marketSymbol($exchange);if(!$symbol)throw new RuntimeException('بازار در والکس یافت نشد.');
        $interval=(string)($p['interval']??'1h');$map=['15m'=>'1','1h'=>'60','4h'=>'180','1d'=>'1D'];$resolution=$map[$interval]??'60';
        $to=time();$days=max(1,min(100,(int)($p['days']??7)));$from=$to-$days*86400;
        $d=fb_http_json('https://api.wallex.ir/v1/udf/history',['symbol'=>$symbol,'resolution'=>$resolution,'from'=>$from,'to'=>$to],20);
        if(($d['s']??'')!=='ok')throw new RuntimeException('والکس تاریخچه کندل معتبری برنگرداند.');
        $rows=[];$n=count($d['t']??[]);for($i=0;$i<$n;$i++)$rows[]=['time'=>$d['t'][$i]??null,'open'=>$d['o'][$i]??null,'high'=>$d['h'][$i]??null,'low'=>$d['l'][$i]??null,'close'=>$d['c'][$i]??null,'volume'=>$d['v'][$i]??null];
        return ['exchange'=>$exchange,'symbol'=>$symbol,'interval'=>$interval,'unit'=>$quote==='IRT'?'toman':'usdt','rows'=>$rows];
    }
    if($tool==='movers'){
        $limit=max(1,min(50,(int)($p['limit']??10)));$direction=(string)($p['direction']??'gainers');
        if($exchange==='wallex'){
            $symbols=fb_http_json('https://api.wallex.ir/v1/markets')['result']['symbols']??[];$rows=[];
            foreach($symbols as $m)if(strtoupper((string)($m['quoteAsset']??''))===($quote==='IRT'?'TMN':'USDT')){$st=$m['stats']??[];$rows[]=['symbol'=>$m['symbol']??null,'coin'=>$m['baseAsset']??null,'name'=>$m['faName']??null,'price'=>$st['lastPrice']??null,'change_pct'=>$st['24h_ch']??null,'volume'=>$st['24h_volume']??null,'quote_volume'=>$st['24h_quoteVolume']??null];}
            usort($rows,fn($a,$b)=>$direction==='losers'?(float)$a['change_pct']<=>(float)$b['change_pct']:(float)$b['change_pct']<=>(float)$a['change_pct']);
            return ['exchange'=>$exchange,'quote'=>$quote,'direction'=>$direction,'markets'=>array_slice($rows,0,$limit)];
        }
        if($exchange==='nobitex'){
            $stats=fb_http_json('https://apiv2.nobitex.ir/market/stats')['stats']??[];$rows=[];
            foreach($stats as $key=>$v){[$base,$qpart]=array_pad(explode('-',$key,2),2,'');if($qpart!==($quote==='IRT'?'rls':'usdt'))continue;$scale=$quote==='IRT'?10:1;$rows[]=['coin'=>strtoupper($base),'price'=>isset($v['latest'])?(float)$v['latest']/$scale:null,'change_pct'=>(float)($v['dayChange']??0),'volume'=>$v['volumeSrc']??null];}
            usort($rows,fn($a,$b)=>$direction==='losers'?(float)$a['change_pct']<=>(float)$b['change_pct']:(float)$b['change_pct']<=>(float)$a['change_pct']);
            return ['exchange'=>$exchange,'quote'=>$quote,'direction'=>$direction,'markets'=>array_slice($rows,0,$limit)];
        }
        throw new RuntimeException('فهرست برترین‌ها برای این صرافی متصل نشده است.');
    }
    if($tool==='best_execution'){
        $book=fb_crypto('orderbook',['exchange'=>$exchange,'coin'=>$coin,'quote'=>$quote]);
        $side=(string)($p['side']??'buy');$amount=max(0,(float)($p['amount']??0));if($amount<=0)throw new InvalidArgumentException('مقدار سفارش باید بیشتر از صفر باشد.');
        $levels=$side==='sell'?($book['bids']??[]):($book['asks']??[]);$remaining=$amount;$filled=0;$total=0;$fills=[];
        foreach($levels as $level){$price=(float)($level['price']??0);$qty=(float)($level['quantity']??0);if($price<=0||$qty<=0)continue;$take=$side==='sell'?min($qty,$remaining):min($qty,$remaining/$price);if($take<=0)break;$value=$take*$price;$filled+=$take;$total+=$value;$remaining-=$side==='sell'?$take:$value;$fills[]=['price'=>$price,'quantity'=>$take,'value'=>$value];if($remaining<=0)break;}
        return ['exchange'=>$exchange,'side'=>$side,'requested_base_quantity'=>$side==='sell'?$amount:null,'requested_quote_amount'=>$side==='buy'?$amount:null,'filled_base_quantity'=>$filled,'filled_quote_amount'=>$total,'average_price'=>$filled?$total/$filled:null,'unfilled_base_quantity'=>$side==='sell'?max(0,$remaining):null,'unfilled_quote_amount'=>$side==='buy'?max(0,$remaining):null,'fills'=>$fills,'note'=>'برآورد بر اساس عمق سفارش فعلی است؛ اجرای واقعی سفارش یا کارمزد را تضمین نمی‌کند.'];
    }
    if($tool==='arbitrage'){
        return fb_crypto('compare',['coin'=>$coin,'quote'=>$quote]);
    }
    if($tool==='overview'){
        $rows=[];foreach(['BTC','ETH','USDT'] as $asset){try{$rows[]=fb_crypto('compare',['coin'=>$asset,'quote'=>$quote]);}catch(Throwable $e){}}
        return ['quote'=>$quote,'assets'=>$rows,'note'=>'قیمت‌ها از صرافی‌های متصل دریافت می‌شوند؛ اختلاف قیمت به‌تنهایی فرصت آربیتراژ قابل اجرا را اثبات نمی‌کند.'];
    }
    throw new RuntimeException('ابزار انتخابی رمزارز هنوز به API عمومی صرافی‌ها متصل نشده است.');
}
function fb_dispatch_module(string $module,string $tool,array $p): array {
    $ttl=$module==='codal'?3600:($module==='crypto'?30:($module==='tsetmc'&&$tool==='fund_list'?300:15));
    return fb_cache_module($module.':'.$tool.':'.http_build_query($p),$ttl,function()use($module,$tool,$p){
        if($module==='tsetmc')return fb_tsetmc($tool,$p);
        if($module==='codal')return fb_codal($tool,$p);
        if($module==='crypto')return fb_crypto($tool,$p);
        throw new InvalidArgumentException('ماژول ناشناخته است.');
    });
}
