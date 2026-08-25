# Edge agent ↔ bulut protokoli

Sana: 2026-08-25
Holat: v0 loyiha — Sprint 1 da real qurilmada sinaladi
Bog'liq: `02-arxitektura.md` §4-6

Bu hujjat agent va bulut o'rtasidagi shartnomani belgilaydi. Uning maqsadi — agent repo'sini shu hujjatdan boshlab yozish mumkin bo'lishi.

---

## 1. Asosiy cheklovlar

1. **Faqat outbound.** Agent HTTPS bo'yicha bulutga o'zi murojaat qiladi. Mijoz tarmog'ida internetdan kiradigan hech qanday port ochilmaydi.
2. **Qurilma paroli tarmoqdan chiqmaydi.** Bulut faqat "parol o'rnatildi/o'rnatilmadi" holatini biladi.
3. **Yuz rasmi bulutda saqlanmaydi.** Vazifa bilan bir marta uzatiladi, agent qurilmaga yozadi, bulut nusxani o'chiradi.
4. **At-least-once + dedup.** Hech narsa "aniq bir marta" yetkazilishiga tayanmaydi.
5. **Agent holatsiz emas.** Internet uzilganda voqealarni lokal SQLite'ga yozadi va ulanish tiklanganda yuboradi.

## 2. Autentifikatsiya

Ro'yxatdan o'tish (bir marta):

```
POST /api/agent/v1/enroll
{ "enroll_token": "...", "agent_version": "0.1.0", "hostname": "kassa-pc" }
→ { "agent_id": "agt_...", "agent_secret": "...", "branch_id": "brn_..." }
```

`enroll_token` bulut panelida filial uchun generatsiya qilinadi, 24 soat amal qiladi, bir marta ishlatiladi.

Keyingi barcha so'rovlarda:

```
X-Agent-Id: agt_...
X-Timestamp: 1756130000            # unix, ±300 soniya oynasi
X-Signature: hex(hmac_sha256(agent_secret, "{method}\n{path}\n{timestamp}\n{sha256(body)}"))
```

Bulut: `agent_id` topilmasa yoki imzo mos kelmasa → `401`. Timestamp oynadan tashqarida → `401` (replay himoyasi).

`agent_secret` 90 kunda aylantiriladi: bulut heartbeat javobida yangi sirni beradi, agent uni saqlaydi va keyingi so'rovdan boshlab ishlatadi.

> **Tuzatish (2026-08-25):** yo'llar `/agent/v1/...` dan `/api/agent/v1/...` ga
> o'zgartirildi. Bulut Laravel'da yozilgan va agent API'sini `api` prefiksi
> ostida beradi. Prefiks kosmetik emas — imzolanadigan kanonik satr so'rov
> yo'lini o'z ichiga oladi, ya'ni nomuvofiqlik redirect emas, umuman
> autentifikatsiyadan o'tolmaydigan 404 bo'ladi. Buni faqat agent va bulutni
> birinchi marta haqiqiy ulaganda topdik: ikkala tomonning testlari ham
> bir-birini mock qilgani uchun jim turgan edi.

## 3. Endpointlar

### 3.1. Heartbeat — har 30 soniyada

```
POST /api/agent/v1/heartbeat
{
  "agent_version": "0.1.0",
  "devices": [
    { "device_id": "dev_...", "status": "online",  "checked_at": "2026-08-25T14:00:00+05:00" },
    { "device_id": "dev_...", "status": "error", "error": "auth_failed" }
  ]
}
→ { "server_time": "...", "agent_upgrade": null, "secret_rotation": null }
```

Bulut 90 soniya heartbeat kelmasa agentni `offline` deb belgilaydi va HR ga Telegram signal yuboradi.

Qurilma holatlari: `online` | `offline` | `error`. `error` — qurilma javob beradi, lekin ishlay olmaydi (masalan parol noto'g'ri).

### 3.2. Vazifalarni olish — long-poll

```
GET /api/agent/v1/jobs?wait=30
→ { "jobs": [ { ... }, { ... } ] }
```

Bulut so'rovni 30 soniyagacha ushlab turadi. Vazifa paydo bo'lsa darhol qaytaradi, aks holda bo'sh ro'yxat. Agent javobni olgach darhol yangi so'rov yuboradi.

Bir martada ko'pi bilan 20 vazifa. Bulut qaytargan vazifalarni `dispatched` deb belgilaydi va 5 daqiqa davomida qayta bermaydi (visibility timeout); natija kelmasa vazifa yana navbatga qaytadi.

### 3.3. Natijani qaytarish

```
POST /api/agent/v1/jobs/{job_id}/result
{
  "idempotency_key": "sha256(...)",
  "status": "succeeded",              # succeeded | failed | unsupported
  "attempted_at": "...",
  "duration_ms": 412,
  "error": null                       # { "kind": "...", "message": "...", "retryable": false }
}
→ 200 { "accepted": true }
```

