# Telegram log alerts

Gửi log **warning**, **error**, **critical** (và cao hơn) lên Telegram qua package [`nvnhan0810/laravel-telegram-logging`](https://github.com/nvnhan0810/laravel-telegram-logging).

## Cài package (FLC backend)

Repo GitHub (chưa tag Packagist) — đã khai báo trong `backend/composer.json`:

```bash
cd backend
composer update nvnhan0810/laravel-telegram-logging
```

Require hiện tại: `"nvnhan0810/laravel-telegram-logging": "dev-main"` (VCS → `main`).

Sau khi publish tag (vd. `v1.0.0`), có thể đổi thành `"^1.0"` và bỏ block `repositories` nếu đăng lên Packagist.

## Cấu hình bot

1. Telegram → [@BotFather](https://t.me/BotFather) → `/newbot` → lấy **bot token**.
2. Nhắn `/start` cho bot vừa tạo (hoặc thêm bot vào group nếu dùng group).
3. Lấy **chat id**:
   - Cá nhân: nhắn bot, mở `https://api.telegram.org/bot<TOKEN>/getUpdates`, tìm `"chat":{"id":123456789}`.
   - Hoặc dùng [@userinfobot](https://t.me/userinfobot) (chat id cá nhân).
4. Thêm vào `.env` production:

```env
TELEGRAM_BOT_TOKEN=123456:ABC...
TELEGRAM_CHAT_ID=123456789
TELEGRAM_LOG_ENABLED=true
TELEGRAM_LOG_LEVEL=warning
LOG_STACK=daily,telegram
```

`TELEGRAM_LOG_LEVEL=warning` → gửi warning, error, critical, alert, emergency (không gửi info/debug).

## Queue (không block request)

```env
TELEGRAM_LOG_QUEUE=true
# TELEGRAM_LOG_QUEUE_CONNECTION=redis
# TELEGRAM_LOG_QUEUE_NAME=default
```

Cần chạy worker: `php artisan queue:work`.

## Tuỳ chỉnh template message

Publish config:

```bash
php artisan vendor:publish --tag=telegram-logging-config
```

Chỉnh `config/telegram-logging.php` → `template`. Placeholders:

| Placeholder | Giá trị |
|-------------|---------|
| `{%emoji%}` | Emoji theo level |
| `{%level%}` | `WARNING`, `ERROR`, … |
| `{%message%}` | Nội dung log |
| `{%context_block%}` | `<pre>JSON context</pre>` hoặc rỗng |
| `{%app_name%}` | `config('app.name')` |
| `{%app_env%}` | `config('app.env')` |
| `{%config:app.url%}` | Bất kỳ `config('…')` |

## Kiểm tra

```bash
php artisan telegram-log:test
```

Hoặc:

```bash
php artisan tinker
>>> Log::warning('Test warning from FLC');
```

## Cách hoạt động

- Kênh `telegram` trong `config/logging.php` dùng driver `telegram-logging`.
- `LOG_STACK` gợi ý: `single,telegram` (local) hoặc `daily,telegram` (production).
- Không đặt token/chat id → handler no-op (không lỗi).
- **Dedupe** mặc định 60s: cùng level + message không gửi lặp.

## Tắt tạm

```env
TELEGRAM_LOG_ENABLED=false
```

Hoặc bỏ `telegram` khỏi `LOG_STACK`:

```env
LOG_STACK=daily
```

## Lưu ý

- Cần PHP **curl** (Http facade) ra internet tới `api.telegram.org`.
- Exception không bắt vẫn được Laravel log → Telegram nếu level đủ cao.
- Không gửi secret từ `.env` trong message; context JSON có thể bị cắt (mặc định 800 ký tự).
