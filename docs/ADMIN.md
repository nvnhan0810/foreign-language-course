# Trang Admin FLC

URL: **http://localhost:8080/admin** (hoặc `{APP_URL}/admin`)

## Đăng nhập

1. Cấu hình email admin trong `backend/.env`:

   ```env
   FLC_ADMIN_EMAILS=ban@gmail.com,admin@company.com
   ```

2. Thêm redirect URI trong Google Cloud Console:

   ```
   http://localhost:8080/admin/auth/google/callback
   ```

3. Mở `/admin/login` → **Đăng nhập bằng Google** (chỉ email trong `FLC_ADMIN_EMAILS`).

## Chức năng

| Mục | Mô tả |
|-----|--------|
| **Tổng quan** | Thống kê users, từ vựng, media |
| **Allowlist email** | Thêm/sửa/xóa email được phép đăng nhập extension (lưu DB) |
| **Cài đặt** | Tên app, thông báo extension, bật “cho phép mọi email” |
| **Người dùng** | Xem / xóa user |
| **Từ vựng** | Tìm, sửa JSON meanings, xóa |
| **Media** | Sửa link nghe, tần suất, bật/tắt nhắc |

## Allowlist

- **Database** (admin UI): ưu tiên quản lý qua trang Allowlist
- **`.env`** `FLC_ALLOWED_EMAILS`: vẫn áp dụng song song (đọc-only trên trang Cài đặt)
- **Cài đặt admin**: `allow_all_emails` trong DB — cho phép mọi Google account (ngoài `.env`)

## Thông báo extension

Trong **Cài đặt** → **Thông báo extension**: nội dung hiển thị trên popup sau khi user đồng bộ (`/api/sync`).
