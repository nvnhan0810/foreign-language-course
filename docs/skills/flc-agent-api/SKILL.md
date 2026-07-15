---
name: flc-agent-api
description: >-
  Tra từ / cụm từ và lưu hoặc curate dictionary trên FLC (foreign-language-course)
  qua Agent API. Dùng khi user muốn lookup, tổng hợp nghĩa, save vocab, hoặc cập nhật
  My Dictionary từ Cursor.
---

# FLC Agent API — lookup / save / curate

Agent gọi FLC qua **Bearer token** (`FLC_API_TOKEN`). Token lấy từ Profile web/mobile (Create key) — **không** tạo/revoke key từ skill.

**Setup lần đầu:** xem [`docs/FLC_AGENT_SKILL_SETUP.md`](../../FLC_AGENT_SKILL_SETUP.md).

## Cấu hình global (chung với nvnhan0810.com blog)

Đọc config từ:

`~/.config/nvnhan-blog/agent.env`

Format:

```env
BLOG_API_URL=https://nvnhan0810.com
BLOG_API_TOKEN=your-blog-token
FLC_API_URL=https://your-flc-host
FLC_API_TOKEN=your-flc-agent-token
```

Setup một lần:

```bash
mkdir -p ~/.config/nvnhan-blog
# Nếu đã có agent.env (blog), chỉ thêm FLC_API_URL + FLC_API_TOKEN
chmod 600 ~/.config/nvnhan-blog/agent.env
```

Example trong repo: `docs/examples/flc-agent.env.example`

Tuỳ chọn — load vào mọi terminal:

```bash
# ~/.zshrc
[[ -f ~/.config/nvnhan-blog/agent.env ]] && set -a && source ~/.config/nvnhan-blog/agent.env && set +a
```

**Skill global:** giữ tại `~/.cursor/skills/flc-agent-api/`.

## Load config trước khi gọi API

```python
import os
from pathlib import Path

def load_flc_agent_config() -> tuple[str, str]:
    config_path = Path.home() / ".config" / "nvnhan-blog" / "agent.env"

    if config_path.exists():
        for line in config_path.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, value = line.partition("=")
            key = key.strip()
            value = value.strip().strip('"').strip("'")
            if key and value:
                os.environ.setdefault(key, value)

    url = os.environ.get("FLC_API_URL", "").rstrip("/")
    token = os.environ.get("FLC_API_TOKEN", "").strip()

    if not url or not token:
        raise RuntimeError(
            "Thiếu FLC_API_URL hoặc FLC_API_TOKEN. "
            "Tạo key trên Profile (web/mobile), rồi thêm vào "
            "~/.config/nvnhan-blog/agent.env"
        )

    return url, token
```

## Endpoints

Base: `{FLC_API_URL}/api`

Header: `Authorization: Bearer {FLC_API_TOKEN}`

| Method | Path | Ability | Mục đích |
|--------|------|---------|----------|
| GET | `/api/agent/dictionary/{word}` | `agent:lookup` | Tra từ (My Dictionary → Free Dictionary) |
| PUT | `/api/agent/dictionary/{word}` | `agent:curate` | Curate entry global (`is_curated`) |
| GET | `/api/agent/vocabularies` | `agent:vocab` | List vocab của user |
| POST | `/api/agent/vocabularies` | `agent:vocab` | Lưu / assign từ vào user |
| PUT | `/api/agent/vocabularies/{id}` | `agent:vocab` | Update nghĩa vocab |
| DELETE | `/api/agent/vocabularies/{id}` | `agent:vocab` | Xóa bookmark vocab |

Key management (`/api/me/agent-tokens`) **chỉ** web/mobile — skill không gọi.

## Payload chuẩn (meanings theo từng nghĩa)

```json
{
  "word": "happy",
  "phonetic": "/ˈhæpi/",
  "audio_url": null,
  "meanings": [
    {
      "part_of_speech": "adjective",
      "definition": "Feeling or showing pleasure",
      "examples": ["I'm happy to help."],
      "synonyms": ["glad", "joyful"],
      "antonyms": ["sad"]
    }
  ],
  "synonyms": [],
  "antonyms": []
}
```

- POST vocab: tối thiểu `word`; có thể kèm `phonetic` + `meanings`.
- PUT curate: bắt buộc `meanings` (≥1 definition); set curated trên My Dictionary.

## Quy trình

1. Load config.
2. `GET /api/agent/dictionary/{word}` nếu cần ngữ liệu hiện có.
3. Tổng hợp / chỉnh `meanings` (POS, definition, examples, syn/ant theo từng nghĩa).
4. Hỏi user trước khi ghi:
   - **Save word** → `POST /api/agent/vocabularies` (gán cho user của token).
   - **Curate dictionary** → `PUT /api/agent/dictionary/{word}` (global).
   - Có thể làm cả hai theo yêu cầu user.
5. Trả `id` vocab / payload curated + tóm tắt.

Không curate global trừ khi user nói rõ (ví dụ: "curate", "cập nhật từ điển", "đánh dấu curated").

## Ví dụ lookup + save

```python
import json
import urllib.request
import urllib.parse

FLC_API_URL, FLC_API_TOKEN = load_flc_agent_config()

def flc_request(method: str, path: str, payload: dict | None = None):
    data = None if payload is None else json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        f"{FLC_API_URL}{path}",
        data=data,
        headers={
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Authorization": f"Bearer {FLC_API_TOKEN}",
        },
        method=method,
    )
    with urllib.request.urlopen(req, timeout=60) as resp:
        return json.loads(resp.read().decode("utf-8"))

word = "happy"
lookup = flc_request("GET", f"/api/agent/dictionary/{urllib.parse.quote(word)}")

# sau khi user confirm save:
saved = flc_request(
    "POST",
    "/api/agent/vocabularies",
    {
        "word": word,
        "phonetic": lookup.get("phonetic"),
        "meanings": lookup.get("meanings") or [],
    },
)
print(saved)
```

## Ví dụ curate

```python
payload = {
    "word": "happy",
    "phonetic": "/ˈhæpi/",
    "meanings": [
        {
            "part_of_speech": "adjective",
            "definition": "Feeling or showing pleasure or contentment",
            "examples": ["She was happy to see them."],
            "synonyms": ["glad", "joyful"],
            "antonyms": ["sad", "unhappy"],
        }
    ],
    "synonyms": [],
    "antonyms": [],
}
curated = flc_request("PUT", f"/api/agent/dictionary/{urllib.parse.quote('happy')}", payload)
print(curated)
```
