# Paketni mustahkamlash — shu repodagi ish

Sana: 2026-08-25
Kontekst: Sprint 0 ning texnik qismi (`03-yol-xaritasi.md`)
Repo: `shaykhnazar/hikvision-isapi` — MIT, ochiq, **toza SDK bo'lib qoladi**

---

## Prinsip

Bu repoga **mahsulot mantig'i qo'shilmaydi**. Tenant, migratsiya, controller, davomat hisobi, preset — hech biri. Bu yerga faqat quyidagilar qo'shiladi:

1. Har qanday Hikvision foydalanuvchisiga foydali bo'lgan narsalar
2. Mahsulotga kerak, lekin SDK darajasidagi narsalar (masalan voqealarni vaqt oralig'i bo'yicha o'qish)

Sabab: bu repo mahsulotning marketing kanali. U qanchalik yaxshi va mustaqil bo'lsa, shunchalik ko'p dasturchi uni topadi.

---

## A. Sifat infratuzilmasi — ✅ BAJARILDI (2026-08-25)

### A1. CI — ✅
`.github/workflows/ci.yml` qo'shildi:
- `tests` job: PHP 8.2 / 8.3 / 8.4 x Laravel 11 / 12 / 13 matritsasi (8 kombinatsiya; Laravel 13 PHP 8.3+ talab qilgani uchun `php 8.2 + laravel 13` chiqarib tashlangan).
- `quality` job: `composer validate --strict`, `pint --test`, `phpstan analyse`, `composer audit --locked`.

Matritsa lokal tekshirildi: uchala Laravel versiyasi ham PHP 8.2/8.3/8.4 platform cheklovlari ostida to'g'ri resolve bo'ladi, va test to'plami Laravel 11, 12, 13 da yashil.

### A2. Kod uslubi — ✅
`pint.json` (Laravel preset + `declare_strict_types`) qo'shildi va butun repoga qo'llandi. 25 fayl formatlandi, xulq-atvor o'zgarmadi.

### A3. Statik tahlil — ✅
`phpstan.neon`, larastan + phpstan-mockery kengaytmalari, **level 5**, **baseline yo'q**.

Boshlang'ich holat: 36 xato. Yakuniy holat: **0 xato**. Tuzatilgan toifalar:
- Mockery mock'lariga noto'g'ri tip berilishi (testlarda `HikvisionClient&MockInterface` docblock'i bilan hal qilindi).
- Ikkita ishlatilmagan `private const` (B bo'limiga qarang).
- Ikkita tavtologik assertion (`assertIsArray` massivga, `assertIsInt` int'ga).
- `env()` chaqiruvlari — bu paket konfiguratsiyani `src/Config/` da saqlaydi, larastan esa faqat `config/` ni taniydi; shu ikki faylga aniq `ignoreErrors` yozildi.

### A4. Test qamrovi — ✅
31 test / 70 assertion → **68 test / 131 assertion**.

Qo'shilgan fayllar:
- `tests/Unit/Client/HttpClientTest.php` — JSON/XML javob tahlili, `text/xml`, noma'lum Content-Type, buzilgan XML, JSON va XML body yuborish, multipart, 401 va 403 xato yo'llari.
- `tests/Unit/Client/HikvisionClientTest.php` — base URL, `format` query parametri, auth/timeout/SSL opsiyalari, JSON va XML sarlavhalari, `putXml()` majburiy XML, multipart'da Content-Type qo'yilmasligi, konfiguratsiya validatsiyasi.
- `tests/Unit/Client/DeviceManagerTest.php` — provider tanlovi, default qurilma, klient keshi, `clearClients()`, noma'lum qurilma xatosi, `switchDevice()`, `setProvider()`, `registerDevice()`, callback provider'ning kechiktirilgan default'i.
- `tests/Unit/Authentication/DigestAuthenticatorTest.php`
- `tests/Support/RecordingHttpClient.php` — test yordamchisi.

**Rejadagi tuzatish:** dastlab bu yerda `DigestAuthenticator` uchun "nonce, qc, cnonce, stale" testlari rejalashtirilgan edi. Kodni o'qib chiqilgach ma'lum bo'ldiki, bu klass digest'ni o'zi hisoblamaydi — u faqat Guzzle'ning `auth` opsiyasini quradi (`['user', 'pass', 'digest']`), digest mexanizmini Guzzle/cURL bajaradi. Shuning uchun u yerda uchta kichik test yetarli.

### A5. Qo'shimcha (rejada yo'q edi, lekin yo'l-yo'lakay topildi)
- `HttpClient` konstruktori endi ixtiyoriy `Client` qabul qiladi. Ilgari u `new Client` ni qattiq bog'lagan va shu sababli **umuman test qilib bo'lmas edi**. Bu orqaga mos o'zgarish va edge agent uchun ham kerak bo'ladi (retry middleware, connect timeout).
- `xmlToArray()` endi libxml xatolarini ichki holda ushlaydi. Ilgari buzilgan XML PHP warning chiqarardi — qurilmadan kelgan noto'g'ri payload ilova logini ifloslantirishi mumkin edi.
- `minimum-stability` `dev` dan `stable` ga tushirildi (Laravel 13 endi stabil).
- `composer audit` 17 ta ogohlantirish ko'rsatdi (guzzle'da bittasi **high**). Guzzle 7.11 → 7.15.5, psr7 → 2.13.1, commonmark → 2.10.0. Endi: **0 ogohlantirish**.
- `composer.lock` `.gitignore` da turgan edi, lekin fayl aslida repoda kuzatilardi. Qarama-qarshilik olib tashlandi (CI lock'ga tayanadi).

## B. Aniqlangan potensial xatolar (tekshirish kerak)

> **B1 va B2 tuzatildi (Sprint 7).** Ikkalasi ham qurilmasiz hal qilinadigan
> mantiqiy xato bo'lib chiqdi — "tekshirish kerak" emas, "tuzatish kerak" edi.
> Batafsil pastda, har birining ostida.

Kod ko'rib chiqishda topilgan, real qurilmada tasdiqlanishi kerak:

### B1. `searchID` har chaqiruvda o'zgaradi
`PersonService::search()` (`src/Services/PersonService.php:38`) va `EventService::search()` (`src/Services/EventService.php:24`) da:

```php
'searchID' => (string) time(),
```

Hikvision ISAPI'da `searchID` — bitta qidiruv sessiyasining identifikatori va **sahifalar bo'ylab bir xil bo'lishi kerak**. Har chaqiruvda `time()` ishlatilishi:
- bir soniya ichida ikki sahifa so'ralsa — bir xil ID (tasodifan to'g'ri);
- soniya o'zgarsa — qurilma yangi qidiruv boshlaydi va `responseStatusStrg`/pozitsiya kutilganidan farq qiladi.

**Tuzatish:** qidiruv sessiyasini ochiq qilish — `search()` ga `?string $searchId = null` parametri, yoki `searchAll()` iteratori ichida bitta UUID ishlatish. Orqaga moslik saqlanadi.

### ✅ B1 — tuzatildi (Sprint 7)

`Concerns\PagesSearchResults` qo'shildi: bitta `searchID`, **haqiqatan qaytgan**
yozuvlar soniga siljish, va `responseStatusStrg` bo'yicha to'xtash. `PersonService`,
`CardService`, `FingerprintService` shunga o'tkazildi; `EventService` ham
o'shanga birlashtirildi, ya'ni endi bitta amalga oshirish bor.

`PersonService::all()` va `CardService::all()` generatorlari qo'shildi.

⚠️ **Bu Sprint 6 da yozilgan drift tekshiruviga bevosita ta'sir qilardi.** U
`PersonService::search()` ustida sahifalab yurardi. Agentdagi `RosterPager` da
"firmware nol-sahifani qayta beryapti" degan himoya bor edi — va o'sha
simptomning eng ehtimoliy sababi aynan shu xato edi. Ya'ni himoya ishlab,
ro'yxat `partial` bo'lib, drift tekshiruvi jimgina ishlamay qolishi mumkin edi.

**Test qanday yozilgani muhim:** dastlabki testim xatoni **ushlamadi**. `time()`
bitta soniya ichida bir xil qiymat qaytaradi, ya'ni testdagi uchta sahifa ham
bir xil `searchID` oladi va test o'tadi. Xato faqat yurish soniya chegarasidan
o'tganda paydo bo'ladi. Shuning uchun test endi `searchID` **soatdan
olinmasligini** ham tekshiradi — bu deterministik tekshirish mumkin bo'lgan
yagona xossa.

### B2. `EventService::count()` javob strukturasi
```php
return $response['totalNum'] ?? 0;
```
`AcsEventTotalNum` endpointi javobni odatda `AcsEventTotalNum` kaliti ostida qaytaradi. Agar shunday bo'lsa, bu metod **doim 0 qaytaradi** va hech kim sezmaydi. Real qurilmada tekshirilsin.

### ✅ B2 — tuzatildi (Sprint 7)

Endi **ikkala shakl ham** o'qiladi (ichma-ich va tekis), va raqamli satr ham
qabul qilinadi. Ya'ni qurilma qaysi shaklda javob berishi **endi ahamiyatsiz** —
bu savolni haqiqiy terminalda hal qilish shart emas.

Nega muhim: kunlik audit qurilmadagi sonni bulutdagi bilan solishtiradi. `count()`
doim 0 qaytarsa, audit **har kuni, har terminalda** "hamma voqealar yo'qolgan"
deb ko'rsatardi.

### B3. Sahifalash chegarasi
`search()` metodlarida umumiy sonni bilmasdan sahifalash mumkin emas — chaqiruvchi qachon to'xtashni bilmaydi. Iterator qo'shilsin (B4 ga qarang).

---

## C. Mahsulotga kerak bo'ladigan qo'shimchalar

Bularning hammasi SDK darajasida ma'noli, ya'ni ochiq repoga to'g'ri keladi.

### C1. Vaqt oralig'i bo'yicha voqea iteratori (gap-filling uchun — eng muhim)
```php
EventService::between(
    \DateTimeInterface $from,
    \DateTimeInterface $to,
    array $extraConditions = []
): \Generator
```
Ichida: bitta `searchID`, avtomatik sahifalash, `responseStatusStrg` bo'yicha to'xtash. Bu `02-arxitektura.md` §6 dagi backfill qatlamining asosi.

### C2. Qurilma imkoniyatlari va sig'imi
```php
DeviceService::profile(): DeviceProfile
```
Bitta chaqiruvda: model, firmware, qo'llab-quvvatlanadigan credential turlari, maksimal odam / karta / yuz soni. Hozir bu ma'lumot `getCapabilities()` dan xom holda keladi va har bir chaqiruvchi o'zi tahlil qiladi.

### C3. `DeviceDriver` kontrakti
```php
interface DeviceDriver {
    public function profile(): DeviceProfile;
    public function upsertPerson(Person $person): OperationResult;
    public function deletePerson(string $employeeNo): OperationResult;
    public function upsertCard(Card $card): OperationResult;
    public function upsertFace(string $employeeNo, string $imageBase64): OperationResult;
    public function setPersonEnabled(string $employeeNo, bool $enabled): OperationResult;
    public function eventsBetween(\DateTimeInterface $from, \DateTimeInterface $to): \Generator;
}
```
Implementatsiya bitta: `HikvisionDriver` (mavjud servislarni o'raydi). Mahsulot **faqat shu interfeys bilan** gaplashadi. ZKTeco keyin qo'shilsa — mahsulot kodi o'zgarmaydi.

Bu bugun ~4 soat, mahsulot 10 mijozda ishlaganda ~3 hafta.

### ✅ C3 — bajarildi (Sprint 8), lekin boshqa joyda

**Interfeys SDK ga qo'yilmadi — ataylab.** `shaykhnazar/hikvision-isapi` —
Hikvision mijozi, va ZKTeco drayveri hech qachon unga qo'shilmaydi; u alohida
paket bo'ladi. Neytral kontrakt drayverlar **iste'mol qilinadigan** joyda
turishi kerak, ya'ni agentda — va u allaqachon o'sha yerda edi.

**Bajarilgandek ko'rinardi, lekin bajarilmagandi.** `DeviceCommands`,
`EventSource`, `DeviceInventory` interfeyslari Sprint 1–7 da yozilgan edi.
Ammo:

- `Device/` dan tashqaridagi **beshta fayl** `HikvisionException` ni nomi bilan
  tutardi;
- **uchala kontraktning o'zi** `@throws HikvisionException` deb e'lon qilardi —
  ikkinchi ishlab chiqaruvchi bajarishi kerak bo'lgan metodlarda.

Ya'ni interfeyslar neytral, **nosozlik lug'ati esa yo'q** edi. Bu bog'liqlikning
eng sof ko'rinishi: kontraktning o'zi birinchi vendorni nomlab turibdi.

**`DeviceFailure`** — mahsulotning o'z nosozlik turi. U faqat mahsulot amal
qiladigan ikki narsani olib yuradi: qayta urinish mantiqiymi, va bulut
saqlaydigan qisqa sabab. Status kodlar, javob tanalari, vendor sinflari
olib yurilmaydi — mahsulot ular bilan hech narsa qila olmaydi, ya'ni ular log
qatorining detali, ikki ishlab chiqaruvchi bajarishi kerak bo'lgan kontraktning
sharti emas.

`FaultReason` yordamchi emas, **tarjimonga** aylandi.

**Oqim shakli ham muhim.** Generator o'z ishini chaqiruvchi iteratsiya
qilayotganda bajaradi — ya'ni uni *yaratadigan* chaqiruv atrofidagi `try/catch`
hech narsa ushlamaydi. Vendor xatosi halqa o'rtasida chiqardi, aynan chaqiruvchi
unga eng tayyor bo'lmagan paytda.

### Qoladigan qismi — majburiy tekshiruv

Hammaning esida turishiga tayangan kafolat — kafolat emas. `VendorIsolationTest`
ikki narsani tekshiradi: vendor namespace faqat `src/Device/` ostida uchraydi,
va **hech bir drayver kontrakti uni umuman nomlamaydi**.

Import'ni qaytarib sinaldi — test fayl nomini va nima qilish kerakligini aytib
yiqiladi. Ikkinchi ishlab chiqaruvchi qo'shilganda, ish tugaganini aytadigan
narsa — o'sha test.

**Testlarning o'zi ham bog'liqlikning bir qismi edi:** ular vendor xatolarini
neytral interfeyslarni bajaradigan soxta obyektlarga uzatardi, ya'ni vendor turi
har bir stsenariyda mahsulot ichidan o'tardi.

Agent testlari 245 → **293**.

### C4. Xatolarni manba bo'yicha tasniflash
Hozir `HikvisionException` bitta. Kerak:
- `DeviceAuthenticationException` — parol noto'g'ri, qayta urinish foydasiz
- `DeviceUnreachableException` — timeout/tarmoq, **retry qilinadi**
- `DeviceCapacityException` — sig'im to'lgan
- `UnsupportedOperationException` — qurilma qo'llab-quvvatlamaydi
- `InvalidResponseException` — mavjud

Agentda retry siyosati aynan shu tasnifga tayanadi (`02-arxitektura.md` §5).

### C5. Redaction helper
Yuz rasmi (base64) hech qachon logga, exception xabariga yoki stack trace'ga tushmasligi kerak. Markazlashtirilgan `Redactor::payload(array $data): array` va logging joylarida majburiy ishlatish + test.

### ✅ C5 — tuzatildi (Sprint 8), lekin boshqa yo'l bilan

`Redactor` helper yozilmadi — kerak emas edi. Muammo log qatorlarida emas,
**stack trace'da** ekan.

PHP har bir chaqiruv argumentini trace'ga yozadi, agar `zend.exception_ignore_args`
aks holda aytmasa. Uning kompilyatsiya defaulti — **yozish**, va rasmiy
`php:*-alpine` image'lari faol `php.ini` bilan kelmaydi. Ya'ni "hech narsa
sozlanmagan" holat — aynan sizib chiqadigan holat, va agent image'i shunday edi.

Taxmin qilinmadi, sinab ko'rildi: ini'siz PHP'da xato otilganda base64 yuz
`getTrace()` ga **to'liq** tushadi.

Yechim ikki qavatli, va ikkalasi ham kerak:

1. **SDK** — o'ziga tegishli hamma narsa `#[\SensitiveParameter]` bilan
   belgilandi (yuz baytlari, parollar, so'rov tanalari, `$options`). Bu INI
   dan qat'i nazar ishlaydi.
2. **Agent image'i** — `php.ini-production` faollashtirildi. SDK Guzzle'ning
   parametrlarini belgilay olmaydi, so'rov `$options` esa ham tanani, ham
   `auth` ma'lumotlarini olib o'tadi. O'sha chegaradan pastda faqat shu
   sozlama qoladi.

Birinchisi yolg'iz yetarli emas, ikkinchisi yolg'iz kod xossasi emas.

### Test yozishda ikki marta aldandim

**Birinchi test Mockery bilan yozilgan edi va befoyda chiqdi.** Mockery
yaratgan sinf parametr atributlarini tashlab yuboradi, va bundan yomoni —
u **qabul qilgan argumentlarni o'zida saqlaydi**. `print_r($e->getTrace())`
mock'ning ichki daftariga kirib borib, yuzni o'sha yerdan topardi. Test
yiqilardi, ishlab chiqarish yo'li esa toza edi.

**Ikkinchisi tasodifan o'tardi.** Nazorat testi ("atributsiz parametr
haqiqatan sizadimi") trace'ni paket sinflari bo'yicha filtrlardi — va test
sinfining o'zi ham `Shaykhnazar\HikvisionIsapi\` prefiksida bo'lgani uchun
filtrdan o'tib ketardi. Endi u freymdan to'g'ridan-to'g'ri o'qiydi.

### Yo'l-yo'lakay topilgan nuqson

`timeout` va `verify_ssl` qurilma konfiguratsiyasidan **defaultsiz** o'qilardi.
Ularsiz konfiguratsiya butunlay yaroqli — agent ularni beradi, qo'lda yozilgani
ko'pincha bermaydi — va bu har bir so'rovda PHP warning chiqarardi.

### C6. Batch va rate limit
`CardService::batchAdd()` bor, lekin qurilma limitlari hisobga olinmagan. Batch o'lchami sozlanadigan bo'lsin va chaqiruvlar orasida throttle imkoniyati bo'lsin.

### ⏸ C6 — ataylab qilinmadi (Sprint 8)

`batchAdd()` ni chaqiradigan **birorta iste'molchi yo'q** — na agentda, na
bulutda. Iste'molchisi yo'q narsaning batch o'lchamini "sozlanadigan" qilish
degani — qaysi o'lcham to'g'riligini taxmin qilib, o'sha taxminni default
qilib yozib qo'yish. Qurilma limitlari ham noma'lum (C2 shuni so'rayapti).

Bu B1 va C4 bilan bir xil siyosat: ma'lum bo'lmagan narsa ustiga kod
yozilmaydi. Birinchi haqiqiy iste'molchi paydo bo'lganda — masalan agent bir
yo'la 500 ta karta yozishi kerak bo'lganda — o'sha paytdagi haqiqiy yuklama
bilan qilinadi.

---

## C-bis. Qurilma to'sig'i: `bin/hikvision-probe` (Sprint 8)

§C dagi uchta band (C2, C4, va §B dagi B1/B2 ning yakuniy tasdig'i) bitta narsa
uchun kutib turardi: **oldida turgan haqiqiy terminal**. Ular "real qurilmada
tekshirilsin" deb belgilangan edi, va bu amalda "hech qachon" degani edi —
tekshiruvni qanday o'tkazish kerakligi hech qayerda yozilmagandi.

`bin/hikvision-probe` shuni bitta buyruqqa aylantiradi:

```
HIKVISION_PASSWORD='...' php bin/hikvision-probe --host=192.168.1.100
```

Bir yurishda so'raydigan savollar:

| Savol | Nima uchun |
|---|---|
| Model, firmware, seriya | Moslik matritsasining birinchi ustuni (§D) |
| 11 ta capabilities endpoint | Sig'im, maydonlar, qo'llab-quvvatlanadigan turlar — **C2** |
| Har bir rad javobining `subStatusCode` si | Retry siyosati shunga tayanadi — **C4** |
| Sahifalash haqiqatan davom etadimi | **B1** ning yakuniy tasdig'i |
| Voqea soni qaysi kalit ostida keladi | **B2** ning yakuniy tasdig'i |

### Ikkita majburiy xossa

**Hech narsa yozmaydi.** Rad javoblari ham faqat o'qish bilan chaqiriladi — noto'g'ri
parol, mavjud bo'lmagan endpoint, ro'yxatda yo'q xodim, buzuq so'rov. Probe
odatda biznes tayanadigan terminalda ishlaydi.

**Hech kimning ma'lumoti chiqmaydi.** Xodimlar ro'yxati faqat **shakli** uchun
o'qiladi — nechta qator keldi, sahifalash maydonlari nima dedi, qator qanday
kalitlangan — qatorlarning o'zi sanaladi va tashlanadi. Maydon **nomlari**
saqlanadi, **qiymatlari** hech qachon. Hisobot elektron pochta bilan
yuboriladi, ya'ni u xodimlar ro'yxati mijoz LAN'idan chiqadigan yo'lga
aylanmasligi kerak.

Ikkalasi ham test bilan qo'riqlanadi, va ikkalasi ham himoyani olib tashlab
tasdiqlandi: qatorlarni saqlab qo'ysa — maxfiylik testi yiqiladi; `delete()`
chaqirsa — "faqat o'qish" testi yiqiladi.

### Nima hamon javobsiz qoladi

Hisobotning o'zi buni yozib qo'yadi (`not_probed` bo'limi), chunki o'nta javob
berib o'n birinchi savolni jimgina tashlab ketgan fayl **tugallangan** ko'rinadi:

- **sig'im to'lganda** qurilma nima deydi — buni provokatsiya qilish qurilmani
  to'ldirish, ya'ni yozish demakdir. Zaxira qurilma kerak.
- **qo'llab-quvvatlanmagan yozuv** qanday rad etiladi — kartaga yuz qo'shish,
  masalan. Bu yerdagi hamma probe — o'qish.
- **uzoq yurish** — sessiya katta ob'ektda daqiqalar davomida yashaydimi.
  Probe 3 sahifa × 10 qator yuradi.

### Mantiq `src/` da, `bin/` da emas

Boshida hammasi `bin/` da yozildi — ya'ni testsiz va PHPStan'siz. `DeviceProbe`
`src/Probe/` ga ko'chirildi, `bin/` esa faqat argument, parol va fayl bilan
shug'ullanadigan qobiq bo'lib qoldi. Parol **hech qachon argumentdan** olinmaydi:
argumentlar `ps` da har bir foydalanuvchiga ko'rinadi va shell ularni tarixga
yozadi.

Yo'l-yo'lakay ikkita xato topildi — ikkalasi ham skriptni haqiqiy soxta terminal
ustida yurgizganda:

1. Endpoint satrlariga qo'lda `?format=json` yozilgan edi, klient esa uni
   o'zi qo'shadi — natijada `?format=json?format=json`.
2. Testdagi soxta javoblarda `Content-Type` yo'q edi, klient esa onsiz parse
   qilmaydi. Ya'ni test o'tayotgandek ko'rinib, aslida bo'sh massivlarni
   tekshirayotgandi.

---

## D. Marketing kanali sifatida (arzon, lekin muhim)

- [ ] README boshiga qisqa "nima uchun kerak" bloki (hozir darhol xususiyatlar ro'yxati boshlanadi)
- [ ] README oxiriga: "Ushbu paket asosida qurilgan tayyor davomat tizimi — [havola]" (mahsulot tayyor bo'lgach)
- [ ] Qurilma moslik matritsasi jadvali — sinalgan modellar va firmware versiyalari. Bu qidiruvda kuchli, chunki hech kimda yo'q
- [ ] `CONTRIBUTING.md`

---

## E. Bajarilish tartibi

| № | Ish | Vaqt | Sprint | Holat |
|---|---|---|---|---|
| 1 | CI + Pint + PHPStan | 4 soat | 0 | ✅ |
| 2 | Mavjud testlarni yashil qilish + qamrovni kengaytirish | 2 soat | 0 | ✅ |
| 3 | `EventService::between()` iteratori | 3 soat | 1 | ✅ |
| 4 | Xato tasnifi (C4) | 2 soat | 1 | ✅ qisman |
| 5 | `DeviceDriver` kontrakti (C3) | 4 soat | 3 | ⏳ |
| 6 | `DeviceService::profile()` (C2) | 3 soat | 3 | ⏳ |
| 7 | Redaction helper (C5) | 2 soat | 5 | ⏳ |
| 8 | B1/B2 tuzatish (real qurilmada tasdiqlangach) | 3 soat | 1-3 | ⏳ blokda |
| 9 | Moslik matritsasi | doimiy | — | ⏳ |

Hammasi bir vaqtda qilinmaydi — Sprint 0 ning texnik qismi shu bilan yopildi.

**#4 nima uchun "qisman":** xato tasnifi faqat aniq ma'lum bo'lgan narsalarga tayanadi — javob umuman keldimi va HTTP status qanday. `DeviceCapacityException` va `UnsupportedOperationException` hozircha qo'shilmadi, chunki ular Hikvision'ning `subStatusCode` qiymatlarini bilishni talab qiladi, bu qiymatlar esa model va firmware bo'yicha farq qiladi. Ularni real qurilmadan ko'rmasdan yozish — taxmin qilish bo'lardi (B1/B2 bilan bir xil siyosat).

## F. Keyingi to'siq

№ 8 (B1 `searchID`, B2 `count()`) **real qurilmada tasdiqlanmaguncha tuzatilmaydi.** Ikkalasi ham qurilma xulq-atvoriga bog'liq va noto'g'ri "tuzatish" hozir ishlayotgan narsani buzishi mumkin. Bu Sprint 1 da, agent birinchi marta real terminalga ulanganda tekshiriladi.

## G. Laravel 11 muammosi (CI birinchi ishga tushganda topildi)

**Sana:** 2026-08-25 · **Manba:** PR #4, CI run 32851669081

Matritsaning uchala Laravel 11 job'i ham `composer update` bosqichida yiqildi. Xato:

> `laravel/framework[v11.0.0, ..., v11.56.0] ... were not loaded, because they are affected by security advisories`

Ya'ni **laravel/framework ning 11.x qatoridagi barcha relizlarda yopilmagan xavfsizlik ogohlantirishlari bor**, va Composer 2.9+ shunday paketlarni resolve qilishdan bosh tortadi (`policy.advisories.block` sukut bo'yicha yoqilgan). Natijada `orchestra/testbench 9.*` uchun o'rnatiladigan Laravel versiyasi umuman topilmaydi.

**Nega lokal tekshiruvda chiqmadi:** ishlab chiqish muhitidagi Composer 2.8.12 — bu siyosat unda yo'q. Runner'da yangiroq Composer. Bu tekshiruv metodikasidagi kamchilik: kelajakda CI muhitiga yaqinroq versiyada sinash kerak.

**QAROR (2026-08-25): Laravel 11 qo'llab-quvvatlashi butunlay olib tashlandi.** `composer.json` endi `illuminate/support: ^12.0|^13.0` va `orchestra/testbench: ^10.0|^11.0` talab qiladi. Bu buzuvchi o'zgarish, shuning uchun keyingi reliz **major (v2.0.0)** bo'ladi; Laravel 11 da qolgan loyihalar paketning 1.x qatorida qoladi. Sabab: Laravel 11 ni hozirgi Composer bilan o'rnatishning iloji yo'q, ya'ni e'lon qilingan qo'llab-quvvatlash amalda mavjud emas edi.

**Oldingi vaqtinchalik chora:** Laravel 11 CI matritsasidan chiqarildi, shunda CI haqiqatan o'rnatilishi mumkin bo'lgan narsani tekshiradi. `composer.json` esa hali ham `illuminate/support: ^11.0` ni e'lon qiladi — ya'ni **e'lon qilingan, lekin sinalmaydigan qo'llab-quvvatlash**. Bu holat uzoq turmasligi kerak.

**Hal qilinishi kerak bo'lgan savol:** Laravel 11 qo'llab-quvvatlashi butunlay olib tashlansinmi?

Foydasiga: Laravel 11 2024-yil mart oyida chiqqan, xavfsizlik qo'llab-quvvatlash oynasi allaqachon yopilgan, va yuqoridagi xato buni tasdiqlaydi — yangi loyihada Laravel 11 ni o'rnatishning o'zi endi siyosatni chetlab o'tishni talab qiladi.

Qarshi: `^11.0` ni olib tashlash — mavjud foydalanuvchilar uchun buzuvchi o'zgarish (major versiya talab qiladi).

Uchinchi variant: `composer.json` da `^11.0` qoldirib, CI da faqat Laravel 11 job'lari uchun advisory siyosatini o'chirish. Bu qo'llab-quvvatlashni sinalgan holda saqlaydi, lekin CI'ni xavfsizlik ogohlantirishlariga ko'r qiladi.
