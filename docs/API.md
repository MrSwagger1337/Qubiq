# Developer REST API v1 Reference

Qubiq provides a **100% free, stateless-capable REST API** under `/api/v1` alongside a machine-readable **OpenAPI 3.1** specification at `GET /openapi.json` and interactive documentation at `/api-docs`.

---

## Base URL & Conventions

- **Base Path**: `/api/v1`
- **Content Types**:
  - `application/json` for standard JSON requests
  - `multipart/form-data` when uploading a binary `logo` file (`PNG`, `JPEG`, or `WebP`), passing JSON options inside a `config` form field or flat form fields
- **Authentication**:
  - Static QR endpoints (`/health`, `/qr/validate`, `/qr/preview`, `/qr/generate`, `/qr/export`): **No authentication or API key required**.
  - Dynamic QR management endpoints (`PATCH /links/{id}`, `DELETE /links/{id}`, `GET /links/{id}/stats`): `Authorization: Bearer <management_token>` returned once at link creation.
- **Rate Limiting Headers**:
  - `X-RateLimit-Limit`
  - `X-RateLimit-Remaining`
  - `X-RateLimit-Reset`
  - `Retry-After` (included on `429 Too Many Requests`)

---

## Endpoints Summary

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/health` | Service health, runtime capabilities, and supported QR types/formats |
| `POST` | `/api/v1/qr/validate` | Validate payload fields & design options, check QR capacity & contrast warnings |
| `POST` | `/api/v1/qr/preview` | Generate live SVG preview, matrix metadata, contrast ratio, and scannability advice |
| `POST` | `/api/v1/qr/generate` | Generate QR code in requested format (returns JSON with base64/SVG or binary stream) |
| `POST` | `/api/v1/qr/export` | Download raw binary/vector QR file (`png`, `svg`, `pdf`, `eps`, `webp`, `jpeg`) |
| `POST` | `/api/v1/links` | Create an anonymous dynamic short link (`/r/{code}`) and receive its one-time management token |
| `PATCH` | `/api/v1/links/{code}` | Update destination URL or active state of a dynamic QR link (Bearer token required) |
| `DELETE` | `/api/v1/links/{code}` | Permanently delete a dynamic QR link and its aggregate scan counters (Bearer token required) |
| `GET` | `/api/v1/links/{code}/stats` | Fetch aggregate privacy-preserving scan metrics & daily rollups (Bearer token required) |

---

## 1. Health Check — `GET /api/v1/health`

```bash
curl -s http://localhost:8000/api/v1/health
```

```json
{
  "ok": true,
  "data": {
    "service": "Qubiq",
    "status": "healthy",
    "api_version": "v1",
    "php_version": "8.2+",
    "dynamic_qr_enabled": true,
    "supported_types": [
      "url", "text", "email", "phone", "sms", "vcard", "mecard",
      "location", "facebook", "twitter", "youtube", "wifi", "event",
      "bitcoin", "whatsapp", "telegram", "instagram", "linkedin",
      "paypal", "applink", "custom_uri"
    ],
    "supported_formats": ["png", "svg", "pdf", "eps", "webp", "jpeg"]
  }
}
```

---

## 2. Validate Payload & Design — `POST /api/v1/qr/validate`

Validates type-specific fields, computes exact UTF-8 byte length, selects or verifies QR version (`1–40`), and returns contrast/readability warnings without rendering export files.

```bash
curl -X POST http://localhost:8000/api/v1/qr/validate \
  -H "Content-Type: application/json" \
  -d '{
    "type": "wifi",
    "fields": {
      "ssid": "StudioGuest",
      "encryption": "WPA",
      "password": "correct-horse-battery",
      "hidden": false
    },
    "design": {
      "ecc": "Q",
      "foregroundColor": "#0f172a",
      "backgroundColor": "#ffffff"
    }
  }'
```

---

## 3. Generate QR Code (JSON Envelope) — `POST /api/v1/qr/generate`

```bash
curl -X POST http://localhost:8000/api/v1/qr/generate \
  -H "Content-Type: application/json" \
  -d '{
    "type": "url",
    "fields": { "url": "https://example.org/docs?ref=qubiq" },
    "format": "svg",
    "design": {
      "size": 1000,
      "ecc": "H",
      "quietZone": 4,
      "moduleShape": "rounded",
      "finderOuterShape": "rounded",
      "finderInnerShape": "circle",
      "colorMode": "linear",
      "foregroundColor": "#065f46",
      "gradientEndColor": "#0f172a",
      "gradientAngle": 135,
      "backgroundColor": "#ffffff",
      "transparentBackground": false
    }
  }'
