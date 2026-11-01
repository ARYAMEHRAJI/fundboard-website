<?php
declare(strict_types=1);
$configPath = __DIR__ . '/config/config.php';
$config = is_file($configPath) ? require $configPath : require __DIR__ . '/config/config.example.php';
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Tehran');
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b1220"><meta name="description" content="فاندبورد؛ داشبورد هوشمند پایش بازار سرمایه، صندوق‌ها، طلا، ارز و رمزارز">
<title>فاندبورد | داشبورد بازارهای مالی</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css?v=1.4.0">
<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
</head>
<body>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
  <a class="brand" href="#"><span class="brand-mark">F</span><span><b>فاندبورد</b><small>INTELLIGENCE TERMINAL</small></span></a>
  <div class="side-caption">نمای کلی</div>
  <button class="nav-item active" data-view="overview"><i>◫</i> داشبورد اصلی</button>
  <div class="side-caption">بازارها</div>
  <button class="nav-item" data-view="funds"><i>▦</i> صندوق‌های سرمایه‌گذاری</button>
  <button class="nav-item" data-view="stocks"><i>▥</i> بورس و فرابورس</button>
  <button class="nav-item" data-view="gold"><i>◇</i> طلا و ارز</button>
  <button class="nav-item" data-view="crypto"><i>₿</i> رمزارزها</button>
  <div class="side-caption">ابزارهای تحلیلی</div>
  <button class="nav-item" data-view="intelligence"><i>✧</i> اتاق هوش بازار</button>
  <button class="nav-item" data-view="screener"><i>⌕</i> دیده‌بان و فیلتر</button>
  <button class="nav-item" data-view="compare"><i>⇄</i> مقایسه دارایی‌ها</button>
  <button class="nav-item" data-view="portfolio"><i>◉</i> پرتفوی من</button>
  <button class="nav-item" data-view="codal"><i>▤</i> اطلاعیه‌های کدال</button>
  <div class="sidebar-bottom"><div class="connection"><span class="pulse"></span><span>سامانه آماده دریافت داده</span></div><small>FANDBOARD • v1.0.0</small></div>
</aside>
<main class="main">
<header class="topbar">
  <div class="top-left"><button class="icon-btn mobile-menu" id="menuBtn" aria-label="منو">☰</button><div><div class="eyebrow">MARKET INTELLIGENCE</div><h1 id="pageTitle">داشبورد بازارهای مالی</h1></div></div>
  <div class="top-actions"><div class="market-status"><span class="pulse"></span> در انتظار اتصال</div><button class="icon-btn" id="refreshBtn" title="به‌روزرسانی">↻</button><button class="profile-chip">A<span>کاربر</span></button></div>
</header>
<section class="page-content">
<div class="welcome-row"><div><div class="date-line" id="todayLabel">نمای یکپارچه بازار</div><h2>تصویر روشن‌تری از <em>بازار</em> داشته باش.</h2><p>داده‌ها، روندها و فرصت‌ها را در یک نگاه دنبال کن.</p></div><div class="hero-actions"><button class="btn btn-ghost" id="exportBtn">↓ خروجی CSV</button><button class="btn btn-primary" data-view="screener">باز کردن دیده‌بان <b>←</b></button></div></div>
<div class="notice"><span>ⓘ</span><div><b>نسخه تحلیلی فاندبورد</b><small>داده‌های متصل از منابع اصلی دریافت می‌شوند؛ هر شاخص فاقد منبع، خالی نمایش داده می‌شود.</small></div><span class="notice-tag">SOURCE DATA</span></div>
<div class="section-heading"><div><h3>نبض بازار</h3><p>نمای کلی شاخص‌های منتخب</p></div><span class="muted">آخرین وضعیت ثبت‌شده</span></div>
<div class="metric-grid" id="marketMetrics">
  <article class="metric-card"><div class="metric-top"><span>شاخص کل بورس</span><span class="metric-icon violet">▥</span></div><strong>—</strong><small>در انتظار اتصال منبع</small></article>
  <article class="metric-card"><div class="metric-top"><span>طلای ۱۸ عیار</span><span class="metric-icon gold">◇</span></div><strong>—</strong><small>در انتظار اتصال منبع</small></article>
  <article class="metric-card"><div class="metric-top"><span>دلار آزاد</span><span class="metric-icon green">$</span></div><strong>—</strong><small>در انتظار اتصال منبع</small></article>
  <article class="metric-card"><div class="metric-top"><span>بیت‌کوین</span><span class="metric-icon blue">₿</span></div><strong>—</strong><small>در انتظار اتصال منبع</small></article>
