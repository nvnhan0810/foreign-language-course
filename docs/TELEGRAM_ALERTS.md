# Telegram log alerts

Gửi log **warning**, **error**, **critical** (và cao hơn) lên Telegram khi hệ thống ghi log qua Laravel `Log` facade hoặc exception handler.

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
TELEGRAM_ALERTS_ENABLED=true
LOG_STACK=daily,telegram
LOG_TELEGRAM_LEVEL=warning
```

`LOG_TELEGRAM_LEVEL=warning` → gửi warning, error, critical, alert, emergency (không gửi info/debug).

## Kiểm tra

```bash
php artisan flc:telegram-test
```

Hoặc kích hoạt log thử:

```bash
php artisan tinker
>>> Log::warning('Test warning from FLC');
```

## Cách hoạt động

- Kênh `telegram` trong `config/logging.php` (Monolog custom handler).
- `LOG_STACK` mặc định gợi ý: `single,telegram` (local) hoặc `daily,telegram` (production).
- Nếu **không** đặt token/chat id → handler no-op (không lỗi).
- **Dedupe** 60s: cùng level + message không gửi lặp (tránh spam cron/queue).

## Tắt tạm

```env
TELEGRAM_ALERTS_ENABLED=false
```

Hoặc bỏ `telegram` khỏi `LOG_STACK`:

```env
LOG_STACK=daily
```

## Lưu ý

- Cần PHP **curl** (Http facade) ra internet tới `api.telegram.org`.
- Exception không bắt vẫn được Laravel log → Telegram nếu level đủ cao.
- Không gửi secret từ `.env` trong message; context JSON có thể bị cắt 800 ký tự.
