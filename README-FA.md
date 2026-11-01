# فاندبورد | راه‌اندازی و اتصال منابع

## پیش‌نیاز هاست
- PHP 8.1 یا بالاتر با افزونه cURL
- Apache و پشتیبانی از فایل htaccess
- MySQL 5.7+ (برای قابلیت‌های حساب کاربری و پرتفوی)
- HTTPS فعال

## نصب
1. فایل‌های پروژه را در public_html یا پوشه دامنه استخراج کنید.
2. از config/config.example.php یک کپی با نام config/config.php بسازید.
3. توکن Tindex و آدرس سایت را در تنظیمات قرار دهید.
4. پوشه storage/cache را برای کاربر PHP قابل نوشتن کنید.
5. فایل database/schema.sql را در phpMyAdmin اجرا کنید.
6. دامنه را با HTTPS باز کنید.

## اتصال مستقیم Tindex
آدرس API برابر https://tindex.app است و احراز هویت با هدر Authorization: Bearer TOKEN انجام می‌شود. در فایل config/config.php مقدار sources.tindex_token را وارد کنید. داشبورد از تابلوهای بازار برای دریافت داده‌های منتخب استفاده می‌کند؛ بخش‌های طلا، رمزارز و شاخص‌های بورس نیز از تابلوهای متناظر تغذیه می‌شوند. API تاریخچه روزانه شاخص‌ها را هم برای نمودار ارائه می‌کند.

API در این پروژه از مسیرهای زیر استفاده می‌کند:
- api/?action=overview
- api/?action=board&key=gold
- api/?action=board&key=crypto
- api/?action=board&key=market
- api/?action=movers
- api/?action=history&slug=GOLD-18K&range=3m
- api/?action=category&slug=precious-metals

## اتصال MCPهای بورس، کدال و رمزارز
پروژه‌های tsetmc-mcp، codal-mcp و iran-crypto-mcp سرورهای MCP مبتنی بر stdio هستند و از PHP روی هاست اشتراکی مستقیماً اجرا نمی‌شوند. برای استفاده در وب‌سایت، آن‌ها را روی VPS اجرا کنید و یک HTTP Bridge امن در مقابلشان قرار دهید. آدرس HTTPS آن Bridge را در فایل config.php قرار دهید:

- sources.tsetmc_bridge_url
- sources.codal_bridge_url
- sources.crypto_bridge_url
- sources.funds_bridge_url
- sources.bridge_bearer_token

درخواست فاندبورد به Bridge با هدر Authorization: Bearer انجام می‌شود. پاسخ پیشنهادی Bridge:

    {
      "items": [
        {
          "name": "نمونه دارایی",
          "symbol": "نماد",
          "value": 123456,
          "change": 1.25,
          "unit": "تومان",
          "updated_at": "2026-10-31T10:00:00+03:30"
        }
      ]
    }

برای داده‌های TSETMC توجه کنید قیمت‌های خروجی آن منبع ممکن است ریالی باشند؛ پیش از نمایش تومان، تبدیل واحد را در Bridge به‌صورت صریح انجام دهید. داده‌های کدال را با تاریخ شمسی اصلی نگه دارید و زمان دریافت را جداگانه ثبت کنید.

## وضعیت فعلی پروژه
- رابط کاربری فارسی و واکنش‌گرا و منوی بخش‌ها آماده است.
- API امن سمت PHP برای Tindex، کش فایل و مسیرهای بازار پیاده شده است.
- داشبورد در صورت تنظیم توکن Tindex، قیمت‌های منتخب و تاریخچه شاخص را دریافت می‌کند.
- جدول‌های تابلوهای طلا، رمزارز و بورس از Tindex خوانده می‌شوند.
- اتصال Bridge برای صندوق‌ها، TSETMC، کدال و رمزارز آماده دریافت JSON استاندارد است.
- نمودارهای ترکیب و روند فعلی در حالت دمو با برچسب نمونه نمایش داده می‌شوند؛ داده نمونه نباید به‌عنوان قیمت واقعی تفسیر شود.
- ورود کاربران، مدیریت عملیاتی پرتفوی، فیلترهای پیشرفته صندوق و هشدارها هنوز فاز توسعه بعدی هستند.

## امنیت
- هرگز config/config.php یا توکن API را در GitHub منتشر نکنید.
- Bridge را فقط با HTTPS و توکن Bearer در دسترس قرار دهید.
- مسیرهای config و storage با htaccess محافظت شده‌اند.
- این ابزار اطلاعاتی است و توصیه سرمایه‌گذاری محسوب نمی‌شود.
