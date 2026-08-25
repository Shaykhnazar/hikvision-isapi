# Eski rejaning tanqidiy tahlili

Sana: 2026-08-25
Holat: Yakuniy
Tahlil qilingan hujjatlar:
- `docs/superpowers/specs/2026-06-06-universal-access-control-platform-design.md`
- `docs/superpowers/plans/2026-06-06-universal-access-control-platform-phase-1-plan.md`

Bu hujjat eski rejani **bekor qilmaydi**, balki undan nimani saqlab qolish, nimani o'zgartirish va nimani butunlay tashlab yuborish kerakligini belgilaydi.

---

## 1. Boshlang'ich nuqta: bugun bizda nima bor

| Narsa | Holat | Baho |
|---|---|---|
| SDK kodi | 30 fayl, ~2 680 qator, 8 servis | Yaxshi ishlangan, ishlaydi |
| Test | 5 fayl | Yetarsiz |
| CI / statik tahlil | Yo'q | Bo'shliq |
| README | 1 300+ qator | **Eng qimmatli aktiv** — distribusiya kanali |
| Biznes hujjati | Yo'q | Asosiy bo'shliq |

Xulosa: bizda **mahsulot emas, komponent** bor. Eski reja shu komponentni platformaga aylantirmoqchi bo'lgan, lekin komponent va mahsulotni bitta repoga tiqishga urinib, bir nechta tuzatib bo'lmas qarorlarni qabul qilgan.

---

## 2. Saqlab qolinadigan qismlar (eski reja to'g'ri aytgan)

1. **Lokal holat = desired state, qurilma = eventually consistent tashqi tizim.** Bu to'g'ri va yangi arxitekturada ham asos bo'lib qoladi.
2. **Voqealarni normalizatsiya qilish** (`access_events` yagona oqim). To'g'ri.
3. **Segment mantig'ini biznes yo'llariga `if (gym)` ko'rinishida sochmaslik.** To'g'ri prinsip — lekin MVP'da preset tizimi umuman kerak emas (pastga qarang).
4. **Biometrik ma'lumot logga yozilmaydi, shifrlanadi, retention siyosati bor.** To'g'ri va majburiy.
5. **Xatolarni manba bo'yicha tasniflash** (auth / offline / capability / parse). To'g'ri va yangi rejaga ko'chiriladi.
6. **Tenant izolyatsiyasi har bir so'rov va job'da.** To'g'ri.

---

## 3. Tuzatib bo'lmas darajadagi xatolar

### 3.1. SaaS mahsulot MIT litsenziyali ochiq paket ichiga qurilmoqda

Phase-1 reja quyidagilarni `hikvision-isapi` paketi ichiga joylaydi:
- `src/UniversalAccess/...` — butun domen va biznes mantiq
- `src/Http/Controllers/...` — mahsulot HTTP yuzasi
- `database/migrations/...` — mahsulot ma'lumotlar bazasi

Oqibatlari:
- **Monetizatsiya asosi yo'q.** MIT — istagan odam forkni oladi, "MyAttendance" deb sotadi. Sizda himoya qiladigan hech narsa qolmaydi.
- **SDK foydalanuvchilari jazolanadi.** Bugun paketni faqat `PersonService` uchun ishlatayotgan odam 15 ta migratsiya va tenant konsepsiyasini yuklab olishga majbur bo'ladi.
- **Versiyalash halokati.** Mahsulot haftada bir marta o'zgaradi, SDK oyiga bir marta. Bitta semver ostida ikkalasi yashay olmaydi.

**Qaror: mahsulot alohida yopiq repo. Paket toza SDK bo'lib qoladi va marketing kanali vazifasini bajaradi.**

### 3.2. NAT/tarmoq muammosi — spec'da umuman ko'rilmagan

Spec'dagi asosiy oqim: "Worker pushes person and credential changes to Hikvision devices."

Realda: terminal mijoz LAN'ida `192.168.1.x` da turadi. Bulutdagi server unga ulana olmaydi. Ya'ni **spec'dagi sync oqimi real mijozda ishlamaydi.** Bu bitta jumla emas — bu butun arxitektura xatosi, chunki quyidagilarning hammasi shunga bog'liq:
- odam/karta/yuz qo'shish
- eshikni masofadan ochish
- qurilma holatini tekshirish
- qurilmaga webhook manzilini yozish

