(()=>{'use strict';
const $=s=>document.querySelector(s);
const toolSets={
 stocks:[
  ['search','جست‌وجوی نماد','نماد یا نام شرکت','query'],
  ['quote','تابلوی نماد','نماد','symbol'],
  ['movers','برترین‌های بازار','نوع مرتب‌سازی',''],
  ['orderbook','عمق سفارش‌ها','نماد','symbol'],
  ['history','تاریخچه قیمت','نماد','symbol'],
  ['flow','معاملات حقیقی و حقوقی','نماد','symbol'],
  ['indices','شاخص‌ها و صنایع','نام شاخص یا صنعت (اختیاری)','query'],
  ['index_history','تاریخچه شاخص','نام شاخص','index']
 ],
 codal:[
  ['company','جست‌وجوی ناشر','نام شرکت یا نماد','query'],
  ['search_letters','جست‌وجوی اطلاعیه‌ها','نماد (اختیاری)','symbol'],
  ['categories','گروه‌های اطلاعیه','بدون ورودی',''],
  ['financial_years','سال مالی ناشر','نماد','symbol'],
  ['letter','خواندن اطلاعیه','شماره پیگیری','tracing_no']
 ],
 crypto:[
  ['exchanges','فهرست ۱۲ صرافی','بدون ورودی',''],
  ['compare','مقایسه قیمت در صرافی‌ها','نماد ارز (مثلاً BTC)','coin'],
  ['ticker','قیمت در یک صرافی','نماد ارز','coin'],
  ['orderbook','دفتر سفارش','نماد ارز','coin'],
  ['trades','معاملات اخیر','نماد ارز','coin'],
  ['candles','نمودار کندل','نماد ارز','coin'],
  ['movers','بیشترین رشد و افت','نماد ارز (اختیاری)','coin'],
  ['markets','فهرست بازارهای یک صرافی','شناسه صرافی',''],
  ['best_execution','برآورد اجرای سفارش','نماد ارز','coin'],
  ['arbitrage','اختلاف قیمت صرافی‌ها','نماد ارز','coin'],
  ['fees','کارمزد و شبکه برداشت','شناسه صرافی','exchange'],
  ['overview','نمای کلی بازار رمزارز','بدون ورودی','']
 ]
};
const titles={stocks:'ابزارهای TSETMC',codal:'ابزارهای کدال',crypto:'ابزارهای بازار رمزارز'};
const paramsFor={
 search:{limit:10},movers:{market:'bourse',sort_by:'gain',limit:10},history:{days:30},flow:{days:3},
 indices:{limit:60},index_history:{days:30},company:{limit:10},search_letters:{page:1,category:-1,letter_type:-1},
 compare:{quote:'IRT'},ticker:{exchange:'wallex',quote:'IRT'},orderbook:{exchange:'wallex',quote:'IRT',depth:10},
 trades:{exchange:'wallex',quote:'IRT',limit:20},candles:{exchange:'wallex',quote:'IRT',interval:'1h',days:7},
 movers:{exchange:'wallex',quote:'IRT',direction:'gainers',limit:10},markets:{exchange:'wallex',quote:'IRT'},
 best_execution:{exchange:'wallex',quote:'IRT',side:'buy',amount:10000000},arbitrage:{quote:'IRT'},fees:{exchange:'wallex'}
};
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function currentView(){return document.querySelector('#dynamicView')}
function render(module){
 const host=currentView();if(!host)return;
 const tools=toolSets[module]; if(!tools)return;
 const supported=module==='stocks'?new Set(['search','quote','movers','orderbook','history','flow','indices','index_history']):module==='codal'?new Set(['company','search_letters','categories','financial_years','letter']):new Set(['exchanges','compare','ticker','markets','orderbook','trades','candles','movers','best_execution','arbitrage','overview']);
 host.innerHTML='<div class="view-intro"><h2>'+titles[module]+'</h2><p>ابزارهای داده‌ای متناظر با قابلیت‌های پروژه مرجع؛ تمام درخواست‌ها از PHP روی هاست اجرا می‌شوند.</p></div>'+
 '<div class="tool-layout"><div class="tool-list"><h3>انتخاب ابزار</h3>'+tools.map(([id,label])=>'<button class="tool-choice '+(supported.has(id)?'':'tool-pending')+'" data-module-tool="'+id+'"><span>'+esc(label)+'</span><small>'+(supported.has(id)?'قابل اجرا':'در صف پیاده‌سازی')+'</small></button>').join('')+'</div>'+
 '<div class="tool-workspace"><div class="tool-description"><h3 id="toolTitle">یک ابزار را انتخاب کن</h3><p id="toolDesc">برای دریافت داده، ابزار موردنظر را از فهرست انتخاب کن.</p></div><div id="toolForm"></div><div id="toolResult" class="tool-result"><div class="data-empty">خروجی ابزار در این قسمت نمایش داده می‌شود.</div></div></div></div>';
 host.dataset.module=module;
 host.querySelectorAll('[data-module-tool]').forEach(b=>b.addEventListener('click',()=>selectTool(module,b.dataset.moduleTool)));
}
function selectTool(module,id){
 const host=currentView();if(!host)return;
 const item=toolSets[module].find(x=>x[0]===id);if(!item)return;
 const supported=module==='stocks'?true:module==='codal'?['company','search_letters','categories','financial_years','letter'].includes(id):(['exchanges','compare','ticker','markets','orderbook','trades','candles','movers','best_execution','arbitrage','overview'].includes(id));
 host.querySelectorAll('.tool-choice').forEach(b=>b.classList.toggle('selected',b.dataset.moduleTool===id));
 $('#toolTitle').textContent=item[1];
 $('#toolDesc').textContent=supported?'پارامترها را وارد کن و درخواست را اجرا کن. پاسخ از منبع اصلی دریافت می‌شود.':'این قابلیت در فهرست ابزارهای مرجع وجود دارد؛ آداپتور مستقیم آن هنوز به هاست منتقل نشده و به‌جای نمایش داده ساختگی غیرفعال است.';
 const form=$('#toolForm'); if(!supported){form.innerHTML='<div class="data-empty">این ابزار هنوز پیاده‌سازی نشده است. سایر ابزارهای فعال همین بخش قابل استفاده‌اند.</div>';return;}
 const defaults=(module==='stocks'&&id==='movers')?{market:'bourse',sort_by:'gain',limit:10}:(paramsFor[id]||{});
 const fields=[];
 if(module==='codal' && id==='search_letters'){
  fields.push('<label>عنوان اطلاعیه (اختیاری)<input name="title" placeholder="مثلاً گزارش فعالیت ماهانه"></label>');
  fields.push('<label>از تاریخ شمسی<input name="from_date" placeholder="1405/01/01"></label>');
  fields.push('<label>تا تاریخ شمسی<input name="to_date" placeholder="1405/12/29"></label>');
  fields.push('<label>دوره گزارش (ماه)<input name="period_months" type="number" min="1" max="12" placeholder="مثلاً 3"></label>');
  fields.push('<label>شماره پیگیری (اختیاری)<input name="tracing_no" type="number"></label>');
 }
 if(item[3])fields.push('<label>'+esc(item[2])+'<input name="'+esc(item[3])+'" placeholder="'+esc(item[2])+'" '+(id==='categories'?'disabled':'')+'></label>');
 for(const [k,v] of Object.entries(defaults)){
  if(k===item[3])continue;
  if(k==='market')fields.push('<label>بازار<select name="market"><option value="bourse">بورس تهران</option><option value="farabourse">فرابورس</option><option value="rights">حق‌تقدم</option><option value="bonds">اوراق بدهی</option></select></label>');
  else if(k==='sort_by')fields.push('<label>مرتب‌سازی<select name="sort_by"><option value="gain">بیشترین رشد</option><option value="loss">بیشترین افت</option><option value="volume">حجم</option><option value="value">ارزش</option><option value="trades">تعداد معاملات</option></select></label>');
  else if(k==='quote')fields.push('<label>واحد قیمت<select name="quote"><option value="IRT">تومان</option><option value="USDT">تتر</option></select></label>');
  else if(k==='exchange')fields.push('<label>صرافی<select name="exchange">'+['nobitex','wallex','bitpin','ramzinex','tabdeal','ompfinex','exir','bit24','abantether','kifpool','okex','tetherland'].map(x=>'<option value="'+x+'">'+x+'</option>').join('')+'</select></label>');
  else if(k==='side')fields.push('<label>جهت سفارش<select name="side"><option value="buy">خرید</option><option value="sell">فروش</option></select></label>');
  else if(k==='direction')fields.push('<label>جهت<select name="direction"><option value="gainers">بیشترین رشد</option><option value="losers">بیشترین افت</option></select></label>');
  else if(k==='interval')fields.push('<label>بازه کندل<select name="interval"><option>15m</option><option selected>1h</option><option>4h</option><option>1d</option></select></label>');
  else fields.push('<label>'+esc(k)+'<input name="'+esc(k)+'" value="'+esc(v)+'" type="number"></label>');
 }
 form.innerHTML='<form id="moduleRequest" class="module-form">'+fields.join('')+'<button class="btn btn-primary" type="submit">اجرای ابزار ←</button></form>';
 $('#moduleRequest').addEventListener('submit',e=>{e.preventDefault();runTool(module,id,new FormData(e.currentTarget))});
}
async function runTool(module,id,fd){
 const result=$('#toolResult');result.innerHTML='<div class="data-empty">در حال دریافت اطلاعات…</div>';
 const q=new URLSearchParams();for(const [k,v] of fd.entries())if(String(v).trim()!=='')q.set(k,v);
 const url='api/?action=module&module='+encodeURIComponent(module)+'&tool='+encodeURIComponent(id)+'&'+q.toString();
 try{
  const r=await fetch(url,{headers:{Accept:'application/json'}});const j=await r.json();
  if(!j.success)throw new Error(j.message||'دریافت اطلاعات ناموفق بود');
  const data=j.data||{};
  let visual='';
  if(id==='candles'&&Array.isArray(data.rows)&&data.rows.length){
   visual='<div id="cryptoCandleChart" class="chart chart-tall"></div><div class="workspace-table-wrap"><table class="data-table"><thead><tr><th>زمان</th><th>باز</th><th>بیشینه</th><th>کمینه</th><th>بسته</th><th>حجم</th></tr></thead><tbody>'+data.rows.slice(-30).map(x=>'<tr><td>'+esc(x.time)+'</td><td>'+esc(x.open)+'</td><td>'+esc(x.high)+'</td><td>'+esc(x.low)+'</td><td>'+esc(x.close)+'</td><td>'+esc(x.volume)+'</td></tr>').join('')+'</tbody></table></div>';
  }else if(id==='orderbook'){
   const rows=[...(data.bids||[]).slice(0,20).map(x=>({...x,side:'خرید'})),...(data.asks||[]).slice(0,20).map(x=>({...x,side:'فروش'}))];
   visual='<div class="workspace-table-wrap"><table class="data-table"><thead><tr><th>سمت</th><th>قیمت</th><th>حجم</th><th>ارزش</th></tr></thead><tbody>'+rows.map(x=>'<tr><td>'+esc(x.side)+'</td><td>'+esc(x.price)+'</td><td>'+esc(x.quantity)+'</td><td>'+esc(x.sum||'—')+'</td></tr>').join('')+'</tbody></table></div>';
  }else if(id==='trades'){
   const rows=data.trades||[];visual='<div class="workspace-table-wrap"><table class="data-table"><thead><tr><th>زمان</th><th>قیمت</th><th>حجم</th><th>جهت</th></tr></thead><tbody>'+rows.map(x=>'<tr><td>'+esc(x.timestamp||x.time||'')+'</td><td>'+esc(x.price)+'</td><td>'+esc(x.quantity??x.volume)+'</td><td>'+esc(x.side||x.type||(x.isBuyOrder?'خرید':'فروش'))+'</td></tr>').join('')+'</tbody></table></div>';
  }else if(id==='movers'){
   const rows=data.markets||[];visual='<div class="workspace-table-wrap"><table class="data-table"><thead><tr><th>ارز</th><th>قیمت</th><th>تغییر ۲۴ساعته</th><th>حجم</th></tr></thead><tbody>'+rows.map(x=>'<tr><td>'+esc(x.name||x.coin)+'</td><td>'+esc(x.price)+'</td><td>'+esc(x.change_pct)+'٪</td><td>'+esc(x.volume)+'</td></tr>').join('')+'</tbody></table></div>';
  }
  result.innerHTML='<div class="result-head"><span class="status-pill">LIVE SOURCE RESPONSE</span><button class="text-btn" id="copyToolResult">کپی JSON</button></div>'+visual+'<pre class="json-output">'+esc(JSON.stringify(data,null,2))+'</pre>';
  $('#copyToolResult').addEventListener('click',()=>navigator.clipboard?.writeText(JSON.stringify(data,null,2)));
  if(id==='candles'&&Array.isArray(data.rows)&&data.rows.length&&window.echarts){const el=$('#cryptoCandleChart');const c=echarts.init(el);c.setOption({tooltip:{trigger:'axis'},grid:{left:55,right:20,top:20,bottom:35},xAxis:{type:'category',data:data.rows.map(x=>new Date(Number(x.time)*1000).toLocaleString('fa-IR')),axisLabel:{color:'#8796ad',hideOverlap:true}},yAxis:{type:'value',scale:true,axisLabel:{color:'#8796ad'},splitLine:{lineStyle:{color:'#26364b'}}},series:[{type:'candlestick',data:data.rows.map(x=>[Number(x.open),Number(x.close),Number(x.low),Number(x.high)]),itemStyle:{color:'#36c99a',color0:'#ef7180',borderColor:'#36c99a',borderColor0:'#ef7180'}}]});}
 }catch(err){result.innerHTML='<div class="data-empty">'+esc(err.message)+'</div>'}
}
document.addEventListener('click',e=>{
 const b=e.target.closest('[data-view]');if(!b)return;
 const v=b.dataset.view;
 if(['stocks','codal','crypto'].includes(v))setTimeout(()=>render(v),0);
});
document.addEventListener('click',e=>{
 const b=e.target.closest('[data-module-tool]');if(!b)return;
 // tool choices are wired during render
});
})();
