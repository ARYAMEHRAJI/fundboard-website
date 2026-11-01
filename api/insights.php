<?php
declare(strict_types=1);

/**
 * Cross-source research layer.
 * Combines TSETMC market history/flow with Codal issuer disclosures.
 * Derived metrics are descriptive, never trade recommendations.
 */
function fb_build_research(string $kind,string $id,array $history,string $codalSymbol=''): array {
    $rows=array_values(array_filter($history,static fn($x)=>is_array($x)&&is_numeric($x['close']??null)));
    usort($rows,static fn($a,$b)=>(int)($a['date']??0)<=>(int)($b['date']??0));
    $closes=array_map(static fn($x)=>(float)$x['close'],$rows);
    $returns=[];
    for($i=1;$i<count($closes);$i++)$returns[]=$closes[$i-1]!=0?($closes[$i]/$closes[$i-1]-1):0;
    $last=$closes?end($closes):null;
    $atReturn=static function(int $period)use($closes):?float{
        $n=count($closes);if($n<2)return null;$idx=max(0,$n-1-$period);$base=$closes[$idx];return $base!=0?round(($closes[$n-1]/$base-1)*100,2):null;
    };
    $volatility=null;
    if(count($returns)>1){$mean=array_sum($returns)/count($returns);$variance=0;foreach($returns as $v)$variance+=($v-$mean)**2;$volatility=round(sqrt($variance/(count($returns)-1))*sqrt(252)*100,2);}
    $peak=0;$maxDrawdown=0;foreach($closes as $v){$peak=max($peak,$v);if($peak>0)$maxDrawdown=min($maxDrawdown,($v/$peak-1)*100);}
    $rsi=null;if(count($closes)>=15){$up=0;$down=0;for($i=count($closes)-14;$i<count($closes);$i++){$d=$closes[$i]-$closes[$i-1];if($d>0)$up+=$d;else $down-=$d;}$rsi=$down==0?100:round(100-(100/(1+(($up/14)/($down/14)))),1);}
    $ma20=count($closes)>=20?array_sum(array_slice($closes,-20))/20:null;
    $volumes=array_map(static fn($x)=>(float)($x['volume']??0),$rows);
    $vol20=count($volumes)>=20?array_sum(array_slice($volumes,-20))/20:null;
    $vol60=count($volumes)>=60?array_sum(array_slice($volumes,-60))/60:null;
    $volumeRatio=($vol20!==null&&$vol60>0)?round($vol20/$vol60,2):null;
    $flow=[];
    try{$flow=fb_dispatch_module('tsetmc','flow',['symbol'=>$id,'days'=>90])['rows']??[];}catch(Throwable $e){}
    $sumFlow=static function(array $items):array{$v=0;$volume=0;foreach($items as $x){$v+=(float)($x['net_real_value']??0);$volume+=(float)($x['net_real_volume']??0);}return ['net_value'=>$v,'net_volume'=>$volume];};
    $flow5=$sumFlow(array_slice($flow,-5));$flow20=$sumFlow(array_slice($flow,-20));
    $flowSeries=array_slice($flow,-30);
    $disclosures=[];$codalError=null;
    $symbol=$codalSymbol!==''?$codalSymbol:$id;
    try{$d=fb_dispatch_module('codal','search_letters',['symbol'=>$symbol,'page'=>1]);$disclosures=array_slice($d['letters']??[],0,12);}catch(Throwable $e){$codalError='اطلاعیه‌های کدال در این لحظه در دسترس نیست.';}
    $events=[];
    foreach($disclosures as $x){
        $title=(string)($x['title']??'');$low=mb_strtolower($title);
        $type='سایر اطلاعیه‌ها';
        foreach(['صورت مالی'=>'صورت مالی','گزارش فعالیت ماهانه'=>'عملکرد ماهانه','فعالیت ماهانه'=>'عملکرد ماهانه','افشای اطلاعات بااهمیت'=>'افشای بااهمیت','افزایش سرمایه'=>'افزایش سرمایه','تقسیم سود'=>'تقسیم سود','مجمع'=>'مجمع','تجدید ارزیابی'=>'تجدید ارزیابی','قرارداد'=>'قرارداد','توقف'=>'توقف نماد','پیشنهاد هیئت مدیره'=>'پیشنهاد هیئت‌مدیره'] as $needle=>$label){if(mb_stripos($title,$needle)!==false){$type=$label;break;}}
        $events[]=['type'=>$type,'title'=>$title,'sent'=>$x['sent']??null,'url'=>$x['url']??null,'tracing_no'=>$x['tracing_no']??null];
    }
    $flags=[];
    if($last!==null&&$ma20!==null)$flags[]=['key'=>'trend','label'=>$last>=$ma20?'قیمت بالاتر از میانگین ۲۰ روزه':'قیمت پایین‌تر از میانگین ۲۰ روزه','tone'=>$last>=$ma20?'positive':'negative'];
    if($rsi!==null)$flags[]=['key'=>'rsi','label'=>$rsi>=70?'RSI در محدوده بالای ۷۰':($rsi<=30?'RSI در محدوده زیر ۳۰':'RSI در محدوده میانی'),'tone'=>$rsi>=70?'warning':($rsi<=30?'positive':'neutral')];
    if($flow20['net_value']>0)$flags[]=['key'=>'flow','label'=>'خالص ارزش خرید حقیقی در ۲۰ جلسه مثبت بوده است','tone'=>'positive'];
    elseif($flow20['net_value']<0)$flags[]=['key'=>'flow','label'=>'خالص ارزش خرید حقیقی در ۲۰ جلسه منفی بوده است','tone'=>'negative'];
    if($volumeRatio!==null&&$volumeRatio>=1.8)$flags[]=['key'=>'volume','label'=>'میانگین حجم اخیر به‌طور محسوسی بالاتر از میانگین ۶۰ جلسه است','tone'=>'warning'];
    return [
        'kind'=>$kind,'symbol'=>$symbol,'metrics'=>[
            'return_1w'=>$atReturn(5),'return_1m'=>$atReturn(21),'return_3m'=>$atReturn(63),'return_1y'=>$atReturn(252),
            'annualized_volatility'=>$volatility,'max_drawdown'=>$rows?round($maxDrawdown,2):null,'rsi14'=>$rsi,
            'ma20'=>$ma20!==null?round($ma20,2):null,'last_close'=>$last,'avg_volume_20'=>$vol20!==null?round($vol20):null,
            'volume_ratio_20_60'=>$volumeRatio,'net_real_value_5'=>$flow5['net_value'],'net_real_value_20'=>$flow20['net_value'],
            'net_real_volume_20'=>$flow20['net_volume']
        ],
        'flow_series'=>$flowSeries,'events'=>$events,'flags'=>$flags,'codal_error'=>$codalError,
        'methodology'=>'بازدهی از قیمت‌های پایانی روزانه TSETMC؛ نوسان سالانه‌شده با انحراف معیار بازده روزانه و ضریب ریشه ۲۵۲؛ افت از بیشینه تاریخی بازه دریافت‌شده؛ جریان حقیقی بر اساس اختلاف ارزش خرید و فروش حقیقی. این شاخص‌ها توصیفی‌اند و توصیه معاملاتی نیستند.',
        'history_points'=>count($rows),'flow_points'=>count($flow)
    ];
}