Spec bu muammoni ko'rmagani uchun `SyncOrchestrator`, `WebhookService` va butun Phase-1 reja noto'g'ri asosga qurilgan.

**Qaror: edge agent arxitekturasi (`02-arxitektura.md`). Bu Phase 0, boshqa hamma narsadan oldin.**

### 3.3. Bir vaqtda oltita segment

Spec ofis, zavod, maktab, fitnes, biznes-markaz va xavfsizlik jamoalarini bir vaqtda nishonlaydi. Yakka, to'liq bo'lmagan vaqtda ishlaydigan asoschi uchun bu — kafolatlangan muvaffaqiyatsizlik.

**Qaror: bitta beachhead — ofis/zavod davomati. Preset tizimi MVP'dan butunlay chiqariladi** (`PresetCatalog`, `OfficePreset`, `GymPreset`, `EducationPreset`, `segment_presets` jadvali — hammasi keyinga). Bitta segment uchun preset abstraksiyasi sof ortiqcha ish.

### 3.4. Mijoz sotib oladigan narsa MVP'dan chiqarilgan

Spec "MVP excludes: payroll calculation, complex shift planning" deydi. Lekin bizning bozorda:
- hech kim "universal access control platform" so'ramaydi;
- HR **"oylik davomat tabeli"** so'raydi;
- direktor **"kim kechikdi"** so'raydi;
- buxgalter **"1C ga qanday tushadi"** so'raydi.

Spec texnik jihatdan to'g'ri, tijorat jihatdan sotib bo'lmaydigan MVP tuzgan. Kirish jurnali (`access log`) — bu xom material, mahsulot emas.

**Qaror: smena jadvali + kunlik davomat hisobi + Excel/1C export MVP'ning yadrosi.** Aksincha, `access_groups`, `access_schedules`, `doors`, remote door control MVP'dan chiqariladi.

### 3.5. Biznes qismining to'liq yo'qligi

805 qator hujjatda quyidagilar bo'yicha nol qator: ICP, narx, raqobat, sotuv kanali, unit economics, mijozgacha yetib borish yo'li.

Eng og'rituvchi javobsiz savol: **HikCentral Professional 32 qurilmagacha bepul**, va terminal sotgan integrator uni tekinga o'rnatib beradi. "Nega men oyiga pul to'layman?" — bu savolga javob bo'lmasa, qolgan barcha texnik ish qiymatsiz.

**Qaror: `01-biznes-reja.md` — pozitsiyalash HikCentral qila olmaydigan narsalar ustiga quriladi.**

---

## 4. Jiddiy, lekin tuzatsa bo'ladigan kamchiliklar

### 4.1. Sync modeli sayoz

Spec'da yo'q, lekin realda birinchi haftadayoq uriladigan narsalar:

| Muammo | Nima bo'ladi |
|---|---|
| `employeeNo` taqsimlash strategiyasi yo'q | Ikki filialda bir xil raqam — voqealar noto'g'ri odamga bog'lanadi |
| Idempotentlik kaliti yo'q | Retry paytida qurilmada dublikat yaratiladi |
| Drift/reconciliation yo'q | Kimdir qurilmadan qo'lda odam o'chiradi — tizim bilmaydi |
| Qurilma sig'imi limitlari yo'q | 3 000-inchi yuzda sync jimgina buziladi |
| Firmware farqlari hisobga olinmagan | Bir model ishlaydi, ikkinchisi 400 qaytaradi |
| Batch/rate limit yo'q | 500 xodim yuklashda qurilma osiladi |

### 4.2. Voqealar bo'shlig'i (gap-filling) yo'q — eng xavfli texnik kamchilik

Spec faqat webhook oqimiga tayanadi. Internet uzilsa, agent qayta ishga tushsa yoki qurilma bufer to'lsa — **voqealar jimgina yo'qoladi**. Natijada davomat tabeli noto'g'ri chiqadi va **mijoz buni bizdan oldin sezadi**. Bu mahsulotga bo'lgan ishonchni bitta marta o'ldiradi.

**Qaror: har bir qurilma uchun davriy `AcsEvent` search orqali oxirgi N daqiqani qayta o'qib, dedup kalit bo'yicha solishtirish. Bu MVP talabi, keyingi faza emas.**

