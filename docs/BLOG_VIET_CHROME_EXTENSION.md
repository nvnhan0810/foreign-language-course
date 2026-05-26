# Viết Chrome Extension: Bài học từ dự án FLC

*Foreign Language Companion — extension học tiếng Anh trên trình duyệt.*

---

## Mở đầu

Chrome Extension cho phép thêm tính năng vào **bất kỳ trang web nào**: bôi chữ để tra nghĩa, lưu từ, nhắc ôn tập. User không cần rời khỏi trang đang đọc.

Bài viết tập trung **extension**: cấu trúc file, tính năng FLC, và các lỗi thực tế khi làm UI bôi chữ. Không đi sâu phần server.

---

## 1. Extension gồm những gì?

Manifest V3 (MV3) chia extension thành vài phần, mỗi phần một việc:

| Thành phần | Làm gì? | FLC dùng để |
|------------|---------|-------------|
| **manifest.json** | Khai báo tên, quyền, file chạy | Cấu hình toàn extension |
| **Content script** | Chạy trên trang web user đang xem | Icon nổi + panel tra từ khi bôi chữ |
| **Popup** | Cửa sổ nhỏ khi bấm icon trên thanh Chrome | Từ đã lưu, media, quiz, đăng nhập |
| **Service worker** | Chạy nền (không hiện UI) | Nhắc quiz, nhắc nghe lại |
| **Options** | Trang cài đặt | Bật/tắt thông báo, tùy chỉnh lịch nhắc |

```text
Trang web (Wikipedia, YouTube, ...)
    └── Content script → icon + panel tra từ tại chỗ bôi chữ

Icon trên thanh Chrome
    └── Popup → quản lý từ, media, quiz

Nền (service worker)
    └── Alarm → gửi notification nhắc học
```

**Lưu ý:** Code extension và code của trang web **tách biệt** (isolated world). Extension vẫn **thêm** phần tử HTML/CSS riêng (ví dụ `#flc-selection-root`) lên trang.

---

## 2. Cấu trúc thư mục cơ bản

```text
extension/
├── manifest.json              # “Hộ chiếu” của extension
├── public/icons/              # Icon 16, 48, 128 px
├── src/
│   ├── content/               # Chạy trên mọi trang
│   │   ├── content-script.ts  # File vào — gọi selection UI
│   │   ├── selection-ui.ts    # Icon nổi + panel tra từ
│   │   └── content-overlay.css
│   ├── popup/                 # UI khi bấm icon toolbar
│   │   ├── popup.html
│   │   ├── popup.ts
│   │   └── popup.css
│   ├── options/               # Trang cài đặt
│   │   ├── options.html
│   │   └── options.ts
│   ├── background/
│   │   └── service-worker.ts  # Alarm, notification, menu chuột phải
│   └── shared/                # Code dùng chung
│       ├── storage.ts         # Lưu token, settings (chrome.storage)
│       ├── googleAuth.ts      # Đăng nhập Google
│       └── dictionary-ui.ts   # Hiển thị nghĩa từ
├── vite.config.ts             # Build popup, background
├── vite.content.config.ts     # Build content script (IIFE)
└── dist/                      # ← Load folder này lên Chrome
```

**Cài thử:** `npm run build` → `chrome://extensions` → Developer mode → **Load unpacked** → chọn `extension/dist`.

---

## 3. Thuật ngữ cần biết (khi đọc code FLC)

| Thuật ngữ | Nghĩa đơn giản |
|-----------|----------------|
| **MV3** | Phiên bản manifest hiện tại của Chrome Extension |
| **Content script** | Script inject vào trang web |
| **Popup** | Cửa sổ nhỏ từ icon trên thanh Chrome |
| **Service worker** | Script nền; có thể bị Chrome tắt khi không dùng |
| **FAB** | Nút tròn nổi (Floating Action Button) cạnh chỗ bôi chữ |
| **Panel** | Khung tra từ hiện **trên trang**, không phải popup toolbar |
| **IIFE** | Một file JS “tự chạy”, không dùng `import` — **bắt buộc** cho content script |
| **Shadow DOM** | DOM ẩn trong component (YouTube hay gặp) — selection đôi khi khó |
| **`chrome.storage`** | Chỗ lưu dữ liệu extension (đăng nhập, cài đặt) |
| **Debounce** | Chờ vài chục ms rồi mới xử lý (tránh chạy logic quá nhiều lần) |

### IIFE là gì? Vì sao content script cần?

Khi build bằng Vite + TypeScript, code thường có `import`:

```javascript
import { api } from '../shared/api.js';
```

**Popup** load `<script type="module">` → chạy được.

**Content script** Chrome inject như file thường → **`import` bị lỗi**:

```text
Uncaught SyntaxError: Cannot use import statement outside a module
```

→ Script chết, **không có icon**, không có panel.

**Cách FLC:** build content script **riêng** thành **một file IIFE** (gộp hết code vào một khối):

```bash
npm run build
# = vite build + vite build --config vite.content.config.ts
```

File `vite.content.config.ts` dùng `formats: ['iife']` — toàn bộ logic nằm trong `dist/content/content-script.js`, không phụ thuộc file `chunks/` khác.

**Debug:** Mở F12 trên **trang web** (không chỉ popup extension) khi kiểm tra content script.

---

## 4. Tính năng FLC (phía extension)

### 4.1. Bôi chữ → icon → panel (content script)