</div>
<div class="content-grid">
  <section class="panel chart-panel"><div class="panel-head"><div><h3>روند بازار</h3><p>تاریخچه واقعی شاخص در صورت دسترسی به منبع</p></div><div class="segmented"><button class="selected" data-period="1W">۱ هفته</button><button data-period="1M">۱ ماه</button><button data-period="3M">۳ ماه</button><button data-period="1Y">۱ سال</button></div></div><div id="mainChart" class="chart large-chart"></div><div class="chart-foot"><span>● داده تاریخی منبع متصل</span></div></section>
  <section class="panel allocation-panel"><div class="panel-head"><div><h3>ترکیب بازار</h3><p>نمایش دسته‌بندی دارایی‌ها</p></div><span class="more">•••</span></div><div id="allocationChart" class="chart donut-chart"><div class="data-empty">برای نمایش ترکیب دارایی، منبع تخصصی پرتفوی صندوق‌ها لازم است.</div></div></section>
</div>
<div class="section-heading"><div><h3>بخش‌های تحلیلی</h3><p>برای ورود به جزئیات هر بازار انتخاب کن</p></div></div>
<div class="feature-grid">
 <button class="feature-card" data-view="funds"><span class="feature-icon violet">▦</span><b>مرکز تحلیل صندوق‌ها</b><small>بازدهی، NAV، حباب، جریان پول و رتبه‌بندی</small><span class="feature-arrow">←</span></button>
 <button class="feature-card" data-view="stocks"><span class="feature-icon blue">▥</span><b>رصد بورس</b><small>شاخص‌ها، نمادها، تابلو و معاملات حقیقی‌ـ‌حقوقی</small><span class="feature-arrow">←</span></button>
 <button class="feature-card" data-view="gold"><span class="feature-icon gold">◇</span><b>طلا و ارز</b><small>قیمت‌ها، حباب، بازدهی و مقایسه بازارها</small><span class="feature-arrow">←</span></button>
 <button class="feature-card" data-view="crypto"><span class="feature-icon green">₿</span><b>مرکز رمزارز</b><small>قیمت صرافی‌ها، اختلاف قیمت و نمودار کندل</small><span class="feature-arrow">←</span></button>
 <button class="feature-card" data-view="intelligence"><span class="feature-icon violet">✧</span><b>اتاق هوش بازار</b><small>نقشه حرارتی، رویدادهای کدال و واگرایی جریان پول</small><span class="feature-arrow">←</span></button>
</div>
<div class="content-grid lower-grid">
 <section class="panel"><div class="panel-head"><div><h3>آخرین اطلاعیه‌ها</h3><p>جریان اطلاع‌رسانی ناشران و صندوق‌ها</p></div><button class="text-btn" data-view="codal">همه اطلاعیه‌ها ←</button></div><div id="disclosures" class="empty-state">برای نمایش اطلاعیه‌های کدال، اتصال منبع مربوطه را فعال کنید.</div></section>
 <section class="panel"><div class="panel-head"><div><h3>ابزارهای سریع</h3><p>میان‌برهای تحلیلی</p></div></div><div class="quick-links"><button data-view="screener">⌕ فیلتر صندوق‌ها</button><button data-view="compare">⇄ مقایسه بازدهی</button><button data-view="portfolio">◉ ساخت پرتفوی</button><button data-view="codal">▤ جست‌وجوی کدال</button></div></section>
</div>
<footer>فاندبورد · داشبورد سرمایه‌گذاری <span>اطلاعات صرفاً جهت اطلاع‌رسانی است و توصیه سرمایه‌گذاری محسوب نمی‌شود.</span></footer>
</section>
</main>
</div>
<div class="toast" id="toast" role="status"></div>
<script src="assets/js/app.js?v=1.1.0"></script>
<script src="assets/js/module-tools.js?v=1.2.0"></script>
<script src="assets/js/asset-profile.js?v=1.4.0"></script>
<script src="assets/js/market-workspaces.js?v=1.6.0"></script>
</body></html>
