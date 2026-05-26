# Kế hoạch: Chrome Extension học ngoại ngữ + Backend Laravel

> Tài liệu tham chiếu cho dự án `my-foreign-language-coures`. Cập nhật khi triển khai.

## 1. Tổng quan kiến trúc

```
┌─────────────────────────────────────────────────────────────┐
│  Chrome Extension (Manifest V3)                             │
│  ├─ Content Script   → bôi đen từ, tra EN–EN                 │
│  ├─ Popup          → từ vựng, media, quiz                   │
│  ├─ Service Worker → alarms, notifications, sync          │
│  └─ Options        → lịch nhắc, đăng nhập                   │
└──────────────────────────┬──────────────────────────────────┘
                           │ HTTPS + Sanctum token
┌──────────────────────────▼──────────────────────────────────┐
│  Backend: Laravel 11 + Sail + PostgreSQL                    │
│  ├─ Dictionary proxy + cache (Free Dictionary API)          │
│  ├─ Vocabulary / Media CRUD                                 │
│  ├─ Quiz selection (weighted: từ ít hỏi ưu tiên)             │
│  └─ Media schedule (daily / weekly / monthly)               │
└─────────────────────────────────────────────────────────────┘
```

| Thành phần | Vai trò |
|-----------|---------|
| **Extension** | Tra từ, lưu từ/media, nhận notification, mở link nghe |
| **Backend** | Đồng bộ dữ liệu, thống kê quiz, lịch nhắc nghe, cache định nghĩa |

**Offline tối thiểu:** Danh sách đã sync đọc từ `chrome.storage.local`; tra từ mới cần mạng.

---

## 2. Cấu trúc thư mục

```
my-foreign-language-coures/
├── docs/
│   └── PLAN.md                 # File này
├── extension/                  # Chrome Extension MV3
│   ├── manifest.json
│   ├── src/
│   │   ├── background/         # service worker
│   │   ├── content/            # chọn từ / tra
│   │   ├── popup/              # UI chính
│   │   ├── options/            # cài đặt
│   │   └── shared/             # API client, types
│   └── package.json
├── backend/                    # Laravel + Sail (PostgreSQL)
│   ├── app/
│   ├── database/migrations/
│   └── routes/api.php
└── README.md
```

---

## 3. Tính năng Extension

### 3.1. Dịch từ vựng Anh–Anh + lưu (1.1)

- Bôi đen từ → context menu / popup tra
- Nguồn: [Free Dictionary API](https://dictionaryapi.dev/) qua BE (`GET /api/dictionary/{word}`)
- Hiển thị: từ, IPA, POS, definitions, ví dụ
- Lưu: `POST /api/vocabularies`

### 3.2. Link audio / YouTube (1.2)

| Trường | Mô tả |
|--------|--------|
| `title` | Tên bài |
| `url` | URL audio hoặc YouTube |
| `type` | `audio` \| `youtube` |
| `frequency` | `daily` \| `weekly` \| `monthly` |
| `notes` | Ghi chú |
| `is_active` | Bật/tắt nhắc |

### 3.3. Notification (1.3)

**Quiz từ vựng**

- Trắc nghiệm 4 đáp án (nghĩa → từ hoặc từ → nghĩa)
- Weighted random: `weight = 1 / (times_quizzed + 1) * decay(last_quizzed_at)`
- `chrome.alarms` + `chrome.notifications`

**Nhắc nghe media**

| frequency | next_listen_at |
|-----------|----------------|
| daily | +1 ngày |
| weekly | +7 ngày |
| monthly | +30 ngày |

- Alarm định kỳ → `GET /api/media-items/due` → notification → mở URL
- Snooze: `POST /api/media-items/{id}/listened`

### 3.4. Quyền manifest

`storage`, `alarms`, `notifications`, `activeTab`, `contextMenus`, host API backend

---

## 4. Backend API

### 4.1. Auth

Laravel Sanctum + **Google OAuth** (Socialite); token lưu `chrome.storage.local`.

- Allowlist email cấu hình qua `FLC_ALLOWED_EMAILS` trong `.env`
- Hỗ trợ `*@domain.com`; xem [GOOGLE_AUTH.md](GOOGLE_AUTH.md)

### 4.2. Bảng chính

- `users`
- `vocabularies` — word, phonetic, meanings (jsonb), times_quizzed, last_quizzed_at
- `vocabulary_examples`
- `media_items` — title, url, type, frequency, next_listen_at, is_active
- `quiz_attempts`
- `listen_logs`
- `dictionary_cache`

### 4.3. Endpoints

| Method | Endpoint | Mô tả |
|--------|----------|--------|
| POST | `/api/register`, `/api/login` | Auth |
| GET | `/api/dictionary/{word}` | Proxy + cache |
| CRUD | `/api/vocabularies` | Từ vựng |
| CRUD | `/api/media-items` | Media |
| GET | `/api/quiz/next` | Câu hỏi weighted |
| POST | `/api/quiz/attempts` | Kết quả quiz |
| GET | `/api/media-items/due` | Media cần nhắc |
| POST | `/api/media-items/{id}/listened` | Đã nghe / snooze |
| GET | `/api/sync` | Full snapshot |

### 4.4. Services

- `DictionaryService` — Free Dictionary API + cache 7 ngày
- `QuizSelectionService` — weighted random
- `MediaScheduleService` — `next_listen_at`

---

## 5. Lộ trình triển khai

| Phase | Nội dung | Trạng thái |
|-------|----------|------------|
| P0 | Sail + migrations + auth + vocabulary + dictionary | ✅ |
| P1 | Extension: tra từ, lưu, list | ✅ |
| P2 | Media CRUD + schedule | ✅ |
| P3 | Alarms + quiz + weighted | ✅ |
| P4 | Media listen notification | ✅ |
| P5 | README, polish | ✅ |

### Quyết định mặc định (MVP)

- Auth: Google OAuth + email allowlist
- Dictionary: Free Dictionary API qua BE
- Quiz: trắc nghiệm 4 đáp án
- Notification mặc định: 2 quiz/ngày, check media mỗi 30 phút
- UI extension: tiếng Việt
- Tên extension: **FLC - Foreign Language Companion**

---

## 6. Rủi ro

| Rủi ro | Xử lý |
|--------|--------|
| Service worker sleep | `chrome.alarms` |
| Dictionary API down | Cache BE |
| User tắt notification | Hướng dẫn Options + check on startup |
