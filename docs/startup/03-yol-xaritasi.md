# Yo'l xaritasi — 6 sprint, pilotgacha

Sana: 2026-08-25
Resurs: 1 asoschi, haftasiga ~12 soat → sprint = 2 hafta ≈ **24 soat**
Prinsip: **har bir sprint oxirida ko'rsatsa bo'ladigan natija bo'lishi shart.** Skelet, interfeys va "keyin to'ldiramiz" — natija emas.

---

## 0. Vaqt taqsimoti qoidasi

Haftasiga 12 soat quyidagicha bo'linadi:

| Ulush | Nima | Soat |
|---|---|---|
| 75% | Qurish | 9 soat |
| 25% | Sotuv va mijoz bilan gaplashish | 3 soat |

**25% sotuv Sprint 0 dan keyin ham to'xtamaydi.** Yakka asoschining eng ko'p uchraydigan xatosi — kod yozishga berilib, 4 oy davomida hech kim bilan gaplashmaslik.

Kunlik amaliy taqsimot misoli: 3 ta kechqurun × 2 soat (qurish) + shanba 6 soat (3 qurish + 3 sotuv).

---

## Sprint 0 — Tasdiqlash va poydevor (2 hafta)

**Bu sprintda mahsulot kodi yozilmaydi.**

### Sotuv yo'nalishi (asosiy)
- [ ] 15 ta suhbat: HR direktor / moliya direktori / kompaniya egasi
- [ ] Suhbat skripti: hozir qanday qilasiz → oyiga necha soat ketadi → oxirgi marta tabel xato bo'lganida nima bo'ldi → hozir buning uchun nima to'layapsiz
- [ ] **Narx haqida so'ramaslik, narxni aytish va reaksiyani kuzatish**
- [ ] 3 ta integrator bilan uchrashuv (25% komissiya taklifi)
- [ ] 1 soatlik yurist konsultatsiyasi (`01-biznes-reja.md` §8 dagi 4 savol)

### Texnik yo'nalishi (ikkilamchi, ~8 soat) — ✅ bajarildi 2026-08-25
Faqat mavjud paketda, mahsulotga tegilmaydi:
- [x] `.github/workflows/ci.yml` — PHP 8.2/8.3/8.4 × Laravel 11/12/13 matritsasi
- [x] Laravel Pint + PHPStan (larastan) level 5, baseline'siz
- [x] Test qamrovi: 31 → 68 test; `HttpClient`, `HikvisionClient`, `DeviceManager`, `DigestAuthenticator`
- [x] Xavfsizlik: `composer audit` 17 ta ogohlantirishdan 0 ga tushirildi

Tafsilotlar: `04-paket-mustahkamlash.md` → A bo'limi.

### 🚦 Gate — davom etish sharti
- 8+ suhbatda muammo tasdiqlandi
- 3+ pilotga og'zaki rozilik
- 1+ o'rnatish haqini to'lashga rozilik
- 1+ integrator qiziqdi

**Gate o'tmasa:** kod yozilmaydi. Fitnes segmentiga o'tish yoki muammoni qayta aniqlash.

---

## Sprint 1 — Edge agent spike (2 hafta) — ✅ BAJARILDI 2026-08-25

**Eng katta texnik riskni birinchi bo'lib o'ldiramiz.** Agar agent ishlamasa, qolgan hamma narsa ma'nosiz.

- [x] Protokol spetsifikatsiyasi: `05-agent-protokoli.md`
- [x] SDK poydevori: `EventService::between()` (backfill) va xato tasnifi (retry siyosati) — `hikvision-isapi` v2.0.0-beta.1
- [x] `attendance-agent` repo: imzolash, LAN listener, payload parser, normalizer, SQLite outbox, enrolment, qurilma reyestri, heartbeat va backfill sikllari — 132 test
- [x] `attendance-cloud` repo: 5 ta endpoint (enroll, jobs, result, heartbeat, events), imzo tekshiruvi, dedup, tenancy — 51 test
- [x] Uchdan-uchgacha sinov (pastga qarang)
- [ ] Qurilmaga webhook manzilini yozish (`EventNotificationService`) — real terminal kerak
- [ ] Qurilmani tekshirish: model, imkoniyat, sig'im — real terminal kerak

