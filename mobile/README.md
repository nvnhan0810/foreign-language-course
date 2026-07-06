# FLC Mobile (Flutter)

Shell app nhúng **web app FLC** qua WebView + push notification (FCM).

## Cách hoạt động

- App mở `WEBAPP_URL` (mặc định `https://flc.nvnhan0810.com`) trong WebView
- Đăng nhập, tra từ, media, quiz… do **web** xử lý (session cookie)
- FCM token: mobile phát event `flc:fcm-token` (kèm `token`, `platform`); web `user-app.js` gọi `POST /home/push-token` qua session
- FCM tap notification → mở path tương ứng trên web (vd. `/home/quiz`)

## Cấu hình (`.env`)

```env
WEBAPP_URL=https://flc.nvnhan0810.com
```

Android emulator (host machine):

```env
WEBAPP_URL=http://10.0.2.2:8080
```

Copy: `cp .env.example .env`

## Yêu cầu

- Flutter SDK 3.16+
- Backend FLC chạy tại `WEBAPP_URL`

## Chạy app

```bash
cd mobile
cp .env.example .env   # chỉnh WEBAPP_URL nếu cần
flutter pub get
flutter run
```

Hot restart sau khi sửa `.env` — cần **stop & run lại**.

## Cấu trúc chính

```
lib/
  config/app_config.dart      # WEBAPP_URL
  features/webapp/            # WebView shell
  core/fcm/                   # Push → navigate WebView + sync token
```

## Push quiz từ vựng (FCM)

Nhắc **11:00** và **20:00** (giờ VN), tap mở `/home/quiz` trên web. Cấu hình Firebase: [docs/FCM_VOCAB_QUIZ.md](../docs/FCM_VOCAB_QUIZ.md).

## Build release

Đặt `WEBAPP_URL` production trong `.env` trước khi build:

```bash
flutter build apk
flutter build ios
```

Package: `com.nvnhan0810.flc`
