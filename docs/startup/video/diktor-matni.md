# Diktor matni — Davomat videosi

Ovozni videoga `render.js` o'zi joylaydi: har bir jumlani o'z subtitri boshlanadigan
paytga qo'yadi, boshidagi va oxiridagi jimlikni kesadi, balandlikni tenglashtiradi.
Jumla ajratilgan vaqtdan uzun bo'lsa, 25% gacha tezlashtiradi; undan ham uzun bo'lsa,
yig'ish to'xtaydi va qaysi jumlani qayta yozish kerakligini aytadi.

Matnni o'zgartirmang: subtitr ham shu matn. O'zgartirmoqchi bo'lsangiz, avval
`sahna.html` dagi `CUES` ni o'zgartiring, keyin shu fayl qayta yaratiladi.

## Qanday yozish kerak

- **Har bir jumla alohida fayl.** Nomi jadvaldagidek: `01`, `02` … `14`.
  Format muhim emas: m4a, mp3, wav, ogg (Telegram ovozli xabari ham bo'ladi).
- **Telefon diktofoni yetarli.** Jim xonada, telefon og'izdan 20–30 sm uzoqda.
  Gilam, parda, mebel bor xona bo'sh xonadan yaxshi (aks-sado kam).
- Boshida va oxirida bir soniya jimlik qoldiring, men kesaman.
- **Soniya ustuni — jumla uchun maksimal vaqt.** Shoshilmang, lekin cho'zmang ham.
  Bir jumlani 2–3 marta o'qib, eng yaxshisini yuboring.
- Ruscha qismni rus tilida erkin gapiradigan odam o'qisa yaxshi.
- Davomat so'zi ikkala tilda ham bir xil o'qiladi: *da-vo-MAT*.

Tayyor fayllarni menga yuboring yoki `docs/startup/video/ovoz/uz/` va
`docs/startup/video/ovoz/ru/` papkalariga qo'ying.

## O'zbekcha — `ovoz/uz/`

| № | Fayl | Soniya | Matn |
|---|---|---|---|
| 01 | `01.m4a` | 6.7 | Devoringizda Hikvision terminali bormi? U har kuni kim kelib-ketganini allaqachon yozadi. |
| 02 | `02.m4a` | 4.1 | Lekin oy oxirida HR tabelni baribir Excel'da qo'lda yig'adi. |
| 03 | `03.m4a` | 4.6 | Terminal o'chib qolsa, buni hech kim bilmaydi. Filiallar esa alohida-alohida. |
| 04 | `04.m4a` | 5.9 | Davomat buni avtomatlashtiradi. Terminal har bir o'tishni binodagi agentga yuboradi. |
| 05 | `05.m4a` | 6.9 | Agent uni bulutga uzatadi. Router'da port ochilmaydi, oq IP sotib olish shart emas. |
| 06 | `06.m4a` | 4.5 | Xodim yuzini ko'rsatadi. O'tish bir necha soniyada bulutga tushadi. |
| 07 | `07.m4a` | 5.2 | Kechikkanlar avtomatik belgilanadi. Rahbar ertalab Telegram'da xulosa oladi. |
| 08 | `08.m4a` | 5.9 | Terminal jim qolsa, HR 2 daqiqada xabar oladi. Oy oxirida emas, o'sha kuni. |
| 09 | `09.m4a` | 4.2 | Oy oxirida tabel tayyor: soatlar, kechikishlar, kelmagan kunlar. |
| 10 | `10.m4a` | 6.5 | Excel bitta tugma bilan chiqadi. Tuzatish faqat sabab bilan, asl yozuv o'chmaydi. |
| 11 | `11.m4a` | 8.9 | Yuz rasmlari bulutda saqlanmaydi, terminal paroli binodan chiqmaydi, server O'zbekistonda. |
| 12 | `12.m4a` | 7.9 | Bog'cha, maktab, klinika, ofis, zavod, savdo tarmog'i: har biri uchun alohida tarif. |
| 13 | `13.m4a` | 8.9 | Mahsulot yangi. Birinchi uchta mijozga 3 oy obuna bepul, faqat o'rnatish to'lanadi. |
| 14 | `14.m4a` | 7.4 | Batafsil ma'lumot saytimizda. Bir soatlik demo uchun bizga yozing. |

## Русский — `ovoz/ru/`

Каждая фраза — отдельный файл (`01` … `14`), любой формат: m4a, mp3, wav, ogg.
Диктофона телефона достаточно; тихая комната, 20–30 см от рта, по секунде тишины
в начале и в конце. «Секунд» — максимальная длина фразы. Слово «Davomat»
читается *да-во-МАТ*.

| № | Файл | Секунд | Текст |
|---|---|---|---|
| 01 | `01.m4a` | 6.7 | На стене стоит терминал Hikvision? Он уже каждый день записывает, кто пришёл и ушёл. |
| 02 | `02.m4a` | 4.1 | Но в конце месяца HR всё равно собирает табель в Excel вручную. |
| 03 | `03.m4a` | 4.6 | Если терминал отключится, никто не узнает. А филиалы считаются отдельно. |
| 04 | `04.m4a` | 5.9 | Davomat это автоматизирует. Терминал отправляет каждый проход агенту в здании. |
| 05 | `05.m4a` | 6.9 | Агент передаёт его в облако. Порты на роутере не открываются, белый IP не нужен. |
| 06 | `06.m4a` | 4.5 | Сотрудник показывает лицо. Через несколько секунд проход уже в облаке. |
| 07 | `07.m4a` | 5.2 | Опоздания отмечаются сами. Утром руководитель получает сводку в Telegram. |
| 08 | `08.m4a` | 5.9 | Если терминал замолчит, HR узнает через 2 минуты. В тот же день, а не в конце месяца. |
| 09 | `09.m4a` | 4.2 | В конце месяца табель готов: часы, опоздания, пропуски. |
| 10 | `10.m4a` | 6.5 | Excel выгружается одной кнопкой. Исправления только с причиной, исходная запись остаётся. |
| 11 | `11.m4a` | 8.9 | Фото лиц не хранятся в облаке, пароль терминала не покидает здание, сервер в Узбекистане. |
| 12 | `12.m4a` | 7.9 | Детский сад, школа, клиника, офис, завод, торговая сеть: для каждого свой тариф. |
| 13 | `13.m4a` | 8.9 | Продукт новый. Первым трём клиентам 3 месяца подписки бесплатно, платится только установка. |
| 14 | `14.m4a` | 7.4 | Подробности на нашем сайте. Напишите нам, и мы покажем демо за один час. |
