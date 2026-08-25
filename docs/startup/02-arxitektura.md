# Arxitektura

Sana: 2026-08-25
Holat: Qaror qabul qilingan
Bog'liq: `00-eski-rejaning-tahlili.md`, `01-biznes-reja.md`

---

## 1. Asosiy cheklov: NAT

Hikvision terminal mijoz LAN'ida turadi. Bulutdagi server unga **ulana olmaydi**. Bu barcha qolgan qarorlarni belgilaydi.

Ko'rib chiqilgan variantlar:

| Variant | Nima uchun rad etildi |
|---|---|
| Port forwarding / static IP | Terminalni internetga ochish — jiddiy xavfsizlik riski (Hikvision terminallarida ma'lum CVE'lar bor). Har bir mijozda alohida tarmoq ishi. Mijozning IT bo'limi rad etadi. |
| VPN (har mijozga tunnel) | O'rnatish murakkab, sizning tarmoq infratuzilmangizni talab qiladi, nosozlikni tuzatish og'ir. |
| Qurilma to'g'ridan-to'g'ri bulutga webhook | Faqat bir yo'nalishli — voqealarni oladi, lekin xodim qo'shish/yuz yuklash ishlamaydi. Yarim yechim. |
| **Edge agent** | **Tanlandi** |

## 2. Umumiy sxema

```
   Mijoz LAN'i                          Internet                  Bulut (UZ hosting)
 ┌────────────────────────────┐                            ┌──────────────────────────┐
 │  Hikvision terminal(lar)   │                            │  Laravel 12 + Filament   │
 │  192.168.1.x               │                            │  PostgreSQL + Redis      │
 │        ▲         │         │                            │  Horizon (queue)         │
 │        │ ISAPI   │ webhook │                            │                          │
 │        │         ▼         │                            │                          │
 │  ┌──────────────────────┐  │   HTTPS (faqat outbound)   │  ┌────────────────────┐  │
 │  │   Edge Agent         │──┼───────────────────────────►│  │ /agent/v1/...      │  │
 │  │   (Docker, PHP)      │  │   long-poll + POST         │  │                    │  │
 │  │   qurilma parollari  │◄─┼────────────────────────────┼──│ jobs, heartbeat,   │  │
 │  │   shu yerda qoladi   │  │                            │  │ events             │  │
 │  └──────────────────────┘  │                            │  └────────────────────┘  │
 └────────────────────────────┘                            └──────────────────────────┘
                                                                       │
                                                            Telegram bot, Excel/1C export
```

**Muhim nuqta:** qurilma webhook'ni **internetga emas, lokal agentga** yuboradi (`http://192.168.1.50:9080/events`). Ya'ni terminalga internet kerak emas — bu xavfsizlik va o'rnatish jihatidan katta yutuq.

## 3. Uchta repo

| Repo | Litsenziya | Rol |
|---|---|---|
| `shaykhnazar/hikvision-isapi` (mavjud) | MIT, ochiq | Toza SDK. Biznes mantiq yo'q. Marketing/lead kanali. |
| `davomat-cloud` (yangi) | Yopiq | Laravel 12 + Filament. Multi-tenant SaaS. |
| `davomat-agent` (yangi) | Yopiq | Mijoz LAN'idagi daemon. SDK'ni ishlatadi. |

Agent va cloud ikkalasi ham `hikvision-isapi` ni composer orqali oladi. Ya'ni SDK'ni yaxshilash — mahsulotni yaxshilash. Bu ochiq kodni saqlashning tijorat asosi.

**Paketga qo'shiladigan narsalar (mahsulot uchun kerak, lekin SDK'ga ham foydali):**
- `DeviceDriver` kontrakti (kelajakda ZKTeco uchun)
- Voqealarni vaqt oralig'i bo'yicha qidirish (gap-filling uchun)
- Qurilma sig'imi va imkoniyatlarini aniqlash (capability probing)
- CI, phpstan, pint, test qamrovi

**Paketga qo'shilmaydigan narsalar:** tenant, migratsiya, controller, davomat mantig'i, preset.

## 4. Edge agent

### 4.1. Texnologiya

PHP, Docker image sifatida tarqatiladi. Sabab: yakka asoschi uchun bitta til. Go binary tozaroq bo'lardi, lekin ikkinchi tilni qo'llab-quvvatlash haftasiga 12 soatga sig'maydi.

**Tuzatish (2026-08-25, Sprint 1):** dastlab bu yerda Laravel Zero yozilgan edi. Amalda agent uchun framework kerak emas — u bitta siklda ishlaydi: soketdan so'rov qabul qiladi, SQLite'ga yozadi, HTTP so'rov yuboradi. Laravel Zero mijoz kompyuterida qo'llab-quvvatlanadigan yuzlab fayl qo'shadi va hech qanday foyda bermaydi. Shuning uchun agent **sof PHP** da yozildi: bog'liqliklar faqat Guzzle va shu SDK.

