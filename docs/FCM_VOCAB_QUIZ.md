# FCM — Nhắc quiz từ vựng (mobile)

## Lịch (Asia/Ho_Chi_Minh)

| Giờ | Điều kiện gửi |
|-----|----------------|
| **11:00** | Trong ngày (theo TZ VN) user **chưa** có lần làm quiz nào |
| **20:00** | Trong ngày user có **≤ 1** lần làm quiz |

Tap notification → app mở **`/home/quiz?autostart=1`** (tự load câu hỏi).

## Backend

1. Firebase Console → tạo project → thêm app Android (`com.nvnhan0810.flc`) & iOS.
2. Tải **service account JSON** (Project settings → Service accounts → Generate new private key).
3. Lưu file, ví dụ: `backend/storage/app/firebase-service-account.json` (không commit).
4. `.env`:

```env
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_CREDENTIALS_PATH=/absolute/path/to/firebase-service-account.json
```

5. **Scheduler trên VPS production** — xem mục [Scheduler trên VPS](#scheduler-trên-vps-production) bên dưới.

6. Admin → **Cài đặt** → bật “Nhắc quiz từ vựng (FCM)”.

7. Test thủ công:

```bash
php artisan flc:vocab-quiz-reminders midday
php artisan flc:vocab-quiz-reminders evening
```

## Mobile

1. Copy `mobile/.env.example` → `.env`, điền `FIREBASE_*` từ Firebase app settings.
2. Android: đặt `google-services.json` vào `mobile/android/app/`.
3. iOS: `GoogleService-Info.plist` trong `mobile/ios/Runner/`, đã gắn target **Runner** trong Xcode.  
   - **Chỉ bật** capability **Push Notifications** (và Background Modes → **Remote notifications** nếu Xcode thêm vào Signing).  
   - **Không** bật Background Fetch / Background Processing — FCM không cần; nếu bật `processing` trong `UIBackgroundModes` thì App Store yêu cầu `BGTaskSchedulerPermittedIdentifiers`.  
   - `Info.plist` chỉ cần `UIBackgroundModes` → `remote-notification`.  
   - App dùng plist / `google-services.json` trên iOS & Android (không gọi `FIREBASE_*` từ `.env` trên mobile).  
   - Android 13+: app xin `POST_NOTIFICATIONS` qua `flutter_local_notifications` + FCM `requestPermission`.  
   - Foreground: hiển thị local notification (giống `order_mobile_app`).
4. `fvm flutter pub get` → build/run.
5. Profile → bật **Nhắc quiz từ vựng** (đăng ký FCM token).

## Scheduler trên VPS production

Laravel **không** tự chạy lịch. Trên VPS chỉ cần **một** dòng cron gọi `schedule:run` **mỗi phút**; Laravel sẽ kích hoạt job **11:00** và **20:00** theo `Asia/Ho_Chi_Minh` (đã khai báo trong `backend/bootstrap/app.php`).

### 1. Chuẩn bị `.env` production

```env
APP_ENV=production
APP_DEBUG=false

FIREBASE_PROJECT_ID=...
FIREBASE_CREDENTIALS_PATH=/opt/apps/.../backend/storage/app/firebase-service-account.json
```

Đường dẫn `FIREBASE_CREDENTIALS_PATH` phải **tuyệt đối**, file readable bởi user chạy PHP/cron (thường `www-data`).

### 2. Tìm PHP và thư mục backend

SSH vào VPS:

```bash
which php
# ví dụ: /usr/bin/php

cd /opt/apps/flc.nvnhan0810.com/backend   # đổi đúng path deploy của bạn
/usr/bin/php artisan schedule:list
```

Bạn phải thấy hai dòng tương tự:

- `flc:vocab-quiz-reminders midday` — 11:00 Asia/Ho_Chi_Minh
- `flc:vocab-quiz-reminders evening` — 20:00 Asia/Ho_Chi_Minh

### 3. Thêm cron (user chạy web app)

Dùng **cùng user** với PHP-FPM/Nginx (thường `www-data` hoặc user deploy):

```bash
sudo crontab -u www-data -e
```

Thêm **một** dòng (sửa path cho đúng):

```cron
* * * * * cd /opt/apps/flc.nvnhan0810.com/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Hoặc ghi log để debug:

```cron
* * * * * cd /opt/apps/flc.nvnhan0810.com/backend && /usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

Lưu crontab. **Không** cần hai dòng riêng cho 11:00 và 20:00 — Laravel schedule lo việc đó.

### 4. Kiểm tra

```bash
cd /opt/apps/flc.nvnhan0810.com/backend
/usr/bin/php artisan flc:vocab-quiz-reminders midday
/usr/bin/php artisan flc:vocab-quiz-reminders evening
```

Đợi 1–2 phút sau khi lưu cron, xem log (nếu bật):

```bash
tail -f storage/logs/scheduler.log
# hoặc
tail -f storage/logs/laravel.log
```

### 5. Deploy bằng Docker / Sail trên VPS

Nếu app chạy trong container:

```cron
* * * * * cd /path/to/project/backend && docker compose exec -T laravel.test php artisan schedule:run >> /dev/null 2>&1
```

(Tên service `laravel.test` lấy từ `backend/compose.yaml` — đổi cho khớp môi trường bạn.)

Hoặc cron **trong** container / dùng [ofelia](https://github.com/mcuadros/ofelia) — nguyên tắc vẫn là gọi `schedule:run` mỗi phút.

### 6. Lưu ý production

| Việc | Ghi chú |
|------|---------|
| Timezone server | Cron `schedule:run` mỗi phút theo giờ **server**; giờ 11:00/20:00 **VN** do Laravel `->timezone('Asia/Ho_Chi_Minh')` — không cần đổi TZ OS. |
| `php artisan config:cache` | Sau khi sửa `.env`, chạy lại trên VPS. |
| Queue | Job quiz là **command đồng bộ**, không cần `queue:work` cho FCM reminder. |
| Admin | Tắt “Nhắc quiz” trong admin → không gửi dù cron vẫn chạy. |

## API

- `POST /api/me/push-token` — `{ token, platform: ios|android }`
- `DELETE /api/me/push-token` — `{ token }`
- `GET|PUT /api/me/notification-settings`