Bir xil `idempotency_key` bilan takroriy natija `200` qaytaradi, lekin holatni o'zgartirmaydi.

### 3.4. Voqealarni yuborish — to'plam bilan

```
POST /api/agent/v1/events
{
  "events": [
    {
      "dedup_key": "sha256(device_id|event_time|employee_no|event_type|serial_no)",
      "device_id": "dev_...",
      "event_time": "2026-08-25T08:59:41+05:00",
      "employee_no": "1042",
      "event_type": "access_granted",
      "credential": "face",
      "source": "webhook"             # webhook | backfill
    }
  ]
}
→ { "accepted": 118, "duplicates": 12 }
```

Bir so'rovda ko'pi bilan 500 voqea. `dedup_key` bulutda unique index — takror kelgani jimgina tashlanadi.

## 4. Vazifa turlari

| Tur | Yuk | Izoh |
|---|---|---|
| `person.upsert` | employee_no, ism, faollik | Birinchi bo'lib bajariladi |
| `person.delete` | employee_no | |
| `person.set_enabled` | employee_no, enabled | Obuna/ishdan bo'shash uchun |
| `card.upsert` | employee_no, card_no | `person.upsert` dan keyin |
| `face.upsert` | employee_no, image_base64 | Eng oxirida; rasm faqat shu vazifada bo'ladi |
| `device.probe` | — | Model, firmware, imkoniyat, sig'im |
| `device.configure_webhook` | agent_url | Qurilmaga lokal agent manzilini yozadi |

Agent bitta qurilma uchun vazifalarni **ketma-ket** bajaradi (terminal parallel so'rovlarda beqaror), turli qurilmalarni parallel.

## 5. Xatolar va qayta urinish

Agent xatoni SDK'ning exception ierarxiyasidan oladi (`HikvisionException::isRetryable()`):

| Xato | Retryable | Agent nima qiladi |
|---|---|---|
| `DeviceUnreachableException` | ha | Backoff: 5s, 15s, 60s, 300s; keyin `failed` |
| `DeviceBusyException` | ha | Xuddi shunday |
| `AuthenticationException` | yo'q | Darhol `failed`, qurilma `error` holatiga o'tadi, HR ga signal |
| Boshqa `HikvisionException` | yo'q | `failed`, xato matni bilan |

Bulut tomonida: `failed` vazifa ekranda sababi bilan ko'rinadi va qo'lda qayta urinish tugmasi bo'ladi. Avtomatik cheksiz qayta urinish yo'q — bu qurilmani bo'g'adi.

## 6. Voqealar oqimi — uch qatlam

1. **Real-time.** Qurilma → agentning lokal HTTP listeneri (`:9080`) → bufer → bulut. Kechikish ~1-3 soniya.
2. **Backfill.** Har 10 daqiqada har bir qurilma uchun oxirgi 30 daqiqa qayta o'qiladi:
   `EventService::between($now->modify('-30 minutes'), $now)` — SDK'dagi iterator. `source: "backfill"` bilan yuboriladi, dedup ortiqchasini tashlaydi.
3. **Kunlik audit.** Har kecha oxirgi 24 soat qayta o'qiladi va voqealar soni bulutdagi son bilan solishtiriladi. Farq bo'lsa — HR ga emas, **bizga** signal: bu mahsulot nosozligi.

Oynalarning ustma-ust tushishi (30 daqiqalik oyna har 10 daqiqada) ataylab: kechikkan yozuvlar va soat siljishi uchun zaxira.

## 7. Agentning lokal holati

SQLite, bitta fayl:
- `outbox_events` — bulutga yuborilmagan voqealar
- `job_results` — yuborilmagan natijalar
- `devices` — qurilma parollari (shifrlangan), oxirgi holat
- `config` — agent_id, secret, bulut manzili

Bufer chegarasi: 100 000 voqea yoki 7 kun. Chegaradan oshsa eng eskilari o'chiriladi va bu hodisa bulutga xabar qilinadi.

## 8. Hali hal qilinmagan savollar

Bular Sprint 1 da real qurilmada aniqlanadi:

1. `responseStatusStrg` ning aniq qiymatlari (`OK` / `MORE` / `NO MATCH`) — model va firmware bo'yicha farq qiladimi?
2. `AcsEventTotalNum` javobining aniq strukturasi (`04-paket-mustahkamlash.md` B2).
3. Qurilma sig'imi qaysi maydonda qaytadi va `subStatusCode` qiymatlari qanday.
4. Qurilma webhook'ni HTTP (HTTPS emas) bo'yicha lokal manzilga yubora oladimi va autentifikatsiya qo'llab-quvvatlaydimi.
5. Bir vaqtda nechta so'rovni ko'taradi — batch o'lchami shunga qarab belgilanadi.