### ✅ Demo — o'tkazildi

Agent va bulut bitta mashinada, haqiqiy HTTP orqali:

```
enroll → 401 (token ikkinchi marta) → device:add → callback POST
→ 200 terminalga → bulut bazasida access_events yozuvi (dev_1, 1042, face)
→ takroriy callback: hamon 1 yozuv (dedup)
→ imzosiz va soxta imzoli so'rovlar: 401
```

Yagona farq: yuz o'rniga qo'lda POST. Zanjirning qolgan hamma bo'g'ini haqiqiy.

### Demo nimani ko'rsatdi

Integratsiya ikkala tomonning testlari ko'rmagan xatoni topdi: agent `/agent/v1/...` ga murojaat qilardi, bulut esa `/api/agent/v1/...` beradi. Har bir tomon bir-birini mock qilgani uchun ichki jihatdan izchil edi. Imzo yo'lni qamragani uchun bu redirect emas, autentifikatsiyadan o'tolmaydigan 404 bo'lardi.

### 🚦 Gate
Agent ishladi. Arxitektura qayta ko'rib chiqilmaydi.

## Sprint 2 — Bulut skeleti va voqealar (2 hafta)

- [ ] `davomat-cloud` repo: Laravel 12 + Filament v4 + Postgres + Redis/Horizon
- [ ] Auth, `organizations`, `memberships`, rollar, tenant global scope
- [ ] Filament: filial, agent, qurilma ro'yxatlari; enroll token generatsiyasi
- [ ] `access_events` jadvali + dedup unique index
- [ ] Agent endpointlarini bulutga ko'chirish, HMAC imzo bilan himoyalash
- [ ] Voqealar ekrani: filtr (filial, qurilma, sana, xodim)
- [ ] Tenant izolyatsiyasi uchun feature testlar

### ✅ Demo
HR panelga kiradi, filial yaratadi, token oladi, agent ulanadi, qurilma ko'rinadi, kirish-chiqishlar jonli oqadi.

---

## Sprint 3 — Xodimlar va sinxronizatsiya (2 hafta)

**Karta birinchi, yuz keyin** — karta ancha oddiy va pilotni tezlashtiradi.

- [ ] `employees`, `credentials`, `sync_states`, `sync_jobs` jadvallari
- [ ] `employee_no` ketma-ketligi (tashkilot ichida global, qayta ishlatilmaydi)
- [ ] Filament: xodim CRUD, Excel'dan import
- [ ] Reconciler: `desired_hash != applied_hash` → vazifa yaratish
- [ ] Agent tomonida vazifa bajarish: person → card
- [ ] Idempotentlik kaliti, retry + backoff, `failed` holati va sababi ekranda
- [ ] Qurilma imkoniyatlari va sig'imini o'qish
- [ ] Voqeani xodimga bog'lash (`employee_no` orqali)

### ✅ Demo
HR 50 ta xodimni Excel'dan yuklaydi → hammasi terminalga tushadi → kartani bosgan odam ekranda ism bilan ko'rinadi.

---

## Sprint 4 — Davomat mexanizmi (2 hafta)

**Bu sprint mahsulotni "jurnal"dan "mahsulot"ga aylantiradi.**

