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

## Sprint 2 — Bulut skeleti va voqealar (2 hafta) — ✅ BAJARILDI 2026-08-26

- [x] `attendance-cloud` repo: **Laravel 13 + Filament v5** + SQLite (test) / Postgres (prod)
- [x] Auth, `organizations`, `memberships`, rollar, tenant global scope
- [x] Filament: filial, agent, qurilma ro'yxatlari; enroll token generatsiyasi
- [x] `access_events` jadvali + dedup unique index
- [x] Agent endpointlari, HMAC imzo bilan himoyalangan
- [x] Voqealar ekrani: filtr (filial, terminal, tur, xodim, sana)
- [x] Tenant izolyatsiyasi uchun feature testlar
- [ ] Redis / Horizon — hali kerak emas, navbat `database` drayverida

### Rejadan farqlar

| Reja | Amalda | Sabab |
|---|---|---|
| `davomat-cloud` | `attendance-cloud` | Repo Sprint 1 da shu nom bilan yaratilgan |
| Laravel 12 + Filament v4 | Laravel 13 + Filament v5 | Ikkalasi ham chiqib bo'lgan; eski versiyani tanlash qarzdan boshqa narsa emas |
| Redis / Horizon | Yo'q | Hozircha fon vazifalari yo'q. Kerak bo'lganda qo'shiladi |

### Tenancy qanday ishlaydi

Har bir kirish nuqtasi so'rovga **bitta tashkilotni** qo'yadi — agent HMAC'idan yoki
tizimga kirgan foydalanuvchi a'zoligidan — va `organization_id` ustuni bor har bir
model global scope bilan cheklanadi. Kontrollerlar `where` ni takrorlamaydi, ya'ni
uni unutish boshqa mijoz ma'lumotini ochib yubormaydi. Chegaradan chiqish mumkin,
lekin yozib qo'yish shart: `Model::acrossOrganizations()`, u atigi ikki joyda bor —
agentni topish va enrolment, ikkalasi ham tashkilotni bilishdan **oldin** ishlaydi.

### ✅ Demo — o'tkazildi

Haqiqiy agent jarayoni, haqiqiy HTTP, bitta mashinada:

```
enroll                        → agt_v9kh8...
enroll (o'sha token qayta)    → 401 invalid_token
heartbeat                     → bulutda dev_kirish paydo bo'ldi (branch=1, org=1)
multipart callback → agent    → 1 voqea: emp=1042, credential=face, source=webhook
o'sha callback qayta          → hamon 1 voqea (dedup)
GET /admin/access-events      → 302 → /admin/login
```

Panelga brauzerdan kirish jonli o'tkazilmadi; u to'liq middleware zanjiri orqali
ishlaydigan HTTP testlari bilan qoplangan.

### Demo nimani ko'rsatdi

**Uchta xato, uchalasi ham faqat haqiqiy ishga tushirganda chiqdi.**

1. **Qurilma bulutda o'z-o'zidan paydo bo'lmasdi.** Panel terminallarni ko'rsatardi,
   lekin ularni hech nima yaratmasdi — ro'yxatni faqat qo'lda to'ldirish mumkin edi.
   Endi heartbeat notanish terminalni ro'yxatdan o'tkazadi. Bu tilak ro'yxati bilan
   LAN'da haqiqatan turgan narsa o'rtasidagi farq.

2. **`devices.device_id` butun o'rnatma bo'yicha unique edi.** Bu dedup kalitidan
   farqli — bu yerda to'qnashuv kutilmagan hol emas, **kutiladigan** hol: id ni
   agentni o'rnatgan odam yozadi va ikkinchi mijoz ham `dev_kirish` deb nomlaydi.
   Global unique degani o'sha mijozning terminali umuman ro'yxatdan o'tolmasligi.

3. **Livewire yangilanishlari panel middleware'idan o'tmaydi.** Livewire'ning update
   endpointi faqat `web` guruhini olib yuradi, ya'ni saralash, filtr yoki sahifa
   almashtirish panel zanjirini qayta ishga tushirmaydi — agar u `isPersistent`
   deb ro'yxatdan o'tkazilmagan bo'lsa. Bu sizishning eng yomon shakli: birinchi
   sahifa to'g'ri chiqadi, kimdir ustun sarlavhasini bosgunicha.

Uchinchisini sinash uchun ikki marta xato qildim va ikkalasini ham tuzatdim:
`Livewire::test()` uni umuman ushlay olmaydi (u middleware o'chirilgan sintetik
endpoint orqali render qiladi), va haqiqiy so'rov bilan yozilgan test ham avval
noto'g'ri sababdan o'tdi — test bitta konteynerni `GET` va `POST` orasida
qayta ishlatgani uchun ijaraga olingan tashkilot sizib o'tgan edi.

### 🚦 Gate
Bulut skeleti tayyor. Ekranlar bor, tenant chegarasi model qatlamida majburlangan.

---

## Sprint 3 — Xodimlar va sinxronizatsiya (2 hafta) — ✅ BAJARILDI 2026-08-26

**Karta birinchi, yuz keyin** — karta ancha oddiy va pilotni tezlashtiradi.

- [x] `employees`, `credentials`, `sync_states`, `sync_jobs` jadvallari
- [x] `employee_no` ketma-ketligi (tashkilot ichida global, qayta ishlatilmaydi)
- [x] Filament: xodim CRUD, Excel/CSV dan import
- [x] Reconciler: `desired_hash != applied_hash` → vazifa yaratish
- [x] Agent tomonida vazifa bajarish: person → card
- [x] Idempotentlik kaliti, retry, `failed` holati va sababi ekranda
- [x] Voqeani xodimga bog'lash (`employee_no` orqali)
- [ ] Qurilma imkoniyatlari va sig'imini o'qish — **real terminal kerak**

