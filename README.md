# DNS Sentinel

DNS Sentinel là công cụ tra cứu DNS, điều tra bảo mật tên miền và quét mạng có kiểm soát. Tên hiển thị của từng môi trường được cấu hình bằng `APP_NAME`. Ứng dụng sử dụng PHP 8.2+, Blade, JavaScript thuần, Tailwind CSS 4 và Vite.

> Chỉ quét hệ thống bạn sở hữu hoặc đã được cấp phép rõ ràng. Network Scanner dùng allowlist theo cơ chế fail-closed: nếu `SCANNER_ALLOWLIST` để trống, mọi yêu cầu quét đều bị từ chối.

## Chức năng

- Đăng ký, đăng nhập, đăng xuất, Google reCAPTCHA và lịch sử đăng nhập.
- Tra cứu DNS records, RDAP/WHOIS, IP geolocation và public IP của máy chủ.
- Phân tích SSL/TLS, Security Headers, SPF, DKIM, DMARC và công nghệ website.
- Tìm subdomain qua Certificate Transparency.
- Network Scanner chạy Nmap bằng queue, có kiểm tra ownership, allowlist và giới hạn CIDR.
- Tích hợp Nessus/Tenable tùy chọn; không cấu hình Nessus vẫn dùng được DNS Recon và Nmap.
- Lịch sử recon/scan, so sánh scan và xuất JSON, CSV, PDF.
- Gói Free/Plus, feature flags, credit wallet và credit ledger.
- Trang quản trị user, plan, feature, credits, settings, About, FAQ và audit log.
- Giao diện responsive, dark/light mode, tiếng Việt và tiếng Anh.

## Yêu cầu hệ thống

- PHP 8.2 trở lên với OpenSSL, cURL, PDO SQLite, Mbstring, XML, Ctype, JSON và Fileinfo.
- Composer 2.
- Node.js 20.19+ hoặc Node.js 22.12+ và npm.
- Nmap chỉ cần khi sử dụng Network Scanner hoặc tab Nmap tùy chọn trong DNS Recon.
- Nessus/Tenable là tùy chọn và cần license/API hợp lệ.

## Cài đặt

### Windows PowerShell

```powershell
composer install
Copy-Item .env.example .env
if (-not (Test-Path database/database.sqlite)) {
    New-Item database/database.sqlite -ItemType File
}
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
```

### macOS/Linux

```bash
composer install
cp .env.example .env
test -f database/database.sqlite || touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
```

Khởi động web và queue worker ở hai terminal:

```bash
php artisan serve
php artisan queue:work --queue=scanner --tries=1 --timeout=3750
```

Mở `http://127.0.0.1:8000`. Trong môi trường phát triển có thể dùng `composer run dev` để chạy server, queue, log và Vite cùng lúc.

## Database và dữ liệu tài khoản

Mặc định ứng dụng dùng SQLite tại `database/database.sqlite`. File này lưu user, session, lịch sử, credits và kết quả scan giữa các lần tắt/mở project.

- Không xóa hoặc tạo lại `database/database.sqlite` sau mỗi lần chạy.
- Không dùng `DB_DATABASE=:memory:` cho môi trường demo; giá trị này chỉ dành cho automated test.
- Sau khi đổi cấu hình database trong `.env`, chạy:

```bash
php artisan optimize:clear
php artisan migrate --seed
```

Kiểm tra migration:

```bash
php artisan migrate:status
```

## Tạo tài khoản Admin

Project không tạo sẵn admin hoặc mật khẩu mặc định. Hãy đăng ký một tài khoản, sau đó chạy:

```bash
php artisan user:make-admin user@example.com
```

Đăng nhập lại và mở `/admin`. Toàn bộ route quản trị yêu cầu đồng thời `auth`, tài khoản đang hoạt động và quyền `admin`.

Quyền Admin không tự cấp gói Plus. Để demo scanner nâng cao, hãy gán Plus và cấp đủ credits cho tài khoản tại `/admin/users`.

Admin có thể:

- quản lý user, role, trạng thái và membership;
- cấp/trừ credits với lý do bắt buộc;
- cấu hình plan và feature flags;
- quản lý About, FAQ và system settings an toàn;
- xem credit ledger và audit log.

Secret, API key và password hash không được hiển thị trong trang quản trị.

## Free, Plus và Credits

Seeder tạo hai gói mặc định:

