(()=>{'use strict';
const $=s=>document.querySelector(s),esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let timer=null,profile=null,research=null,regNo='',disclosures=[],chart=null,volumeChart=null,rsiChart=null,kind='stock',assetId='',range='1m',mode='candle';
function fmt(v){return v==null||v===''?'—':Number(v).toLocaleString('fa-IR',{maximumFractionDigits:2})}
function faDate(v){const z=String(v??'');if(/^\d{8}$/.test(z)){const d=new Date(Date.UTC(+z.slice(0,4),+z.slice(4,6)-1,+z.slice(6,8)));return d.toLocaleDateString('fa-IR-u-ca-persian')}return z}
function calcInsights(rows){
 const values=(rows||[]).map(x=>Number(x.close??x.pClosing??x.value??x)).filter(x=>Number.isFinite(x)&&x>0);
 if(values.length<2)return null;
 const first=values[0],last=values[values.length-1],rets=[];
 for(let i=1;i<values.length;i++)rets.push(values[i]/values[i-1]-1);
 const mean=rets.reduce((a,b)=>a+b,0)/rets.length;
 const variance=rets.reduce((a,b)=>a+(b-mean)**2,0)/rets.length;
 let peak=values[0],maxDD=0;for(const v of values){peak=Math.max(peak,v);maxDD=Math.min(maxDD,v/peak-1)}
 return {periodReturn:(last/first-1)*100,volatility:Math.sqrt(variance)*Math.sqrt(252)*100,maxDrawdown:maxDD*100,observations:values.length};
}
function addSearch(){
 const top=$('.top-left');if(!top||$('#assetSearch'))return;
 const wrap=document.createElement('div');wrap.className='asset-search-wrap';wrap.innerHTML='<div class="asset-search"><span>⌕</span><input id="assetSearch" autocomplete="off" placeholder="جستجوی نماد، سهم یا صندوق…"><kbd>Enter</kbd></div><div id="assetSearchResults" class="asset-search-results" hidden></div>';
 top.append(wrap);
 const input=$('#assetSearch');input.addEventListener('input',()=>{clearTimeout(timer);const q=input.value.trim();if(q.length<2){$('#assetSearchResults').hidden=true;return}timer=setTimeout(()=>search(q),350)});
 input.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();search(input.value.trim())}if(e.key==='Escape')$('#assetSearchResults').hidden=true});
 document.addEventListener('click',e=>{if(!wrap.contains(e.target))$('#assetSearchResults').hidden=true});
}
async function search(q){
 const box=$('#assetSearchResults');if(!box||q.length<2)return;box.hidden=false;box.innerHTML='<div class="search-message">در حال جستجو در صندوق‌ها و نمادهای بورسی…</div>';
 try{
  const r=await fetch('api/?action=search&q='+encodeURIComponent(q));const j=await r.json();if(!j.success)throw new Error(j.message||'جستجو ناموفق بود');
  const funds=j.results?.funds||[],stocks=j.results?.stocks||[];
  let html='';
  if(stocks.length)html+='<div class="search-group-title">سهام و نمادهای بورسی · TSETMC</div>'+stocks.map(x=>'<button class="search-result" data-kind="stock" data-id="'+esc(x.symbol)+'"><b>'+esc(x.symbol)+'</b><span>'+esc(x.name)+'</span><small>سهم</small></button>').join('');
  if(funds.length)html+='<div class="search-group-title">صندوق‌ها · TSETMC</div>'+funds.slice(0,20).map(x=>'<button class="search-result" data-kind="fund" data-id="'+esc(x.ins_code||x.slug)+'" data-reg-no="'+esc(x.reg_no||'')+'"><b>'+esc(x.name)+'</b><span>'+esc(x.symbol||x.slug)+'</span><small>'+esc(x.board||'صندوق')+'</small></button>').join('');
  box.innerHTML=html||'<div class="search-message">نتیجه‌ای پیدا نشد. املای نماد یا نام را تغییر بده.</div>';
  box.querySelectorAll('[data-kind]').forEach(b=>b.addEventListener('click',()=>openProfile(b.dataset.kind,b.dataset.id,b.dataset.regNo||'')));
 }catch(e){box.innerHTML='<div class="search-message">'+esc(e.message)+'</div>'}
}
function destroyCharts(){[chart,volumeChart,rsiChart].forEach(c=>{if(c)c.dispose()});chart=volumeChart=rsiChart=null}
function movingAverage(vals,n){return vals.map((_,i)=>i<n-1?null:vals.slice(i-n+1,i+1).reduce((a,b)=>a+b,0)/n)}
function rsi(vals,n=14){return vals.map((_,i)=>{if(i<n)return null;let up=0,down=0;for(let k=i-n+1;k<=i;k++){const d=vals[k]-vals[k-1];if(d>0)up+=d;else down-=d}if(!down)return 100;const rs=(up/n)/(down/n);return 100-100/(1+rs)})}
function showProfile(){
 let root=$('#assetProfileOverlay');if(!root){root=document.createElement('div');root.id='assetProfileOverlay';document.body.append(root)}
 const p=profile||{},isStock=kind==='stock',name=isStock?(p.name||p.symbol):(p.name||p.title||assetId),unit=p.unit||(isStock?'rial':'');
 root.innerHTML='<div class="profile-page"><div class="profile-toolbar"><button id="profileBack" class="btn btn-ghost">← بازگشت</button><span class="profile-source">FANDBOARD ASSET INTELLIGENCE</span><button id="profileRefresh" class="btn btn-ghost">↻ به‌روزرسانی</button></div><div class="profile-heading"><div><div class="profile-kind">'+(kind==='fund'?'صندوق قابل معامله (ETF)':'سهام و اوراق بهادار')+'</div><h2>'+esc(name)+'</h2><p>'+esc(isStock?(p.sector||'بازار بورس و فرابورس'):(p.slug||assetId))+'</p></div><div class="profile-price"><strong>'+fmt(isStock?p.last:p.price)+'</strong><small>'+esc(unit==='rial'?'ریال':unit||'واحد قیمت')+'</small><b class="'+(Number(isStock?p.change_pct:p.change)>=0?'positive':'negative')+'">'+(Number(isStock?p.change_pct:p.change)>0?'+':'')+fmt(isStock?p.change_pct:p.change)+'٪</b></div></div><div class="profile-stat-grid">'+(isStock?(kind==='fund'?[['قیمت آخرین معامله',p.last],['قیمت پایانی',p.close],['NAV ابطال',p.nav?.redemption],['NAV صدور',p.nav?.subscription],['فاصله قیمت با NAV٪',p.premium_pct],['حجم معاملات',p.volume]]:[['قیمت پایانی',p.close],['قیمت آغازین',p.open],['بیشترین روز',p.high],['کمترین روز',p.low],['حجم معاملات',p.volume],['ارزش معاملات (ریال)',p.value_rial],['EPS',p.eps],['P/E',p.pe],['P/E گروه',p.sector_pe],['شناوری٪',p.free_float_pct],['کف ۵۲ هفته',p.year_low],['سقف ۵۲ هفته',p.year_high],['حجم مبنا',p.base_volume]]):[['قیمت',p.price],['تغییر روزانه',p.change],['واحد',p.unit],['گروه',p.category||p.board],['آخرین بروزرسانی',p.updated_at],['شناسه',p.slug]]).map(x=>'<div class="profile-stat"><small>'+esc(x[0])+'</small><b>'+esc(fmt(x[1]))+'</b></div>').join('')+'</div><section class="profile-chart-panel"><div class="profile-chart-head"><div><h3>تاریخچه قیمت و تحلیل تکنیکال</h3><p>نمودار بر اساس داده تاریخی منبع متصل</p></div><div class="profile-controls"><div class="segmented" id="profileRanges">'+['1w','1m','3m','6m','1y'].map(x=>'<button data-range="'+x+'" class="'+(range===x?'selected':'')+'">'+({'1w':'هفته','1m':'ماه','3m':'سه‌ماه','6m':'شش‌ماه','1y':'یک‌سال'}[x])+'</button>').join('')+'</div><div class="segmented"><button data-mode="candle" class="'+(mode==='candle'?'selected':'')+'">کندل</button><button data-mode="line" class="'+(mode==='line'?'selected':'')+'">خطی</button></div></div></div><div id="profileMainChart" class="profile-main-chart"></div><div class="chart-legend"><span>قیمت</span><span>میانگین متحرک ۲۰</span><span>حجم معاملات</span></div><h4>حجم معاملات</h4><div id="profileVolumeChart" class="profile-volume-chart"></div><h4>شاخص قدرت نسبی (RSI 14)</h4><div id="profileRsiChart" class="profile-rsi-chart"></div><div id="profileDataNote" class="profile-data-note"></div></section><section class="research-panel"><div class="research-head"><div><span class="research-kicker">CROSS-SOURCE INTELLIGENCE</span><h3>اتاق تحلیل ترکیبی فاندبورد</h3><p>ترکیب تاریخچه و جریان حقیقی TSETMC با اطلاعیه‌های رسمی کدال</p></div><span class="research-source">TSETMC + CODAL</span></div><div id="researchFlags" class="research-flags"></div><div id="researchMetrics" class="research-metrics"></div><div class="research-flow-head"><div><h4>رد پول حقیقی</h4><small>خالص ارزش خرید حقیقی در ۳۰ جلسه اخیر · ریال</small></div><b id="researchFlowSummary">—</b></div><div id="researchFlowChart" class="research-flow-chart"></div><div class="research-method" id="researchMethod"></div></section><section class="event-panel"><div class="event-head"><div><span class="research-kicker">OFFICIAL DISCLOSURES</span><h3>رادار رویدادهای کدال</h3><p>اطلاعیه‌های ناشر در کنار وضعیت بازار</p></div><span>منبع رسمی</span></div><div id="researchEvents" class="research-events"></div></section><div class="profile-disclaimer">داده‌ها ممکن است با تأخیر منبع منتشر شوند. شاخص‌های تکنیکال صرفاً محاسباتی و ابزار بررسی هستند و توصیه خرید یا فروش نیستند.</div>'+(function(){const insight=calcInsights(window.__fbHistory||[]);return '<section class="profile-insight-panel"><div class="profile-section-heading"><div><h3>تحلیل ترکیبی فاندبورد</h3><p>محاسبه از تاریخچه قیمت دریافت‌شده؛ بازده این بازه، نوسان سالانه‌شده و افت از سقف</p></div><span class="source-tag">محاسبات فاندبورد</span></div>'+(insight?'<div class="profile-insight-grid"><div><small>بازده بازه نمودار</small><b class="'+(insight.periodReturn>=0?'positive':'negative')+'">'+fmt(insight.periodReturn)+'٪</b></div><div><small>نوسان سالانه‌شده</small><b>'+fmt(insight.volatility)+'٪</b></div><div><small>بیشترین افت از سقف</small><b class="negative">'+fmt(insight.maxDrawdown)+'٪</b></div><div><small>تعداد مشاهدات</small><b>'+fmt(insight.observations)+'</b></div></div>':'<p>برای محاسبه شاخص‌های بازده و ریسک، تاریخچه کافی دریافت نشد.</p>')+'<p class="profile-data-note">این ارقام صرفاً محاسبات آماری روی داده‌های موجود هستند و جایگزین تحلیل بنیادی یا توصیه سرمایه‌گذاری نیستند.</p></section><section class="profile-disclosures"><div class="profile-section-heading"><div><h3>ردپای اطلاعاتی در کدال</h3><p>اطلاعیه‌ها و گزارش‌های ناشر که می‌توانند به تحلیل دارایی کمک کنند</p></div><span class="source-tag">CODAL</span></div>'+(disclosures.length?disclosures.map(x=>'<a target="_blank" rel="noopener" href="'+esc(x.url||'#')+'"><b>'+esc(x.title)+'</b><small>'+esc(x.company||x.symbol||'')+' · '+esc(x.sent||'')+'</small></a>').join(''):'<p>در این نوبت اطلاعیه مرتبطی از کدال دریافت نشد؛ ممکن است نماد صندوق/شرکت در کدال با نام دیگری ثبت شده باشد.</p>')+'</section>'})()+'</div>';
 $('#profileBack').onclick=()=>{root.remove();destroyCharts()};$('#profileRefresh').onclick=()=>loadProfile();
 root.querySelectorAll('[data-range]').forEach(b=>b.onclick=()=>{range=b.dataset.range;loadProfile()});
 root.querySelectorAll('[data-mode]').forEach(b=>b.onclick=()=>{mode=b.dataset.mode;renderCharts()});
 renderCharts();
 renderResearch();
}
function renderResearch(){
 const r=research||{},m=r.metrics||{},flags=r.flags||[],events=r.events||[];
 const fmtCompact=v=>v==null?'—':Number(v).toLocaleString('fa-IR',{notation:'compact',maximumFractionDigits:2});
 const metrics=[['بازده ۱ هفته',m.return_1w,'٪'],['بازده ۱ ماه',m.return_1m,'٪'],['بازده ۳ ماه',m.return_3m,'٪'],['بازده ۱ سال',m.return_1y,'٪'],['نوسان سالانه‌شده',m.annualized_volatility,'٪'],['بیشترین افت بازه',m.max_drawdown,'٪'],['RSI (۱۴)',m.rsi14,''],['نسبت حجم ۲۰ به ۶۰ روز',m.volume_ratio_20_60,'x'],['خالص پول حقیقی ۵ جلسه',fmtCompact(m.net_real_value_5),' ریال'],['خالص پول حقیقی ۲۰ جلسه',fmtCompact(m.net_real_value_20),' ریال']];
 const me=$('#researchMetrics');if(me)me.innerHTML=metrics.map(x=>'<div class="research-metric"><small>'+esc(x[0])+'</small><b>'+esc(x[1]==null?'—':fmt(x[1]))+esc(x[2])+'</b></div>').join('');
 const fe=$('#researchFlags');if(fe)fe.innerHTML=flags.length?flags.map(x=>'<span class="research-flag '+esc(x.tone)+'">'+esc(x.label)+'</span>').join(''):'<div class="data-empty">برای استخراج وضعیت تحلیلی، داده کافی دریافت نشد.</div>';
 const summary=$('#researchFlowSummary');if(summary)summary.textContent=m.net_real_value_20==null?'—':fmtCompact(m.net_real_value_20)+' ریال';
 const flowEl=$('#researchFlowChart'),flow=r.flow_series||[];
 if(flowEl&&flow.length&&window.echarts){const c=echarts.init(flowEl);c.setOption({tooltip:{trigger:'axis'},grid:{left:65,right:20,top:15,bottom:28},xAxis:{type:'category',data:flow.map(x=>faDate(x.date)),axisLabel:{color:'#8796ad',hideOverlap:true}},yAxis:{type:'value',axisLabel:{color:'#8796ad',formatter:v=>Number(v).toLocaleString('fa-IR',{notation:'compact'})},splitLine:{lineStyle:{color:'#26364b'}}},series:[{type:'bar',name:'خالص ارزش خرید حقیقی',data:flow.map(x=>Number(x.net_real_value||0)),itemStyle:{color:'#49b997'}}]});}
 else if(flowEl)flowEl.innerHTML='<div class="data-empty">داده جریان حقیقی برای نمودار موجود نیست.</div>';
 const ev=$('#researchEvents');if(ev)ev.innerHTML=events.length?events.map(x=>'<a class="research-event" href="'+esc(x.url||'#')+'" target="_blank" rel="noopener"><span class="event-type">'+esc(x.type)+'</span><b>'+esc(x.title)+'</b><small>'+esc(x.sent||'تاریخ نامشخص')+' · شماره پیگیری '+esc(x.tracing_no||'—')+'</small><span class="event-open">مشاهده در کدال ←</span></a>').join(''):'<div class="data-empty">'+esc(r.codal_error||'اطلاعیه مرتبطی از کدال دریافت نشد.')+'</div>';
 const method=$('#researchMethod');if(method)method.textContent=r.methodology||'شاخص‌های محاسباتی بر اساس داده‌های موجود منبع؛ در صورت ناقص بودن تاریخچه، دوره محاسبه کوتاه‌تر خواهد بود.';
}
function renderCharts(){
 destroyCharts();const rows=Array.isArray(window.__fbHistory)?window.__fbHistory:[];
 const stock=kind==='stock';
 const labels=rows.map(x=>faDate(x.date??x.dEven??''));
 const closes=rows.map(x=>Number(x.close??x.pClosing??x.value??x));
 const valid=closes.filter(Number.isFinite);const ma=movingAverage(closes,20),r=rsi(closes);
 const main=$('#profileMainChart');if(!main)return;
 if(!rows.length||!valid.length){main.innerHTML='<div class="data-empty">تاریخچه قیمت از منبع متصل دریافت نشد؛ قیمت فعلی در صورت دسترسی در بالا نمایش داده می‌شود.</div>';$('#profileDataNote').textContent='بدون داده تاریخی قابل ترسیم';return}
 const axis={axisLine:{lineStyle:{color:'#2a3b54'}},axisLabel:{color:'#91a0b8',fontFamily:'Vazirmatn'},splitLine:{lineStyle:{color:'#26364b'}}};
 const series=mode==='candle'&&stock?[{type:'candlestick',name:'قیمت',data:rows.map(x=>[Number(x.open),Number(x.close),Number(x.low),Number(x.high)]),itemStyle:{color:'#36c99a',color0:'#ef7180',borderColor:'#36c99a',borderColor0:'#ef7180'}},{type:'line',name:'MA20',data:ma,symbol:'none',lineStyle:{width:1.5,type:'dashed',color:'#e8bd69'}}]:[{type:'line',name:'قیمت',data:closes,symbol:'none',smooth:true,lineStyle:{width:2.5,color:'#9486ff'},areaStyle:{opacity:.08,color:'#9486ff'}},{type:'line',name:'MA20',data:ma,symbol:'none',lineStyle:{width:1.5,type:'dashed',color:'#e8bd69'}}];
 chart=echarts.init(main);chart.setOption({tooltip:{trigger:'axis'},legend:{show:true,textStyle:{color:'#9aa9be'}},grid:{left:60,right:25,top:40,bottom:35},xAxis:{type:'category',data:labels,axisLabel:{...axis.axisLabel,hideOverlap:true}},yAxis:{type:'value',scale:true,...axis},dataZoom:[{type:'inside'},{type:'slider',height:18,bottom:4}],series});
 const volEl=$('#profileVolumeChart');if(volEl){volumeChart=echarts.init(volEl);volumeChart.setOption({tooltip:{trigger:'axis'},grid:{left:60,right:25,top:10,bottom:25},xAxis:{type:'category',data:labels,axisLabel:{show:false}},yAxis:{type:'value',axisLabel:{color:'#8796ad'},splitLine:{lineStyle:{color:'#26364b'}}},series:[{type:'bar',name:'حجم',data:rows.map(x=>Number(x.volume??x.qTotTran5J??0)),itemStyle:{color:'#638fe0'}}]})}
 const rsiEl=$('#profileRsiChart');rsiChart=echarts.init(rsiEl);rsiChart.setOption({tooltip:{trigger:'axis'},grid:{left:60,right:25,top:10,bottom:25},xAxis:{type:'category',data:labels,axisLabel:{show:false}},yAxis:{type:'value',min:0,max:100,...axis},series:[{type:'line',name:'RSI 14',data:r,symbol:'none',lineStyle:{color:'#46c9a0',width:2},markLine:{silent:true,data:[{yAxis:70},{yAxis:30}],lineStyle:{type:'dashed',color:'#75849b'}}}]});
 $('#profileDataNote').textContent='تعداد نقاط: '+fmt(rows.length)+' · میانگین متحرک ۲۰ دوره‌ای و RSI ۱۴ دوره‌ای از داده‌های دریافت‌شده محاسبه شده‌اند.';
}
async function loadProfile(){
 const root=$('#assetProfileOverlay');if(!root)return;$('#profileMainChart').innerHTML='<div class="data-empty">در حال دریافت پروفایل و تاریخچه…</div>';
 try{
  const r=await fetch('api/?action=profile&kind='+encodeURIComponent(kind)+'&id='+encodeURIComponent(assetId)+'&range='+encodeURIComponent(range)+'&reg_no='+encodeURIComponent(regNo));const j=await r.json();if(!j.success)throw new Error(j.message||'دریافت پروفایل ناموفق بود');
  profile=j.profile||{};research=j.research||null;disclosures=j.disclosures||[];window.__fbHistory=j.history||[];
  showProfile();
 }catch(e){$('#profileMainChart').innerHTML='<div class="data-empty">'+esc(e.message)+'</div>';$('#profileDataNote').textContent='منبع داده در دسترس نیست.'}
}
async function openProfile(k,id,registration=''){kind=k;assetId=id;regNo=registration;range='1m';mode='candle';const root=document.createElement('div');root.id='assetProfileOverlay';document.body.append(root);root.innerHTML='<div class="profile-page"><div class="data-empty">در حال دریافت پروفایل…</div></div>';await loadProfile()}
window.addEventListener('fundboard-open-profile',e=>openProfile(e.detail.kind,e.detail.id,e.detail.reg_no||''));
document.addEventListener('DOMContentLoaded',addSearch);if(document.readyState!=='loading')addSearch();
window.addEventListener('resize',()=>{chart?.resize();volumeChart?.resize();rsiChart?.resize()});
})();