```

Response (`200 OK`):

```json
{
  "ok": true,
  "data": {
    "type": "url",
    "format": "svg",
    "mime_type": "image/svg+xml",
    "filename": "qubiq-url-1000px.svg",
    "size_px": 1000,
    "byte_length": 14820,
    "content_base64": "PD94bWwgdmVyc2lvbj0iMS4wIi...",
    "metadata": {
      "version": 3,
      "ecc_level": "H",
      "mask_pattern": 2,
      "module_count": 37,
      "contrast_ratio": 9.41
    },
    "warnings": [],
    "renderer_notes": []
  }
}
```

---

## 4. Direct Binary/Vector Export — `POST /api/v1/qr/export`

Streams the authentic file directly (`image/png`, `image/svg+xml`, `application/pdf`, `application/postscript`, `image/webp`, or `image/jpeg`).

### JSON Example (PDF Print Vector)

```bash
curl -X POST http://localhost:8000/api/v1/qr/export \
  -H "Content-Type: application/json" \
  -d '{
    "type": "vcard",
    "fields": {
      "first_name": "Ada",
      "last_name": "Lovelace",
      "organization": "Analytical Engine Lab",
      "email": "ada@example.org",
      "mobile_phone": "+442079460123"
    },
    "format": "pdf",
    "design": {
      "size": 1200,
      "ecc": "Q",
      "moduleShape": "rounded",
      "foregroundColor": "#0f172a"
    }
  }' \
  --output ada-vcard.pdf
```

### Multipart Logo Upload Example (PNG)

```bash
curl -X POST http://localhost:8000/api/v1/qr/export \
  -F 'config={"type":"url","fields":{"url":"https://example.com"},"format":"png","design":{"size":1000,"ecc":"H","logoScale":0.20,"logoBackground":true}}' \
  -F 'logo=@./brand-mark.png' \
  --output branded-qr.png
```

---

## 5. Anonymous Dynamic QR Links — `/api/v1/links`

### Create Link (`POST /api/v1/links`)

```bash
curl -X POST http://localhost:8000/api/v1/links \
  -H "Content-Type: application/json" \
  -d '{
    "destination_url": "https://example.org/launch-2026",
    "title": "Product Launch Poster"
  }'
```

Returns (`201 Created`):
- `short_code` (e.g. `k9x4m2p8`)
- `short_url` (e.g. `https://qr.example.com/r/k9x4m2p8` — encode this into your QR code!)
- `management_token` (**shown only once**; Qubiq stores only its `SHA-256` digest)
- `manage_url` (`https://qr.example.com/manage/k9x4m2p8#token=...` — token stays in URI `#fragment`, never sent in HTTP Referer headers)

### Update Destination (`PATCH /api/v1/links/{code}`)

```bash
curl -X PATCH http://localhost:8000/api/v1/links/k9x4m2p8 \
  -H "Authorization: Bearer <YOUR_MANAGEMENT_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "destination_url": "https://example.org/launch-2027",
    "is_active": true
  }'
```

### View Privacy-Preserving Analytics (`GET /api/v1/links/{code}/stats`)

```bash
curl http://localhost:8000/api/v1/links/k9x4m2p8/stats \
  -H "Authorization: Bearer <YOUR_MANAGEMENT_TOKEN>"
```

### Delete Link (`DELETE /api/v1/links/{code}`)

```bash
curl -X DELETE http://localhost:8000/api/v1/links/k9x4m2p8 \
  -H "Authorization: Bearer <YOUR_MANAGEMENT_TOKEN>"
```

---

## Error Envelope Format

All validation, capacity, rate-limit, and authorization errors return consistent structured JSON:

```json
{
  "ok": false,
  "error": {
    "code": "validation_error",
    "message": "Please correct the highlighted input fields.",
    "fields": {
      "url": "Only http:// and https:// URLs are permitted."
    }
  }
}
```

