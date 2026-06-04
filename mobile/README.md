# FLC Mobile (Flutter)

Ứng dụng Flutter dùng chung API Laravel với Chrome extension.

## Tính năng (MVP)

| Tính năng | Mô tả |
|-----------|--------|
| Đăng nhập Google | OAuth qua backend (`flc://oauth-callback`) |
| Tra từ | `/api/dictionary/{word}` — Anh–Anh như extension |
| Từ vựng | Xem / lưu / xóa từ đã lưu |
| Media | Xem/nghe YouTube + MP3 (upload & phân tích trên admin) |
| Listening quiz | Quiz / test / exam (tạo từ admin) |
| Quiz từ vựng | `/api/quiz/next` — cần ≥ 4 từ đã lưu |

## Yêu cầu

- Flutter SDK 3.16+ ([cài đặt](https://docs.flutter.dev/get-started/install))
- Backend FLC chạy và có Google OAuth + allowlist email

## Khởi tạo project (lần đầu)

Thư mục `lib/` đã có sẵn. Tạo platform Android/iOS:

```bash
cd mobile
cp .env.example .env   # chỉnh API_BASE_URL
flutter create . --org com.nvnhan0810 --project-name flc_mobile
flutter pub get
```

Package native: `com.nvnhan0810.flc` (Android `applicationId`, iOS bundle identifier).

### Cấu hình API (`.env`)

File `mobile/.env` (không commit — xem `.env.example`):

```env
API_BASE_URL=http://localhost:8080/api
```

Production:

```env
API_BASE_URL=https://flc.nvnhan0810.com/api
```

Android emulator (máy ảo gọi host máy):

```env
API_BASE_URL=http://10.0.2.2:8080/api
```

Ưu tiên: `.env` → `--dart-define=API_BASE_URL=...` → mặc định `http://localhost:8080/api`.

### Deep link OAuth (`flc://oauth-callback`)

**Android** — trong `android/app/src/main/AndroidManifest.xml`, thêm vào `<activity android:name=".MainActivity">`:

```xml
<intent-filter>
    <action android:name="android.intent.action.VIEW" />
    <category android:name="android.intent.category.DEFAULT" />
    <category android:name="android.intent.category.BROWSABLE" />
    <data android:scheme="flc" android:host="oauth-callback" />
</intent-filter>
```

**iOS** — trong `ios/Runner/Info.plist`:

```xml
<key>CFBundleURLTypes</key>
<array>
  <dict>
    <key>CFBundleURLSchemes</key>
    <array>
      <string>flc</string>
    </array>
  </dict>
</array>
```

Backend đã cho phép redirect `flc://oauth-callback` (xem `GoogleAuthController`).

## Chạy app

Sau khi có `.env`:

```bash
flutter run
```

Hot restart sau khi sửa `.env` — cần **stop & run lại** (asset `.env` load lúc khởi động).

## Cấu trúc

```
lib/
  config/          # API_BASE_URL, OAuth redirect
  core/api/        # Dio client + FlcApi
  core/auth/       # Google OAuth (flutter_web_auth_2)
  features/        # Màn hình theo tính năng
  models/          # DTO JSON
  router/          # go_router
```

## Lưu ý

- **Tra từ** = định nghĩa tiếng Anh (dictionary), không phải dịch Việt — giống extension.
- **YouTube**: cần kết nối mạng; một số video chặn embed.
- **Media / quiz nghe**: thêm và phân tích trên admin; app chỉ xem danh sách, nghe và làm bài.

## App icon

Icon launcher lấy từ extension (`extension/public/icons/icon128.png`), bản 1024px tại `assets/icons/app_icon.png`. Đổi icon extension rồi chạy lại:

```bash
sips -z 1024 1024 ../extension/public/icons/icon128.png --out assets/icons/app_icon.png
dart run flutter_launcher_icons
```

## Push quiz từ vựng (FCM)

Nhắc **11:00** và **20:00** (giờ VN), tap mở thẳng tab Quiz. Cấu hình Firebase + cron backend: [docs/FCM_VOCAB_QUIZ.md](../docs/FCM_VOCAB_QUIZ.md).

## Build release

Đặt `API_BASE_URL` trong `.env` trước khi build (file được bundle vào app):

```bash
flutter build apk
flutter build ipa   # iOS — nên dùng thay vì chỉ Archive thủ công
```

### iOS dSYM (`objective_c.framework`)

Khi **Validate / Distribute** archive, Xcode có thể báo thiếu dSYM cho `objective_c.framework`. Đây là dependency native của Flutter (`package:objective_c`), [lỗi đã biết](https://github.com/dart-lang/native/issues/3290): framework đôi khi build không có debug symbols.

**Thường chỉ là cảnh báo** — upload App Store Connect vẫn có thể thành công.

**Nên thử (theo thứ tự):**

1. Build IPA từ CLI (đã có Run Script `Generate objective_c dSYM` trong Xcode):
   ```bash
   fvm flutter clean
   fvm flutter pub get
   cd ios && pod install && cd ..
   fvm flutter build ipa --release
   ```
2. Cập nhật Flutter stable (`fvm flutter upgrade`) — bản mới có thể đã sửa engine/dSYM.
3. Archive lại trong Xcode sau bước trên.

**Nếu vẫn báo sau khi Archive** (thay `YourApp.xcarchive` bằng archive thật):

```bash
chmod +x ios/scripts/generate_objective_c_dsym.sh
ARCHIVE=~/Library/Developer/Xcode/Archives/.../YourApp.xcarchive
./ios/scripts/generate_objective_c_dsym.sh \
  "$ARCHIVE/Products/Applications/Runner.app/Frameworks/objective_c.framework/objective_c" \
  "$ARCHIVE/dSYMs/objective_c.framework.dSYM"
```

Validate lại archive trong Xcode.

Symbolication crash **trong** `objective_c` có thể kém cho đến khi package upstream build kèm `-g`.