- [ ] `work_schedules` (5/2, 6/1, suzuvchi), kechikish chegarasi, tanaffus
- [ ] `schedule_assignments` (xodim ↔ smena, davr bilan)
- [ ] `attendance_days` hisoblagichi: birinchi kirish, oxirgi chiqish, ish soati, kechikish, erta ketish, kelmagan
- [ ] Kech kelgan voqealar uchun kunni qayta hisoblash (backfill bilan bog'liq)
- [ ] Gap-filling: agent har 10 daqiqada oxirgi 30 daqiqani qayta o'qiydi
- [ ] Kunlik audit: voqealar soni farqi bo'lsa ichki signal
- [ ] Filament: kunlik tabel ekrani, oylik tabel ekrani
- [ ] Excel export

### ✅ Demo
HR "avgust oyi tabeli" tugmasini bosadi → tayyor Excel. Qo'lda ish nolga tushdi.

### 🚦 Gate
Bu nuqtada mahsulot **sotiladigan minimum**ga yetadi. Shu yerda pilot mijozga o'rnatish boshlanadi, keyingi sprintlarni kutmasdan.

---

## Sprint 5 — Telegram, signallar, birinchi o'rnatish (2 hafta)

- [ ] Telegram bot: tashkilotni ulash, chat bog'lash
- [ ] Direktorga kunlik xulosa (soat 10:00): kim keldi, kim kechikdi, kim yo'q
- [ ] HR ga signal: qurilma offline (2 daqiqa), sync xatosi, ommaviy kechikish
- [ ] Xodim roziligi qayd etish, voqealar uchun saqlash muddati sozlamasi
- [ ] Yuz yuklash oqimi (rasm → agent → qurilma → bulutdan o'chirish)
- [ ] **Birinchi pilot mijozga real o'rnatish**
- [ ] O'rnatish runbook'i (integrator ham bajara olishi kerak)

### ✅ Demo
Real mijozda ishlaydigan tizim + direktorning telefonida har kuni ertalab xulosa.

---

## Sprint 6 — Pilotni mustahkamlash (2 hafta)

- [ ] Pilot davomida chiqqan xatolarni tuzatish (bu ro'yxat pilotdan keladi, oldindan yozib bo'lmaydi)
- [ ] Tunlik drift tekshiruvi va inventarizatsiya
- [ ] Agentning o'z-o'zini yangilashi
- [ ] Sentry, uptime monitoring, kunlik backup
- [ ] 1C export formati (buxgalter bilan birga aniqlanadi)
- [ ] Onboarding hujjati + video

### 🚦 Gate — pilotdan to'lovga
- Pilot mijoz 1 oy uzluksiz ishlatdi
- Tabel qo'lda tekshirilganda **to'g'ri chiqdi**
- Mijoz oylik to'lovga rozi
- Qo'llab-quvvatlash oyiga ≤ 2 soat

---

## Umumiy vaqt jadvali

| Bosqich | Sprint | Taxminiy sana |
|---|---|---|
| Tasdiqlash | 0 | 1-2 hafta |
| Agent ishlaydi | 1 | 3-4 hafta |
| Bulut + voqealar | 2 | 5-6 hafta |
| Sync ishlaydi | 3 | 7-8 hafta |
| **Sotiladigan MVP** | 4 | **9-10 hafta** |
| Birinchi pilot | 5 | 11-12 hafta |
| To'lovchi mijoz | 6 | 13-16 hafta |

**Halol baho:** haftasiga 12 soatda bu jadval **optimistik**. Realistik kutish — pilotgacha 4 oy, birinchi to'lovgacha 5-6 oy. Kechikish bo'lsa, **scope qisqartiriladi, muddat cho'zilmaydi.**

## Birinchi qurbon bo'ladigan narsalar (kechikish bo'lsa)

Ushbu tartibda tashlanadi:
1. Yuz yuklash (faqat karta bilan pilot qilinadi)
2. 1C export (Excel yetarli)
3. Telegram xodim tabeli (faqat direktor xulosasi qoladi)
4. Excel'dan import (qo'lda kiritish)
5. Agentning o'z-o'zini yangilashi (qo'lda yangilanadi)

## Hech qachon tashlanmaydigan narsalar

1. Gap-filling — tabelning to'g'riligi shunga bog'liq
2. Tenant izolyatsiyasi
3. Dedup unique index
4. Qurilma parolining bulutga chiqmasligi
5. Backup

---

## Keyingi qadam

`04-paket-mustahkamlash.md` — Sprint 0 ning texnik qismi, ya'ni **hozir shu repoda** boshlanadigan ish.
