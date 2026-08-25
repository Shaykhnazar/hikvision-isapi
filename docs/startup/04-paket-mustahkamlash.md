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

## A. Sifat infratuzilmasi (Sprint 0, ~6 soat)

### A1. CI
`.github/workflows/ci.yml`:
- Matritsa: PHP 8.2 / 8.3 / 8.4 × Laravel 11 / 12 / 13
- Qadamlar: `composer install` → `pint --test` → `phpstan` → `phpunit`
- `composer.lock` cheklanmaydi (kutubxona uchun `--prefer-lowest` varianti ham qo'shiladi)

### A2. Kod uslubi
- `laravel/pint` dev-dependency, `pint.json` (Laravel preset)
- Bir marta butun repoga qo'llaniladi

### A3. Statik tahlil
- `larastan/larastan` level 5 dan boshlanadi
- `phpstan.neon`, baseline **yaratilmaydi** — 2 680 qatorda level 5 ni to'g'ridan-to'g'ri tozalash mumkin

### A4. Test qamrovi
Hozir: 5 fayl. Qo'shiladi:
- `DigestAuthenticator` — nonce, qc, cnonce hisobi, `stale` javobi
- `HttpClient` — XML↔array konvertatsiyasi, Content-Type aniqlash, timeout
- `DeviceManager` — provider tanlovi, default device, mavjud bo'lmagan qurilma
- Xato yo'llari: 401, 403, 500, buzilgan XML, bo'sh javob

Maqsad: `src/Client` va `src/Authentication` uchun qamrov ≥ 80%. Servislar uchun happy path + kamida bitta xato yo'li.

---

## B. Aniqlangan potensial xatolar (tekshirish kerak)

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

### B2. `EventService::count()` javob strukturasi
```php
return $response['totalNum'] ?? 0;
```
`AcsEventTotalNum` endpointi javobni odatda `AcsEventTotalNum` kaliti ostida qaytaradi. Agar shunday bo'lsa, bu metod **doim 0 qaytaradi** va hech kim sezmaydi. Real qurilmada tekshirilsin.

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

### C6. Batch va rate limit
`CardService::batchAdd()` bor, lekin qurilma limitlari hisobga olinmagan. Batch o'lchami sozlanadigan bo'lsin va chaqiruvlar orasida throttle imkoniyati bo'lsin.

---

## D. Marketing kanali sifatida (arzon, lekin muhim)

- [ ] README boshiga qisqa "nima uchun kerak" bloki (hozir darhol xususiyatlar ro'yxati boshlanadi)
- [ ] README oxiriga: "Ushbu paket asosida qurilgan tayyor davomat tizimi — [havola]" (mahsulot tayyor bo'lgach)
- [ ] Qurilma moslik matritsasi jadvali — sinalgan modellar va firmware versiyalari. Bu qidiruvda kuchli, chunki hech kimda yo'q
- [ ] `CONTRIBUTING.md`

---

## E. Bajarilish tartibi

| № | Ish | Vaqt | Sprint |
|---|---|---|---|
| 1 | CI + Pint + PHPStan | 4 soat | 0 |
| 2 | Mavjud testlarni yashil qilish | 2 soat | 0 |
| 3 | `EventService::between()` iteratori | 3 soat | 1 |
| 4 | Xato tasnifi (C4) | 2 soat | 1 |
| 5 | `DeviceDriver` kontrakti (C3) | 4 soat | 3 |
| 6 | `DeviceService::profile()` (C2) | 3 soat | 3 |
| 7 | Redaction helper (C5) | 2 soat | 5 |
| 8 | B1/B2 tuzatish (real qurilmada tasdiqlangach) | 3 soat | 1-3 |
| 9 | Moslik matritsasi | doimiy | — |

Jami: ~23 soat, sprintlarga taqsimlangan. Hammasi bir vaqtda qilinmaydi.
