(()=>{'use strict';
const $=s=>document.querySelector(s),esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt=n=>n==null||n===''?'—':Number(n).toLocaleString('fa-IR',{maximumFractionDigits:3});
let boards=[],allFunds=[],selected=[],workspaceChart=null,intelChart=null;
async function get(url){const r=await fetch(url);const j=await r.json();if(!j.success)throw new Error(j.message||j.status||'دریافت داده ناموفق بود');return j}
async function loadBoards(){if(boards.length)return boards;const j=await get('api/?action=overview');boards=j.boards||[];allFunds=boards.flatMap(b=>(b.rows||[]).map(x=>({...x,_board:b.key})));return boards}
function base(title,desc){const h=$('#dynamicView');if(!h)return null;h.innerHTML='<div class="view-intro"><h2>'+title+'</h2><p>'+desc+'</p></div><div id="workspaceBody"><div class="data-empty">در حال دریافت داده‌های متصل…</div></div>';return $('#workspaceBody')}
function table(rows,profile=true){if(!rows.length)return '<div class="data-empty">داده‌ای برای نمایش از منبع متصل دریافت نشد.</div>';return '<div class="workspace-table-wrap"><table class="data-table"><thead><tr><th>نام دارایی</th><th>قیمت</th><th>تغییر</th><th>واحد</th><th>منبع</th><th></th></tr></thead><tbody>'+rows.map(x=>'<tr><td><b>'+esc(x.name||x.title||x.slug)+'</b><small class="table-sub">'+esc(x.symbol||x.slug||'')+'</small></td><td>'+fmt(x.price)+'</td><td class="'+(Number(x.change)>=0?'positive':'negative')+'">'+(Number(x.change)>0?'+':'')+fmt(x.change)+'٪</td><td>'+esc(x.unit||'—')+'</td><td>'+esc(x._board||'Tindex')+'</td><td>'+(profile&&(x.ins_code||x.slug)?'<button class="text-btn" data-open-fund="'+esc(x.ins_code||x.slug)+'">پروفایل ←</button>':'')+'</td></tr>').join('')+'</tbody></table></div>'}

async function renderIntelligence(){
 intelChart?.dispose();intelChart=null;
 const body=base('اتاق هوش بازار','نمای یکپارچه از نقشه بازار TSETMC، اطلاعیه‌های رسمی کدال و شاخص‌های کلان Tindex؛ داده‌های هر بخش با منبع و وضعیت دریافت نمایش داده می‌شوند.');
 if(!body)return;
 body.innerHTML='<div id="intelStatus" class="intel-status">در حال همگام‌سازی منابع داده…</div><div id="intelMetrics" class="intel-metrics"></div><div class="intel-grid"><section class="intel-card intel-map"><div class="intel-card-head"><div><h3>نقشه حرارتی بازار</h3><p>اندازه هر خانه بر اساس ارزش معاملات دریافتی</p></div><span>TSETMC</span></div><div id="intelTreemap" class="intel-treemap"><div class="data-empty">در انتظار داده بازار…</div></div></section><section class="intel-card"><div class="intel-card-head"><div><h3>نمادهای پرتحرک</h3><p>بیشترین رشد و افت در تابلو</p></div><span>TSETMC</span></div><div id="intelMovers"><div class="data-empty">در حال دریافت…</div></div></section></div><section class="intel-card intel-events"><div class="intel-card-head"><div><h3>رادار اطلاعیه‌های ناشران</h3><p>آخرین رویدادهای منتشرشده در کدال</p></div><span>CODAL</span></div><div id="intelCodal" class="intel-event-grid"><div class="data-empty">در حال دریافت اطلاعیه‌ها…</div></div></section><div class="intel-footnote">این صفحه داده‌های چند منبع را کنار هم قرار می‌دهد؛ پرچم‌ها و نماهای تحلیلی ابزار پژوهش هستند، نه پیشنهاد خرید یا فروش. در صورت قطع دسترسی هاست به یک منبع، همان بخش با وضعیت خطا مشخص می‌شود.</div>';
 const jobs=[
  ['market',get('api/?action=module&module=tsetmc&tool=market_map&market=0&type=1&limit=1500')],
  ['movers',get('api/?action=module&module=tsetmc&tool=movers&market=bourse&sort_by=gain')],
  ['codal',get('api/?action=module&module=codal&tool=search_letters&page=1')],
  ['overview',get('api/?action=overview')]
 ];
 const result=await Promise.allSettled(jobs.map(x=>x[1]));
 const data={};jobs.forEach((x,n)=>{if(result[n].status==='fulfilled')data[x[0]]=result[n].value});
 const status=[];jobs.forEach((x,n)=>status.push((result[n].status==='fulfilled'?'✓ ':'! ')+({market:'نقشه TSETMC',movers:'تابلو TSETMC',codal:'کدال',overview:'Tindex'}[x[0]])));
 $('#intelStatus').textContent=status.join('  ·  ');
 const metrics=data.overview?.metrics||[];
 $('#intelMetrics').innerHTML=metrics.map(x=>'<div class="intel-metric"><small>'+esc(x.label||x.key)+'</small><b>'+fmt(x.price??x.value)+'</b><span>'+esc(x.unit||'')+' '+(x.change==null?'':(Number(x.change)>=0?'+':'')+fmt(x.change)+'٪')+'</span></div>').join('')||'<div class="data-empty">شاخص‌های Tindex در دسترس نیستند؛ توکن یا اتصال هاست را بررسی کن.</div>';
 const movers=data.movers?.data?.rows||[];
 $('#intelMovers').innerHTML=movers.length?'<div class="workspace-table-wrap"><table class="data-table"><thead><tr><th>نماد</th><th>آخرین</th><th>تغییر٪</th><th>ارزش (ریال)</th></tr></thead><tbody>'+movers.slice(0,12).map(x=>'<tr><td><b>'+esc(x.symbol)+'</b></td><td>'+fmt(x.last)+'</td><td class="'+(Number(x.change_pct)>=0?'positive':'negative')+'">'+fmt(x.change_pct)+'٪</td><td>'+fmt(x.value_rial)+'</td></tr>').join('')+'</tbody></table></div>':'<div class="data-empty">تابلوی نمادهای پرتحرک از TSETMC دریافت نشد.</div>';
 const items=data.market?.data?.items||[];
 const mapEl=$('#intelTreemap');
 if(items.length&&window.echarts){intelChart=echarts.init(mapEl);intelChart.setOption({tooltip:{formatter:x=>'<b>'+esc(x.data.name)+'</b><br>'+esc(x.data.sector||'')+'<br>ارزش معاملات: '+fmt(x.data.value)+' ریال<br>تغییر: '+fmt(x.data.change)+'٪'},series:[{type:'treemap',data:items.map(x=>({name:x.symbol||x.name||'—',value:Math.max(1,Number(x.value_rial||0)),sector:x.sector,change:x.change_pct,itemStyle:{color:x.change_pct==null?'#53647b':Number(x.change_pct)>=0?'#167b68':'#a34f5e'}})),label:{show:true,formatter:'{b}',color:'#fff'},breadcrumb:{show:false},itemStyle:{borderColor:'#101b2b',borderWidth:2,gapWidth:2}}]})}
 else mapEl.innerHTML='<div class="data-empty">نقشه بازار از TSETMC دریافت نشد.</div>';
 const letters=data.codal?.data?.letters||[];
 $('#intelCodal').innerHTML=letters.length?letters.slice(0,12).map(x=>'<a class="intel-event" href="'+esc(x.url||'#')+'" target="_blank" rel="noopener"><span>'+esc(x.symbol||x.company||'ناشر')+' · '+esc(x.sent||'')+'</span><b>'+esc(x.title||'اطلاعیه')+'</b><small>شماره پیگیری: '+esc(x.tracing_no||'—')+' · مشاهده در کدال ←</small></a>').join(''):'<div class="data-empty">اطلاعیه‌های کدال در دسترس نیستند؛ محدودیت شبکه یا پاسخ کدال را بررسی کن.</div>';
}
function renderFunds(){
 const body=base('مرکز تحلیل صندوق‌ها','جستجوی صندوق‌های ETF پذیرفته‌شده در بازار؛ پروفایل شامل تابلو و تاریخچه قیمت است. NAV و ترکیب پرتفوی به منبع تخصصی صندوق نیاز دارد.');
 if(!body)return;
 body.innerHTML='<div class="workspace-toolbar"><input id="fundQuery" placeholder="نام یا نماد صندوق را وارد کن؛ مثال: افران"><button id="fundSearch" class="btn btn-primary">جستجوی صندوق</button><span>منبع: TSETMC</span></div><div id="fundRows"><div class="data-empty">برای جلوگیری از نمایش فهرست ناقص، نام یا نماد صندوق را جستجو کن.</div></div>';
 const run=async()=>{const q=$('#fundQuery').value.trim();if(q.length<2)return;$('#fundRows').innerHTML='<div class="data-empty">در حال جستجو…</div>';try{const j=await get('api/?action=search&q='+encodeURIComponent(q));const rows=j.results?.funds||[];$('#fundRows').innerHTML=rows.length?table(rows.map(x=>({...x,_board:x.type_title||'TSETMC ETF',slug:x.ins_code||x.symbol}))):'<div class="data-empty">صندوقی با این عبارت در نتایج TSETMC تشخیص داده نشد. می‌توانی از جستجوی عمومی بالای صفحه استفاده کنی.</div>'}catch(e){$('#fundRows').innerHTML='<div class="data-empty">'+esc(e.message)+'</div>'}};
 $('#fundSearch').onclick=run;$('#fundQuery').addEventListener('keydown',e=>{if(e.key==='Enter')run()});
}
function filterFunds(){const q=$('#fundFilter').value.toLowerCase(),cat=$('#fundCategory').value;const rows=allFunds.filter(x=>(!cat||x._board===cat)&&((x.name||'').toLowerCase().includes(q)||(x.slug||'').toLowerCase().includes(q)));$('#fundRows').innerHTML=table(rows)}
function renderGold(){
 const body=base('بازار طلا و ارز','تابلوهای طلا و ارز متصل به Tindex؛ قیمت و تغییرات فقط در صورت دریافت از منبع نمایش داده می‌شوند.');
 loadBoards().then(()=>{const b=boards.find(x=>x.key==='gold'||x.key==='currency');const rows=(b?.rows||[]).map(x=>({...x,_board:b.key}));body.innerHTML='<div class="mini-stat-grid">'+rows.slice(0,4).map(x=>'<div class="mini-stat"><small>'+esc(x.name||x.slug)+'</small><b>'+fmt(x.price)+'</b><small class="'+(Number(x.change)>=0?'positive':'negative')+'">'+fmt(x.change)+'٪</small></div>').join('')+'</div><div class="panel"><div class="panel-head"><div><h3>روند شاخص‌های منتخب</h3><p>مقادیر spark از تابلو Tindex</p></div></div><div id="goldSparkChart" class="chart chart-tall"></div></div>'+table(rows,false);const el=$('#goldSparkChart');const series=rows.filter(x=>Array.isArray(x.spark)&&x.spark.length).slice(0,5).map(x=>({name:x.name||x.slug,type:'line',data:x.spark,symbol:'none',smooth:true}));if(el&&series.length){const n=Math.max(...series.map(x=>x.data.length));const c=echarts.init(el);c.setOption({tooltip:{trigger:'axis'},legend:{textStyle:{color:'#9aa9be'}},grid:{left:55,right:20,top:35,bottom:30},xAxis:{type:'category',data:Array.from({length:n},(_,i)=>String(i+1)),axisLabel:{color:'#8796ad'}},yAxis:{type:'value',scale:true,axisLabel:{color:'#8796ad'},splitLine:{lineStyle:{color:'#26364b'}}},series})}}).catch(e=>body.innerHTML='<div class="data-empty">'+esc(e.message)+'</div>');
}
function renderScreener(){
 const body=base('دیده‌بان و غربالگر بازار','غربالگر برترین نمادهای تابلو بر اساس رشد، افت، حجم، ارزش و تعداد معاملات؛ داده از TSETMC دریافت می‌شود.');
 if(!body)return;
 body.innerHTML='<div class="workspace-toolbar"><select id="screenMarket"><option value="bourse">بورس تهران</option><option value="farabourse">فرابورس</option><option value="rights">حق‌تقدم</option><option value="bonds">اوراق بدهی</option></select><select id="screenSort"><option value="gain">بیشترین رشد</option><option value="loss">بیشترین افت</option><option value="volume">بیشترین حجم</option><option value="value">بیشترین ارزش</option><option value="trades">بیشترین معاملات</option></select><button id="screenRun" class="btn btn-primary">اجرای فیلتر</button></div><div id="screenRows"><div class="data-empty">برای دریافت آخرین فهرست، اجرای فیلتر را بزن.</div></div>';
 const run=async()=>{const target=$('#screenRows');target.innerHTML='<div class="data-empty">در حال دریافت تابلو…</div>';try{const j=await get('api/?action=module&module=tsetmc&tool=movers&market='+$('#screenMarket').value+'&sort_by='+$('#screenSort').value);const rows=j.data?.rows||[];target.innerHTML=rows.length?'<div class="workspace-table-wrap"><table class="data-table"><thead><tr><th>نماد</th><th>نام شرکت</th><th>آخرین قیمت (ریال)</th><th>تغییر٪</th><th>حجم</th><th>ارزش (ریال)</th><th></th></tr></thead><tbody>'+rows.map(x=>'<tr><td><b>'+esc(x.symbol)+'</b></td><td>'+esc(x.name)+'</td><td>'+fmt(x.last)+'</td><td class="'+(Number(x.change_pct)>=0?'positive':'negative')+'">'+fmt(x.change_pct)+'٪</td><td>'+fmt(x.volume)+'</td><td>'+fmt(x.value_rial)+'</td><td><button class="text-btn" data-open-stock="'+esc(x.symbol)+'">پروفایل ←</button></td></tr>').join('')+'</tbody></table></div>':'<div class="data-empty">داده‌ای از تابلو دریافت نشد.</div>'}catch(e){target.innerHTML='<div class="data-empty">'+esc(e.message)+'</div>'}};
 $('#screenRun').onclick=run;run();
}
function renderCompare(){
 const body=base('مقایسه چندبعدی دارایی‌ها','تا چهار سهم یا ETF را کنار هم بررسی کن: بازده نسبی، نوسان، بیشترین افت و همبستگی بازده روزانه؛ بدون نیاز به محاسبه دستی.');
 if(!body)return;
 body.innerHTML='<div class="workspace-toolbar"><input id="compareSymbol" placeholder="نماد؛ مثال: فولاد، افران"><button id="compareAdd" class="btn btn-primary">افزودن نماد</button><span id="compareCount">۰ از ۴</span></div><div id="compareSelected" class="compare-chips"></div><div class="panel"><div class="panel-head"><div><h3>مقایسه بازده تجمعی</h3><p>شروع همه سری‌ها از مبنای ۱۰۰</p></div><span class="status-pill">TSETMC · ۹۰ جلسه</span></div><div id="compareChart" class="chart chart-tall"></div></div><div class="panel"><div class="panel-head"><div><h3>مقایسه ریسک و عملکرد</h3><p>بازده، نوسان سالانه‌شده و بیشترین افت در بازه مشترک</p></div></div><div id="compareTable"></div></div><div class="panel"><div class="panel-head"><div><h3>ماتریس همبستگی</h3><p>همبستگی بازده روزانه؛ نزدیک به ۱ یعنی حرکت مشابه‌تر</p></div></div><div id="compareCorrelation"></div></div>';
 selected=[];$('#compareAdd').onclick=()=>{const v=$('#compareSymbol').value.trim();if(v&&selected.length<4&&!selected.includes(v)){selected.push(v);$('#compareSymbol').value='';drawCompare()}};
}
function correlation(a,b){
 const ma=new Map(a.map(x=>[x.date,x.value])),pairs=b.filter(x=>ma.has(x.date)).map(x=>[ma.get(x.date),x.value]);
 if(pairs.length<5)return null;const ax=pairs.map(x=>x[0]),bx=pairs.map(x=>x[1]),am=ax.reduce((x,y)=>x+y,0)/ax.length,bm=bx.reduce((x,y)=>x+y,0)/bx.length;
 let cov=0,av=0,bv=0;for(let i=0;i<pairs.length;i++){const x=ax[i]-am,y=bx[i]-bm;cov+=x*y;av+=x*x;bv+=y*y}
 return av&&bv?cov/Math.sqrt(av*bv):null;
}
async function drawCompare(){
 $('#compareCount').textContent=fmt(selected.length)+' از ۴';$('#compareSelected').innerHTML=selected.map(id=>'<button class="compare-chip" data-remove="'+esc(id)+'">'+esc(id)+' ×</button>').join('');$('#compareSelected').querySelectorAll('[data-remove]').forEach(b=>b.onclick=()=>{selected=selected.filter(x=>x!==b.dataset.remove);drawCompare()});
 if(workspaceChart){workspaceChart.dispose();workspaceChart=null}
 const series=[],tableRows=[],dataSets=[];
 for(const symbol of selected){
  try{
   const j=await get('api/?action=module&module=tsetmc&tool=history&symbol='+encodeURIComponent(symbol)+'&days=90');
   const rows=(j.data?.rows||[]).slice().sort((a,b)=>Number(a.date)-Number(b.date));
   const valid=rows.filter(x=>Number(x.close)>0);if(valid.length<6)continue;
   const first=Number(valid[0].close),vals=valid.map(x=>Number(x.close));
   const daily=[];for(let i=1;i<valid.length;i++)daily.push({date:String(valid[i].date),value:vals[i]/vals[i-1]-1});
   const mean=daily.reduce((a,x)=>a+x.value,0)/daily.length;
   const vol=Math.sqrt(daily.reduce((a,x)=>a+(x.value-mean)**2,0)/Math.max(1,daily.length-1))*Math.sqrt(252)*100;
   let peak=vals[0],dd=0;for(const v of vals){peak=Math.max(peak,v);dd=Math.min(dd,v/peak-1)}
   const ret=(vals.at(-1)/first-1)*100;
   series.push({name:symbol,type:'line',data:vals.map(v=>v/first*100),symbol:'none',smooth:true});
   tableRows.push({name:symbol,return:ret,volatility:vol,drawdown:dd*100,observations:valid.length});
   dataSets.push({name:symbol,daily});
  }catch(e){}
 }
 if(series.length){
  workspaceChart=echarts.init($('#compareChart'));const max=Math.max(...series.map(x=>x.data.length));
  workspaceChart.setOption({tooltip:{trigger:'axis'},legend:{textStyle:{color:'#9aa9be'}},grid:{left:55,right:20,top:35,bottom:35},xAxis:{type:'category',data:Array.from({length:max},(_,i)=>String(i+1)),axisLabel:{color:'#8796ad'}},yAxis:{type:'value',name:'مبنای ۱۰۰',axisLabel:{color:'#8796ad'},splitLine:{lineStyle:{color:'#26364b'}}},series});
 }
 $('#compareTable').innerHTML=tableRows.length?'<div class="workspace-table-wrap"><table class="data-table"><thead><tr><th>نماد</th><th>بازده ۹۰ جلسه</th><th>نوسان سالانه‌شده</th><th>بیشترین افت</th><th>تعداد داده</th></tr></thead><tbody>'+tableRows.map(x=>'<tr><td><b>'+esc(x.name)+'</b></td><td class="'+(x.return>=0?'positive':'negative')+'">'+fmt(x.return)+'٪</td><td>'+fmt(x.volatility)+'٪</td><td class="negative">'+fmt(x.drawdown)+'٪</td><td>'+fmt(x.observations)+'</td></tr>').join('')+'</tbody></table></div>':'<div class="data-empty">برای نمادهای واردشده تاریخچه کافی از TSETMC دریافت نشد.</div>';
 const corr=$('#compareCorrelation');
 corr.innerHTML=dataSets.length>1?'<div class="workspace-table-wrap"><table class="data-table"><thead><tr><th>نماد</th>'+dataSets.map(x=>'<th>'+esc(x.name)+'</th>').join('')+'</tr></thead><tbody>'+dataSets.map(a=>'<tr><td><b>'+esc(a.name)+'</b></td>'+dataSets.map(b=>{const v=correlation(a.daily,b.daily);return '<td>'+ (v==null?'—':fmt(v*100)+'٪')+'</td>'}).join('')+'</tr>').join('')+'</tbody></table></div>':'<div class="data-empty">برای ساخت ماتریس همبستگی، دست‌کم دو نماد با تاریخچه مشترک اضافه کن.</div>';
}
function renderPortfolio(){
 const body=base('پرتفوی من','ثبت دارایی و محاسبه سود و زیان بر مبنای قیمت تابلو. داده‌های این نسخه فقط در مرورگر همین دستگاه ذخیره می‌شود.');
 const KEY='fundboard_portfolio_v1';const load=()=>{try{return JSON.parse(localStorage.getItem(KEY)||'[]')}catch(e){return []}};
 body.innerHTML='<div class="workspace-toolbar"><input id="portfolioSymbol" placeholder="نماد سهم یا ETF"><input id="portfolioQty" type="number" min="0" step="any" placeholder="تعداد / واحد"><input id="portfolioCost" type="number" min="0" step="any" placeholder="بهای تمام‌شده واحد (ریال)"><button id="portfolioAdd" class="btn btn-primary">افزودن دارایی</button></div><div id="portfolioSummary" class="mini-stat-grid"></div><div id="portfolioRows"></div>';
 const render=async()=>{const list=load();let cost=0,value=0;const valued=[];for(const x of list){let price=null;try{const j=await get('api/?action=module&module=tsetmc&tool=quote&symbol='+encodeURIComponent(x.symbol));price=Number(j.data?.last)}catch(e){}const v=x.qty*(price||0),c=x.qty*x.cost;cost+=c;value+=v;valued.push({...x,price,value:v,pnl:v-c})}$('#portfolioSummary').innerHTML=[['ارزش برآوردی (ریال)',fmt(value)],['بهای تمام‌شده (ریال)',fmt(cost)],['سود / زیان برآوردی (ریال)',fmt(value-cost)]].map(x=>'<div class="mini-stat"><small>'+x[0]+'</small><b>'+x[1]+'</b></div>').join('');$('#portfolioRows').innerHTML=valued.length?'<div class="workspace-table-wrap"><table class="data-table"><thead><tr><th>نماد</th><th>تعداد</th><th>قیمت تابلو (ریال)</th><th>ارزش (ریال)</th><th>سود/زیان</th><th></th></tr></thead><tbody>'+valued.map(x=>'<tr><td>'+esc(x.symbol)+'</td><td>'+fmt(x.qty)+'</td><td>'+fmt(x.price)+'</td><td>'+fmt(x.value)+'</td><td>'+fmt(x.pnl)+'</td><td><button class="text-btn" data-del="'+esc(x.symbol)+'">حذف</button></td></tr>').join('')+'</tbody></table></div>':'<div class="data-empty">هنوز دارایی به پرتفوی اضافه نشده است.</div>';$('#portfolioRows').querySelectorAll('[data-del]').forEach(b=>b.onclick=()=>{localStorage.setItem(KEY,JSON.stringify(load().filter(x=>x.symbol!==b.dataset.del)));render()})};
 $('#portfolioAdd').onclick=()=>{const symbol=$('#portfolioSymbol').value.trim(),qty=Number($('#portfolioQty').value),cost=Number($('#portfolioCost').value);if(!symbol||qty<=0||cost<0)return;const list=load().filter(x=>x.symbol!==symbol);list.push({symbol,qty,cost});localStorage.setItem(KEY,JSON.stringify(list));render()};render();
}
const renderers={intelligence:renderIntelligence,funds:renderFunds,gold:renderGold,screener:renderScreener,compare:renderCompare,portfolio:renderPortfolio};
document.addEventListener('click',e=>{const b=e.target.closest('[data-view]');if(!b)return;const v=b.dataset.view;if(renderers[v]){selected=[];setTimeout(renderers[v],30)}});
document.addEventListener('click',e=>{const b=e.target.closest('[data-open-fund]');if(b)window.dispatchEvent(new CustomEvent('fundboard-open-profile',{detail:{kind:'fund',id:b.dataset.openFund}}));const st=e.target.closest('[data-open-stock]');if(st)window.dispatchEvent(new CustomEvent('fundboard-open-profile',{detail:{kind:'stock',id:st.dataset.openStock}}))});
window.addEventListener('resize',()=>{workspaceChart?.resize();intelChart?.resize()});
})();