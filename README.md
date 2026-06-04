# FLC — Foreign Language Companion

Chrome Extension + Flutter mobile + Laravel API để học tiếng Anh: tra từ Anh–Anh, lưu từ vựng, quản lý link nghe (audio/YouTube), quiz và nhắc ôn tập.

Kế hoạch chi tiết: [docs/PLAN.md](docs/PLAN.md)

## Cấu trúc

| Thư mục | Mô tả |
|---------|--------|
| `docs/` | Tài liệu kế hoạch |
| `backend/` | Laravel 11 + Sail + PostgreSQL |
| `extension/` | Chrome Extension MV3 (TypeScript + Vite) |
| `mobile/` | Flutter app (iOS / Android) |

## Yêu cầu

- Docker Desktop (cho Laravel Sail)
- Node.js 18+ (build extension)
- Flutter 3.16+ (build mobile — xem [mobile/README.md](mobile/README.md))

## Backend (Laravel Sail)

```bash
cd backend
cp .env.example .env   # nếu chưa có .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

API mặc định: **http://localhost:8080/api**

> Nếu port `5432` hoặc `5173` bị chiếm, chỉnh `FORWARD_DB_PORT` / `VITE_PORT` / `APP_PORT` trong `backend/.env`.

### Trang Admin

**http://localhost:8080/admin** — quản lý allowlist, cài đặt, users, từ vựng, media.

Xem [docs/ADMIN.md](docs/ADMIN.md)

### Đăng nhập Google + allowlist email

Xem hướng dẫn chi tiết: [docs/GOOGLE_AUTH.md](docs/GOOGLE_AUTH.md)

```env
# backend/.env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
FLC_ALLOWED_EMAILS=you@gmail.com,*@yourcompany.com
```

### Endpoints chính

- `GET /api/auth/google/redirect`, `GET /api/auth/google/callback`
- `GET /api/dictionary/{word}`
- `CRUD /api/vocabularies`
- `CRUD /api/media-items`, `GET /api/media-items/due`
- `GET /api/quiz/next`, `POST /api/quiz/attempts`
- `GET /api/sync`

## Chrome Extension

```bash
cd extension
npm install
npm run build
```

Load unpacked trong Chrome:

1. Mở `chrome://extensions`
2. Bật **Developer mode**
3. **Load unpacked** → chọn thư mục `extension/dist`

### Cấu hình lần đầu

1. Mở extension → **Đăng nhập bằng Google** (email phải nằm trong allowlist)
2. Tab **Tra từ**: nhập từ hoặc bôi đen trên trang web → chuột phải **Tra từ với FLC**
3. Tab **Media**: thêm link YouTube/audio, chọn tần suất (ngày/tuần/tháng)
4. **Cài đặt** (options): API URL, số quiz/ngày, chu kỳ nhắc nghe, bật notification

### Notification

- **Quiz**: cần ≥ 4 từ đã lưu; ưu tiên từ ít được hỏi
- **Nghe lại**: nhắc các media đến hạn `next_listen_at`

Cho phép notification cho extension trong Chrome.

## Flutter Mobile

```bash
cd mobile
cp .env.example .env
flutter create . --org com.nvnhan0810 --project-name flc_mobile
flutter pub get
flutter run
```

Chi tiết OAuth deep link, YouTube/MP3, listening quiz: [mobile/README.md](mobile/README.md)

## Phát triển

```bash
# Backend logs
cd backend && ./vendor/bin/sail logs -f

# Extension watch build
cd extension && npm run dev
```

## Ghi chú

- Dictionary: [Free Dictionary API](https://dictionaryapi.dev/) (cache 7 ngày trên PostgreSQL)
- Production: cập nhật `host_permissions` trong `extension/manifest.json` và API URL trong Options