O'rnatish: mijozning mavjud Windows kompyuteriga (Docker Desktop) yoki mini-PC ga. Bitta buyruq:
```
docker run -d --restart=always -e ENROLL_TOKEN=xxxx davomat/agent
```

### 4.2. Ro'yxatdan o'tish (enrollment)

1. Bulutda filial yaratiladi → bir martalik `ENROLL_TOKEN` beriladi (24 soat amal qiladi).
2. Agent ishga tushadi, token bilan `POST /agent/v1/enroll` chaqiradi.
3. Bulut `agent_id` + uzoq muddatli `agent_secret` qaytaradi, token kuydiriladi.
4. Agent lokal tarmoqni skanerlaydi (yoki qo'lda IP kiritiladi), qurilmalarni topadi.
5. HR bulut panelida qurilma parolini kiritadi → **parol agentga bir marta yuboriladi va agentda shifrlangan holda saqlanadi; bulutda saqlanmaydi.**

### 4.3. Protokol (faqat outbound HTTPS)

| Endpoint | Yo'nalish | Tavsif |
|---|---|---|
| `POST /agent/v1/enroll` | agent → bulut | Ro'yxatdan o'tish |
| `GET /agent/v1/jobs?wait=30` | agent → bulut | Long-poll, 30 soniya ushlab turadi, vazifalar ro'yxatini qaytaradi |
| `POST /agent/v1/jobs/{id}/result` | agent → bulut | Natija + `idempotency_key` |
| `POST /agent/v1/heartbeat` | agent → bulut | Har 30 soniyada: agent versiyasi, qurilma holatlari |
| `POST /agent/v1/events` | agent → bulut | Voqealar to'plami (batch), dedup kaliti bilan |

Autentifikatsiya: har bir so'rovda `agent_secret` bilan HMAC imzo + timestamp (replay himoyasi).

Bulut agentni **90 soniya** heartbeat kelmasa `offline` deb belgilaydi va HR ga Telegram signal yuboradi.

### 4.4. Agentning vazifalari

- Vazifalarni bajarish: xodim qo'shish/o'zgartirish/o'chirish, karta yozish, yuz yuklash, faollashtirish/bloklash
- Qurilmadan lokal webhook qabul qilish (`:9080`), buferga yozish, bulutga batch yuborish
- Gap-filling: har 10 daqiqada har bir qurilmadan oxirgi 30 daqiqalik voqealarni qayta o'qish
- Har 60 soniyada qurilma holatini tekshirish
- Internet uzilganda voqealarni lokal SQLite'ga yozish va ulanish tiklanganda yuborish
- O'z-o'zini yangilash (bulut yangi versiyani e'lon qiladi)

## 5. Sinxronizatsiya modeli

### 5.1. Desired state va applied state

Bulutda har bir (xodim, qurilma) jufti uchun:

```
sync_states
  employee_id, device_id
  desired_hash      -- xodim ma'lumoti + kartasi + yuzi + faollik holatidan hisoblangan
  applied_hash      -- qurilmada oxirgi muvaffaqiyatli qo'llangan hash
  status            -- pending | in_progress | applied | failed | unsupported
  attempts, last_error, last_attempt_at
```

`desired_hash != applied_hash` → vazifa yaratiladi. Bu model retry, qisman muvaffaqiyat va drift'ni tabiiy hal qiladi.

### 5.2. Employee raqamlash

`employee_no` — **tashkilot ichida global**, ketma-ketlikdan olinadi, hech qachon qayta ishlatilmaydi (xodim ishdan ketsa ham). Barcha qurilmalarda bir xil. Bu voqealarni odamga bog'lashning yagona ishonchli usuli.

### 5.3. Bajarilish tartibi va idempotentlik

Har bir vazifada `idempotency_key = sha256(employee_id, device_id, desired_hash, operation)`. Agent natijani shu kalit bilan qaytaradi; takroriy natija e'tiborsiz qoldiriladi.

Qurilmada tartib: **person → card → face**. Yuz yuklashdan oldin odam mavjud bo'lishi shart.

### 5.4. Drift va sig'im

- **Har kecha** har bir qurilmada to'liq inventarizatsiya: qurilmadagi odamlar ro'yxati o'qiladi va bulutdagi desired state bilan solishtiriladi. Farqlar `drift` sifatida qayd etiladi va tuzatiladi.
- Har bir qurilma modeli uchun **sig'im limiti** saqlanadi (odam / karta / yuz). Limitning 90% iga yetganda HR ga ogohlantirish. Limitdan oshiq vazifa yaratilmaydi — `unsupported` holati bilan to'xtatiladi.
- Qurilma imkoniyatlari (`Capabilities`) ro'yxatdan o'tishda o'qiladi va saqlanadi. Yuzni qo'llab-quvvatlamaydigan qurilmaga yuz vazifasi yuborilmaydi.

## 6. Voqealar oqimi va gap-filling

Bu MVP'ning eng muhim ishonchlilik qismi. Davomat tabelining to'g'riligi shunga bog'liq.

**Uch qatlamli kafolat:**

1. **Real-time:** qurilma → agent (lokal webhook) → bulut. Tez, lekin ishonchsiz.
2. **Backfill:** agent har 10 daqiqada `AcsEvent` search bilan oxirgi 30 daqiqani qayta o'qiydi. Webhook yo'qolgan voqealar shu yerda tutiladi.
3. **Kunlik audit:** har kecha oxirgi 24 soat qayta o'qiladi va voqealar soni solishtiriladi. Farq bo'lsa — HR ga emas, **bizga** signal (mahsulot nosozligi).

**Dedup kaliti:** `sha256(device_id, event_time, employee_no, event_type, serial_no)`. Bulutda unique index. Ya'ni bir xil voqea uch qatlamdan kelsa ham bir marta yoziladi.

**Prinsip:** at-least-once yetkazish + dedup > exactly-once ga urinish.

## 7. Ma'lumotlar modeli (MVP — 12 jadval)

```
organizations        -- tenant
users                -- kirish
memberships          -- user ↔ organization + rol (owner | hr | viewer)
branches             -- filial
agents               -- edge agent, holati, versiyasi, oxirgi heartbeat
devices              -- terminal: agent_id, lokal IP, model, imkoniyatlar, sig'im, holat
employees            -- xodim: employee_no, F.I.Sh, bo'lim, lavozim, holat, rozilik
credentials          -- karta raqami / yuz mavjudligi (yuz rasmi bulutda saqlanmaydi)
sync_states          -- (employee, device) desired vs applied
sync_jobs            -- agentga yuboriladigan vazifalar navbati
access_events        -- normalizatsiya qilingan kirish-chiqish oqimi
work_schedules       -- smena: ish kunlari, boshlanish/tugash, kechikish chegarasi
schedule_assignments -- xodim ↔ smena (davr bilan)
attendance_days      -- hisoblangan kunlik natija (kirish, chiqish, kechikish, ish soati)
audit_logs           -- kim nima o'zgartirdi
```

Eski spec'dagi `doors`, `access_groups`, `access_group_people`, `access_group_doors`, `access_schedules`, `automation_rules`, `automation_executions`, `segment_presets`, `person_attributes` — **MVP'da yo'q**.

`attendance_days` — hisoblangan (derived) jadval, `access_events` dan qayta yaratilishi mumkin. Bu muhim: voqea kech kelsa (backfill), kun qayta hisoblanadi.

**Tenant izolyatsiyasi:** har bir jadvalda `organization_id`, Eloquent global scope + har bir job'da tenant konteksti. Testda majburiy tekshiriladi.

## 8. Bulut stack

| Qatlam | Tanlov | Sabab |
|---|---|---|
| Framework | Laravel 12 | Ma'lum, paket bilan bir xil ekotizim |
| Admin UI | Filament v4 | Yakka asoschi uchun 2-3 oy tejaydi |
| DB | PostgreSQL | JSON maydonlar, partitioning (voqealar uchun) |
| Queue | Redis + Horizon | Ko'rinuvchan navbat |
| Hosting | O'zbekistondagi VPS | Lokalizatsiya talabi (`01-biznes-reja.md` §8) |
| Monitoring | Sentry + oddiy uptime | Minimal, lekin bor |
| Backup | Kunlik pg_dump + tashqi saqlash | Mijoz ma'lumoti — muzokara mavzusi emas |

## 9. Xavfsizlik qarorlari

1. Qurilma parollari **bulutda saqlanmaydi** — agentda, shifrlangan holda.
2. Yuz rasmlari **bulutda saqlanmaydi** — HR yuklaydi → agentga uzatiladi → qurilmaga yoziladi → bulutdan o'chiriladi. Bulutda faqat `face_enrolled: true/false`.
3. Agent faqat **outbound** ulanish ochadi. Mijoz tarmog'ida hech qanday port ochilmaydi (lokal webhook portidan tashqari, u LAN ichida).
4. Biometrik ma'lumot **hech qachon logga yozilmaydi** — markazlashtirilgan redaction helper.
5. Har bir sezgir amal (xodim bloklash, parol o'zgartirish, export) `audit_logs` ga yoziladi.
6. Rollar: `owner` (hammasi), `hr` (xodimlar, tabel), `viewer` (faqat ko'rish).
7. Agent secret aylantiriladi (rotation) — 90 kunda.

## 10. Nima qilinmaydi (aniq chegara)

MVP'da **yo'q**, va bu ataylab:
- Eshiklarni masofadan boshqarish
- Access group / jadval bo'yicha kirish huquqi
- Mobil ilova (Telegram bot uni almashtiradi)
- To'lov integratsiyasi (birinchi mijozlarga qo'lda hisob-faktura)
- Video / stream bilan ishlash
- Boshqa vendor drayverlari (interfeys bor, implementatsiya yo'q)
- Preset / ko'p segment
