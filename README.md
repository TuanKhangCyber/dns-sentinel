# KiemTraDNS — DNS Recon & Security Investigation

Ứng dụng Laravel dùng để tra cứu DNS/RDAP, phân tích cấu hình bảo mật web và thực hiện các lần quét mạng **đã được cấp phép**. Project dùng Blade, JavaScript thuần, Tailwind CSS/Vite, Laravel Queue và mặc định hỗ trợ SQLite.

> Chỉ quét hệ thống bạn sở hữu hoặc được cấp phép rõ ràng. Scanner dùng allowlist fail-closed: khi `SCANNER_ALLOWLIST` rỗng, mọi lần quét Nmap/Nessus đều bị từ chối.

## Yêu cầu hệ thống

- PHP 8.2+ với OpenSSL, PDO SQLite, Mbstring, XML, Ctype, JSON và Fileinfo.
- Composer 2.
- Node.js 20.19+ hoặc Node.js 22 LTS và npm.
- Nmap chỉ cần khi bật Nmap scanner.
- Nessus/Tenable là tùy chọn và cần license/API hợp lệ.

## Cài đặt từ source sạch

```powershell
composer install
Copy-Item .env.example .env
New-Item database/database.sqlite -ItemType File -Force
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
```

Trên macOS/Linux, thay hai lệnh PowerShell bằng:

```bash
cp .env.example .env
touch database/database.sqlite
```

Chạy web và worker ở hai terminal riêng:

```bash
php artisan serve
php artisan queue:work --queue=scanner --tries=1 --timeout=3750
```

Mở `http://127.0.0.1:8000` và đăng ký tài khoản. Có thể dùng `composer run dev` trong môi trường phát triển.

## Membership, feature flags và credits

Seeder tạo hai plan mặc định:

- **Free:** DNS/RDAP/IP cơ bản, recon history giới hạn và credits thấp hơn.
- **Plus:** gồm Free và các phân tích nâng cao, scanner, compare, export và hạn mức credits cao hơn.

Danh sách capability thực sự được application hỗ trợ nằm tại `config/features.php`. Database lưu trạng thái bật/tắt, show/hide, chi phí và mapping theo plan. Feature do admin tạo nhưng không có trong registry chỉ là metadata: không thể được bật để thực thi mã động.

Feature access luôn được kiểm tra ở backend theo thứ tự: registry, global flag, trạng thái user, plan/membership, plan mapping, dependency kỹ thuật và credits. UI khóa/mờ chỉ là phản hồi trực quan, không thay thế authorization.

Chính sách credits:

- Validation, allowlist hoặc authorization thất bại không bị trừ credits.
- DNS/recon đồng bộ tính phí sau validation và trước lookup; lỗi dịch vụ ngoài sau khi đã bắt đầu lookup vẫn được tính là một lần sử dụng.
- Queue scan tính phí một lần sau khi tạo scan hợp lệ và trước dispatch.
- Double submit được chặn bằng idempotency/reference duy nhất.
- Dispatch thất bại, job thất bại hoặc hủy scan đang queued sẽ refund tự động, mỗi giao dịch chỉ refund một lần.
- Mọi thay đổi số dư có ledger; admin adjustment bắt buộc có lý do và actor audit.

Reset hạn mức tháng bằng scheduler hoặc thủ công:

```bash
php artisan credits:reset-monthly
php artisan schedule:run
# hoặc chạy scheduler liên tục trong môi trường phát triển
php artisan schedule:work
```

Production nhiều web/worker phải dùng database/Redis cache chung để rate limit, replay protection và idempotency nhất quán; không dùng array cache. Tác vụ reset dùng `onOneServer()`, vì vậy mọi scheduler node phải dùng cùng một cache backend có hỗ trợ distributed lock.

## Tạo và sử dụng admin

Không có admin/password cố định trong seeder. Đăng ký user bình thường rồi nâng quyền bằng:

```bash
php artisan user:make-admin user@example.com
```