### 4.3. Phase-1 reja mahsulot emas, skelet ishlab chiqaradi

- Migratsiyalar "scaffold only", maydonlar "nullable for v1".
- Muvaffaqiyat mezoni ko'p joyda `php -l` xatosiz o'tishi.
- Repository interfeyslari yaratiladi, implementatsiyasi yo'q.
- 10 ta vazifa tugagach ham mijozga ko'rsatadigan hech narsa yo'q.

Bundan tashqari tartib xatosi: Task 1 → Step 3 `PresetCatalogTest.php` ni yaratadi va "Task 8 dan keyin o'tadi" deb yozadi — ya'ni 7 ta vazifa davomida test qizil turadi.

**Qaror: har bir sprint oxirida ishlaydigan vertical slice bo'lishi shart** (ma'lumotlar bazasidan qurilmagacha, ekrandan hisobotgacha).

### 4.4. Frontend qarori yo'q

Spec 9 ta ekranni sanaydi, lekin stack tanlanmagan. Bu scope'ning taxminan yarmi.

**Qaror: Filament v4 (admin panel). Yakka asoschi uchun Inertia+Vue ni qo'lda yozish — 2-3 oy ortiqcha ish.**

### 4.5. Ops va sifat infratuzilmasi yo'q

CI yo'q, statik tahlil yo'q, monitoring yo'q, backup yo'q, qurilma offline alert yo'q. Repo'da hozir `.github/` katalogi ham mavjud emas.

### 4.6. Vendor lock

Faqat Hikvision. ZKTeco arzon segmentda keng tarqalgan. Driver abstraksiyasi **bugun deyarli tekin**, mahsulot 10 mijozda ishlaganda esa juda qimmat.

**Qaror: mahsulot faqat `DeviceDriver` interfeysi bilan gaplashadi. Implementatsiya bitta — Hikvision. ZKTeco keyin qo'shiladi.**

### 4.7. Huquqiy tomon "Phase 3" ga surilgan

Spec: "For markets with biometric privacy rules, onboarding must include consent and retention-policy support before production rollout" — va bu Phase 3 ga qo'yilgan.

O'zbekistonda shaxsiy ma'lumotlarni lokalizatsiya qilish talabi bor (fuqarolar ma'lumotlari mamlakat hududidagi serverlarda). Biometrika — alohida sezgir toifa. Bu **hosting tanloviga, xarajatga va shartnoma shakliga** ta'sir qiladi, ya'ni birinchi mijozdan oldin hal bo'lishi kerak, keyin emas.

**Qaror: hosting O'zbekiston hududida; xodim roziligi va saqlash muddati MVP'ning bir qismi. Aniq talablar yurist bilan tasdiqlanadi (`01-biznes-reja.md` → Huquqiy).**

---

## 5. Yangi va eski rejaning yonma-yon solishtiruvi

| Mavzu | Eski reja | Yangi qaror |
|---|---|---|
| Repo | Hammasi paket ichida | Paket (MIT, ochiq) + Cloud (yopiq) + Agent (yopiq) |
| Qurilmaga ulanish | Bulutdan to'g'ridan-to'g'ri | LAN'dagi edge agent, faqat outbound |
| Segment | 6 ta, preset tizimi bilan | 1 ta: ofis/zavod davomati |
| Asosiy qiymat | Kirish jurnali va avtomatizatsiya | Davomat tabeli + Telegram + 1C export |
| Preset katalogi | Phase 1 | Chiqarildi (kelajakda, kerak bo'lsa) |
| Access groups / doors / schedules | Phase 1 | Chiqarildi (v2) |
| Remote door control | MVP | Chiqarildi (v2) |
| Attendance hisobi | Chiqarilgan | **MVP yadrosi** |
| Voqea gap-filling | Yo'q | MVP talabi |
| Frontend | Aniqlanmagan | Filament v4 |
| Jadvallar soni | 16 | ~12 |
| Birinchi natija | Skelet + `php -l` | Ishlaydigan vertical slice |

---

## 6. Eski hujjatlarning maqomi

`docs/superpowers/` ichidagi ikkala hujjat **arxiv** sifatida qoladi. Ular bekor qilinmaydi, chunki 2-bo'limdagi to'g'ri prinsiplar manbasi. Lekin ijro uchun `docs/startup/` hujjatlari ustuvor.
