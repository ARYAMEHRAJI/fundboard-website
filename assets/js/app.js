(()=>{'use strict';
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
let charts=[];
const titles={overview:'داشبورد بازارهای مالی',funds:'مرکز تحلیل صندوق‌ها',stocks:'بورس و فرابورس',gold:'طلا و ارز',crypto:'بازار رمزارزها',screener:'دیده‌بان و فیلتر هوشمند',compare:'مقایسه دارایی‌ها',portfolio:'پرتفوی من',codal:'سامانه اطلاعیه‌های کدال'};
const pageConfig={
 funds:{bridge:true,items:['بازدهی دوره‌ای','NAV و حباب','جریان ورود و خروج پول','ترکیب دارایی و تمرکز پرتفوی']},
 stocks:{board:'market',bridge:true,items:['شاخص کل و هم‌وزن','شاخص فرابورس','نمای کلی بورس و انرژی']},
 gold:{board:'gold',items:['طلای ۱۸ و ۲۴ عیار','سکه و آب‌شده','انس جهانی و نقره']},
 crypto:{board:'crypto',bridge:true,items:['قیمت رمزارزها','مقایسه و اختلاف قیمت','تغییرات بازار']},
 screener:{items:['فیلتر بازدهی و ریسک','فیلتر نقدشوندگی و اندازه صندوق','مرتب‌سازی و ذخیره فیلتر']},
 compare:{items:['مقایسه بازدهی در بازه‌های مختلف','مقایسه نوسان و افت سرمایه','مقایسه ریسک به بازده']},
 portfolio:{items:['ثبت دارایی و تعداد واحد','بهای تمام‌شده و سود و زیان','سهم هر دارایی از کل پرتفوی']},
 codal:{bridge:true,items:['جست‌وجوی ناشر و نماد','فیلتر نوع اطلاعیه','گزارش‌های ماهانه و صورت‌های مالی']}
};
const toast=m=>{const e=$('#toast');e.textContent=m;e.classList.add('show');setTimeout(()=>e.classList.remove('show'),2800)};
const fmt=n=>n===null||n===undefined||n===''?'—':Number(n).toLocaleString('fa-IR',{maximumFractionDigits:4});
function addChart(el,opt){if(!el||!window.echarts)return null;const c=echarts.init(el);c.setOption(opt);charts.push(c);return c}
function drawOverviewCharts(){
 charts.forEach(c=>c.dispose());charts=[];
 const ax={axisLine:{lineStyle:{color:'#26364d'}},axisLabel:{color:'#8090a8',fontFamily:'Vazirmatn',fontSize:9},splitLine:{lineStyle:{color:'rgba(130,150,180,.10)'}}};
 addChart($('#mainChart'),{tooltip:{trigger:'axis',backgroundColor:'#1b2940',textStyle:{color:'#eef3fc',fontFamily:'Vazirmatn'}},grid:{left:35,right:12,top:15,bottom:25},xAxis:{type:'category',boundaryGap:false,data:['ش','ی','د','س','چ','پ','ج','ش','ی','د','س','ی'],...ax},yAxis:{type:'value',...ax},series:[{name:'روند نمونه',type:'line',smooth:true,symbol:'none',data:[18,21,19,24,22,28,26,31,29,35,33,38],lineStyle:{width:3,color:'#9486ff'},areaStyle:{opacity:.08,color:'#9486ff'}},{name:'میانگین نمونه',type:'line',smooth:true,symbol:'none',data:[17,18,19,20,22,23,25,26,28,29,31,33],lineStyle:{type:'dashed',color:'#46c9a0',width:2}}]});
 addChart($('#allocationChart'),{tooltip:{trigger:'item'},series:[{type:'pie',radius:['62%','82%'],center:['50%','48%'],label:{show:false},itemStyle:{borderColor:'#111b2c',borderWidth:4},data:[{value:38,name:'سهام (نمونه)',itemStyle:{color:'#8878f5'}},{value:29,name:'درآمد ثابت (نمونه)',itemStyle:{color:'#46c9a0'}},{value:21,name:'طلا (نمونه)',itemStyle:{color:'#e8b85f'}},{value:12,name:'سایر (نمونه)',itemStyle:{color:'#619ee9'}}]}]});
}
async function getJson(url){const r=await fetch(url,{headers:{Accept:'application/json'}});if(!r.ok)throw new Error('HTTP '+r.status);return r.json()}
function renderMetrics(rows){
 const cards=$$('#marketMetrics .metric-card');
 (rows||[]).forEach((r,i)=>{const c=cards[i];if(!c)return;const val=c.querySelector('strong'),small=c.querySelector('small');val.textContent=r.price===undefined?'—':fmt(r.price);small.textContent=(r.change===undefined?'داده دریافتی':(Number(r.change)>0?'+':'')+fmt(r.change)+'٪')+' · '+(r.unit||'');});
}
async function loadOverview(){
 try{
  const d=await getJson('api/?action=overview');
  if(!d.success||d.status!=='live')return;
  renderMetrics(d.metrics);
  const market=(d.boards||[]).find(x=>x.key==='market');
  const idx=(market?.rows||[]).find(x=>/tedpix/i.test(x.slug||''));
  if(idx?.slug){const h=await getJson('api/?action=history&slug='+encodeURIComponent(idx.slug)+'&range=3m');const data=h.data||{};if(h.success&&Array.isArray(data.points)&&data.points.length){const c=echarts.getInstanceByDom($('#mainChart'));if(c)c.setOption({xAxis:{data:data.labels?.length?data.labels:data.dates},series:[{name:'شاخص کل',data:data.points},{name:'میانگین متحرک',data:[]}]});$('.chart-panel .panel-head p').textContent='تاریخچه شاخص کل · داده دریافتی از Tindex';}}
  $('.market-status').innerHTML='<span class="pulse"></span> داده متصل';
  $('.notice small').textContent='قیمت‌های کارت‌های بازار از API تیندکس دریافت می‌شوند؛ نمودار ترکیب دارایی همچنان نمای نمونه است.';
 }catch(e){/* Demo mode remains visible; never substitute fabricated live prices. */}
}
async function loadBoard(key){
 const d=await getJson('api/?action=board&key='+encodeURIComponent(key));
 if(!d.success||d.status!=='live')throw new Error(d.message||'منبع داده متصل نیست');
 const data=d.data,rows=Array.isArray(data.rows)?data.rows:[];
 const target=$('#boardRows');if(!target)return;
 if(!rows.length){target.innerHTML='<div class="data-empty">در حال حاضر داده‌ای برای این تابلو دریافت نشد.</div>';return}
 target.innerHTML='<div style="overflow:auto"><table class="data-table"><thead><tr><th>دارایی / شاخص</th><th>قیمت</th><th>تغییر</th><th>واحد</th><th>آخرین به‌روزرسانی</th></tr></thead><tbody>'+rows.map(r=>'<tr><td>'+safe(r.name||r.slug)+'</td><td>'+fmt(r.price)+'</td><td style="color:'+(Number(r.change)>=0?'#46c9a0':'#f07885')+'">'+(Number(r.change)>0?'+':'')+fmt(r.change)+'٪</td><td>'+safe(r.unit||'—')+'</td><td>'+safe(r.updated_at||'—')+'</td></tr>').join('')+'</tbody></table></div>';
 const first=rows.find(r=>Array.isArray(r.spark)&&r.spark.length);
 if(first){const chartEl=$('#viewChart');if(chartEl){const c=addChart(chartEl,{tooltip:{trigger:'axis'},grid:{left:40,right:15,top:20,bottom:30},xAxis:{type:'category',data:first.spark.map((_,i)=>String(i+1)),axisLabel:{color:'#8292aa'}},yAxis:{type:'value',axisLabel:{color:'#8292aa'},splitLine:{lineStyle:{color:'#243249'}}},series:[{name:first.name,type:'line',data:first.spark,symbol:'none',smooth:true,lineStyle:{color:'#8b7cff',width:3},areaStyle:{opacity:.08,color:'#8b7cff'}}]});}}
}
async function loadBridge(source){
 const host=$('#bridgeRows');if(!host)return;
 try{
  const result=await getJson('api/?action=bridge&source='+encodeURIComponent(source));
  if(!result.success||result.status!=='live')throw new Error(result.message||'not configured');
  const raw=result.data||{},items=Array.isArray(raw)?raw:(raw.items||raw.rows||raw.results||[]);
  if(!items.length){host.innerHTML='<div class="data-empty">اتصال برقرار شد؛ اما هنوز رکوردی از منبع دریافت نشده است.</div>';return}
  const title=titles[source]||'بازار';
  host.innerHTML='<div class="panel" style="margin-top:14px"><div class="panel-head"><div><h3>داده‌های تکمیلی '+title+'</h3><p>دریافت‌شده از سرویس واسط</p></div><span class="status-pill">LIVE BRIDGE</span></div><div style="overflow:auto"><table class="data-table"><thead><tr><th>عنوان</th><th>نماد</th><th>مقدار</th><th>تغییر</th><th>آخرین به‌روزرسانی</th></tr></thead><tbody>'+items.map(x=>'<tr><td>'+safe(x.name||x.title||'—')+'</td><td>'+safe(x.symbol||x.ticker||'—')+'</td><td>'+fmt(x.value??x.price??x.nav)+'</td><td>'+fmt(x.change??x.return??'—')+'</td><td>'+safe(x.updated_at||'—')+'</td></tr>').join('')+'</tbody></table></div></div>';
  const empty=host.parentElement.querySelector('.data-empty');if(empty)empty.remove();
 }catch(e){host.innerHTML='<div class="data-empty">سرویس واسط این بخش هنوز تنظیم نشده یا پاسخ معتبری نداد.</div>'}
}
function safe(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function navigate(v){
 if(!titles[v])return;$('#pageTitle').textContent=titles[v];$$('.nav-item').forEach(b=>b.classList.toggle('active',b.dataset.view===v));
 const home=v==='overview';['.welcome-row','.notice','#marketMetrics','.content-grid','.feature-grid','.lower-grid'].forEach(s=>{const e=$(s);if(e)e.hidden=!home});
 const old=$('#dynamicView');if(old)old.remove();
 if(home){drawOverviewCharts();loadOverview()}else{
  const cfg=pageConfig[v]||{items:[]},e=document.createElement('section');e.id='dynamicView';e.className='view-panel';
  e.innerHTML='<div class="view-intro"><h2>'+titles[v]+'</h2><p>پنل تخصصی فاندبورد؛ داده زنده پس از تنظیم منبع مربوطه فعال می‌شود.</p></div><div class="mini-stat-grid">'+cfg.items.slice(0,3).map(x=>'<div class="mini-stat"><small>'+x+'</small><b>—</b></div>').join('')+'</div><div class="panel"><div class="panel-head"><div><h3>نمای تحلیلی</h3><p>روند و داده‌های بازار</p></div><span class="status-pill">اتصال منبع</span></div><div id="viewChart" class="chart chart-tall"></div>'+(cfg.board?'<div id="boardRows"><div class="data-empty">در حال دریافت داده…</div></div>':'')+(cfg.bridge?'<div id="bridgeRows"><div class="data-empty">در حال بررسی سرویس واسط…</div></div>':'<div class="data-empty"><b>منبع داده این بخش هنوز متصل نشده است</b><p>برای جلوگیری از نمایش اطلاعات ساختگی، اطلاعات واقعی تا زمان فعال‌سازی منبع نمایش داده نمی‌شود.</p><span class="status-pill">SOURCE NOT CONFIGURED</span></div>')+'</div>';
  $('.page-content').insertBefore(e,$('footer'));
  if(cfg.board){loadBoard(cfg.board).catch(()=>{$('#boardRows').innerHTML='<div class="data-empty">اتصال این منبع در دسترس نیست؛ توکن Tindex و دسترسی سرور را بررسی کنید.</div>'})}
  if(cfg.bridge)loadBridge(v);
  if(!cfg.board)addChart($('#viewChart'),{grid:{left:35,right:15,top:20,bottom:30},xAxis:{type:'category',data:[],axisLabel:{color:'#8292aa'}},yAxis:{type:'value',axisLabel:{color:'#8292aa'},splitLine:{lineStyle:{color:'#243249'}}},series:[{type:'line',data:[],symbol:'none',lineStyle:{color:'#8b7cff',width:3}}]});
 }
 $('#sidebar').classList.remove('open');window.scrollTo({top:0,behavior:'smooth'});
}
$$('[data-view]').forEach(b=>b.addEventListener('click',()=>navigate(b.dataset.view)));
$('#refreshBtn').addEventListener('click',async()=>{const b=$('#refreshBtn');b.textContent='…';try{await loadOverview();toast('درخواست به‌روزرسانی انجام شد.')}catch(e){toast('خطا در دریافت اطلاعات؛ تنظیمات سرور را بررسی کنید.')}finally{b.textContent='↻'}});
$('#menuBtn').addEventListener('click',()=>$('#sidebar').classList.toggle('open'));
$('#exportBtn').addEventListener('click',()=>toast('خروجی CSV پس از اتصال داده‌های جدول فعال می‌شود.'));
$$('[data-period]').forEach(b=>b.addEventListener('click',()=>{$$('[data-period]').forEach(x=>x.classList.remove('selected'));b.classList.add('selected');toast('بازه انتخاب شد. برای دریافت تاریخچه کامل، منبع داده باید متصل باشد.')}));
$('#todayLabel').textContent=new Intl.DateTimeFormat('fa-IR',{weekday:'long',year:'numeric',month:'long',day:'numeric'}).format(new Date());
drawOverviewCharts();loadOverview();window.addEventListener('resize',()=>charts.forEach(c=>c.resize()));
})();