Trang `/admin` cho phép quản lý user, membership, credit adjustment, plan-feature mapping, feature flags, About, FAQ và các system setting an toàn. Admin không thể xem password/API key, không thể bật feature chưa đăng ký, và không thể hạ quyền admin active cuối cùng. Các thay đổi quan trọng ghi vào audit log đã lọc trường nhạy cảm.

Project chưa có payment gateway. Nút yêu cầu nâng cấp chỉ thông báo quy trình nâng cấp, không tạo checkout hoặc giao dịch giả.

## Google reCAPTCHA v3

reCAPTCHA được xác minh ở backend. Registration có thể bật riêng; login dùng adaptive challenge sau số lần thất bại cấu hình. Secret chỉ nằm trong `.env`, không lưu hoặc hiển thị trong admin/database.

```dotenv
RECAPTCHA_ENABLED=false
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
RECAPTCHA_MIN_SCORE=0.5
RECAPTCHA_EXPECTED_HOSTNAME=
RECAPTCHA_REGISTER_ENABLED=true
RECAPTCHA_LOGIN_ENABLED=true
RECAPTCHA_LOGIN_FAILURE_THRESHOLD=2
```

Chỉ bật reCAPTCHA trong admin sau khi cả site key và secret đã được cấu hình. Sau khi đổi `.env`, chạy `php artisan optimize:clear`. Automated tests fake Google HTTP và không cần key thật.

## DNS Recon

Endpoint RDAP, IP geolocation và public IP nằm trong `.env.example`. HTTP request có connect/total timeout, giới hạn response, không tự theo redirect và có guard SSRF/DNS resolution. Traceroute mặc định tắt:

```dotenv
DNS_TRACEROUTE_ENABLED=false
DNS_TRACEROUTE_BINARY=
```

Không ghép target người dùng vào shell command. Tab Nmap cũ trong DNS Recon mặc định tắt (`NMAP_ENABLED=false`) và vẫn tuân thủ `SCANNER_ALLOWLIST`. Module `/scanner` là luồng khuyến nghị vì chạy qua queue.

## Bật Nmap scanner

1. Cài Nmap trên scanner worker và kiểm tra `nmap --version`.
2. Chạy worker bằng tài khoản hệ thống ít quyền, không chạy Laravel web process bằng root/Administrator.
3. Khai báo executable và allowlist target đã được cấp phép:

```dotenv
SCANNER_NMAP_BINARY="C:\Program Files (x86)\Nmap\nmap.exe"
SCANNER_ALLOWLIST="AUTHORIZED_HOSTNAME,AUTHORIZED_PUBLIC_IP,AUTHORIZED_PUBLIC_CIDR"
SCANNER_MAX_HOSTS=256
SCANNER_MIN_IPV4_PREFIX=24
SCANNER_MIN_IPV6_PREFIX=120
SCANNER_ALLOW_PRIVILEGED_PROFILES=false
```

Trên Linux thường dùng `SCANNER_NMAP_BINARY=/usr/bin/nmap`. Allowlist hỗ trợ IP, CIDR, hostname và wildcard dạng `*.example.test`. CIDR còn bị giới hạn theo prefix và số host.

Sau khi sửa `.env`:

```bash
php artisan optimize:clear
php artisan queue:restart
```

## Bật Nessus/Tenable tùy chọn

DNS Recon và Nmap vẫn hoạt động khi Nessus chưa cấu hình:

```dotenv
SCANNER_VULNERABILITY_ENABLED=true
NESSUS_ENABLED=true
NESSUS_URL=https://127.0.0.1:8834
NESSUS_ACCESS_KEY=
NESSUS_SECRET_KEY=
NESSUS_TEMPLATE_UUID=
NESSUS_TIMEOUT_SECONDS=30
NESSUS_VERIFY_TLS=true
```

Không commit key vào Git. `NESSUS_VERIFY_TLS=false` chỉ phù hợp với lab được kiểm soát. Target luôn phải thuộc scanner allowlist.

