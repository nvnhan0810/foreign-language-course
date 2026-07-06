# FLC Mobile (Flutter)

App native Flutter cho người học — tương ứng web user, gọi **REST API** với **Sanctum Bearer token** (giống browser extension).

## Tính năng

| Tab | Mô tả |
|-----|--------|
| Tra từ | Tra Anh–Anh, lưu từ vựng |
| Từ vựng | Danh sách, xóa, nghe phát âm |
| Nghe | YouTube (native player) / MP3, làm quiz listening |
| Quiz | Ôn từ đã lưu |
| Cá nhân | Thống kê, lịch sử, bật/tắt nhắc push |

Đăng nhập: **Google OAuth** → redirect `flc://oauth-callback` → lưu Bearer token.

## Cấu hình (`.env`)

```env
API_BASE_URL=https://flc.nvnhan0810.com/api
```

Local dev:

```env
# Android emulator → host
API_BASE_URL=http://10.0.2.2:8080/api

# iOS Simulator
API_BASE_URL=http://localhost:8080/api
```

Copy: `cp .env.example .env`

## Yêu cầu

- Flutter SDK 3.16+
- Backend FLC với API routes (`/api/...`)

## Chạy app

```bash
cd mobile
cp .env.example .env
flutter pub get
flutter run
```

Hot restart sau khi sửa `.env` — cần **stop & run lại**.

## Cấu trúc chính

```
lib/
  config/app_config.dart       # API_BASE_URL, OAuth redirect
  core/api/                    # Dio + FlcApi
  core/auth/                   # Google OAuth (flutter_web_auth_2)
  core/fcm/                    # Push token via API + deep link routes
  features/                    # login, lookup, vocab, media, quiz, profile
  router/app_router.dart       # GoRouter
```

## Push quiz từ vựng (FCM)

Nhắc **11:00** và **20:00** (giờ VN), tap mở tab Quiz. Token đăng ký qua `POST /api/me/push-token`. Chi tiết Firebase: [docs/FCM_VOCAB_QUIZ.md](../docs/FCM_VOCAB_QUIZ.md).

## Build release

Đặt `API_BASE_URL` production trong `.env` trước khi build:

```bash
flutter build apk
flutter build ios
```

Package: `com.nvnhan0810.flc`
