# Setup FLC Cursor Agent Skill

Hướng dẫn cài skill Cursor để **tra từ**, **lưu vocab**, và **curate My Dictionary** trên FLC từ local Cursor agent.

Skill dùng chung file config với blog `nvnhan0810.com` (`~/.config/nvnhan-blog/agent.env`).

---

## Yêu cầu

- FLC backend đã deploy (có `/api/agent/*`)
- Tài khoản user đăng nhập được trên **web** hoặc **mobile**
- Cursor Desktop/CLI hỗ trợ Agent Skills (`~/.cursor/skills/`)

---

## Bước 1 — Tạo API key (web hoặc mobile)

Chỉ tạo/revoke key trên Profile. Skill **không** tạo key.

### Web

1. Đăng nhập → **Profile**
2. Section **Agent API keys** → **Create key**
3. **Copy token ngay** (chỉ hiện một lần)

### Mobile

1. Mở tab **Profile**
2. Card **Agent API keys** → **Create**
3. Copy token từ dialog

Default abilities: `agent:lookup`, `agent:vocab`, `agent:curate`.

---

## Bước 2 — Cấu hình `agent.env`

```bash
mkdir -p ~/.config/nvnhan-blog
```

### Nếu chưa có file (chỉ FLC)

```bash
cp docs/examples/flc-agent.env.example ~/.config/nvnhan-blog/agent.env
chmod 600 ~/.config/nvnhan-blog/agent.env
```

Sửa:

```env
FLC_API_URL=https://your-flc-host
FLC_API_TOKEN=paste-token-vừa-copy
```

`FLC_API_URL` = origin backend (không có `/api` ở cuối), ví dụ `https://flc.example.com` hoặc `http://localhost`.

### Nếu đã dùng blog skill

Mở `~/.config/nvnhan-blog/agent.env` và **thêm** hai dòng:

```env
FLC_API_URL=https://your-flc-host
FLC_API_TOKEN=paste-token-vừa-copy
```

Giữ nguyên `BLOG_API_URL` / `BLOG_API_TOKEN` nếu có.

### (Tuỳ chọn) Load vào mọi shell

Thêm vào `~/.zshrc` hoặc `~/.bashrc`:

```bash
[[ -f ~/.config/nvnhan-blog/agent.env ]] && set -a && source ~/.config/nvnhan-blog/agent.env && set +a
```

Skill vẫn đọc file trực tiếp nếu chưa export env.

---

## Bước 3 — Cài skill vào Cursor

Từ root repo `foreign-language-course`:

```bash
mkdir -p ~/.cursor/skills
cp -R docs/skills/flc-agent-api ~/.cursor/skills/flc-agent-api
```

Kiểm tra:

```bash
ls ~/.cursor/skills/flc-agent-api/SKILL.md
```

Skill path chuẩn: `~/.cursor/skills/flc-agent-api/SKILL.md`

Restart Cursor (hoặc mở lại agent session) nếu skill chưa hiện.

---

## Bước 4 — Kiểm tra nhanh

Trong Cursor, gọi skill / nhờ agent:

> Dùng flc-agent-api tra từ "happy"

Agent sẽ:

1. Load `FLC_API_URL` + `FLC_API_TOKEN` từ `agent.env`
2. `GET /api/agent/dictionary/happy`
3. Hiện meanings; chỉ **save** / **curate** khi bạn xác nhận

Smoke test API bằng tay (tuỳ chọn):

```bash
source ~/.config/nvnhan-blog/agent.env
curl -sS \
  -H "Authorization: Bearer $FLC_API_TOKEN" \
  -H "Accept: application/json" \
  -H "User-Agent: Mozilla/5.0 (compatible; FLC-Cursor-Agent/1.0)" \
  "$FLC_API_URL/api/agent/dictionary/happy" | head
```

> Cloudflare có thể trả **403 / error 1010** nếu thiếu `User-Agent` (UA mặc định của Python/`curl` bị chặn). Skill bắt buộc gửi header trên.

---

## Cập nhật skill sau khi pull repo

```bash
cp -R docs/skills/flc-agent-api ~/.cursor/skills/flc-agent-api
```

---

## Revoke / xoay key

1. Profile (web/mobile) → **Revoke** key cũ  
2. **Create key** mới  
3. Cập nhật `FLC_API_TOKEN` trong `~/.config/nvnhan-blog/agent.env`

---

## Troubleshooting

| Triệu chứng | Cách xử lý |
|-------------|------------|
| `Thiếu FLC_API_URL hoặc FLC_API_TOKEN` | Kiểm tra `~/.config/nvnhan-blog/agent.env`, `chmod 600`, không thừa dấu ngoặc |
| `401` / Unauthorized | Token sai hoặc đã revoke — tạo key mới |
| `403` Missing ability | Key thiếu ability; tạo lại key mặc định (đủ 3 abilities) |
| `403` khi gọi `/api/me/agent-tokens` bằng agent key | Đúng thiết kế — quản lý key chỉ trên web/mobile |
| Skill không được Cursor nhận | Confirm path `~/.cursor/skills/flc-agent-api/SKILL.md`, restart Cursor |
| `404` Word not found | Từ không có trên Free Dictionary / My Dictionary |
| `403` Cloudflare **1010** / browser signature banned | Thiếu hoặc sai `User-Agent` — dùng `Mozilla/5.0 (compatible; FLC-Cursor-Agent/1.0)` trên mọi request (xem skill) |

---

## Tài liệu liên quan

- Skill chi tiết (endpoints, payload, ví dụ Python): [`docs/skills/flc-agent-api/SKILL.md`](skills/flc-agent-api/SKILL.md)
- Env example: [`docs/examples/flc-agent.env.example`](examples/flc-agent.env.example)
