# Frontend audit và kiểm kê chức năng

Ngày đánh giá: 2026-07-19
Phạm vi: Blade, CSS, JavaScript, nội dung VN/EN, accessibility, responsive, frontend tests và CI. Không thay đổi controller, service, model, job, route, migration hoặc nghiệp vụ backend.

## Kiến trúc frontend

- Laravel Blade, không dùng Livewire/Vue/React.
- Tailwind CSS 4 và CSS component tùy chỉnh, build bằng Vite.
- JavaScript thuần cho theme, navigation, DNS/recon dashboard, Leaflet map, scanner polling, filter, sort và trạng thái loading.
- Giao diện tối mặc định, hỗ trợ dark/light mode, tiếng Việt/Anh và lựa chọn quốc gia.
- Layout responsive cho dashboard, authentication, membership và admin.

## Chức năng hiện có

### Tài khoản và trải nghiệm người dùng

- Đăng ký, đăng nhập, đăng xuất và ghi nhớ đăng nhập.
- Google reCAPTCHA thích ứng cho authentication.
- Dark/light mode, VN/EN và lựa chọn quốc gia có biểu tượng cờ.
- Trang About, FAQ, membership và credit ledger.

### DNS Recon

- DNS records và nhóm bản ghi dạng timeline.
- Public IP của máy chủ ứng dụng.
- IP geolocation và bản đồ OpenStreetMap/Leaflet.
- RDAP/WHOIS chuẩn hóa.
- SSL/TLS certificate analysis.
- Security Headers assessment.
- SPF, DKIM selector discovery và DMARC assessment.
- Certificate Transparency/subdomain discovery.
- Technology/CDN/WAF fingerprint.
- Lịch sử recon và export JSON/PDF.

### Network & Vulnerability Scanner

- Profile Nmap an toàn, allowlist, CIDR limit và xác nhận quyền quét.
- Queue lifecycle, progress polling, cancel, rerun và trạng thái lỗi.
- Host, port, service, OS và finding dashboard.
- Nessus/Tenable adapter tùy chọn.
- Lịch sử scan, comparison và export JSON/CSV/PDF.
- Scanner không được cấu hình sẽ hiển thị trạng thái không khả dụng thay vì làm sập DNS Recon.

### Membership và quản trị

- Gói Free/Plus, feature registry và plan-feature mapping.
- Atomic credit wallet/ledger, usage, refund và admin adjustment.
- Admin dashboard; quản lý user, plan, feature, settings, About và FAQ.
- Audit log, suspended-user guard và ownership policy.

Tổng số route ứng dụng tại thời điểm audit: **51**.

## Kết quả frontend audit

### Điểm tốt

- Không phát hiện `innerHTML`, `outerHTML`, `insertAdjacentHTML`, `eval`, inline event handler hoặc URL `javascript:` trong frontend.
- Nội dung động được đưa vào DOM bằng `textContent`, `createElement` và `replaceChildren`.
- Fetch có timeout/abort, kiểm tra HTTP status, parse lỗi và dọn timer/controller.
- Các bảng lớn có vùng cuộn riêng; layout có breakpoint mobile và chống tràn ngang.
- Tab recon/scanner hỗ trợ bàn phím, ARIA state và reduced-motion.
- Theme được khởi tạo trước asset để giảm nháy giao diện.

### Cải thiện trong vòng audit này

- Đồng bộ thêm nội dung user/admin theo language files VN/EN.
- Dịch nhãn scanner, filter, finding, bảng kết quả và admin forms.
- Bổ sung accessible name cho filter/search/date controls.
- Bổ sung `aria-sort` cho bảng có thể sắp xếp bằng chuột hoặc bàn phím.
- Bổ sung ma trận kiểm thử frontend quy mô lớn và test rendered content cho hai locale.

### Giới hạn còn lại

- Không có phiên trình duyệt tích hợp nên chưa thực hiện visual regression/pixel comparison ở viewport thật.
- Nmap/Nessus cần cấu hình worker, allowlist và credential riêng để chạy demo đầy đủ.
- IP geolocation, crt.sh, RDAP và bản đồ phụ thuộc dịch vụ ngoài; automated tests không gọi Internet thật.
- Một số mã kỹ thuật như DNS, IP, CVE, TCP/UDP, trạng thái nội bộ và tên feature được giữ nguyên có chủ đích.

## Ma trận kiểm thử frontend

Ma trận `FrontendQualityMatrixTest` gồm tối thiểu 500 trường hợp độc lập:

- Kiểm tra parity và chất lượng toàn bộ translation key EN/VI.
- Kiểm tra từng Blade view: không rỗng, output được escape, không inline event, không URL JavaScript, không link placeholder.
- Kiểm tra CSS contract: theme, responsive, overflow, focus, reduced motion và các pattern nguy hiểm.
- Kiểm tra JavaScript contract: DOM-safe rendering, timeout, cleanup, keyboard tabs, storage fallback và các sink bị cấm.

`FrontendLocalizationTest` bổ sung kiểm tra rendered HTML cho guest, user và admin bằng cả tiếng Việt lẫn tiếng Anh.

Kết quả xác minh cuối vòng audit:

- `php artisan test --compact`: **786 passed, 4.048 assertions**.
- Riêng `FrontendQualityMatrixTest`: **605 passed, 3.357 assertions**.
- `vendor/bin/pint --test`: pass.
- `composer validate --strict`: pass.
- `npm run build`: pass; JavaScript 224,20 kB (gzip 70,57 kB), CSS ứng dụng 46,29 kB (gzip 10,28 kB).
- `git diff --check`: pass.

GitHub Actions baseline thất bại do workflow chạy test render Blade trước khi tạo Vite manifest. Đây là lỗi thứ tự CI, không phải lỗi backend; cần chuyển bước frontend build lên trước bước test.

## Đánh giá hiệu quả

| Hạng mục | Đánh giá |
|---|---:|
| Độ đầy đủ chức năng | 9/10 |
| Bảo mật hiển thị và DOM | 9/10 |
| Đa ngôn ngữ và nội dung | 8.5/10 |
| Responsive và accessibility | 8.5/10 |
| Khả năng kiểm thử | 9/10 |
| Sẵn sàng vận hành | 7.5/10 |

Đánh giá tổng thể: **8.6/10 — sẵn sàng demo có điều kiện**. Điều kiện chính là CI phải xanh, cần smoke test bằng trình duyệt thật và scanner chỉ được bật sau khi cấu hình allowlist/worker hợp lệ.
