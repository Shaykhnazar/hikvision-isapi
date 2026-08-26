# Startup hujjatlari

Ushbu katalog `hikvision-isapi` paketini to'laqonli mahsulotga aylantirish rejasini o'z ichiga oladi.

| Hujjat | Nima uchun |
|---|---|
| [`00-eski-rejaning-tahlili.md`](00-eski-rejaning-tahlili.md) | 2026-06-06 dagi "Universal Access Control Platform" rejasining tanqidiy tahlili: nima saqlanadi, nima o'zgaradi, nima tashlanadi |
| [`01-biznes-reja.md`](01-biznes-reja.md) | ICP, muammo, raqobat, pozitsiyalash, narx, sotuv kanali, iqtisodiyot, huquq, risklar |
| [`02-arxitektura.md`](02-arxitektura.md) | Edge agent arxitekturasi, uchta repo, sync modeli, voqealar oqimi, ma'lumotlar modeli, xavfsizlik |
| [`03-yol-xaritasi.md`](03-yol-xaritasi.md) | 6 sprint, har birida demo qilinadigan natija va o'tish mezonlari |
| [`04-paket-mustahkamlash.md`](04-paket-mustahkamlash.md) | **Shu repoda** bajariladigan ish: CI, testlar, topilgan xatolar, SDK qo'shimchalari |
| [`05-agent-protokoli.md`](05-agent-protokoli.md) | Agent ↔ bulut shartnomasi: enrolment, imzolash, vazifalar, voqealar |

## Repolar

| Repo | Rol | Holat |
|---|---|---|
| `hikvision-isapi` | MIT ochiq SDK, distribusiya kanali | v2.0.0-beta.1 |
| `attendance-agent` | Mijoz LAN'idagi edge agent (yopiq) | Sprint 6 — vazifalar, backfill, audit, yuz, drift tekshiruvi (188 test) |
| `attendance-cloud` | Bulut xizmati (yopiq) | Sprint 6 — tabel, Telegram, rozilik, inventarizatsiya, zaxira (458 test) |

Mahsulot **sotiladigan minimum**dan ancha o'tdi. Sprint 5 va 6 ning kod qismi
tayyor, ammo **ikkalasi ham yopilmadi**, va ikkalasining ham yopilmagan bandi
bir xil: mijoz.

Sprint 6 ning nomi — "pilotni mustahkamlash". Mustahkamlanadigan pilot yo'q.
Uning oltita bandidan uchtasi (pilot xatolari, 1C formati, onboarding video)
ta'rifi bo'yicha mijozdan yoki buxgalterdan keladi.

Sprint 0 ning sotuv gate'i hamon **nolda** (15 intervyu, 3 pilot, 1 integrator).
Besh sprintlik texnik ish hech kim tasdiqlamagan taxminlar ustiga qurildi. Eng
qimmatli keyingi qadam — kod emas, bitta haqiqiy ofis:
[`attendance-cloud/docs/ornatish-runbook.md`](../../../attendance-cloud/docs/ornatish-runbook.md).

## Qabul qilingan asosiy qarorlar

1. **Segment:** ofis/zavod davomati (bitta beachhead, oltita segment emas)
2. **Arxitektura:** bulut + mijoz LAN'idagi edge agent (NAT muammosi shu bilan yechiladi)
3. **Repo:** bu paket MIT ochiq SDK bo'lib qoladi; mahsulot alohida yopiq repolarda
4. **Resurs:** 1 asoschi, haftasiga ~12 soat — scope shunga qarab shafqatsiz qisqartirilgan
5. **Birinchi qadam:** kod emas, **mijoz suhbatlari** (Sprint 0 gate)

## O'qish tartibi

Birinchi marta o'qiyotgan bo'lsangiz: `00` → `01` → `02` → `03` → `04`.

`docs/superpowers/` ichidagi eski hujjatlar arxiv sifatida saqlanadi.
