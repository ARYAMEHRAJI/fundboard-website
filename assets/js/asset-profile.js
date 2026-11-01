(()=>{'use strict';
const $=s=>document.querySelector(s),esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let timer=null,profile=null,chart=null,volumeChart=null,rsiChart=null,kind='stock',assetId='',range='1m',mode='candle';
function fmt(v){return v==null||v===''?'—':Number(v).toLocaleString('fa-IR',{maximumFractionDigits:2})}
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
  if(funds.length)html+='<div class="search-group-title">صندوق‌ها · Tindex</div>'+funds.slice(0,20).map(x=>'<button class="search-result" data-kind="fund" data-id="'+esc(x.slug)+'"><b>'+esc(x.name)+'</b><span>'+esc(x.slug)+'</span><small>'+esc(x.board||'صندوق')+'</small></button>').join('');
  box.innerHTML=html||'<div class="search-message">نتیجه‌ای پیدا نشد. املای نماد یا نام را تغییر بده.</div>';
  box.querySelectorAll('[data-kind]').forEach(b=>b.addEventListener('click',()=>openProfile(b.dataset.kind,b.dataset.id)));
 }catch(e){box.innerHTML='<div class="search-message">'+esc(e.message)+'</div>'}
}
function destroyCharts(){[chart,volumeChart,rsiChart].forEach(c=>{if(c)c.dispose()});chart=volumeChart=rsiChart=null}
function movingAverage(vals,n){return vals.map((_,i)=>i<n-1?null:vals.slice(i-n+1,i+1).reduce((a,b)=>a+b,0)/n)}
function rsi(vals,n=14){return vals.map((_,i)=>{if(i<n)return null;let up=0,down=0;for(let k=i-n+1;k<=i;k++){const d=vals[k]-vals[k-1];if(d>0)up+=d;else down-=d}if(!down)return 100;const rs=(up/n)/(down/n);return 100-100/(1+rs)})}
function showProfile(){
 let root=$('#assetProfileOverlay');if(!root){root=document.createElement('div');root.id='assetProfileOverlay';document.body.append(root)}
 const p=profile||{},isStock=kind==='stock',name=isStock?(p.name||p.symbol):(p.name||p.title||assetId),unit=p.unit||(isStock?'rial':'');
 root.innerHTML='<div class="profile-page"><div class="profile-toolbar"><button id="profileBack" class="btn btn-ghost">← بازگشت</button><span class="profile-source">FANDBOARD ASSET INTELLIGENCE</span><button id="profileRefresh" class="btn btn-ghost">↻ به‌روزرسانی</button></div><div class="profile-heading"><div><div class="profile-kind">'+(isStock?'سهام و اوراق بهادار':'صندوق سرمایه‌گذاری')+'</div><h2>'+esc(name)+'</h2><p>'+esc(isStock?(p.sector||'بازار بورس و فرابورس'):(p.slug||assetId))+'</p></div><div class="profile-price"><strong>'+fmt(isStock?p.last:p.price)+'</strong><small>'+esc(unit==='rial'?'ریال':unit||'واحد قیمت')+'</small><b class="'+(Number(isStock?p.change_pct:p.change)>=0?'positive':'negative')+'">'+(Number(isStock?p.change_pct:p.change)>0?'+':'')+fmt(isStock?p.change_pct:p.change)+'٪</b></div></div><div class="profile-stat-grid">'+(isStock?[['قیمت پایانی',p.close],['قیمت آغازین',p.open],['بیشترین',p.high],['کمترین',p.low],['حجم معاملات',p.volume],['ارزش معاملات (ریال)',p.value_rial]]:[['قیمت',p.price],['تغییر روزانه',p.change],['واحد',p.unit],['گروه',p.category||p.board],['آخرین بروزرسانی',p.updated_at],['شناسه',p.slug]]).map(x=>'<div class="profile-stat"><small>'+esc(x[0])+'</small><b>'+esc(fmt(x[1]))+'</b></div>').join('')+'</div><section class="profile-chart-panel"><div class="profile-chart-head"><div><h3>تاریخچه قیمت و تحلیل تکنیکال</h3><p>نمودار بر اساس داده تاریخی منبع متصل</p></div><div class="profile-controls"><div class="segmented" id="profileRanges">'+['1w','1m','3m'].map(x=>'<button data-range="'+x+'" class="'+(range===x?'selected':'')+'">'+({'1w':'هفته','1m':'ماه','3m':'سه‌ماه'}[x])+'</button>').join('')+'</div><div class="segmented"><button data-mode="candle" class="'+(mode==='candle'?'selected':'')+'">کندل</button><button data-mode="line" class="'+(mode==='line'?'selected':'')+'">خطی</button></div></div></div><div id="profileMainChart" class="profile-main-chart"></div><div class="chart-legend"><span>قیمت</span><span>میانگین متحرک ۲۰</span><span>حجم معاملات</span></div><h4>شاخص قدرت نسبی (RSI 14)</h4><div id="profileRsiChart" class="profile-rsi-chart"></div><div id="profileDataNote" class="profile-data-note"></div></section><div class="profile-disclaimer">داده‌ها ممکن است با تأخیر منبع منتشر شوند. شاخص‌های تکنیکال صرفاً محاسباتی و ابزار بررسی هستند و توصیه خرید یا فروش نیستند.</div></div>';
 $('#profileBack').onclick=()=>{root.remove();destroyCharts()};$('#profileRefresh').onclick=()=>loadProfile();
 root.querySelectorAll('[data-range]').forEach(b=>b.onclick=()=>{range=b.dataset.range;loadProfile()});
 root.querySelectorAll('[data-mode]').forEach(b=>b.onclick=()=>{mode=b.dataset.mode;renderCharts()});
 renderCharts();
}
function renderCharts(){
 destroyCharts();const rows=Array.isArray(window.__fbHistory)?window.__fbHistory:[];
 const stock=kind==='stock';
 const labels=rows.map(x=>String(x.date??x.dEven??''));
 const closes=rows.map(x=>Number(x.close??x.pClosing??x.value??x));
 const valid=closes.filter(Number.isFinite);const ma=movingAverage(closes,20),r=rsi(closes);
 const main=$('#profileMainChart');if(!main)return;
 if(!rows.length||!valid.length){main.innerHTML='<div class="data-empty">تاریخچه قیمت از منبع متصل دریافت نشد؛ قیمت فعلی در صورت دسترسی در بالا نمایش داده می‌شود.</div>';$('#profileDataNote').textContent='بدون داده تاریخی قابل ترسیم';return}
 const axis={axisLine:{lineStyle:{color:'#2a3b54'}},axisLabel:{color:'#91a0b8',fontFamily:'Vazirmatn'},splitLine:{lineStyle:{color:'#26364b'}}};
 const series=mode==='candle'&&stock?[{type:'candlestick',name:'قیمت',data:rows.map(x=>[Number(x.open),Number(x.close),Number(x.low),Number(x.high)]),itemStyle:{color:'#36c99a',color0:'#ef7180',borderColor:'#36c99a',borderColor0:'#ef7180'}},{type:'line',name:'MA20',data:ma,symbol:'none',lineStyle:{width:1.5,type:'dashed',color:'#e8bd69'}}]:[{type:'line',name:'قیمت',data:closes,symbol:'none',smooth:true,lineStyle:{width:2.5,color:'#9486ff'},areaStyle:{opacity:.08,color:'#9486ff'}},{type:'line',name:'MA20',data:ma,symbol:'none',lineStyle:{width:1.5,type:'dashed',color:'#e8bd69'}}];
 chart=echarts.init(main);chart.setOption({tooltip:{trigger:'axis'},legend:{show:true,textStyle:{color:'#9aa9be'}},grid:{left:60,right:25,top:40,bottom:35},xAxis:{type:'category',data:labels,axisLabel:{...axis.axisLabel,hideOverlap:true}},yAxis:{type:'value',scale:true,...axis},dataZoom:[{type:'inside'},{type:'slider',height:18,bottom:4}],series});
 const rsiEl=$('#profileRsiChart');rsiChart=echarts.init(rsiEl);rsiChart.setOption({tooltip:{trigger:'axis'},grid:{left:60,right:25,top:10,bottom:25},xAxis:{type:'category',data:labels,axisLabel:{show:false}},yAxis:{type:'value',min:0,max:100,...axis},series:[{type:'line',name:'RSI 14',data:r,symbol:'none',lineStyle:{color:'#46c9a0',width:2},markLine:{silent:true,data:[{yAxis:70},{yAxis:30}],lineStyle:{type:'dashed',color:'#75849b'}}}]});
 $('#profileDataNote').textContent='تعداد نقاط: '+fmt(rows.length)+' · میانگین متحرک ۲۰ دوره‌ای و RSI ۱۴ دوره‌ای از داده‌های دریافت‌شده محاسبه شده‌اند.';
}
async function loadProfile(){
 const root=$('#assetProfileOverlay');if(!root)return;$('#profileMainChart').innerHTML='<div class="data-empty">در حال دریافت پروفایل و تاریخچه…</div>';
 try{
  const r=await fetch('api/?action=profile&kind='+encodeURIComponent(kind)+'&id='+encodeURIComponent(assetId)+'&range='+encodeURIComponent(range));const j=await r.json();if(!j.success)throw new Error(j.message||'دریافت پروفایل ناموفق بود');
  profile=j.profile||{};window.__fbHistory=kind==='stock'?(j.history||[]):(j.history||[]).map((v,i)=>typeof v==='number'?{date:j.labels?.[i]||i,close:v}:v);
  showProfile();
 }catch(e){$('#profileMainChart').innerHTML='<div class="data-empty">'+esc(e.message)+'</div>';$('#profileDataNote').textContent='منبع داده در دسترس نیست.'}
}
async function openProfile(k,id){kind=k;assetId=id;range='1m';mode='candle';const root=document.createElement('div');root.id='assetProfileOverlay';document.body.append(root);root.innerHTML='<div class="profile-page"><div class="data-empty">در حال دریافت پروفایل…</div></div>';await loadProfile()}
document.addEventListener('DOMContentLoaded',addSearch);if(document.readyState!=='loading')addSearch();
window.addEventListener('resize',()=>{chart?.resize();rsiChart?.resize()});
})();