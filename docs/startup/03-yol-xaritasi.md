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

## Sprint 4 — Davomat mexanizmi (2 hafta) — ✅ BAJARILDI 2026-08-26

**Bu sprint mahsulotni "jurnal"dan "mahsulot"ga aylantirdi.**

- [x] `work_schedules` (5/2, 6/1, suzuvchi), kechikish chegarasi, tanaffus
- [x] `schedule_assignments` (xodim ↔ smena, davr bilan)
- [x] `attendance_days` hisoblagichi: birinchi kirish, oxirgi chiqish, ish soati, kechikish, erta ketish, kelmagan
- [x] Kech kelgan voqealar uchun kunni qayta hisoblash (backfill bilan bog'liq)
- [x] Gap-filling: agent har 10 daqiqada oxirgi 30 daqiqani qayta o'qiydi *(Sprint 1 da bajarilgan)*
- [x] Kunlik audit: voqealar soni farqi bo'lsa ichki signal
- [x] Filament: kunlik tabel ekrani, oylik tabel ekrani
- [x] Excel export

PR: cloud #9, #10, #11, #12; agent #2. Bulut testlari 171 → **274**, agent 145 → **159**.

### Rejadan farqlar

**Smena kunlari — ISO hafta kunlari ro'yxati, nomlangan naqsh emas.** 5/2 va 6/1
keng tarqalgan, lekin novvoyxona seshanbadan yakshanbagacha ishlaydi va nomlangan
enum'da bunga javob yo'q.

**Kecha smenasi — bitta kun, ikkita yarim kun emas.** 20:00–04:00 smenasining
o'tishlari ikki kalendar sanaga tushadi. Oyna kalendar emas, smenaning o'zi
atrofida quriladi, ikki tomondan to'rt soat zaxira bilan — erta kelish uchun
yetarli, ketma-ket ikki tun bir-biriga tegmaydigan darajada tor.

**Tanaffus har doim ayiriladi, hatto qisqa kundan ham.** Bu qo'pol va ataylab:
har qanday chegara ("faqat olti soatdan uzun kunlarda ayirilsin") — siyosat
qarori, va uni shu yerda o'ylab topish har bir oyliqqa taxminni singdirgan
bo'lardi. Pilot buni noto'g'ri deb topsa, yechim — smenaga minimal davomiylik
maydoni qo'shish, yashirin konstanta emas.

**Kunlik audit dedup kalitlarini sanaydi, xom yozuvlarni emas.** Bulut har
kalitga bitta qator saqlaydi, shuning uchun kalit ajrata olmaydigan ikki yozuv
baribir bitta qator bo'lardi. Xom soni bilan solishtirish hech qachon
yopilmaydigan farqni ko'rsatgan bo'lardi — va nolga tushmaydigan raqamni odamlar
o'qishni to'xtatadi. Agent ikkalasini ham yuboradi.

**O'qib bo'lmagan terminal — nol emas, alohida holat.** Nol ekranda voqealarning
butunlay yo'qolgani kabi o'qiladi, ya'ni haqiqatning teskarisi. Aynan shunday
yolg'on signal operatorni ustunni e'tiborsiz qoldirishga o'rgatadi.

### Nima uchun ikkita tabel ekrani

Ikki xil odam ikki xil savol beradi. **Kunlik tabel** — tushlikkacha kim
kelmaganini bilmoqchi bo'lgan HR uchun; nishoni faqat bugungi kelmaganlarni
sanaydi. **Oylik tabel** — yon tomonda odamlar, tepada kunlar: bu buxgalterning
stolida allaqachon turgan shakl. Normallashtirilgan eksport berib, "o'zingiz
pivot qiling" deyish qo'l mehnatini olib tashlamaydi, faqat ko'chiradi.

Katakchalar soat ko'rsatadi, daqiqa emas: `8:07`, `487` emas. Xom daqiqa sonini
hech kim tekshirmaydi; soat va daqiqani kimdir noto'g'ri ekanini payqaydi.
`K` — kelmadi, `D` — dam olish, chunki nol bu ikkisini ajrata olmaydi, ular esa
oyliqda qarama-qarshi ma'noga ega.

### ✅ Demo — o'tkazildi

Ikkita jonli ishga tushirish, mock emas — haqiqiy jarayonlar:

1. **Tabel zanjiri.** Voqealardan `attendance_days` hisoblandi, ekranda
   ko'rsatildi, keyin haqiqiy `OpenSpout` o'quvchisi bilan qayta o'qib
   tekshirildi — hech kim ochmagan fayl ochilmasligi mumkin bo'lgan fayl.
2. **Audit zanjiri.** Haqiqiy agent jarayoni xizmat ko'rsatayotgan bulutga
   ro'yxatdan o'tdi, terminalini heartbeat orqali qayd etdi, to'rtta alohida
   o'tishdan ikkitasini yetkazdi. Audit `raw=5 distinct=4 cloud=2 missing=2
   collapsed=1` yozdi — birlashgan juftlik farqdan to'g'ri chetda qoldi.
   Imzosiz hisobot jonli endpoint'dan 401 oldi.

### Yozish jarayonida topilgan nuqsonlar

Barchasi testlarda emas, ishlatib ko'rilganda chiqdi:

1. **Kecha smenasi nol soat qaytardi.** Voqea so'rovi oxirgi kun yarim tunida
   to'xtardi va aynan smenani yakunlovchi ertalabki o'tishni kesib tashlardi.
2. **`updateOrCreate` hech qachon mavjud qatorni topmadi.** Laravel'ning oddiy
   `date` cast'i `date` ustuniga `2026-08-24 00:00:00` yozardi, shuning uchun har
   o'tish unique indeksga urilardi.
3. **`runRecent` PHP soatini o'qirdi, framework'nikini emas** — `Carbon::setTestNow`
   ta'sir qilmasdi, ya'ni test o'tishi mumkin edi, haqiqiy kod esa boshqa kunni
   o'lchardi.
4. **`routes/console.php` dagi `date('n')`** jadval e'lon qilinganda bir marta,
   server vaqt mintaqasida hisoblanardi — tungi oylik qayta hisoblash noto'g'ri
   oyga siljigan bo'lardi.
5. **`TimesheetExporter` da `static` qator hisoblagichi** — bitta jarayonda ikkita
   eksport qilinsa, ikkinchi varaq 4-raqamdan boshlanardi.

### 🚦 Gate — o'tildi
Bu nuqtada mahsulot **sotiladigan minimum**ga yetdi. Shu yerda pilot mijozga
o'rnatish boshlanadi, keyingi sprintlarni kutmasdan.

**Lekin Sprint 0 sotuv gate'i hamon nolda** — 15 intervyu, 3 pilot, 1 integrator
kelishuvi. Texnik tomon sotuvdan oldinda ketmoqda, va bu xavf: hech kim
so'ramagan narsani mukammal qilish eng qimmat xato turi.

---

## Sprint 5 — Telegram, signallar, birinchi o'rnatish (2 hafta) — ⚠️ KOD QISMI BAJARILDI 2026-08-26

- [x] Telegram bot: tashkilotni ulash, chat bog'lash
- [x] Direktorga kunlik xulosa (har tashkilot o'z vaqt mintaqasida): kim keldi, kim kechikdi, kim yo'q
- [x] HR ga signal: qurilma offline (2 daqiqa), sync xatosi, ommaviy kechikish
- [x] Xodim roziligi qayd etish, voqealar uchun saqlash muddati sozlamasi
- [x] Yuz yuklash oqimi (rasm → agent → qurilma → bulutdan o'chirish)
- [ ] **Birinchi pilot mijozga real o'rnatish** ← **bajarilmadi, mijoz kerak**
- [x] O'rnatish runbook'i (integrator ham bajara olishi kerak)

PR: cloud #13, #14, #15, #16; agent #3. Bulut testlari 274 → **396**, agent 159 → **169**.

**Sprint yopilmadi.** Yettitadan oltitasi bajarildi, lekin qolgani — haqiqiy
o'rnatish — kod bilan yopiladigan band emas. Sprintning demo shartida
"real mijozda ishlaydigan tizim" deyilgan, va bu hali yo'q.

### Rejadan farqlar

**Bitta bot butun o'rnatish uchun, har mijozga alohida emas.** O'z botini
yaratishi kerak bo'lgan mijozga Telegram developer akkaunti va topshiriladigan
token kerak bo'lardi — bu besh daqiqalik sozlashni qo'ng'iroqqa aylantiradigan
qadam.

**Chat kod orqali ulanadi, chat id yozib emas.** Agent ro'yxatga olish bilan bir
xil shakl: xeshlangan, muddatli, bir martalik. Boshqa tashkilotga ulangan chat
**ko'chirilmaydi, rad etiladi** — Telegram chat id lari global, va ikkinchi kodni
qabul qilish bir kompaniyaning davomatini boshqasining chatiga yo'naltirardi.

**Obunalar, bitta oqim emas.** Telegram'ni istaydigan ikki odam qarama-qarshi
narsani istaydi: direktor kuniga bitta xabar xohlaydi va har bir terminal
uzilishi ham kelsa kanalni o'chiradi; HR esa uzilishni bir necha daqiqada bilishi
kerak.

**Ommaviy kechikish hech kimni nomlamaydi** va ham ulush, ham quyi chegara
talab qiladi: besh kishilik ofisda ikki kishi 40% bo'ladi va bu tizimli nosozlik
emas.

**Rozilik — yozuv, belgi emas.** Kim, qachon, **qaysi versiyaga**, qanday
usulda. Versiyasiz — kelasi yil qayta yozilgan siyosat hamma unga rozi bo'lgandek
ko'rinadi. Qaytarish yozuvni o'chirmaydi, belgilaydi: yozuvning o'zi rozilik
mavjud bo'lganining dalili.

**Yuz rasmi bulutdan o'tadi, lekin unda qolmaydi.** Reja "bulutda saqlanmaydi"
deydi; so'zma-so'z bu imkonsiz, chunki terminal NAT orqasida va agent o'zi
so'raydi. Amalga oshirilgani: private diskda, yetkazilishi bilan o'chadi,
xatoda ham o'chadi, olib ketilmasa muddati bilan o'chadi. Doimiy qoladigani —
xodimdagi bitta sana.

### ⚠️ Demo — qisman

Telegram zanjiri stub Telegram serveriga qarshi to'liq ishga tushirildi: ikki
chat ulandi, ertalabki xulosa faqat direktorga bordi, signallar faqat HR
guruhiga, bloklangan chat haqiqiy 403 dan keyin o'chirildi. Yuz zanjiri haqiqiy
agent jarayoni bilan tekshirildi — haqiqiy PNG bulutdan olindi, xeshi mos keldi,
stub terminalga yozildi, va bulutdagi nusxa darhol yo'q bo'ldi.

**Lekin sprintning haqiqiy demosi — "real mijozda ishlaydigan tizim" — yo'q.**

### Yozish jarayonida topilgan nuqsonlar

1. **`.env.example` yolg'on gapirardi.** `APP_TIMEZONE=Asia/Tashkent` yozilgan,
   lekin `config/app.php` da `'timezone' => 'UTC'` qattiq yozilgan va bu
   o'zgaruvchi umuman o'qilmaydi.
2. **Saqlash muddati tabelni jimgina nolga aylantirardi.** `attendance_days`
   voqealardan hosil qilinadi; voqealar o'chirilgach o'sha oyni qayta hisoblash
   tayyor tabelni nolga aylantirardi — xatosiz, jimgina. Himoyani olib tashlab
   sinab ko'rildi: 480 daqiqalik kun haqiqatan nolga aylandi.
3. **`face_synced_at` da cast yo'q edi** — ustun xom satr bo'lib qaytardi va
   xodimlar ro'yxati "Call to a member function format() on string" bilan 500
   berardi. Testim uni o'tkazib yuborgan edi, chunki faqat `assertNotNull`
   tekshirardi.

### 🚦 Gate
Sprint 6 ga o'tish uchun **kamida bitta haqiqiy o'rnatish** kerak. Sprint 6 —
"pilotni mustahkamlash", va mustahkamlanadigan pilot yo'q.

---

## Sprint 6 — Pilotni mustahkamlash (2 hafta) — ⚠️ QISMAN, 2026-08-26

- [ ] Pilot davomida chiqqan xatolarni tuzatish ← **pilotsiz yozib bo'lmaydi**
- [x] Tunlik drift tekshiruvi va inventarizatsiya
- [x] Agentning o'z-o'zini yangilashi
- [x] Sentry, uptime monitoring, kunlik backup
- [ ] 1C export formati ← **buxgaltersiz taxmin qilib bo'lmaydi**
- [~] Onboarding hujjati + video ← **hujjat bor** (`mijoz-qollanmasi.md`), video yo'q

PR: cloud #17, #18, #19, #20; agent #4, #5. Bulut testlari 396 → **482**,
agent 169 → **244**.

**Sprintning nomi bajarilmadi.** "Pilotni mustahkamlash" — mustahkamlanadigan
pilot yo'q. Kod bilan yopiladigan hamma band yopildi; qolgan ikkitasi mijoz yoki
buxgalter talab qiladi.

### Nima qilindi

**Drift tekshiruvi.** Bulut terminal haqida bilgan yagona narsa — `applied_hash`,
ya'ni agent qachondir "yozdim" deganining xotirasi. Uni hech narsa qayta
tekshirmasdi. Terminal factory reset qilinsa yoki texnik foydalanuvchilar
ro'yxatini tozalasa, bulut hamon "hammasi joyida" deb hisoblardi, hech narsa
navbatga qo'yilmasdi, va birinchi belgi — eshik oldida turgan xodim. Hech qanday
xato yo'q edi, chunki hech narsa yiqilmagan: yozuv oylar oldin muvaffaqiyatli
o'tgan va tizim ko'ra olmaydigan qo'l uni bekor qilgan.

Endi agent kuniga bir marta ro'yxatni o'qiydi. Faqat **oxirigacha o'qilgan**
ro'yxat "yo'q" degan xulosaga asos bo'ladi: yarim o'qilgani va o'qilmagani
alohida holat, chunki ularni bo'sh ro'yxat deb hisoblash butun terminalga qayta
yozishni navbatga qo'yardi.

**Zaxira — tekshirilgani.** Nusxa ochiladi va butunligi so'raladi. O'tmagan fayl
o'chiriladi. Diskda yotgan yaroqsiz nusxa "zaxira bor" degan tuyg'u beradi, va bu
hech qanday zaxiradan yomonroq. Tiklanishini kafolatlamaydi — buni faqat qo'lda
mashq qilingan tiklash isbotlaydi, runbook shuni aytadi.

**`/health`.** Laravel'ning `/up` ekrani "dastur ishga tushdimi" deydi va bu
tizimning haqiqiy nosozliklarida ham yashil qoladi. Ikkita jim nosozlik bor:
crontab'dan `schedule:run` tushib qolishi va zaxiraning to'xtashi. Ikkalasi ham
hech qanday xato bermaydi.

**Xatolar — o'chirilgan holda.** Bu tizimdagi yagona funksiya mijoz ma'lumotini
tashqariga chiqaradi. Yoqilsa, nima chiqishi **ruxsat ro'yxati** bilan
belgilangan, taqiq ro'yxati bilan emas — taqiq ro'yxati faqat oxirgi
yangilanishigacha to'g'ri bo'ladi.

**Mijoz qo'llanmasi.** O'rnatuvchi uchun runbook bor edi; bu — mijozning o'zi
uchun. Eng muhim qismi — **tizim nima qilmasligi**, chunki birinchi haftada
ishonch aynan shu yerda yutiladi yoki yo'qoladi.

Eng keskini: **kunlik tabelni qo'lda tuzatib bo'lmaydi.** Tahrirlash tugmasi
yo'q, ataylab — tahrirlanadigan tabel dalil bo'lishdan to'xtaydi. Ya'ni
kartasini uyda unutgan odam "Kelmadi" bo'lib qoladi va tuzatish oylik
hisob-kitobda, tizimdan tashqarida bo'ladi. Qo'llanma buni ochiq aytadi va
HR'dan **birinchi oyda bu qancha xalaqit berganini yozib borishni** so'raydi —
chunki bu qarorning keyingi versiyasi uchun kerak bo'ladigan yagona ma'lumot
shu, va uni keyin eslab bo'lmaydi.

### Yozish jarayonida topilgan xavf

Qo'llanmaning "ishdan bo'shash" bo'limini yozayotganda ma'lum bo'ldi: xodimni
**o'chirish mumkin edi** (tahrirlash sahifasida va ommaviy amalda), va
`attendance_days` cascade bilan bog'langan. Ya'ni bir necha xodimni belgilab
o'chirish **bir necha oylik tabelni jimgina yo'q qilardi** — qaytarib
bo'lmaydigan tarzda.

Bu tizimning o'z mantiqiga zid edi: saqlash muddati mexanizmi aynan shu
qatorlarni **saqlab qolish** uchun alohida yozilgan (xom voqealar o'chadi,
tabel qoladi). Bitta yo'l himoya qilardi, ikkinchisi o'chirardi.

Endi tarixi bor xodim o'chmaydi va ommaviy tanlovga ham tushmaydi. Bugun
ertalab o'tgan, lekin hali tabel qatori yaratilmagan odam ham himoyalangan.
Xato bilan kiritilgan qator esa o'chaveradi — u haqiqatan xato.

### Agentning o'z-o'zini yangilashi

Men buni ikki marta qilmaslikni tavsiya qilgandim: buzilgan yangilanuvchi
**siz bora olmaydigan ofisdagi agentni** o'ldiradi. Qaror qabul qilingach, aynan
shu nosozlik **mumkin bo'lmaydigan** qilib yozildi.

**Asosiy tamoyil — "pol".** Image ichidagi versiya hech qachon o'zgartirilmaydi,
o'chirilmaydi va har doim tanlanishi mumkin. Yuklab olingan relizlar uning
yonida turadi va faqat **afzal ko'riladi**. Nosozlikning har bir yo'li —
tekshiruvdan o'tmagan fayl, yarim ochilgan papka, buzilgan holat fayli, ishga
tushib bulutga ulana olmagan reliz, bajarilmagan `exec` — bitta natijaga olib
keladi: agent o'z image'idagi kod bilan ishlaydi. Bu funksiya paydo bo'lishidan
oldingi xatti-harakatning aynan o'zi.

Pol o'chirilmaydigan bo'lishi **qoida bilan emas, tuzilish bilan** ta'minlangan:
u tozalash ishlaydigan papkada umuman yo'q.

**Ikkita mustaqil tekshiruv.** Bayt'lar e'lon qilingan xeshga mos kelishi kerak,
**va** o'sha xesh reliz kalitining Ed25519 imzosini olib yurishi kerak. Ochiq
kalit image ichida, hech qachon yuklab olinmaydi. Kalitsiz build hech narsa
o'rnatmaydi.

"Bulut aytdi" — bu dalil emas. Buzg'unchi aynan bulutni taqlid qiladi, va bu
agent mijoz LAN'ida terminal parollarini ushlab turadi.

**Reliz o'zini isbotlashi kerak.** "Ishga tushdi" yetarli emas — bulutga
**heartbeat yetkazishi** kerak. Ishga tushib, hech qayerga ulana olmaydigan build
— aynan mijozni yolg'iz qoldiradigan nosozlik, va uni exit code ko'rmaydi.

**Bulut tomoni — tarqalish tezligi.** Agent nosozlikni *omon qoladigan* qiladi;
bulut uni *hammaga bir kunda yetib bormaydigan* qiladi. Odatiy holat — bir vaqtda
bitta agent. Yangi versiyada bulutga ulanmaguncha navbat siljimaydi, ya'ni
muvaffaqiyatsiz reliz **o'z tarqalishini o'zi to'xtatadi**.

Imzo bulutda yaratilmaydi, faqat uzatiladi. Agar bulut imzo yarata olsa, agentdagi
tekshiruv buzilgan bulutdan himoya qilmaydi va butun mexanizm teatrga aylanadi.

### Testlar topgan ikkita nuqson

Ikkalasi ham tashqaridan **ishlayotgan agent kabi ko'rinardi**.

1. **`pcntl_exec` muhitni `name => value` shaklida oladi**, `"NAME=value"`
   ro'yxatida emas. Yassilangan shakl bolaga marker yetkazmasdi — bola o'zi
   reliz qidirib, yana `exec` qilardi. **Cheksiz exec halqasi.** Buni faqat
   haqiqiy jarayonni almashtiradigan integratsion test ko'ra oldi.

2. **O'sha himoya uchun yozgan testim himoya olib tashlanganda ham o'tardi.**
   Qo'yilgan reliz launcher kodiga yetib bormasdan, birinchi klassda yiqilardi,
   chunki uning `vendor/autoload.php` fayli qalbaki edi. Haqiqiy autoloader'ga
   ulangach, test himoyasiz yiqiladigan bo'ldi: bitta chaqiruvda uchta urinish
   sarflanadi va butunlay yaroqli reliz "yaroqsiz" deb belgilanadi.

⚠️ **Sinalmagani:** shu tarzda yig'ilgan reliz haqiqiy o'rnatilgan agentni
yangilashi. Mexanizmning testlari bor; haqiqiy yangilanish hech qachon bo'lmagan.

### Topilgan nuqsonlar

1. **`date` cast'i noto'g'ri edi** (`date`, `date:Y-m-d` o'rniga). Ustunga
   `2026-08-26 00:00:00` yozilardi, keyin `2026-08-26` bo'yicha qidirilardi,
   topilmasdi va takroriy yozuv urinardi — **kunning ikkinchi hisobotida 500**,
   ya'ni birinchi kundan keyingi har kuni.
2. **`VACUUM INTO` mavjud faylga yozmaydi.** Rejalashtirilgan zaxiradan keyin
   qo'lda ishga tushirilsa (o'sha soniyada) "output file already exists" xatosi
   chiqardi va buzilgan zaxira kabi ko'rinardi.
3. **`prune()` PHP'ning stat kesh'ini o'qirdi** — bitta jarayonda yozib, keyin
   tozalaganda eski vaqtlar bo'yicha saralab, noto'g'ri faylni o'chirishi mumkin
   edi.
4. **Bo'sh `SENTRY_LARAVEL_DSN`** `null` emas, `''` bo'lib kelardi — "o'chirilgan"
   holatning ikkita yozilishi paydo bo'lgandi.
5. **Xodimni o'chirish tabelni ham o'chirardi** (yuqorida batafsil).
6. **`pcntl_exec` muhiti** va **himoyasini sinamaydigan test** (yuqorida batafsil).
7. **Agent modelida yangi ustunlar `$fillable` da yo'q edi** — `Agent::create`
   ularni jimgina tashlab ketardi, va parallel chegara **hech narsani
   sanamasdi**. Chegara o'z testidan tasodifan o'tardi.

### 🚦 Gate — pilotdan to'lovga
- Pilot mijoz 1 oy uzluksiz ishlatdi
- Tabel qo'lda tekshirilganda **to'g'ri chiqdi**
- Mijoz oylik to'lovga rozi
- Qo'llab-quvvatlash oyiga ≤ 2 soat

**Bu gate'ning to'rttasi ham bitta shartga bog'liq: mijoz.** Sprint 7 ni
boshlashdan oldin qiladigan eng foydali ish — kod emas.

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

Sprint 0–4 texnik jihatdan yopildi. Qolgan ish ikkiga bo'linadi va **ikkinchisi
muhimroq**:

### 1. Texnik (Sprint 5)

Telegram bildirishnomalari, signallar, birinchi o'rnatish. Batafsil pastda.

### 2. Sotuv — kechikib ketgan yo'nalish

Sprint 0 ning sotuv gate'i hamon **nolda**: 15 intervyu, 3 pilot kelishuvi,
1 integrator. To'rt sprintlik texnik ish hech kim so'ramagan taxminlar ustiga
qurildi. Ular asosli taxminlar, lekin taxminligicha qolmoqda.

Bu yerda halol bo'lish kerak: mahsulot endi **sotiladigan minimum**ga yetdi, ya'ni
keyingi sprintni yozishdan ko'ra bitta haqiqiy ofisga o'rnatib ko'rish qimmatroq
ma'lumot beradi. Sprint 5 ni boshlashdan oldin bitta pilot topish tavsiya
etiladi — chunki Sprint 5 ning mazmuni (qaysi signal kerak, Telegram'da nima
yozilishi kerak) aynan pilotdan kelib chiqishi lozim.

### Haqiqiy terminalsiz hal qilib bo'lmaydigan narsalar

Bu ro'yxat to'rt sprintdan beri o'zgarmadi va faqat qurilma bilan yopiladi:

- `HikvisionDeviceCommands` — yagona sinf, xatti-harakati hujjatga tayangan,
  javob bergan terminalga emas
- `device.probe` — imkoniyat va sig'im o'qish
- major/minor kodlar xaritasi (hozir `unknown` ga tushadi)
- `04-paket-mustahkamlash.md` §B dagi ikkita tasdiqlanmagan nuqson
- Yo'nalishli kirish/chiqish (hozir: birinchi o'tish — kelish, oxirgisi — ketish)

---

## Sprint 0 ning texnik qismi

`04-paket-mustahkamlash.md` — SDK paketining o'zini mustahkamlash.
