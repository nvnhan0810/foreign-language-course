# FLC Word Learning — Chat + Lookup Resolve

Kế hoạch tổng thể: web chat (SSE + Cursor), extension giữ UX lookup overlay, BE resolve từ trước khi tra (ưu tiên không AI).

---

## 1. Phạm vi theo surface

| Surface | UX | Backend |
|---------|-----|---------|
| **Web `/home/lookup`** | Chat SSE — hỏi đáp đầy đủ | Word Chat API + Cursor agent / user |
| **Extension selection** | **Giữ nguyên** — FAB, overlay, dictionary card | `ResolveLookupWord` → `LookupWord` |
| **Extension popup Lookup tab** | Giữ form lookup đơn (có thể thêm resolve sau) | Cùng resolve endpoint |
| **Mobile WebView** | Web chat (phase sau) | Cùng Word Chat API |

**Nguyên tắc:** Chat = web (và mobile sau). Extension = **tra từ nhanh trên trang**, không chat, không SSE.

---

## 2. Extension — giữ nguyên + resolve từ

### Flow sau Phase 0

```
Select text → FAB / context menu → overlay panel
  → lookupTermFromSelection("outlets") → "outlets"
  → GET /api/dictionary/resolve/outlets
  → { selected: "outlets", resolved: "outlet", dictionary: {...} }
  → renderDictionaryHtml(dictionary)
```

Flow selection, icon, overlay, save word, pronounce — **giữ nguyên**.

### Thay đổi nhỏ (BE + metadata resolve)

Trước lookup, BE **resolve** từ đích:

```
"outlets" → resolve → "outlet" → GET dictionary/outlet
```

FE có thể hiển thị:

```
Selected: outlets
Looking up: outlet
```

### Word resolve — ưu tiên không AI

**Query:** `ResolveLookupWord(selectedWord)`

Cascade (dừng khi hit):

| Step | Cách | Ví dụ |
|------|------|-------|
| 1 | Exact match My Dictionary / Free Dictionary | `happy` → `happy` |
| 2 | Normalize surface form (rules) | strip `-s`, `-es`, `-ies→y`, `-ed`, `-ing`… |
| 3 | Thử từng lemma candidate → dictionary hit | `outlets` → `outlet` ✓ |
| 4 | Datamuse spell suggest (optional) | typo / variant |
| 5 | **AI fallback** (optional, off by default) | chỉ khi 1–4 fail |

---

## 3. Web Word Chat — spec (chưa implement)

| Hạng mục | Quyết định |
|----------|------------|
| Transport | SSE (BE proxy Cursor stream) |
| Session | 1 Cursor agent / user (no-repo, durable) |
| Model | Auto — omit `model` |
| API key | Server `CURSOR_API_KEY` |
| Insights | Structured → quiz / game / exam |

---

## 4. Lộ trình implement

### Phase 0 — Word resolve (extension) ✅ done

| # | Task | Status |
|---|------|--------|
| 0.1 | `ResolveLookupWord` query + handler | done |
| 0.2 | Rule lemmatizer | done |
| 0.3 | Cascade: exact → lemmas → Datamuse | done |
| 0.4 | API `GET /api/dictionary/resolve/{word}` | done |
| 0.5 | Extension: `api.resolveLookup()` + header resolve | done |
| 0.6 | Tests | done |

### Phase 1 — Word Chat backend (web)

Chưa bắt đầu.

### Phase 2 — Web chat UI

Chưa bắt đầu.

### Phase 3 — Learning insights + quiz

Chưa bắt đầu.

### Phase 4 — Extension polish

Chưa bắt đầu.

### Phase 5 — Deprecate agent skill/API

Chưa bắt đầu.

---

## 5. API contract

### Extension (sync)

```
GET /api/dictionary/resolve/{word}
```

Response:

```json
{
  "selected": "outlets",
  "resolved": "outlet",
  "method": "lemma_rules",
  "dictionary": { "word": "outlet", "meanings": [], ... }
}
```

`GET /api/dictionary/{word}` — giữ exact lookup (backward compat).

### Web chat (SSE) — phase 1+

```
GET  /api/word-chat/messages
POST /api/word-chat/messages
GET  /api/word-chat/stream/{runId}
```

---

## 6. Config

```env
LOOKUP_RESOLVE_ENABLE_DATAMUSE=true
LOOKUP_RESOLVE_AI_FALLBACK=false
```

---

## Implementation log

| Date | Done |
|------|------|
| 2026-07-28 | Doc created from design discussion |
| 2026-07-28 | **Phase 0 fix** — when both inflected + lemma exist in API (e.g. `outlets`/`outlet`), prefer lemma if selected lacks phonetic/audio but lemma has it |

### Phase 0 — files touched

**Backend**
- `src/Flc/Dictionary/Application/LookupLemmaGenerator.php`
- `src/Flc/Dictionary/Application/SpellSuggestionGateway.php`
- `src/Flc/Dictionary/Infrastructure/Http/HttpDatamuseSpellSuggestionGateway.php`
- `src/Flc/Dictionary/Application/Query/ResolveLookupWord.php`
- `src/Flc/Dictionary/Application/Handler/ResolveLookupWordHandler.php`
- `app/Http/Controllers/Api/DictionaryController.php` — `resolve()`
- `routes/api.php` — `GET /dictionary/resolve/{word}`
- `config/flc.php` — `lookup_resolve_enable_datamuse`
- `tests/Unit/LookupLemmaGeneratorTest.php`
- `tests/Feature/ResolveLookupWordTest.php`

**Extension**
- `src/shared/types.ts` — `DictionaryResolveResult`
- `src/shared/api.ts` — `resolveLookup()`
- `src/content/selection-ui.ts` — resolve + header "Selected / Looking up"

**Unchanged**
- `GET /api/dictionary/{word}` — exact lookup (backward compat)
- Extension popup lookup tab — still `api.lookup()` (Phase 4)