- **Free:** DNS/RDAP/IP cơ bản, mặc định 25 credits/tháng và lưu lịch sử 7 ngày.
- **Plus:** phân tích nâng cao, scanner, compare, export, mặc định 500 credits/tháng và lưu lịch sử 90 ngày.

User mới được cấp mặc định 10 credits. Admin có thể thay đổi hạn mức, chi phí feature và mapping plan trong trang quản trị.

Feature thực sự được ứng dụng hỗ trợ nằm trong `config/features.php`. Database chỉ thay đổi trạng thái, chi phí và mapping theo plan; metadata có code chưa đăng ký không thể trở thành mã thực thi.

Credits được trừ ở backend bằng transaction và idempotency:

- validation, authorization hoặc allowlist thất bại không bị tính phí;
- double-submit không bị trừ hai lần;
- lỗi dispatch, job thất bại hoặc hủy scan đang chờ được refund theo policy;
- mọi adjustment của admin đều có ledger và audit.

Reset hạn mức thủ công khi cần:

```bash
php artisan credits:reset-monthly
```

Hoặc chạy scheduler liên tục để tự reset theo lịch:

```bash
php artisan schedule:work
```

Production có nhiều web/worker phải dùng chung database hoặc Redis cache để rate limit, idempotency và scheduler lock hoạt động nhất quán.

Project chưa tích hợp payment gateway. Nút nâng cấp chỉ gửi yêu cầu/thông báo, không tạo checkout hoặc giao dịch thanh toán.

## Google reCAPTCHA

reCAPTCHA mặc định tắt để project local có thể setup mà không cần key. Site key và secret key chỉ được đọc từ `.env`, không lưu trong database.

```dotenv
RECAPTCHA_ENABLED=false
RECAPTCHA_TYPE=checkbox
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
RECAPTCHA_EXPECTED_HOSTNAME=
RECAPTCHA_MIN_SCORE=0.5
RECAPTCHA_LOGIN_ENABLED=true
RECAPTCHA_LOGIN_ALWAYS_VISIBLE=true
RECAPTCHA_REGISTER_ENABLED=true
```

- `checkbox`: dùng key Google reCAPTCHA v2 “I'm not a robot”.
- `score`: dùng key reCAPTCHA v3; `RECAPTCHA_MIN_SCORE` mới có hiệu lực.
- Key test của Google chỉ nên dùng local và cảnh báo test key trên widget là bình thường.
- Production phải dùng đúng cặp key, đăng ký đúng hostname và giữ verification ở chế độ fail-closed.
- System Settings có thể ghi đè chính sách bật/tắt, loại checkbox/score và ngưỡng điểm; site key và secret key vẫn chỉ lấy từ `.env`.

Sau khi sửa `.env`:

```bash
php artisan optimize:clear
```

## DNS Recon

Các URL RDAP, geolocation, public IP và timeout có sẵn trong `.env.example`. Request bên ngoài có timeout, giới hạn response và kiểm tra SSRF/DNS resolution.

Traceroute và Nmap trong luồng DNS Recon mặc định tắt:

```dotenv
DNS_TRACEROUTE_ENABLED=false
DNS_TRACEROUTE_BINARY=
NMAP_ENABLED=false
```

Module `/scanner` là luồng quét mạng được khuyến nghị vì tác vụ dài chạy qua queue.

## Bật Nmap Scanner

1. Cài Nmap trên máy chạy scanner worker.
2. Chạy `nmap --version` để xác nhận executable.
3. Cấu hình binary và allowlist target được cấp phép:

```dotenv
SCANNER_NMAP_BINARY="C:\Program Files (x86)\Nmap\nmap.exe"
SCANNER_ALLOWLIST="AUTHORIZED_HOSTNAME,AUTHORIZED_PUBLIC_IP,AUTHORIZED_PUBLIC_CIDR"
SCANNER_MAX_HOSTS=256
SCANNER_MIN_IPV4_PREFIX=24
SCANNER_MIN_IPV6_PREFIX=120
SCANNER_ALLOW_PRIVILEGED_PROFILES=false
```

Linux thường dùng `SCANNER_NMAP_BINARY=/usr/bin/nmap`. Hãy thay các giá trị `AUTHORIZED_*` bằng target thật đã được cấp phép. Allowlist hỗ trợ IP, CIDR, hostname và wildcard như `*.example.test`.

OS Detection và UDP profile yêu cầu `SCANNER_ALLOW_PRIVILEGED_PROFILES=true`; chỉ bật trên scanner worker được quản trị riêng.