Đây là tính năng chính trên mọi trang:

1. User **bôi** từ hoặc câu tiếng Anh  
2. Xuất hiện **icon tròn FLC** cạnh vùng chọn  
3. Bấm icon → **panel** hiện ngay đó: nghĩa Anh–Anh, ví dụ, nút **Lưu từ**  
4. `Esc` hoặc **Đóng** → ẩn panel  

**Câu dài:** API từ điển chỉ tra **từ đơn**; panel vẫn hiện cả đoạn đã chọn và ghi rõ “tra từ: …” (từ đầu tiên).

**Chuột phải:** Menu “Tra từ với FLC” khi đã bôi chữ (vẫn hoạt động song song).

### 4.2. Popup (icon trên thanh Chrome)

- **Đăng nhập Google** (lần đầu)  
- **Tra từ** — nhập tay hoặc nhận từ vừa bôi trên web  
- **Từ của tôi** — danh sách đã lưu  
- **Media** — link YouTube/audio, tần suất nghe (ngày / tuần / tháng)  
- **Quiz** — ôn từ trắc nghiệm (cần đủ số từ tối thiểu)  
- **Cài đặt** — link sang Options  

### 4.3. Notification (service worker)

- Nhắc **quiz** từ vựng đã lưu (theo lịch trong Options)  
- Nhắc **nghe lại** media đến hạn  
- Dùng `chrome.alarms` (không dùng `setInterval` dài trong worker — worker có thể bị tắt)

### 4.4. Options

- Bật/tắt thông báo  
- Số lần quiz / ngày (ước lượng)  
- Chu kỳ kiểm tra nhắc nghe (phút)  

---

## 5. Cách hoạt động selection UI (kỹ thuật gọn)

### Luồng sự kiện

```text
mouseup / pointerup / selectionchange
    → debounce ~80ms
    → đọc window.getSelection()
    → hiện FAB tại getBoundingClientRect() (hoặc vị trí chuột)

click FAB
    → lưu anchorRect (vị trí lúc bôi)
    → mở panel, ẩn FAB
    → đặt panel căn theo anchorRect
```

### `manifest.json` (phần content script)

```json
"content_scripts": [{
  "matches": ["<all_urls>"],
  "js": ["content/content-script.js"],
  "css": ["content/content-overlay.css"],
  "run_at": "document_end",
  "all_frames": true
}],
"web_accessible_resources": [{
  "resources": ["icons/*.png"],
  "matches": ["<all_urls>"]
}]
```

- `all_frames: true` — hỗ trợ iframe (một số trang embed)  
- `web_accessible_resources` — icon trong nút tròn load được từ extension  

### CSS chính

```css
#flc-selection-root {
  position: fixed;
  z-index: 2147483647;
  pointer-events: none;   /* không chặn click trang */
}
.flc-fab, .flc-panel {
  position: fixed;
  pointer-events: auto;   /* chỉ nút và panel nhận click */
}
```

---

## 6. Lỗi thường gặp (chỉ phần extension)

| Triệu chứng | Nguyên nhân | Cách xử lý |
|-------------|-------------|------------|
| Không có icon khi bôi chữ | Content script lỗi `import` | Build IIFE (`vite.content.config.ts`); xem Console **trên trang** |
| Panel ở góc trên-trái | Đặt vị trí theo FAB đã ẩn | Đặt panel theo `anchorRect` của selection |
| Panel mở rồi tắt ngay | `mouseup` gọi `hidePanel()` sau `click` FAB | Cờ `panelOpen`; bỏ qua event từ UI FLC |
| YouTube không được | Shadow DOM / vùng không select được | Thử tiêu đề, mô tả video |
| Popup toolbar lỗi | Sai đường dẫn file sau build | Reload extension; kiểm tra `dist/popup/` |

---

## 7. Quyền trong manifest (FLC)

| Quyền | Dùng cho |
|--------|----------|
| `storage` | Lưu đăng nhập, cài đặt |
| `alarms` | Lịch nhắc quiz / media |
| `notifications` | Hiện thông báo hệ thống |
| `identity` | Đăng nhập Google |
| `contextMenus` | Menu chuột phải “Tra từ với FLC” |
| `activeTab` | Tương tác tab hiện tại |

---

## Kết luận

Extension FLC minh họa ba lớp UI:

1. **Trên trang** — content script: bôi chữ → FAB → panel (cần **IIFE** khi build)  
2. **Toolbar** — popup: quản lý từ, media, quiz  
3. **Nền** — service worker: notification định kỳ  

File then chốt: `manifest.json`, `content/*`, `popup/*`, `background/service-worker.ts`, `shared/*`, build ra `dist/`.

Ba bài học đáng nhớ khi làm overlay tra từ:

- Content script **một file IIFE**, không `import`  
- Panel đặt theo **vùng bôi chữ**, không theo element đã ẩn  
- Tránh **race** giữa `click` và `mouseup` khiến panel tự đóng  

---

## Tài liệu tham khảo

- [Chrome Extensions MV3](https://developer.chrome.com/docs/extensions/mv3/)
- [Content scripts](https://developer.chrome.com/docs/extensions/mv3/content_scripts/)
- [chrome.alarms](https://developer.chrome.com/docs/extensions/reference/api/alarms)
- [chrome.storage](https://developer.chrome.com/docs/extensions/reference/api/storage)

---

*Repo: `extension/` — xem thêm `README.md` để cài và chạy thử.*
