# Biznes reja — Davomat SaaS (ishchi nom: DavomatCloud)

Sana: 2026-08-25
Holat: Gipoteza — Sprint 0 da tasdiqlanadi yoki rad etiladi
Beachhead: Ofis / zavod — davomat
Resurs: 1 asoschi, haftasiga ~10-15 soat

> Bu hujjatdagi barcha raqamlar **gipoteza**. Ularning har biri Sprint 0 dagi mijoz suhbatlarida tekshiriladi. Tasdiqlanmagan raqam ustiga kod yozilmaydi.

---

## 1. Muammo

Kompaniya Hikvision yuz tanish terminalini sotib olgan (integrator o'rnatib bergan). Terminal ishlaydi — kirish-chiqishni yozadi. Keyin nima bo'ladi:

- HR har oy oxirida terminaldan Excel yuklab oladi yoki iVMS-4200 dan hisobot chiqaradi.
- Bir necha filial bo'lsa — har biridan alohida, keyin qo'lda birlashtiradi.
- Smena, kechikish siyosati, ta'til, kasallik varaqasi — hammasi qo'lda Excel'da.
- Buxgalteriyaga 1C ga qo'lda kiritiladi.
- Direktor "bugun kim kechikdi" ni bilmoqchi bo'lsa — HR ga qo'ng'iroq qiladi.
- Terminal ikki kun ishlamay qolsa — buni oy oxirida biladilar.

Ya'ni: **qurilma bor, ma'lumot bor, mahsulot yo'q.** HR har oy 6-15 soat qo'l mehnati sarflaydi va natija baribir ishonchsiz.

## 2. ICP (ideal mijoz profili)

| Parametr | Qiymat |
|---|---|
| Geografiya | Toshkent, keyin Samarqand/Namangan/Farg'ona |
| Xodimlar soni | 50–500 |
| Filiallar | 1–8 |
| Tarmoq | Ishlab chiqarish, logistika, savdo tarmoqlari, xususiy klinikalar, IT/BPO, qurilish |
| Texnik holat | Hikvision terminal(lar) allaqachon o'rnatilgan |
| Sotib oluvchi | HR direktor yoki moliya direktori |
| Foydalanuvchi | HR mutaxassisi (kundalik), bo'lim boshliqlari (haftalik) |
| Rad etuvchi | IT bo'limi ("bulut xavfsiz emas") — shuning uchun edge agent va lokal hosting muhim |

**Chiqarib tashlanadi:** 50 dan kam xodimli kompaniyalar (to'lov qobiliyati past, Excel yetarli) va davlat tashkilotlari (tender sikli yakka asoschi uchun juda uzun).

## 3. Raqobat va pozitsiyalash

| Raqobatchi | Kuchli tomoni | Zaif tomoni (bizning kirish nuqtamiz) |
|---|---|---|
| **HikCentral Professional** | 32 qurilmagacha bepul, Hikvision'ning o'zidan | Windows serverda lokal; filiallararo birlashtirish og'ir; davomat hisoboti xom (smena siyosati, ta'til, kechikish qoidalari yo'q); 1C integratsiyasi yo'q; Telegram yo'q; yangilanish qo'lda |
| **iVMS-4200** | Bepul, har bir integrator o'rnatadi | Bu monitoring dasturi, HR mahsuloti emas; bitta kompyuterga bog'langan |
| **ZKTeco BioTime** | Davomatga yo'naltirilgan, tanish | ZKTeco qurilmalariga bog'langan; UI eski; lokalizatsiya zaif |
| **1C:Zarplata modullari** | Buxgalteriya bilan bitta tizimda | Terminal bilan integratsiya yo'q — kimdir ma'lumotni olib kelishi kerak. **Bu bizning integratsiya nuqtamiz, raqobat emas** |
| **Excel** | Bepul, hamma biladi | Haqiqiy raqobatchi shu. Uni yenggan mahsulot g'olib |

### Pozitsiyalash bayonoti

> Sizda allaqachon Hikvision terminali bor. Biz uni HR tizimiga aylantiramiz: barcha filiallar bitta ekranda, tabel avtomatik hisoblanadi, direktor har kuni ertalab Telegram'da xulosani oladi, buxgalter 1C uchun faylni bir tugma bilan chiqaradi.

### HikCentral bepul bo'lsa, nega pul to'lashadi — 5 ta javob

1. **Tabel avtomatik hisoblanadi.** HikCentral kirish-chiqish jurnalini beradi; biz smena, kechikish qoidasi, tanaffus, ta'til va kasallikni hisobga olib tayyor tabel beramiz. HR ning 6-15 soati qaytariladi.
2. **Filiallar bitta ekranda.** Windows server har bir filialda emas — bulut hammasini birlashtiradi.
3. **Telegram.** Direktorga kunlik xulosa, HR ga kechikish signali, xodimga o'z tabeli. HikCentral'da yo'q va u yerda paydo bo'lmaydi.
4. **1C export.** Buxgalterning qo'lda kiritishi tugaydi.
5. **Qurilma nazorati.** Terminal offline bo'lsa 2 daqiqada Telegram'ga signal, oy oxirida emas.

Diqqat: biz **xavfsizlik/access control** bozorida raqobatlashmaymiz — u yerda HikCentral kuchli. Biz **HR/davomat** bozoridamiz, u yerda HikCentral mahsulot emas.

## 4. Mahsulot bosqichlari

**v1 (MVP, sotiladigan minimum):**
- Filial va qurilmalarni ro'yxatga olish, agent orqali ulash
- Xodimlar bazasi, karta va yuz identifikatorlari, qurilmalarga sinxronizatsiya
- Kirish-chiqish voqealari, gap-filling bilan ishonchli
- Smena jadvali (oddiy: 5/2, 6/1, suzuvchi) va kechikish siyosati
- Kunlik/oylik davomat tabeli, Excel export
- Telegram: direktorga kunlik xulosa, HR ga kechikish/qurilma offline signali
- Rollar: egasi, HR, kuzatuvchi

**v1.1 (birinchi mijoz fikridan keyin):** 1C export formati, ta'til/kasallik varaqasi, xodimga shaxsiy Telegram tabeli.

**v2:** ish vaqtidan tashqari ishlash (overtime), ko'p smenali murakkab jadvallar, mobil ilova, ZKTeco drayveri, access control (eshiklar, guruhlar, jadvallar), boshqa segmentlar (fitnes, maktab).

## 5. Narx gipotezasi

Modeli: **xodim/oy**, minimal to'lov bilan. Qurilma soni cheklanmaydi (qurilma soniga bog'lash mijozni qurilma qo'shishdan qaytaradi — bu bizga zarar).

| Tarif | Kim uchun | Narx gipotezasi |
|---|---|---|
| Start | 50 xodimgacha, 1 filial | 690 000 so'm/oy |
| Biznes | 51–200 xodim | 8 000 so'm × xodim/oy |
| Korporativ | 200+ xodim, ko'p filial | 6 000 so'm × xodim/oy, shartnoma bo'yicha |
| On-prem | IT bo'limi bulutga qarshi bo'lsa | Yillik litsenziya + o'rnatish |

Bir martalik: **o'rnatish va sozlash 1 500 000 – 3 000 000 so'm** (agent o'rnatish, qurilmalarni ulash, xodimlarni import qilish, HR o'qitish). Bu muhim — u birinchi oylarda naqd pul beradi va mijozning jiddiyligini tekshiradi.

Sinov: 14 kun bepul, karta so'ralmaydi.

**Tekshirish savoli (suhbatda):** "HR ning oyiga 10 soati va tabelning ishonchliligi uchun oyiga 1 000 000 so'm to'lash mantiqiymi?" — 200 xodimli kompaniya uchun bu 1.6 mln so'm/oy, ya'ni bitta HR mutaxassisi maoshining kichik ulushi.

## 6. Sotuv kanali

Yakka asoschi uchun to'g'ridan-to'g'ri sotuv ishlamaydi. Uch kanal, ustuvorlik tartibida:

1. **Integratorlar (asosiy kanal).** Toshkentda Hikvision terminal sotadigan va o'rnatadigan 10-20 ta kompaniya bor. Ular allaqachon bizning mijozimizga o'rnatib bergan. Ularga taklif: har bir mijoz uchun **oylik to'lovning 25%** birinchi 12 oy davomida. Ular uchun bu qo'shimcha daromad va mijozga qo'shimcha qiymat. Biz uchun bu — tayyor sotuv jamoasi.
2. **Ochiq paket (inbound).** `hikvision-isapi` Packagist'da. Uni yuklab olayotgan dasturchilar — mijoz uchun shu masalani yechayotgan odamlar. README'ga mahsulot havolasi qo'yiladi. Sekin, lekin tekin kanal.
3. **To'g'ridan-to'g'ri.** HR hamjamiyatlari (Telegram guruhlari, HR konferensiyalari), LinkedIn. Kontent: "Hikvision terminalidan tabel qanday chiqariladi" — bu qidiruvda odam bor.

## 7. Iqtisodiyot (taxminiy)

**Xarajatlar (oyiga, 10 mijozgacha):**
- Hosting (O'zbekistonda VPS, Postgres, backup): ~1 200 000 so'm/oy
- Domen, sertifikat, Telegram bot, monitoring: ~200 000 so'm/oy
- Edge agent hardware: **mijoz to'laydi** (mini-PC ~2 mln so'm, yoki mijozning mavjud serveri/Windows kompyuteriga o'rnatiladi)

**Daromad (10 mijoz × o'rtacha 120 xodim × 8 000 so'm):** ~9 600 000 so'm/oy

Ya'ni marginal xarajat past, asosiy xarajat — **sizning vaqtingiz**. Shuning uchun eng katta iqtisodiy risk — texnik emas, **qo'llab-quvvatlash yuki**. Har bir mijoz oyiga 2 soatdan ko'p vaqtingizni olsa, 10 mijozda siz to'la band bo'lasiz va o'sish to'xtaydi.

**Bundan kelib chiqadigan mahsulot talabi:** o'rnatish o'zi-o'zidan (self-service) bo'lishi, agent o'zini yangilashi va xatolarni o'zi qayta urinishi shart. Bu "yaxshi bo'lardi" emas — bu biznes modelining sharti.

## 8. Huquqiy talablar (yurist bilan tasdiqlanadi)

Bu bo'lim **tasdiqlanmagan** — Sprint 0 da yurist bilan 1 soatlik konsultatsiya rejalashtirilgan.

Tekshiriladigan savollar:
1. Shaxsiy ma'lumotlarni lokalizatsiya qilish talabi — serverlar O'zbekiston hududida bo'lishi shartmi? (Amaliy taxmin: **ha**, shuning uchun hosting mahalliy provayderda rejalashtirilgan.)
2. Biometrik ma'lumot (yuz shabloni) uchun alohida talablar bormi? Xodimdan yozma rozilik shaklimi?
3. Ma'lumotlar bazasi operatori sifatida ro'yxatdan o'tish kerakmi?
4. Mijoz bilan shartnomada ma'lumotlarni qayta ishlash sharti (DPA) qanday shakllantiriladi?

Mahsulotdagi majburiy javob (talabdan qat'i nazar to'g'ri qaror):
- Yuz rasmlari bulutda saqlanmaydi — agent qurilmaga yuklaydi va o'chiradi. Bulutda faqat "yuz mavjud/yo'q" holati.
- Qurilma parollari mijoz LAN'idagi agentda qoladi, bulutga yuborilmaydi.
- Xodim roziligi tizimda qayd etiladi (kim, qachon, qaysi versiya).
- Voqealar uchun saqlash muddati sozlanadi (default: 24 oy), muddat tugagach avtomatik o'chiriladi.
- Biometrik ma'lumot hech qachon logga yozilmaydi.

## 9. Muvaffaqiyat va to'xtatish mezonlari

**Sprint 0 gate (2 hafta ichida):**
- [ ] 15 ta HR/direktor bilan suhbat o'tkazildi
- [ ] Ulardan kamida 8 tasi muammoni tan oldi va hozirgi yechimini tavsifladi
- [ ] Kamida 3 tasi pilotda qatnashishga og'zaki rozi bo'ldi
- [ ] Kamida 1 tasi bir martalik o'rnatish haqini to'lashga rozi
- [ ] 3 ta integrator bilan gaplashildi, kamida 1 tasi hamkorlikka qiziqdi

**Agar bu mezonlar bajarilmasa — kod yozilmaydi.** Segment yoki muammo qayta ko'rib chiqiladi (masalan fitnes segmentiga o'tish).

**6 oylik maqsad:** 3 ta to'lovchi mijoz, oylik takroriy daromad ≥ 3 000 000 so'm, har bir mijoz oyiga ≤ 2 soat qo'llab-quvvatlash talab qiladi.

**12 oylik maqsad:** 10-15 mijoz, MRR ≥ 12 000 000 so'm, integrator kanali orqali kamida 5 ta mijoz.

## 10. Asosiy risklar

| Risk | Ehtimollik | Ta'sir | Yumshatish |
|---|---|---|---|
| HR "Excel yetarli" deydi | O'rta | Yuqori | Sprint 0 suhbatlari; qo'l mehnati soatini raqamlashtirish |
| Integratorlar hamkorlikni istamaydi | O'rta | Yuqori | 25% komissiya; ularga tayyor demo va materiallar berish |
| Qurilma firmware xilma-xilligi | Yuqori | O'rta | Har bir model uchun moslik matritsasi; capability probing |
| IT bo'limi bulutga qarshi | O'rta | O'rta | On-prem tarif; agent parollarni tashqariga chiqarmaydi |
| Vaqt yetishmasligi (haftada 12 soat) | **Yuqori** | **Yuqori** | Scope shafqatsiz qisqartirilgan; Filament; sprintlarda vertical slice |
| Qo'llab-quvvatlash yuki o'sishni bo'g'adi | Yuqori | Yuqori | Self-service o'rnatish; agent o'z-o'zini tiklashi |
| Hikvision o'zi shunday mahsulot chiqaradi | Past | Yuqori | Lokalizatsiya, 1C, Telegram — global vendor bu yo'nalishga kirmaydi |