Không chạy web process bằng root/Administrator. Sau khi đổi cấu hình:

```bash
php artisan optimize:clear
php artisan queue:restart
```

## Bật Nessus/Tenable

```dotenv
SCANNER_VULNERABILITY_ENABLED=true
NESSUS_ENABLED=true
NESSUS_URL=https://nessus.example.test:8834
NESSUS_ACCESS_KEY=
NESSUS_SECRET_KEY=
NESSUS_TEMPLATE_UUID=
NESSUS_TIMEOUT_SECONDS=30
NESSUS_VERIFY_TLS=true
```

Không commit access key hoặc secret key. Chỉ đặt `NESSUS_VERIFY_TLS=false` trong lab được kiểm soát. Target Nessus vẫn phải thuộc `SCANNER_ALLOWLIST`.
Nessus URL phải dùng HTTPS, port `443` hoặc `8834`, không chứa credential, query hay fragment.

## Kiểm thử

Automated test dùng SQLite tách biệt, queue sync và mock/fake HTTP, DNS, Nmap, Nessus, reCAPTCHA; test không quét Internet thật.

Tạo môi trường testing một lần:

```powershell
Copy-Item .env.testing.example .env.testing
if (-not (Test-Path database/testing.sqlite)) {
    New-Item database/testing.sqlite -ItemType File
}
php artisan key:generate --env=testing
php artisan migrate:fresh --env=testing --seed --force
```

Trên macOS/Linux:

```bash
cp .env.testing.example .env.testing
test -f database/testing.sqlite || touch database/testing.sqlite
php artisan key:generate --env=testing
php artisan migrate:fresh --env=testing --seed --force
```

Chạy kiểm tra theo thứ tự dưới đây. Frontend phải build trước các test render Blade để Vite manifest tồn tại:

```bash
composer validate --strict
composer audit --locked
npm ci
npm run build
php artisan test
vendor/bin/pint --test
php artisan route:list
git diff --check
```

Không chạy `migrate:fresh` trên database development hoặc production.

## Cấu trúc chính

```text
app/Http/Controllers     Route handlers cho auth, DNS, recon, scanner và admin
app/Http/Middleware      Active-user, admin và feature enforcement
app/Jobs                 Nmap/Nessus jobs chạy nền
app/Models               User, membership, credits, history và scan data
app/Policies             Ownership policy
app/Services             Nghiệp vụ auth, credits, recon, scanner và audit
config/features.php      Feature registry
config/scanner.php       Profile, allowlist và scanner configuration
database/migrations      Database schema
database/seeders         Dữ liệu Free/Plus, feature, About và FAQ
resources/views          Blade templates
resources/css            Giao diện và responsive styles
resources/js             UI behavior, polling và scanner
lang/vi, lang/en         Bản dịch
tests                    Unit và Feature tests
```

## Lỗi thường gặp

- **User mất sau khi mở lại project:** kiểm tra đang dùng `database/database.sqlite`, không dùng SQLite memory và không chạy `migrate:fresh` trên database demo.
- **Không vào được `/admin`:** đăng nhập đúng user đã chạy `user:make-admin`, rồi đăng nhập lại.
- **Thiếu plan/feature:** chạy `php artisan migrate --seed`.
- **Vite manifest không tồn tại:** chạy `npm ci` và `npm run build`.
- **Scanner từ chối target:** cấu hình `SCANNER_ALLOWLIST`, sau đó clear config và restart worker.
- **Scan đứng ở `queued`:** chạy worker với `--queue=scanner`, kiểm tra bảng `jobs` và `failed_jobs`.
- **reCAPTCHA luôn thất bại:** kiểm tra đúng loại key, hostname, site/secret key và đồng hồ máy chủ; không ghi token hoặc secret vào log.
- **Đổi `.env` chưa có hiệu lực:** chạy `php artisan optimize:clear` và restart server/worker.

## Production

- Dùng `APP_ENV=production`, `APP_DEBUG=false` và HTTPS.
- Dùng cache/queue dùng chung; giữ `DB_QUEUE_RETRY_AFTER` lớn hơn timeout worker dài nhất.
- Tách scanner worker khỏi web process và chạy bằng tài khoản hệ thống ít quyền.
- Bảo vệ `.env`, backup database và theo dõi failed jobs.
- Không ghi credential, API key, reCAPTCHA token hoặc dữ liệu nhạy cảm vào log.