## About, FAQ và localization

About và FAQ public chỉ hiển thị nội dung đã publish. Nội dung được lưu dạng text đa ngôn ngữ (`vi`/`en`) và Blade escape khi render; admin không được nhập PHP/JavaScript thực thi. Giao diện dùng `lang/vi` và `lang/en` theo locale hiện tại.

## Kiểm thử và kiểm tra chất lượng

Test dùng SQLite memory, array session/cache và queue sync; HTTP, DNS, Nmap, Nessus và reCAPTCHA đều được fake/mock, không quét Internet thật.

```bash
composer validate --strict
composer audit --locked
php artisan optimize:clear
php artisan route:list
php artisan test
vendor/bin/pint --test
npm run build
git diff --check
```

Trước khi dùng migration fresh, tạo môi trường testing tách biệt. File `.env.testing` và mọi file SQLite đều được Git ignore:

```powershell
Copy-Item .env.testing.example .env.testing
New-Item database/testing.sqlite -ItemType File -Force
php artisan key:generate --env=testing
```

Sau khi xác nhận `.env.testing` trỏ tới `database/testing.sqlite`, mới chạy:

```bash
php artisan migrate:fresh --env=testing --force --seed
```

Không chạy `migrate:fresh` nếu `.env.testing` chưa tồn tại, và không bao giờ chạy trên database development hoặc production.

PHPUnit còn có guard tại `tests/TestCase.php`: test dừng trước `RefreshDatabase` nếu môi trường không phải `testing`, driver không phải SQLite, hoặc đường dẫn trỏ tới `database/database.sqlite`/nằm ngoài vùng testing được phép. `phpunit.xml` ép `DB_DATABASE=:memory:`, cache/session array và queue sync bằng `force=true`. CI dùng riêng `database/testing.sqlite` cho bước kiểm tra migration.

## Cấu trúc chính

- `app/Http/Controllers`: auth, membership, public content, DNS/recon, scanner, report và admin.
- `app/Services`: feature access, membership, credits, audit, reCAPTCHA, recon và scanner.
- `app/Http/Middleware`: active user, admin và feature enforcement.
- `app/Jobs`: Nmap/vulnerability scan với timeout, retry policy, failure state và refund.
- `app/Policies/ScanPolicy.php`: ownership của scan.
- `config/features.php`: registry capability có implementation.
- `database/seeders/PlatformSeeder.php`: seed idempotent plan, feature mapping, setting, About và FAQ.
- `resources/views`, `resources/js`, `resources/css`: Blade dashboard và frontend.
- `lang/vi`, `lang/en`: bản dịch.

## Lỗi thường gặp

- **Thiếu plan/feature sau setup:** chạy `php artisan migrate --seed`.
- **Scanner báo allowlist rỗng:** cấu hình `SCANNER_ALLOWLIST`, clear config và restart worker.
- **Nmap không khởi động:** kiểm tra `SCANNER_NMAP_BINARY`, quyền worker và `nmap --version`.
- **Scan nằm ở queued:** chạy worker `--queue=scanner`, kiểm tra `jobs` và `failed_jobs`.
- **Vite manifest/asset không tồn tại:** chạy `npm ci` rồi `npm run build`.
- **Nessus unavailable:** giữ `NESSUS_ENABLED=false` nếu chưa có API hợp lệ.
- **reCAPTCHA luôn từ chối:** kiểm tra site/secret key, expected hostname, action và đồng hồ máy chủ; không log token.
- **Đổi `.env` chưa có hiệu lực:** `php artisan optimize:clear` và restart server/worker.

## Production notes

Dùng `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, queue/cache dùng chung và tách scanner worker khỏi web process. `DB_QUEUE_RETRY_AFTER` phải lớn hơn timeout worker dài nhất (mẫu là 3900 giây). Bảo vệ `.env`, backup database và theo dõi log/failed jobs mà không ghi credential hoặc reCAPTCHA token.