### Rejadan farqlar

| Reja | Amalda | Sabab |
|---|---|---|
| `person.upsert` + `card.upsert` alohida vazifalar | Bitta `employee.sync` | `sync_states` da bitta `applied_hash` bor. Bo'lingan vazifalar qisman muvaffaqiyat beradi — odam qo'llandi, karta yo'q — buni bitta hash ifodalay olmaydi. Boshqa joyda kuzatish esa aynan shu model qochadigan "niyatlar navbati"ni qaytadan yaratardi |
| Agentda backoff: 5s, 15s, 60s, 300s | Bulutning visibility timeout'i | Agent bir oqimli. Tick ichida besh daqiqa uxlash uni terminal callback'larini qabul qilishdan to'xtatadi, va karta yozuvini qayta urinish uchun haqiqiy o'tishni yo'qotish — noto'g'ri almashuv. Qayta uriniladigan xato umuman xabar qilinmaydi; bulut vazifani qaytadan beradi |

### Xodim raqami — nega bu shunchalik muhim

`employee_no` — o'tishni odamga bog'laydigan **yagona** narsa, terminal boshqa hech nima yubormaydi. Shuning uchun hisoblagich faqat oldinga yuradi: xodimni o'chirish raqamni qaytarmaydi, muvaffaqiyatsiz import ham. Ikki marta berilgan raqam avvalgi egasining tarixini jimgina qayta yozadi — o'tgan mart oyidagi davomati boshqa odamniki bo'lib qoladi.

Voqealar xodimga `employee_no` orqali bog'lanadi, ingest paytida olingan foreign key orqali emas. Voqealar odam bulutda paydo bo'lishidan **oldin** kelishi odatiy hol; ingest paytidagi kalit o'sha qatorlarni abadiy nomsiz qoldirardi. Raqam orqali moslashtirish esa xodimni ertaga yaratsangiz, u allaqachon qilgan har bir o'tishni nomlaydi.

### ✅ Demo — o'tkazildi

Haqiqiy agent jarayoni, haqiqiy bulut, digest autentifikatsiyasini talab qiladigan o'rindosh terminal:

```
CSV dan 3 xodim import           → 3 xodim, 2 tasida karta
agent enroll, terminal paydo     → dev_kirish, online, qo'lda qadamsiz
attendance:reconcile             → 3 vazifa navbatga
agent oldi va qo'lladi           → digest challenge → autentifikatsiyalangan yozuv → succeeded
                                   3 ta sync state ham applied va sinxron
xodim nomini o'zgartirish        → 1 vazifa, qo'llandi, qayta sinxron
qurilma paroli noto'g'ri         → 1 urinishda failed, kind=authentication, qayta urinilmadi
                                   qolgan ikkitasi applied bo'lib qoldi
parol tuzatildi, retry bosildi   → tiklangan vazifa muvaffaqiyatli, hammasi sinxron
```

Terminal o'rindosh — digest talab qiladi va nima so'ralganini yozib boradi. Ya'ni bu zanjirni **simgacha** isbotlaydi, jumladan `DigestAuthenticator` haqiqatan challenge qiladigan serverda ishlashini (buni hech qanday test qoplamagan edi). Hikvision proshivkasi bu payloadlarni qabul qiladimi — buni isbotlamaydi. U real qurilmagacha tasdiqlanmagan bo'lib qoladi.

### Demo nimani ko'rsatdi

Ekranda operatorga quyidagi matn chiqdi:

```
Client error: `PUT http://127.0.0.1:8899/ISAPI/AccessControl/UserInfo/SetUp?format=json`
resulted in a `401 Unauthorized` response
```

Sinxronizatsiya ekrani "men bu odamni bir soat oldin qo'shdim — nega kira olmayapti?" degan savolga javob berish uchun bor. Bu matn hech qanday javob bermaydi, ustiga-ustak terminalning LAN manzilini HR ekraniga chiqarib qo'yadi. Endi xato turi bo'yicha tarjima qilinadi: "Terminal parolni qabul qilmadi. Qurilma parolini tekshiring."

### Yozish jarayonida topilgan uchta nuqson

1. **Muvaffaqiyatsiz vazifa juftlikni abadiy to'sib qo'yardi.** Idempotentlik kaliti unique, va "shu kalitli vazifa bor bo'lsa o'tkazib yubor" degani — xohlangan holat o'zgarib keyin qaytganda (odam bo'shatilib qayta tiklanganda, karta bekor qilinib qaytadan berilganda) ish umuman navbatga tushmasdi.
2. **Natijadagi dublikat tekshiruvi teskari ishlardi.** Kalit endi navbatga qo'yishda beriladi, ya'ni uni taqqoslash **birinchi** natijani dublikat deb hisoblab, hech qanday natijani yozmasdi.
3. **Import birovning kartasini jimgina ko'chirib olardi.** Forma buni validatsiya bilan rad etardi, importda esa bunday qoida yo'q edi — ro'yxatda birovning kartasi bo'lsa, o'sha odam eshikni ocholmay qolardi, import esa "muvaffaqiyatli" deb hisobot berardi.

Agent tomonida PHPStan to'rtinchisini topdi: "qurilma ro'yxatda yo'q" uchun `catch` o'lik edi, chunki shartnomada bu xato e'lon qilinmagan. Vazifa `unsupported` o'rniga ushlanmagan xato bo'lib chiqardi.

### 🚦 Gate
Zanjir uchdan-uchgacha ishlaydi. Sinxronizatsiya modeli o'zgartirilmaydi.

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
