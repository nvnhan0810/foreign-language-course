# Đăng nhập Google + Email allowlist

## 1. Google Cloud Console

1. Tạo project tại [Google Cloud Console](https://console.cloud.google.com/)
2. **APIs & Services** → **OAuth consent screen** → cấu hình (External / Internal)
3. **Credentials** → **Create credentials** → **OAuth client ID** → **Web application**
4. **Authorized redirect URIs** (bắt buộc khớp backend):

   ```
   http://localhost:8080/api/auth/google/callback
   http://localhost:8080/admin/auth/google/callback
   ```

   Production thêm URL tương ứng, ví dụ:

   ```
   https://api.yourdomain.com/api/auth/google/callback
   ```

5. Copy **Client ID** và **Client secret** vào `backend/.env`:

   ```env
   GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=xxx
   GOOGLE_REDIRECT_URI=http://localhost:8080/api/auth/google/callback
   APP_URL=http://localhost:8080
   ```

   **Lưu ý:** Luồng mobile/extension luôn dùng callback `/api/auth/google/callback` (hard-code trong `GoogleAuthController`). Admin dùng `/admin/auth/google/callback` riêng. Trong Google Console phải đăng ký **cả hai** redirect URI; nếu chỉ có admin, sau Google sẽ vào dashboard admin thay vì `flc://oauth-callback`.

## 2. Email allowlist

Trong `backend/.env`:

```env
# Một hoặc nhiều email, phân tách bằng dấu phẩy
FLC_ALLOWED_EMAILS=ban@gmail.com,dong.nghiep@congty.com,*@congty.com

# Chỉ dùng khi dev — cho phép mọi tài khoản Google
FLC_ALLOW_ALL_EMAILS=false
```

| Giá trị | Ý nghĩa |
|--------|---------|
| `user@gmail.com` | Khớp chính xác email |
| `*@congty.com` | Mọi email thuộc domain `congty.com` |
| Danh sách rỗng + `FLC_ALLOW_ALL_EMAILS=false` | **Không ai** đăng nhập được |
| `FLC_ALLOW_ALL_EMAILS=true` | Bỏ qua allowlist (không khuyến nghị production) |

Sau khi sửa `.env`:

```bash
cd backend && ./vendor/bin/sail artisan config:clear
```

## 3. Flutter Mobile

App dùng redirect `flc://oauth-callback` (đã allow trên backend). Cấu hình deep link Android/iOS: [mobile/README.md](../mobile/README.md).

Luồng giống extension: mở `/api/auth/google/redirect?redirect_uri=flc://oauth-callback` → Google → callback backend → redirect về app kèm `?token=...`.

## 4. Chrome Extension

Extension dùng `chrome.identity.launchWebAuthFlow` — **không** cần thêm redirect URI của extension vào Google Console (Chrome tự xử lý `https://<extension-id>.chromiumapp.org/`).

Cần permission `identity` trong manifest (đã có).

Reload extension sau `npm run build`.

## 5. Luồng đăng nhập

1. User bấm **Đăng nhập bằng Google** trong popup
2. Extension mở OAuth → backend → Google
3. Google callback về backend
4. Backend kiểm tra allowlist → tạo Sanctum token → redirect về extension với `?token=...`
5. Extension lưu token vào `chrome.storage`
