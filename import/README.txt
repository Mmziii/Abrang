آبرنگ — پوشهٔ ورود دسته‌جمعی محصولات

این پوشه فقط برای ورود دسته‌جمعی محصولات روی کامپیوتر شماست و هیچ‌وقت به گیت
نمی‌رود (فقط همین README در گیت می‌ماند). محتویات آن به صورت فقط-خواندنی
داخل کانتینر wpcli با نام /import در دسترس است.

== چگونه محصولات جدید اضافه کنم؟ ==

1) قالب اکسل را باز کنید:
   docs/products-template.xlsx  — یا نمونهٔ CSV در docs/products-template.csv
   (هر دو UTF-8 با BOM هستند تا اکسل ویندوز فارسی را درست نشان دهد)

   ستون‌ها:
     sku, name, category_slug (| برای چند دسته), regular_price, sale_price,
     stock_qty, short_description, description, brand, color, size, material,
     pack_size, weight_grams, tags, status (publish/draft)

   سه ردیف نمونه پر شده است — آن‌ها را پاک کنید و محصولات خود را وارد کنید.
   - قیمت‌ها فقط عدد به تومان (ارقام فارسی هم قبول است، با یا بدون کاما)
   - category_slug باید slug دستهٔ موجود باشد (مثل pens, notebooks, watercolor-gouache)
   - برای دیدن slug دسته‌ها: پیشخوان وردپرس → محصولات → دسته‌ها
   - sale_price باید از regular_price کمتر باشد
   - brand/color/... خالی بگذارید اگر ندارید؛ پر کنید اگر دارید (برچسب خودکار ساخته می‌شود)

2) فایل پرشده را ذخیره کنید و در همین پوشه کپی کنید:
   ./import/products.xlsx  یا  ./import/products.csv

3) در PowerShell داخل پوشهٔ پروژه، ابتدا بررسی آزمایشی:
   docker compose run --rm wpcli bash /scripts/import-products.sh /import/products.xlsx --dry-run

   گزارش خطاهای فارسی و شمارهٔ ردیف چاپ می‌شود. اگر خطایی بود، اکسل را
   اصلاح کنید و دوباره --dry-run بزنید.

4) وقتی خطایی نماند، ورود واقعی:
   docker compose run --rm wpcli bash /scripts/import-products.sh /import/products.xlsx

   محصولات با SKU یکسان به‌روزرسانی می‌شوند، نه تکراری. با --skip-invalid
   فقط ردیف‌های سالم وارد می‌شوند (پیش‌فرض: با یک خطا هیچ ردیفی وارد نمی‌شود).

== چگونه تصاویر را اضافه کنم؟ ==

1) تصاویر را با نام SKU آماده کنید:
   ABR-001.jpg        → تصویر اصلی محصول ABR-001
   ABR-001-2.jpg      → دومین تصویر (گالری)
   ABR-001-3.jpg      → سومین تصویر
   ... jpg/jpeg/png/webp همه قبول است، حروف بزرگ/کوچک فرقی نمی‌کند

2) آن‌ها را در یک پوشه یا یک فایل zip در همین پوشه بگذارید:
   ./import/images/            (پوشه با تصاویر)
   یا ./import/images.zip       (فایل فشرده)

3) ورود تصاویر:
   docker compose run --rm wpcli bash /scripts/import-images.sh /import/images
   یا
   docker compose run --rm wpcli bash /scripts/import-images.sh /import/images.zip

   اجرای دوباره تصاویر تکراری نمی‌سازد (تشخیص بر اساس SKU + هش فایل).
   گزارش «محصول بدون تصویر» و «تصویر بدون محصول همتا» چاپ می‌شود.

نکته: پوشهٔ ./import روی هاست فقط روی کامپیوتر شماست و با docker compose down -v
پاک نمی‌شود. برای شروع تمیز، خودتان فایل‌های داخل آن را حذف کنید.
