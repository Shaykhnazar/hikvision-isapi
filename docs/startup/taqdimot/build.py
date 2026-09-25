#!/usr/bin/env python3
"""Har bir muassasa turi uchun taqdimot sahifasini yig'adi.

    python3 docs/startup/taqdimot/build.py

_bosh.html (uslublar) + _tana.html (tuzilish) + pastdagi SEGMENTLAR ->
<segment>.html. Matnni o'zgartirish kerak bo'lsa, shu faylni tahrirlang va
qayta ishga tushiring; tayyor .html fayllarni qo'lda tahrirlamang.

Tariflar 11-narxlar.html va 07-narx-varaqasi.md bilan bir xil bo'lishi shart.
"""
import json
import pathlib
import re

HERE = pathlib.Path(__file__).parent


def fmt(n):
    return f"{n:,}".replace(",", " ")


def tl(time, dot, tagcls, tag, title, text):
    dotc = f" {dot}" if dot else ""
    tagc = f" {tagcls}" if tagcls else ""
    return (f'\n      <li><span class="time">{time}</span><span class="rail"><span class="dot{dotc}"></span></span>'
            f'<div class="body"><span class="tag{tagc}">{tag}</span><h3>{title}</h3><p>{text}</p></div></li>')


def tl_cloud(time, extra=""):
    return tl(time, "", "", "Agent → bulut", "O'tish bir necha soniyada bulutga yetadi",
              "Terminal xabarni agentga yuboradi, agent bulutga uzatadi. Internet bo'lmasa, agent uni o'zida "
              "saqlab turadi va internet qaytganda yuboradi." + extra)


def tl_recheck():
    return tl("har 10 daq", "", "", "Qayta tekshirish", "Agent terminaldagi oxirgi 30 daqiqani qayta o'qiydi",
              "Biror xabar yo'lda yo'qolgan bo'lsa, shu yerda topiladi va tabelga qo'shiladi. Kech kelgan o'tish "
              "kunni qayta hisoblaydi.")


def tl_offline(time, door, story):
    return tl(time, "bad", "bad", "Signal", story,
              f"{door} terminali javob bermay qoldi. 2 daqiqa ichida HR guruhiga Telegram xabar keladi: qaysi "
              "terminal, qachondan beri. Muammo oy oxirida emas, o'sha kuni bilinadi.")


def tl_night():
    return tl("kechasi", "", "", "Tungi tekshiruv", "Tizim o'zini tekshiradi",
              "Oxirgi 24 soatdagi o'tishlar soni terminal bilan solishtiriladi. Terminaldagi xodimlar ro'yxati "
              "bulutdagi ro'yxat bilan tekshiriladi. Zaxira nusxa olinadi.")


def tl_month():
    return tl("oy oxiri", "ok", "ok", "Excel", "Buxgalter tabelni bitta tugma bilan oladi",
              "Hech kim o'tirib Excel yig'maydi. Qo'lda tuzatilgan kunlar faylda alohida belgi bilan ko'rinadi.")


def limit(title, text, first=False):
    cls = "limit first" if first else "limit"
    return f'<div class="{cls}"><h3>{title}</h3><p>{text}</p></div>'


def qa(q, a):
    return f'<div class="qa"><h3>{q}</h3><p>{a}</p></div>'


def chain(steps):
    out = []
    for i, (t, s) in enumerate(steps):
        if i:
            out.append('<span class="arrow" aria-hidden="true">→</span>')
        out.append(f'<div class="step"><b>{t}</b> {s}</div>')
    return "\n      ".join(out)


def chips(items):
    return "".join(f"<span>{x}</span>" for x in items)


def tier_range(tiers, i):
    if i == 0:
        return f"{tiers[0]['to']} xodimgacha"
    frm = tiers[i - 1]["to"] + 1
    if "to" in tiers[i]:
        return f"{frm}–{tiers[i]['to']} xodim"
    return f"{frm - 1} xodimdan ko'p"


def tier_price(tiers, i):
    x = tiers[i]
    if "price" in x:
        return fmt(x["price"]), "so'm / oy"
    if "min" in x:
        return f"{fmt(x['rate'])} × xodim", f"so'm / oy, kamida {fmt(x['min'])}"
    return f"{fmt(x['rate'])} × xodim", f"so'm, {tiers[i - 1]['to']} dan oshgan har biriga"


def tariff_cards(tiers, highlight):
    out = []
    for i, x in enumerate(tiers):
        p, unit = tier_price(tiers, i)
        cls = "tariff hl" if i == highlight else "tariff"
        out.append(f'\n      <div class="{cls}"><span class="t-name">{x["n"]}</span>'
                   f'<span class="t-for">{tier_range(tiers, i)}</span>'
                   f'<span class="t-price mono">{p}</span><span class="t-unit">{unit}</span></div>')
    return "".join(out)


def accent_css(light, ink, soft, dark, dark_soft):
    light_block = f"--accent: {light}; --accent-ink: {ink}; --accent-soft: {soft};"
    dark_block = f"--accent: {dark}; --accent-ink: {dark}; --accent-soft: {dark_soft};"
    return (f"  :root {{ {light_block} }}\n"
            f"  @media (prefers-color-scheme: dark) {{ :root:not([data-theme=\"light\"]) {{ {dark_block} }} }}\n"
            f"  :root[data-theme=\"dark\"] {{ {dark_block} }}\n")


COMMON_LIMIT_DOORS = limit("Eshiklarni masofadan boshqarmaydi",
                           "Bu xavfsizlik tizimi emas, davomat tizimi. Eshikni terminal o'zi, odatdagidek ochadi.")
ROTATION_TEXT = ("Smena hafta kunlari bilan belgilanadi (masalan: dushanba, chorshanba, juma). "
                 "“2 kun ish, 2 kun dam” kabi aylanma grafik hozircha yo'q.")
LIMIT_ROTATION = limit("Aylanma grafik hali yo'q", ROTATION_TEXT)

SEGMENTLAR = {
    # ------------------------------------------------------------------ BOG'CHA
    "bogcha": dict(
        TITLE="Bog'cha Davomati",
        DESCRIPTION="Bog'cha xodimlari davomati: Hikvision terminali, agent, bulut, tabel va Telegram qanday ishlashi, bosqichma-bosqich.",
        accent=("#E39B12", "#8F5B00", "#FCEFD2", "#F2B23A", "#3A2E14"),
        EYEBROW="Bog'chalar uchun · xodimlar davomati",
        H1="Kirish eshigidagi terminal endi <em>tayyor tabel</em> beradi",
        LEAD="Tarbiyachi, enaga, oshpaz yoki hamshira ertalab terminalga yuzini ko'rsatadi. Qolganini tizim o'zi qiladi: o'tishni yozadi, smenaga qarab kunni hisoblaydi, mudiraga Telegram'da xulosa yuboradi, oy oxirida esa buxgalterga Excel tabel tayyorlab beradi.",
        CHAIN=chain([("07:54", "Yuz ko'rsatildi"), ("07:54", "Bulutga tushdi"), ("09:00", "Mudira Telegram'da ko'rdi"), ("oy oxiri", "Excel tabel tayyor")]),
        WHO=chips(["Mudira: kunlik xulosa", "Kadrlar / HR: xodimlar va tabel", "Buxgalter: oylik Excel", "Xodimlar: faqat yuz yoki karta"]),
        SXEMA_LEAD="Ikkitasi bog'changizda turadi (terminal va kichik kompyuter), bittasi bulutda, bittasi esa telefoningiz va brauzeringizda.",
        BUILDING="BOG'CHA BINOSI", PLACE_LOC="Bog'chada", PLACE_LOC_LOWER="bog'chada", PLACE_GEN="bog'cha", PLACE_ABL="bog'chadan",
        TERMINAL_WHERE="kirish eshigida", AGENT_WHERE="bog'chada turadi",
        TERMINAL_CARD="Devordagi Hikvision terminali. Xodim yuzini ko'rsatadi yoki kartasini tutadi, terminal o'tishni yozadi.",
        AGENT_CARD_EXTRA="",
        TG_NODE="mudira, HR", LEADER_DAT="Mudiraga", LEADER_NOM="Mudira", LEADER_ACC="mudirani",
        DAY_TITLE="Tarbiyachi Dilnozaning bir kuni tizim ko'zi bilan",
        DAY_LEAD="Namuna: Dilnoza va Malikaning smenasi 08:00–17:00, kechikish chegarasi 10 daqiqa, tushlik tanaffusi 1 soat. Bu qiymatlarni har bir bog'cha o'zi belgilaydi.",
        TIMELINE="".join([
            tl("07:54", "acc", "acc", "Terminal", "Dilnoza terminalga yuzini ko'rsatadi", "Terminal uni taniydi va o'tishni vaqti bilan yozadi. Bu kundagi birinchi o'tish, demak kelish vaqti."),
            tl_cloud("07:54"),
            tl("08:17", "warn", "warn", "Kechikish", "Enaga Malika keladi", "Chegara 08:10 edi. Tizim kunni avtomatik ravishda kechikkan deb belgilaydi, buni hech kim qo'lda yozmaydi."),
            tl("09:00", "", "tg", "Telegram", "Mudira kunlik xulosani oladi", "Kim keldi, kim kechikdi, kim yo'q. Mudira HR ga qo'ng'iroq qilmasdan, telefonidan bir qarashda biladi."),
            tl_offline("10:12", "Oshxona eshigi", "Kimdir terminal rozetkasini sug'urib qo'ydi"),
            tl_recheck(),
            tl("17:06", "acc", "acc", "Terminal", "Dilnoza uyga ketadi", "Kundagi oxirgi o'tish ketish vaqti hisoblanadi. Kun: 07:54 dan 17:06 gacha, ya'ni 9 soat 12 daqiqa, tanaffus ayiriladi. Tabelga <span class=\"mono\">8:12</span> tushadi."),
            tl_night(), tl_month()]),
        NEW_TITLE="Yangi tarbiyachi ishga keldi: uni terminalga qanday qo'shasiz",
        SYNC_A="Dilnoza A.<br><span class=\"muted\" style=\"font-size:.82rem\">tarbiyachi</span>",
        SYNC_B="Kamola Y.<br><span class=\"muted\" style=\"font-size:.82rem\">tarbiyachi, yangi</span>", SYNC_B_NAME="Kamola Y.",
        DOOR_1="Kirish eshigi", DOOR_2="Oshxona eshigi",
        SHIFT_RULE="5/2, 6/1 yoki istalgan ish kunlari. Har xodimga o'z smenasi.",
        ORG="“Quyoshcha” bog'chasi",
        TS_STAFF=[
            ["Dilnoza A.", "tarbiyachi", ["8:12", "8:05", "8:20", "8:02", "8:10", "D", "D", "8:07", "8:15", "7:58", "8:11", "8:04"]],
            ["Malika R.", "enaga", ["7:43!", "8:01", "8:00*", "8:09", "7:52!", "D", "D", "8:03", "8:06", "8:00", "7:39!", "8:02"]],
            ["Sardor Q.", "oshpaz", ["8:30", "8:25", "8:31", "8:28", "8:12!", "D", "D", "8:33", "8:29", "8:27", "8:35", "8:30"]],
            ["Nodira T.", "tarbiyachi", ["8:00", "8:03", "7:55", "K", "K", "D", "D", "8:01", "8:04", "7:59", "8:02", "8:06"]],
            ["Zarina M.", "hamshira", ["8:04", "7:58", "8:02", "8:05", "8:00", "D", "D", "7:47!", "8:03", "8:01", "8:08", "7:57"]],
            ["Feruza K.", "logoped, yarim stavka", ["4:02", "D", "4:05", "D", "3:58", "D", "D", "4:01", "D", "4:03", "D", "4:00"]],
        ],
        FIX_STORY="Malika 9-sentabr kuni kartasini uyda unutgan. Terminal o'tishni yozmagan, tabelda “kelmadi” chiqqan.",
        FIX_TO="8:00", FIX_REASON="Kartasini unutgan, mudira tasdiqladi",
        TG_CAME="21 / 24", TG_LATE="<li>Malika R. (08:17)</li><li>Sardor Q., oshpaz (06:52)</li>", TG_ABSENT="<li>Nodira T.</li>", TG_TIME="09:00", TG_MASS_TIME="08:40",
        SEC_LEAD="Bog'chada bolalar bor, xavfsizlikka talab yuqori.",
        ROLE_OWNER="Bog'cha egasi yoki mudira", ROLE_VIEWER="Tashqi buxgalter, muassis",
        LIMIT_FIRST=limit("Bolalar davomatini yuritmaydi", "Tizim xodimlar uchun qurilgan. Bolalarning kelib-ketishi va ota-onalarga xabar yuborish unda yo'q.", True),
        LIMIT_LAST=COMMON_LIMIT_DOORS,
        NEED_PC="Agent uchun mini-PC (taxminan 2 mln so'm) yoki bog'chadagi doim yoniq kompyuter.",
        STEP1="Bog'cha tizimda ochiladi", STEP6="Ish kunlari, boshlanish va tugash, kechikish chegarasi, tanaffus.",
        PRICE_EYEBROW="Bog'chalar uchun narx", PRICE_TITLE="Bog'cha hajmiga qarab uchta tarif",
        PRICE_LEAD="Bog'chalar ofis va zavodlardan kichikroq, shuning uchun ular uchun alohida, arzonroq tariflar bor.",
        CALC_HINT="xodimlar: tarbiyachi, enaga, oshpaz, qorovul va boshqalar", CALC_MAX=150, CALC_DEF=22,
        tiers=[{"n": "Kichik bog'cha", "to": 25, "price": 390000}, {"n": "Bog'cha", "to": 50, "price": 590000}, {"n": "Katta bog'cha", "rate": 8000, "min": 590000}],
        highlight=1,
        FAQ_TITLE="Mudiralar ko'p beradigan savollar",
        FAQ_EXTRA=qa("Ikkinchi bog'cha (filial) ochsak?", "U yerga ham agent o'rnatiladi. Ikkala bog'cha bitta panelda, tabel bitta faylda bo'ladi."),
    ),

    # ------------------------------------------------------------------ MAKTAB
    "maktab": dict(
        TITLE="Maktab Davomati",
        DESCRIPTION="Xususiy maktab o'qituvchilari va xodimlari davomati: terminal, agent, bulut, tabel va Telegram qanday ishlashi.",
        accent=("#3A6FD8", "#2450A8", "#DDE7FB", "#86AAF2", "#1A2A4A"),
        EYEBROW="Xususiy maktablar uchun · xodimlar davomati",
        H1="O'qituvchilar davomati endi <em>o'zi yoziladi</em>",
        LEAD="O'qituvchi, direktor o'rinbosari, texnik xodim yoki oshxona xodimi ertalab terminalga yuzini ko'rsatadi. Tizim o'tishni yozadi, har bir xodimning o'z ish kunlari va smenasiga qarab kunni hisoblaydi, direktorga Telegram'da xulosa yuboradi va oy oxirida Excel tabel tayyorlaydi.",
        CHAIN=chain([("07:48", "Yuz ko'rsatildi"), ("07:48", "Bulutga tushdi"), ("08:30", "Direktor Telegram'da ko'rdi"), ("oy oxiri", "Excel tabel tayyor")]),
        WHO=chips(["Direktor: kunlik xulosa", "Kadrlar / HR: xodimlar va tabel", "Buxgalter: oylik Excel", "O'qituvchilar: faqat yuz yoki karta"]),
        SXEMA_LEAD="Ikkitasi maktabingizda turadi (terminal va kichik kompyuter), bittasi bulutda, bittasi esa telefoningiz va brauzeringizda.",
        BUILDING="MAKTAB BINOSI", PLACE_LOC="Maktabda", PLACE_LOC_LOWER="maktabda", PLACE_GEN="maktab", PLACE_ABL="maktabdan",
        TERMINAL_WHERE="asosiy kirishda", AGENT_WHERE="maktabda turadi",
        TERMINAL_CARD="Kirishdagi Hikvision terminali. O'qituvchi yuzini ko'rsatadi yoki kartasini tutadi, terminal o'tishni yozadi.",
        AGENT_CARD_EXTRA="",
        TG_NODE="direktor, HR", LEADER_DAT="Direktorga", LEADER_NOM="Direktor", LEADER_ACC="direktorni",
        DAY_TITLE="Matematika o'qituvchisi Nigoraning bir kuni tizim ko'zi bilan",
        DAY_LEAD="Namuna: Nigora va Jasurning smenasi 08:00–15:00, kechikish chegarasi 10 daqiqa, tanaffus 30 daqiqa. Har bir o'qituvchiga o'z ish kunlari va vaqti beriladi.",
        TIMELINE="".join([
            tl("07:48", "acc", "acc", "Terminal", "Nigora terminalga yuzini ko'rsatadi", "Birinchi dars 08:00 da. Terminal o'tishni vaqti bilan yozadi, bu kundagi birinchi o'tish, demak kelish vaqti."),
            tl_cloud("07:48"),
            tl("08:14", "warn", "warn", "Kechikish", "Fizika o'qituvchisi Jasur keladi", "Chegara 08:10 edi. Tizim kunni kechikkan deb belgilaydi. Direktor buni jurnaldan emas, xulosadan biladi."),
            tl("08:30", "", "tg", "Telegram", "Direktor kunlik xulosani oladi", "Kim keldi, kim kechikdi, kim yo'q. O'rinbosar birinchi darsdayoq qaysi sinfga almashtiruvchi kerakligini biladi."),
            tl_offline("10:12", "Sport zali kirishi", "Sport zali terminali o'chib qoldi"),
            tl_recheck(),
            tl("15:04", "acc", "acc", "Terminal", "Nigora uyga ketadi", "Kundagi oxirgi o'tish ketish vaqti. Kun: 07:48 dan 15:04 gacha, ya'ni 7 soat 16 daqiqa, 30 daqiqa tanaffus ayiriladi. Tabelga <span class=\"mono\">6:46</span> tushadi."),
            tl_night(), tl_month()]),
        NEW_TITLE="Yangi o'qituvchi ishga keldi: uni terminalga qanday qo'shasiz",
        SYNC_A="Nigora A.<br><span class=\"muted\" style=\"font-size:.82rem\">matematika o'qituvchisi</span>",
        SYNC_B="Shahlo N.<br><span class=\"muted\" style=\"font-size:.82rem\">kimyo o'qituvchisi, yangi</span>", SYNC_B_NAME="Shahlo N.",
        DOOR_1="Asosiy kirish", DOOR_2="Sport zali kirishi",
        SHIFT_RULE="Har o'qituvchiga o'z ish kunlari va vaqti: masalan, faqat seshanba va payshanba 13:00–17:00.",
        ORG="“Ilm yo'li” xususiy maktabi",
        TS_STAFF=[
            ["Nigora A.", "matematika o'qituvchisi", ["6:46", "6:40", "6:52", "6:38", "6:44", "D", "D", "6:41", "6:49", "6:36", "6:45", "6:40"]],
            ["Jasur T.", "fizika o'qituvchisi", ["6:31!", "6:42", "6:40", "6:30*", "6:39", "D", "D", "6:44", "6:28!", "6:41", "6:38", "6:43"]],
            ["Dilfuza R.", "boshlang'ich sinf o'qituvchisi", ["5:12", "5:08", "5:15", "5:10", "5:05", "D", "D", "5:11", "K", "5:09", "5:14", "5:07"]],
            ["Rustam K.", "ingliz tili, yarim stavka", ["D", "3:58", "D", "4:05", "D", "D", "D", "D", "4:02", "D", "3:55", "D"]],
            ["Saida M.", "direktor o'rinbosari", ["8:05", "8:12", "8:01", "8:09", "8:02", "D", "D", "8:10", "8:04", "8:07", "8:00", "8:11"]],
            ["Bahrom Y.", "texnik xodim", ["8:20", "8:14", "8:22", "8:18", "8:15", "D", "D", "8:19", "8:21", "8:16", "8:17", "8:20"]],
        ],
        FIX_STORY="Jasur 10-sentabr kuni o'quvchilar bilan muzeyga ekskursiyaga ketgan va maktabga kirmagan. Tabelda “kelmadi” chiqqan.",
        FIX_TO="6:30", FIX_REASON="Ekskursiya, direktor buyrug'i bilan",
        TG_CAME="46 / 49", TG_LATE="<li>Jasur T. (08:14)</li><li>Bahrom Y., texnik xodim (07:44)</li>", TG_ABSENT="<li>Dilfuza R.</li>", TG_TIME="08:30", TG_MASS_TIME="08:25",
        SEC_LEAD="Maktabda bolalar bor, xodimlar ma'lumotiga ham talab yuqori.",
        ROLE_OWNER="Maktab egasi yoki direktor", ROLE_VIEWER="Tashqi buxgalter, muassis",
        LIMIT_FIRST=limit("O'quvchilar davomatini yuritmaydi", "Tizim xodimlar uchun qurilgan. O'quvchilarning kelib-ketishi va ota-onalarga xabar yuborish unda yo'q.", True),
        LIMIT_LAST=limit("Dars jadvalini bilmaydi", "Tizim o'qituvchi qachon kelib-ketgani va necha soat bo'lganini ko'rsatadi. Nechta dars o'tgani va dars yuklamasi unda yo'q."),
        NEED_PC="Agent uchun mini-PC (taxminan 2 mln so'm) yoki maktabdagi doim yoniq kompyuter.",
        STEP1="Maktab tizimda ochiladi", STEP6="Har bir o'qituvchining ish kunlari, boshlanish va tugash vaqti, kechikish chegarasi, tanaffus.",
        PRICE_EYEBROW="Maktablar uchun narx", PRICE_TITLE="Maktab hajmiga qarab uchta tarif",
        PRICE_LEAD="Xususiy maktablar uchun alohida tariflar.",
        CALC_HINT="xodimlar: o'qituvchi, o'rinbosar, texnik xodim, oshxona va boshqalar", CALC_MAX=250, CALC_DEF=55,
        tiers=[{"n": "Kichik maktab", "to": 40, "price": 490000}, {"n": "Maktab", "to": 80, "price": 690000}, {"n": "Katta maktab", "rate": 8000, "min": 690000}],
        highlight=1,
        FAQ_TITLE="Direktorlar ko'p beradigan savollar",
        FAQ_EXTRA=qa("O'qituvchi haftada ikki kun kelsa?", "Unga faqat o'sha kunlar ish kuni bo'lgan smena beriladi. Qolgan kunlar tabelda <span class=\"mono\">D</span> bo'ladi, “kelmadi” emas.")
        + qa("Soatbay ishlaydigan o'qituvchilar-chi?", "Tabel har kuni necha soat bo'lganini ko'rsatadi. Soatbay haqni buxgalter shu soatlardan hisoblaydi."),
    ),

    # ------------------------------------------------------------------ O'QUV MARKAZI
    "oquv-markazi": dict(
        TITLE="O'quv Markazi Davomati",
        DESCRIPTION="O'quv markazi o'qituvchilari va xodimlari davomati: terminal, agent, bulut, tabel va Telegram qanday ishlashi.",
        accent=("#8A5CD6", "#6A3DB8", "#ECE3FA", "#B69AEE", "#2C2145"),
        EYEBROW="O'quv markazlari uchun · xodimlar davomati",
        H1="O'qituvchilar kelib-ketgani va soatlari <em>o'zi hisoblanadi</em>",
        LEAD="O'qituvchi yoki administrator markazga kirganda terminalga yuzini ko'rsatadi. Tizim o'tishni yozadi, har bir o'qituvchining ish kunlariga qarab soatlarini hisoblaydi, rahbarga Telegram'da xulosa yuboradi va oy oxirida Excel tabel beradi.",
        CHAIN=chain([("13:52", "Yuz ko'rsatildi"), ("13:52", "Bulutga tushdi"), ("14:30", "Rahbar Telegram'da ko'rdi"), ("oy oxiri", "Excel tabel tayyor")]),
        WHO=chips(["Rahbar: kunlik xulosa", "Administrator / HR: xodimlar va tabel", "Buxgalter: oylik Excel", "O'qituvchilar: faqat yuz yoki karta"]),
        SXEMA_LEAD="Ikkitasi markazingizda turadi (terminal va kichik kompyuter), bittasi bulutda, bittasi esa telefoningiz va brauzeringizda.",
        BUILDING="O'QUV MARKAZI", PLACE_LOC="Markazda", PLACE_LOC_LOWER="markazda", PLACE_GEN="markaz", PLACE_ABL="markazdan",
        TERMINAL_WHERE="kirish eshigida", AGENT_WHERE="markazda turadi",
        TERMINAL_CARD="Kirishdagi Hikvision terminali. O'qituvchi yuzini ko'rsatadi yoki kartasini tutadi, terminal o'tishni yozadi.",
        AGENT_CARD_EXTRA="",
        TG_NODE="rahbar, HR", LEADER_DAT="Rahbarga", LEADER_NOM="Rahbar", LEADER_ACC="rahbarni",
        DAY_TITLE="Ingliz tili o'qituvchisi Madinaning bir kuni tizim ko'zi bilan",
        DAY_LEAD="Namuna: Madina va Bekzodning smenasi 14:00–20:00, kechikish chegarasi 10 daqiqa, tanaffus 20 daqiqa. Markaz tushdan keyin ishlaydi, shuning uchun xulosa ham tushdan keyin keladi.",
        TIMELINE="".join([
            tl("13:52", "acc", "acc", "Terminal", "Madina terminalga yuzini ko'rsatadi", "Birinchi guruh 14:00 da. Terminal o'tishni vaqti bilan yozadi, bu kundagi birinchi o'tish."),
            tl_cloud("13:52"),
            tl("14:16", "warn", "warn", "Kechikish", "IT kurs o'qituvchisi Bekzod keladi", "Chegara 14:10 edi. Tizim kunni kechikkan deb belgilaydi. Guruh 16 daqiqa kutganini administrator ham ko'radi."),
            tl("14:30", "", "tg", "Telegram", "Rahbar kunlik xulosani oladi", "Kim keldi, kim kechikdi, kim yo'q. Kelmagan o'qituvchining guruhini kim olishini darhol hal qilish mumkin."),
            tl_offline("16:40", "Ikkinchi qavat", "Ikkinchi qavat terminali o'chib qoldi"),
            tl_recheck(),
            tl("20:07", "acc", "acc", "Terminal", "Madina oxirgi guruhdan keyin ketadi", "Kun: 13:52 dan 20:07 gacha, ya'ni 6 soat 15 daqiqa, 20 daqiqa tanaffus ayiriladi. Tabelga <span class=\"mono\">5:55</span> tushadi."),
            tl_night(), tl_month()]),
        NEW_TITLE="Yangi o'qituvchi ishga keldi: uni terminalga qanday qo'shasiz",
        SYNC_A="Madina S.<br><span class=\"muted\" style=\"font-size:.82rem\">ingliz tili</span>",
        SYNC_B="Otabek F.<br><span class=\"muted\" style=\"font-size:.82rem\">koreys tili, yangi</span>", SYNC_B_NAME="Otabek F.",
        DOOR_1="Kirish eshigi", DOOR_2="Ikkinchi qavat",
        SHIFT_RULE="Har o'qituvchiga o'z ish kunlari va vaqti: masalan, dushanba, chorshanba, juma 14:00–20:00.",
        ORG="“Bilim” o'quv markazi",
        TS_STAFF=[
            ["Madina S.", "ingliz tili", ["5:55", "5:48", "6:02", "5:51", "5:58", "D", "D", "5:50", "5:56", "5:47", "6:01", "5:53"]],
            ["Bekzod N.", "IT kurs", ["5:38!", "5:52", "5:50", "5:49", "5:30*", "D", "D", "5:54", "5:41!", "5:50", "5:52", "5:48"]],
            ["Sevara J.", "rus tili, yarim stavka", ["2:58", "D", "3:02", "D", "2:55", "D", "D", "3:01", "D", "2:59", "D", "3:04"]],
            ["Aziz O.", "matematika, abituriyentlar", ["5:44", "5:50", "K", "5:47", "5:52", "D", "D", "5:49", "5:45", "5:51", "5:48", "5:46"]],
            ["Lola R.", "administrator", ["8:02", "8:05", "7:58", "8:04", "8:01", "D", "D", "7:44!", "8:03", "8:00", "8:06", "7:59"]],
            ["Shoxrux M.", "sotuv menejeri", ["8:10", "8:04", "8:12", "8:07", "8:05", "D", "D", "8:09", "8:11", "8:03", "8:08", "8:06"]],
        ],
        FIX_STORY="Bekzod 11-sentabr kuni guruhni onlayn o'tkazgan va markazga kelmagan. Tabelda “kelmadi” chiqqan.",
        FIX_TO="5:30", FIX_REASON="Onlayn dars, rahbar tasdiqladi",
        TG_CAME="17 / 19", TG_LATE="<li>Bekzod N. (14:16)</li><li>Lola R., administrator (09:21)</li>", TG_ABSENT="<li>Aziz O.</li>", TG_TIME="14:30", TG_MASS_TIME="14:25",
        SEC_LEAD="Markazda o'qituvchilarning yuz ma'lumoti saqlanadi, bunga talab yuqori.",
        ROLE_OWNER="Markaz egasi yoki rahbari", ROLE_VIEWER="Tashqi buxgalter, sherik",
        LIMIT_FIRST=limit("Talabalar davomatini yuritmaydi", "Tizim xodimlar uchun qurilgan. O'quvchilarning guruhga kelgani va to'lovlari unda yo'q.", True),
        LIMIT_LAST=limit("Guruh va dars jadvali yo'q", "Tizim o'qituvchi qaysi guruhga dars bergani bilmaydi. Faqat kelib-ketish va soat. Ertalab va kechqurun ikki marta kelgan o'qituvchining oradagi vaqti ham ish vaqtiga qo'shiladi."),
        NEED_PC="Agent uchun mini-PC (taxminan 2 mln so'm) yoki markazdagi doim yoniq kompyuter.",
        STEP1="Markaz tizimda ochiladi", STEP6="Har bir o'qituvchining ish kunlari, boshlanish va tugash vaqti, kechikish chegarasi, tanaffus.",
        PRICE_EYEBROW="O'quv markazlari uchun narx", PRICE_TITLE="Markaz hajmiga qarab uchta tarif",
        PRICE_LEAD="Ko'p o'qituvchi yarim stavkada ishlaydi va jamoa kichik, shuning uchun o'quv markazlari uchun eng arzon tariflar.",
        CALC_HINT="xodimlar: o'qituvchi, administrator, menejer", CALC_MAX=120, CALC_DEF=15,
        tiers=[{"n": "Kichik markaz", "to": 20, "price": 290000}, {"n": "Markaz", "to": 50, "price": 490000}, {"n": "Markazlar tarmog'i", "rate": 8000, "min": 490000}],
        highlight=0,
        FAQ_TITLE="Markaz rahbarlari ko'p beradigan savollar",
        FAQ_EXTRA=qa("O'qituvchi haftada uch kun kelsa?", "Unga faqat o'sha kunlar ish kuni bo'lgan smena beriladi. Qolgan kunlar tabelda <span class=\"mono\">D</span> bo'ladi, “kelmadi” emas.")
        + qa("Bir nechta filialimiz bor", "Har bir filialga agent o'rnatiladi. Barcha filiallar bitta panelda, tabel bitta faylda bo'ladi."),
    ),

    # ------------------------------------------------------------------ KLINIKA
    "klinika": dict(
        TITLE="Klinika Davomati",
        DESCRIPTION="Xususiy klinika xodimlari davomati, jumladan tungi smenalar: terminal, agent, bulut, tabel va Telegram qanday ishlashi.",
        accent=("#D2485E", "#A8304A", "#FBE1E6", "#F28A9C", "#3F1D25"),
        EYEBROW="Xususiy klinikalar uchun · xodimlar davomati",
        H1="Kunduzgi va tungi smenalar <em>bitta tabelda</em>",
        LEAD="Shifokor, hamshira, registrator yoki laborant ishga kirganda terminalga yuzini ko'rsatadi. Tizim o'tishni yozadi, kunduzgi va tungi smenani to'g'ri hisoblaydi, bosh shifokorga Telegram'da xulosa yuboradi va oy oxirida Excel tabel beradi.",
        CHAIN=chain([("07:52", "Yuz ko'rsatildi"), ("07:52", "Bulutga tushdi"), ("08:30", "Bosh shifokor ko'rdi"), ("oy oxiri", "Excel tabel tayyor")]),
        WHO=chips(["Bosh shifokor: kunlik xulosa", "Kadrlar / HR: xodimlar va tabel", "Buxgalter: oylik Excel", "Xodimlar: faqat yuz yoki karta"]),
        SXEMA_LEAD="Ikkitasi klinikangizda turadi (terminal va kichik kompyuter), bittasi bulutda, bittasi esa telefoningiz va brauzeringizda.",
        BUILDING="KLINIKA BINOSI", PLACE_LOC="Klinikada", PLACE_LOC_LOWER="klinikada", PLACE_GEN="klinika", PLACE_ABL="klinikadan",
        TERMINAL_WHERE="xodimlar kirishida", AGENT_WHERE="klinikada turadi",
        TERMINAL_CARD="Xodimlar kirishidagi Hikvision terminali. Xodim yuzini ko'rsatadi yoki kartasini tutadi, terminal o'tishni yozadi.",
        AGENT_CARD_EXTRA="",
        TG_NODE="bosh shifokor, HR", LEADER_DAT="Bosh shifokorga", LEADER_NOM="Bosh shifokor", LEADER_ACC="bosh shifokorni",
        DAY_TITLE="Bir sutka klinikada tizim ko'zi bilan",
        DAY_LEAD="Namuna: kunduzgi smena 08:00–17:00, tungi smena 20:00–08:00. Kechikish chegarasi 10 daqiqa, tanaffus 1 soat. Hamshira Zilola tungi smenada, shifokor Anvar kunduzgi smenada.",
        TIMELINE="".join([
            tl("07:52", "acc", "acc", "Terminal", "Terapevt Anvar kunduzgi smenaga keladi", "Terminal uni taniydi va o'tishni vaqti bilan yozadi. Bu kundagi birinchi o'tish, demak kelish vaqti."),
            tl_cloud("07:52"),
            tl("08:03", "acc", "acc", "Tungi smena", "Hamshira Zilola tungi smenadan ketadi", "U kecha 19:48 da kelgan. Tungi smena ikki kalendar kunga bo'linmaydi: 19:48 dan 08:03 gacha bitta ish kuni, 12 soat 15 daqiqa, tanaffus ayiriladi. Tabelga <span class=\"mono\">11:15</span> tushadi."),
            tl("08:21", "warn", "warn", "Kechikish", "Registrator Kamola keladi", "Chegara 08:10 edi. Tizim kunni kechikkan deb belgilaydi. Registratura ochilishi kechikkanini bosh shifokor xulosadan ko'radi."),
            tl("08:30", "", "tg", "Telegram", "Bosh shifokor kunlik xulosani oladi", "Kim keldi, kim kechikdi, kim yo'q. Kelmagan shifokorning qabulini qayta taqsimlash uchun vaqt qoladi."),
            tl_offline("13:05", "Laboratoriya eshigi", "Laboratoriya terminali o'chib qoldi"),
            tl_recheck(),
            tl("19:48", "acc", "acc", "Terminal", "Zilola yana tungi smenaga keladi", "Bu o'tish bugun kechqurun boshlanadigan tungi smenaga tegishli deb aniqlanadi, ertalabki smenaga aralashmaydi."),
            tl_night(), tl_month()]),
        NEW_TITLE="Yangi shifokor ishga keldi: uni terminalga qanday qo'shasiz",
        SYNC_A="Anvar B.<br><span class=\"muted\" style=\"font-size:.82rem\">terapevt</span>",
        SYNC_B="Munisa E.<br><span class=\"muted\" style=\"font-size:.82rem\">stomatolog, yangi</span>", SYNC_B_NAME="Munisa E.",
        DOOR_1="Xodimlar kirishi", DOOR_2="Laboratoriya eshigi",
        SHIFT_RULE="Kunduzgi va tungi smena. Tungi smena (masalan 20:00–08:00) bitta kun hisoblanadi, ikkiga bo'linmaydi.",
        ORG="“Shifo Med” klinikasi",
        TS_STAFF=[
            ["Anvar B.", "terapevt", ["8:12", "8:05", "8:20", "8:02", "8:10", "5:04", "D", "8:07", "8:15", "7:58", "8:11", "8:04"]],
            ["Zilola T.", "hamshira, tungi smena", ["11:15", "D", "11:08", "D", "11:20", "D", "11:12", "D", "11:05", "D", "11:18", "D"]],
            ["Kamola S.", "registrator", ["7:49!", "8:01", "8:00*", "8:09", "8:02", "5:00", "D", "8:03", "7:41!", "8:00", "8:05", "8:02"]],
            ["Dilshod X.", "stomatolog", ["7:30", "7:25", "K", "7:28", "7:40", "4:55", "D", "7:33", "7:29", "7:27", "7:35", "7:30"]],
            ["Nargiza A.", "laborant", ["8:04", "7:58", "8:02", "8:05", "8:00", "D", "D", "8:01", "8:03", "8:01", "8:08", "7:57"]],
            ["Otabek R.", "sanitar, tungi smena", ["D", "11:10", "D", "11:14", "D", "11:09", "D", "11:16", "D", "11:11", "D", "11:07"]],
        ],
        FIX_STORY="Kamola 9-sentabr kuni seminarga yuborilgan va klinikaga kirmagan. Tabelda “kelmadi” chiqqan.",
        FIX_TO="8:00", FIX_REASON="Seminar, bosh shifokor buyrug'i bilan",
        TG_CAME="38 / 40", TG_LATE="<li>Kamola S., registrator (08:21)</li><li>Nargiza A., laborant (08:13)</li>", TG_ABSENT="<li>Dilshod X.</li>", TG_TIME="08:30", TG_MASS_TIME="08:25",
        SEC_LEAD="Klinikada tibbiy sir bor. Bu tizim bemor ma'lumotiga umuman tegmaydi, faqat xodimlar bilan ishlaydi.",
        ROLE_OWNER="Klinika egasi yoki bosh shifokor", ROLE_VIEWER="Tashqi buxgalter, muassis",
        LIMIT_FIRST=limit("Bemorlar va qabullarni hisoblamaydi", "Tizim xodimlar uchun qurilgan. Bemorlar, navbat va qabul jadvali unda yo'q.", True),
        LIMIT_LAST=limit("Aylanma grafik va sutkalik navbatchilik", "Smena hafta kunlari bilan belgilanadi. “2 kun ish, 2 kun dam” kabi aylanma grafik hozircha yo'q. 24 soatlik navbatchilik hali real klinikada sinalmagan, birinchi oyda birga tekshiramiz."),
        NEED_PC="Agent uchun mini-PC (taxminan 2 mln so'm) yoki klinikadagi doim yoniq kompyuter.",
        STEP1="Klinika tizimda ochiladi", STEP6="Kunduzgi va tungi smenalar, ish kunlari, kechikish chegarasi, tanaffus.",
        PRICE_EYEBROW="Klinikalar uchun narx", PRICE_TITLE="Klinika hajmiga qarab uchta tarif",
        PRICE_LEAD="Tungi va uzun smenalar ko'p bo'lgani uchun sozlash va tekshirish ko'proq vaqt oladi, shuning uchun klinika tariflari biroz yuqoriroq.",
        CALC_HINT="xodimlar: shifokor, hamshira, registrator, laborant, sanitar", CALC_MAX=300, CALC_DEF=45,
        tiers=[{"n": "Kichik klinika", "to": 30, "price": 590000}, {"n": "Klinika", "to": 60, "price": 890000}, {"n": "Katta klinika", "rate": 12000, "min": 890000}],
        highlight=1,
        FAQ_TITLE="Bosh shifokorlar ko'p beradigan savollar",
        FAQ_EXTRA=qa("Tungi smena ikki kunga bo'linib ketmaydimi?", "Yo'q. 20:00–08:00 smenaning ikkala o'tishi bitta ish kuniga yoziladi va soat to'g'ri hisoblanadi.")
        + qa("Shifokor ikki filialda ishlasa?", "Ikkala filial bitta tizimda bo'lsa, xodimning raqami bitta va ikkala filialdagi o'tishlari uning bitta tabeliga tushadi."),
    ),

    # ------------------------------------------------------------------ OFIS
    "ofis": dict(
        TITLE="Ofis Davomati",
        DESCRIPTION="Ofis va IT kompaniyalar uchun xodimlar davomati: filiallar bitta ekranda, tabel, Telegram, Excel.",
        accent=("#2F8FB0", "#1D6682", "#DAEEF5", "#6CC0DE", "#173640"),
        EYEBROW="Ofislar va IT kompaniyalar uchun · xodimlar davomati",
        H1="Barcha filiallar davomati <em>bitta ekranda</em>",
        LEAD="Xodim ofisga kirganda terminalga yuzini ko'rsatadi yoki kartasini tutadi. Tizim har bir filialdagi o'tishni yozadi, smenaga qarab kunni hisoblaydi, direktorga Telegram'da xulosa yuboradi va oy oxirida bitta Excel tabel beradi.",
        CHAIN=chain([("08:51", "Karta tutildi"), ("08:51", "Bulutga tushdi"), ("09:30", "Direktor Telegram'da ko'rdi"), ("oy oxiri", "Excel tabel tayyor")]),
        WHO=chips(["Direktor: kunlik xulosa", "HR: xodimlar va tabel", "Buxgalter: oylik Excel", "Xodimlar: faqat yuz yoki karta"]),
        SXEMA_LEAD="Ikkitasi har bir ofisingizda turadi (terminal va kichik kompyuter), bittasi bulutda, bittasi esa telefoningiz va brauzeringizda.",
        BUILDING="OFIS", PLACE_LOC="Ofisda", PLACE_LOC_LOWER="ofisda", PLACE_GEN="ofis", PLACE_ABL="ofisdan",
        TERMINAL_WHERE="ofis kirishida", AGENT_WHERE="ofisda turadi",
        TERMINAL_CARD="Kirishdagi Hikvision terminali. Xodim yuzini ko'rsatadi yoki kartasini tutadi, terminal o'tishni yozadi.",
        AGENT_CARD_EXTRA=" Har bir filialda o'z agenti bo'ladi.",
        TG_NODE="direktor, HR", LEADER_DAT="Direktorga", LEADER_NOM="Direktor", LEADER_ACC="direktorni",
        DAY_TITLE="Sotuv menejeri Azizaning bir kuni tizim ko'zi bilan",
        DAY_LEAD="Namuna: smena 09:00–18:00, kechikish chegarasi 10 daqiqa, tushlik 1 soat. Kompaniyaning ikkita ofisi bor: bosh ofis va Chilonzor filiali.",
        TIMELINE="".join([
            tl("08:51", "acc", "acc", "Terminal", "Aziza bosh ofisda kartasini tutadi", "Terminal o'tishni vaqti bilan yozadi. Bu kundagi birinchi o'tish, demak kelish vaqti."),
            tl_cloud("08:51", " Chilonzor filialidagi o'tishlar ham o'sha bulutga, o'sha tabelga tushadi."),
            tl("09:17", "warn", "warn", "Kechikish", "Filialda operator Malika keladi", "Chegara 09:10 edi. Tizim kunni kechikkan deb belgilaydi. Filial boshqa manzilda bo'lsa ham, HR buni bosh ofisdan ko'radi."),
            tl("09:30", "", "tg", "Telegram", "Direktor kunlik xulosani oladi", "Ikkala ofis bo'yicha bitta xabar: kim keldi, kim kechikdi, kim yo'q. HR ga qo'ng'iroq qilish shart emas."),
            tl_offline("12:40", "Chilonzor filiali", "Filialda terminal o'chib qoldi"),
            tl_recheck(),
            tl("18:09", "acc", "acc", "Terminal", "Aziza uyga ketadi", "Kun: 08:51 dan 18:09 gacha, ya'ni 9 soat 18 daqiqa, tushlik ayiriladi. Tabelga <span class=\"mono\">8:18</span> tushadi."),
            tl_night(), tl_month()]),
        NEW_TITLE="Yangi xodim ishga keldi: uni terminallarga qanday qo'shasiz",
        SYNC_A="Aziza K.<br><span class=\"muted\" style=\"font-size:.82rem\">sotuv menejeri</span>",
        SYNC_B="Diyor S.<br><span class=\"muted\" style=\"font-size:.82rem\">dasturchi, yangi</span>", SYNC_B_NAME="Diyor S.",
        DOOR_1="Bosh ofis", DOOR_2="Chilonzor filiali",
        SHIFT_RULE="5/2, 6/1 yoki istalgan ish kunlari. Operatorlar uchun alohida smena.",
        ORG="“Tez Servis” MChJ",
        TS_STAFF=[
            ["Aziza K.", "sotuv menejeri", ["8:18", "8:05", "8:20", "8:02", "8:10", "D", "D", "8:07", "8:15", "7:58", "8:11", "8:04"]],
            ["Timur S.", "dasturchi", ["8:30", "8:25", "K", "8:28", "8:40", "D", "D", "8:33", "8:29", "8:00*", "8:35", "8:30"]],
            ["Malika O.", "operator, filial", ["7:43!", "8:01", "8:00", "8:09", "7:52!", "D", "D", "8:03", "8:06", "8:00", "7:39!", "8:02"]],
            ["Jahongir R.", "buxgalter", ["8:00", "8:03", "7:55", "8:02", "8:01", "D", "D", "8:01", "8:04", "7:59", "8:02", "8:06"]],
            ["Nilufar D.", "HR", ["8:04", "7:58", "8:02", "8:05", "8:00", "D", "D", "7:47!", "8:03", "8:01", "8:08", "7:57"]],
            ["Sanjar B.", "kuryer", ["8:40", "8:52", "8:47", "8:38", "8:44", "4:05", "D", "8:50", "8:41", "8:46", "8:39", "8:43"]],
        ],
        FIX_STORY="Timur 16-sentabr kuni mijoz ofisida ishlagan va o'z ofisiga kirmagan. Tabelda “kelmadi” chiqqan.",
        FIX_TO="8:00", FIX_REASON="Mijoz ofisida ish, rahbar tasdiqladi",
        TG_CAME="72 / 76", TG_LATE="<li>Malika O., filial (09:17)</li><li>Sanjar B., kuryer (09:14)</li>", TG_ABSENT="<li>Timur S.</li><li>yana 2 kishi</li>", TG_TIME="09:30", TG_MASS_TIME="09:25",
        SEC_LEAD="Xodimlarning yuz ma'lumoti va parollar himoyalanishi kerak, IT bo'limi buni birinchi so'raydi.",
        ROLE_OWNER="Kompaniya egasi yoki direktor", ROLE_VIEWER="Tashqi buxgalter, bo'lim boshlig'i",
        LIMIT_FIRST=limit("Masofadan ishlashni hisoblamaydi", "Tizim faqat terminal orqali o'tganlarni ko'radi. Uydan ishlagan kun tabelda “kelmadi” bo'ladi va sababli tuzatish bilan belgilanadi.", True),
        LIMIT_LAST=COMMON_LIMIT_DOORS,
        NEED_PC="Har bir ofis uchun bittadan: mini-PC (taxminan 2 mln so'm) yoki ofisdagi doim yoniq kompyuter.",
        STEP1="Kompaniya va filiallar tizimda ochiladi", STEP6="Ish kunlari, boshlanish va tugash, kechikish chegarasi, tanaffus.",
        PRICE_EYEBROW="Ofislar uchun narx", PRICE_TITLE="Xodimlar soniga qarab uchta tarif",
        PRICE_LEAD="Kichik ofis uchun qat'iy narx, kattasi uchun xodim boshiga.",
        CALC_HINT="xodimlar: barcha filiallardagi faol xodimlar", CALC_MAX=400, CALC_DEF=80,
        tiers=[{"n": "Start", "to": 50, "price": 690000}, {"n": "Biznes", "to": 200, "rate": 8000, "min": 690000}, {"n": "Korporativ", "rate": 6000}],
        highlight=1,
        FAQ_TITLE="Direktorlar va HR ko'p beradigan savollar",
        FAQ_EXTRA=qa("Yangi filial ochsak?", "U yerga agent va terminal o'rnatiladi. Filial bitta panelga qo'shiladi, oylik narx faqat xodimlar soniga bog'liq.")
        + qa("IT bo'limimiz bulutga qarshi bo'lsa?", "Terminal parollari va yuz rasmlari bulutda saqlanmaydi, tarmoqda port ochilmaydi. Bu ham yetmasa, alohida shartnoma bo'yicha o'z serveringizga o'rnatish variantini muhokama qilamiz."),
    ),

    # ------------------------------------------------------------------ ISHLAB CHIQARISH
    "ishlab-chiqarish": dict(
        TITLE="Korxona Davomati",
        DESCRIPTION="Zavod, sex, ombor va qurilish uchun xodimlar davomati: uch smena, tungi smena, tabel, Telegram, Excel.",
        accent=("#C8641E", "#944610", "#F8E4D4", "#EE9A5C", "#3D2615"),
        EYEBROW="Ishlab chiqarish, logistika, qurilish · xodimlar davomati",
        H1="Uch smena, yuzlab ishchi, <em>bitta aniq tabel</em>",
        LEAD="Ishchi sex yoki ombor darvozasidan o'tganda terminalga yuzini ko'rsatadi. Tizim o'tishni yozadi, har bir smenani, jumladan tungi smenani to'g'ri hisoblaydi, direktorga Telegram'da xulosa yuboradi va oy oxirida Excel tabel beradi.",
        CHAIN=chain([("06:52", "Yuz ko'rsatildi"), ("06:52", "Bulutga tushdi"), ("08:00", "Direktor Telegram'da ko'rdi"), ("oy oxiri", "Excel tabel tayyor")]),
        WHO=chips(["Direktor: kunlik xulosa", "HR / tabelchi: xodimlar va tabel", "Buxgalter: oylik Excel", "Ishchilar: faqat yuz yoki karta"]),
        SXEMA_LEAD="Ikkitasi korxonangizda turadi (terminal va kichik kompyuter), bittasi bulutda, bittasi esa telefoningiz va brauzeringizda.",
        BUILDING="KORXONA HUDUDI", PLACE_LOC="Korxonada", PLACE_LOC_LOWER="korxonada", PLACE_GEN="korxona", PLACE_ABL="korxonadan",
        TERMINAL_WHERE="nazorat punktida", AGENT_WHERE="korxonada turadi",
        TERMINAL_CARD="Nazorat punkti yoki sex kirishidagi Hikvision terminali. Ishchi yuzini ko'rsatadi yoki kartasini tutadi, terminal o'tishni yozadi.",
        AGENT_CARD_EXTRA=" Bir nechta sex va ombor bitta agentga ulanadi, agar ular bitta tarmoqda bo'lsa.",
        TG_NODE="direktor, HR", LEADER_DAT="Direktorga", LEADER_NOM="Direktor", LEADER_ACC="direktorni",
        DAY_TITLE="Bir kun sexda tizim ko'zi bilan",
        DAY_LEAD="Namuna: 1-smena 07:00–15:00, 3-smena (tungi) 23:00–07:00. Kechikish chegarasi 10 daqiqa, tanaffus 30 daqiqa. CNC operatori Sherzod 1-smenada.",
        TIMELINE="".join([
            tl("06:52", "acc", "acc", "Terminal", "Sherzod nazorat punktida yuzini ko'rsatadi", "Terminal uni taniydi va o'tishni vaqti bilan yozadi. Bu kundagi birinchi o'tish, demak kelish vaqti."),
            tl_cloud("06:52"),
            tl("07:04", "acc", "acc", "Tungi smena", "3-smena ishchilari chiqib ketadi", "Ular kecha 23:00 atrofida kelgan. Tungi smena ikki kalendar kunga bo'linmaydi: kelish va ketish bitta ish kuniga yoziladi."),
            tl("07:14", "warn", "warn", "Kechikish", "Payvandchi Ulug'bek keladi", "Chegara 07:10 edi. Tizim kunni kechikkan deb belgilaydi."),
            tl("07:30", "warn", "warn", "Ommaviy kechikish", "Xizmat avtobusi kechikdi", "1-smenaning katta qismi chegaradan keyin keldi. HR guruhiga bitta xabar keladi. Unda hech kimning ismi yo'q, chunki bu bir odamning emas, transportning muammosi."),
            tl("08:00", "", "tg", "Telegram", "Direktor kunlik xulosani oladi", "Kim keldi, kim kechikdi, kim yo'q. Smena boshliqlari ham obuna bo'lishi mumkin."),
            tl_offline("11:40", "Ombor darvozasi", "Ombor darvozasidagi terminal o'chib qoldi"),
            tl_recheck(),
            tl("15:03", "acc", "acc", "Terminal", "Sherzod smenadan chiqadi", "Kun: 06:52 dan 15:03 gacha, ya'ni 8 soat 11 daqiqa, 30 daqiqa tanaffus ayiriladi. Tabelga <span class=\"mono\">7:41</span> tushadi."),
            tl_night(), tl_month()]),
        NEW_TITLE="Yangi ishchi keldi: uni terminallarga qanday qo'shasiz",
        SYNC_A="Sherzod A.<br><span class=\"muted\" style=\"font-size:.82rem\">CNC operatori</span>",
        SYNC_B="Akmal Y.<br><span class=\"muted\" style=\"font-size:.82rem\">yuklovchi, yangi</span>", SYNC_B_NAME="Akmal Y.",
        DOOR_1="Nazorat punkti", DOOR_2="Ombor darvozasi",
        SHIFT_RULE="Bir, ikki yoki uch smena. Tungi smena (23:00–07:00) bitta kun hisoblanadi, ikkiga bo'linmaydi.",
        ORG="“Temir Metall” zavodi",
        TS_STAFF=[
            ["Sherzod A.", "CNC operatori, 1-smena", ["7:41", "7:35", "7:44", "7:38", "7:40", "7:36", "D", "7:42", "7:39", "7:33", "7:41", "7:37"]],
            ["Ulug'bek T.", "payvandchi, 1-smena", ["7:22!", "7:38", "7:40", "7:30*", "7:25!", "7:39", "D", "7:41", "7:36", "7:20!", "7:38", "7:40"]],
            ["Botir S.", "usta, 3-smena", ["7:32", "7:28", "7:35", "7:30", "7:31", "D", "D", "7:34", "7:29", "7:33", "7:30", "7:28"]],
            ["Farrux N.", "omborchi", ["8:10", "8:04", "8:12", "K", "8:05", "4:02", "D", "8:09", "8:11", "8:03", "8:08", "8:06"]],
            ["Dilnoza K.", "sifat nazorati", ["8:00", "8:03", "7:55", "8:02", "8:01", "D", "D", "8:01", "8:04", "7:59", "8:02", "8:06"]],
            ["Ravshan H.", "haydovchi", ["9:05", "8:58", "9:10", "9:02", "8:55", "4:30", "D", "9:04", "9:01", "8:57", "9:06", "9:00"]],
        ],
        FIX_STORY="Ulug'bek 10-sentabr kuni buyurtmachining obyektida payvand ishida bo'lgan va zavodga kirmagan. Tabelda “kelmadi” chiqqan.",
        FIX_TO="7:30", FIX_REASON="Obyektda ish, sex boshlig'i tasdiqladi",
        TG_CAME="241 / 256", TG_LATE="<li>Ulug'bek T., payvandchi (07:14)</li><li>yana 36 kishi, xizmat avtobusi</li>", TG_ABSENT="<li>Farrux N., omborchi</li><li>yana 14 kishi</li>", TG_TIME="08:00", TG_MASS_TIME="07:30",
        SEC_LEAD="Yuzlab ishchining yuz ma'lumoti bitta tizimda turadi, bunga talab yuqori.",
        ROLE_OWNER="Korxona egasi yoki direktor", ROLE_VIEWER="Sex boshlig'i, tashqi buxgalter",
        LIMIT_FIRST=limit("Ishlab chiqarish hajmini hisoblamaydi", "Tizim faqat vaqtni hisoblaydi. Kim qancha mahsulot qilgani va ishbay haq unda yo'q.", True),
        LIMIT_LAST=LIMIT_ROTATION,
        NEED_PC="Har bir alohida hudud uchun bittadan: mini-PC (taxminan 2 mln so'm) yoki doim yoniq kompyuter.",
        STEP1="Korxona va hududlar tizimda ochiladi", STEP6="Har bir smenaning vaqti va ish kunlari, kechikish chegarasi, tanaffus.",
        PRICE_EYEBROW="Korxonalar uchun narx", PRICE_TITLE="Jamoa katta bo'lsa, xodim boshiga arzonroq",
        PRICE_LEAD="Ishchilar soni ko'p, shuning uchun xodim boshiga narx ofisnikidan past.",
        CALC_HINT="xodimlar: barcha smenalardagi faol ishchilar", CALC_MAX=400, CALC_DEF=180,
        tiers=[{"n": "Start", "to": 50, "price": 690000}, {"n": "Sanoat", "to": 300, "rate": 7000, "min": 690000}, {"n": "Yirik korxona", "rate": 5000}],
        highlight=1,
        FAQ_TITLE="Korxona rahbarlari ko'p beradigan savollar",
        FAQ_EXTRA=qa("Qurilish obyektida internet uzilib tursa?", "Agent o'tishlarni o'zida saqlab turadi va internet paydo bo'lganda yuboradi. Terminal va agent uchun elektr kerak, UPS tavsiya qilamiz.")
        + qa("Ishchi smena orasida bir necha marta chiqib-kirsa?", "Kundagi birinchi o'tish kelish, oxirgisi ketish. Oradagi chiqishlar ish vaqtidan ayirilmaydi."),
    ),

    # ------------------------------------------------------------------ SAVDO
    "savdo-tarmogi": dict(
        TITLE="Savdo Tarmog'i Davomati",
        DESCRIPTION="Do'konlar, dorixonalar va restoranlar tarmog'i uchun xodimlar davomati: har bir filial bitta ekranda, tabel, Telegram, Excel.",
        accent=("#3E9B4F", "#2A7639", "#DFF2E2", "#78CC87", "#1A3620"),
        EYEBROW="Do'kon, dorixona va restoran tarmoqlari · xodimlar davomati",
        H1="Har bir do'kondagi xodimlar <em>bitta ekranda</em>",
        LEAD="Kassir, sotuvchi yoki omborchi do'konga kirganda terminalga yuzini ko'rsatadi. Tizim har bir filialdagi o'tishni yozadi, uzun smenalarni ham hisoblaydi, direktorga butun tarmoq bo'yicha Telegram xulosa yuboradi va oy oxirida bitta Excel tabel beradi.",
        CHAIN=chain([("08:47", "Yuz ko'rsatildi"), ("08:47", "Bulutga tushdi"), ("09:30", "Direktor tarmoqni ko'rdi"), ("oy oxiri", "Excel tabel tayyor")]),
        WHO=chips(["Direktor: butun tarmoq bo'yicha xulosa", "HR: xodimlar va tabel", "Buxgalter: oylik Excel", "Xodimlar: faqat yuz yoki karta"]),
        SXEMA_LEAD="Ikkitasi har bir do'koningizda turadi (terminal va kichik kompyuter), bittasi bulutda, bittasi esa telefoningiz va brauzeringizda.",
        BUILDING="HAR BIR DO'KON", PLACE_LOC="Do'konda", PLACE_LOC_LOWER="do'konda", PLACE_GEN="do'kon", PLACE_ABL="do'kondan",
        TERMINAL_WHERE="xodimlar kirishida", AGENT_WHERE="do'konda turadi",
        TERMINAL_CARD="Xodimlar kirishidagi Hikvision terminali. Xodim yuzini ko'rsatadi yoki kartasini tutadi, terminal o'tishni yozadi.",
        AGENT_CARD_EXTRA=" Har bir do'konda o'z agenti bo'ladi.",
        TG_NODE="direktor, HR", LEADER_DAT="Direktorga", LEADER_NOM="Direktor", LEADER_ACC="direktorni",
        DAY_TITLE="Kassir Gulchehraning bir kuni tizim ko'zi bilan",
        DAY_LEAD="Namuna: tarmoqda 12 ta do'kon. Kassirlar smenasi 09:00–21:00, haftada to'rt kun (dushanba, seshanba, payshanba, shanba). Kechikish chegarasi 10 daqiqa, tanaffus 1 soat.",
        TIMELINE="".join([
            tl("08:47", "acc", "acc", "Terminal", "Gulchehra Yunusobod do'konida yuzini ko'rsatadi", "Terminal o'tishni vaqti bilan yozadi. Bu kundagi birinchi o'tish, demak kelish vaqti."),
            tl_cloud("08:47", " 12 ta do'konning har biridagi o'tishlar bitta bulutga, bitta tabelga tushadi."),
            tl("09:18", "warn", "warn", "Kechikish", "Sergeli do'konida sotuvchi Jasur keladi", "Chegara 09:10 edi. Do'kon ochilishi kechikkanini direktor boshqa shahar chetidan turib ko'radi."),
            tl("09:30", "", "tg", "Telegram", "Direktor butun tarmoq bo'yicha xulosani oladi", "Kim keldi, kim kechikdi, kim yo'q, barcha do'konlar bo'yicha bitta xabarda."),
            tl_offline("12:05", "Chilonzor do'koni", "Chilonzor do'konida terminal o'chib qoldi"),
            tl_recheck(),
            tl("21:06", "acc", "acc", "Terminal", "Gulchehra do'kon yopilgach ketadi", "Kun: 08:47 dan 21:06 gacha, ya'ni 12 soat 19 daqiqa, 1 soat tanaffus ayiriladi. Tabelga <span class=\"mono\">11:19</span> tushadi."),
            tl_night(), tl_month()]),
        NEW_TITLE="Yangi sotuvchi ishga keldi: uni do'kon terminaliga qanday qo'shasiz",
        SYNC_A="Gulchehra M.<br><span class=\"muted\" style=\"font-size:.82rem\">kassir, Yunusobod</span>",
        SYNC_B="Sardor E.<br><span class=\"muted\" style=\"font-size:.82rem\">sotuvchi, yangi</span>", SYNC_B_NAME="Sardor E.",
        DOOR_1="Yunusobod do'koni", DOOR_2="Chilonzor do'koni",
        SHIFT_RULE="Har xodimga o'z ish kunlari: masalan dushanba, seshanba, payshanba, shanba. 12 soatlik smena ham bitta kun.",
        ORG="“Oila Market” tarmog'i",
        TS_STAFF=[
            ["Gulchehra M.", "kassir, Yunusobod", ["11:19", "11:05", "D", "11:12", "D", "11:08", "D", "11:15", "11:02", "D", "11:10", "D"]],
            ["Jasur A.", "sotuvchi, Sergeli", ["10:52!", "11:04", "D", "11:00*", "D", "10:47!", "D", "11:06", "11:03", "D", "10:58", "D"]],
            ["Nodir B.", "kassir, Chilonzor", ["D", "D", "11:07", "D", "11:11", "D", "11:04", "D", "D", "11:09", "D", "11:13"]],
            ["Oydin S.", "do'kon mudiri", ["9:05", "9:12", "9:01", "9:09", "9:02", "4:10", "D", "9:10", "9:04", "9:07", "9:00", "9:11"]],
            ["Kamron T.", "omborchi", ["8:20", "8:14", "8:22", "K", "8:15", "D", "D", "8:19", "8:21", "8:16", "8:17", "8:20"]],
            ["Zuhra E.", "farrosh, yarim stavka", ["4:02", "4:05", "3:58", "4:01", "4:03", "D", "D", "4:00", "4:04", "3:59", "4:02", "4:01"]],
        ],
        FIX_STORY="Jasur 10-sentabr kuni boshqa do'konda inventarizatsiyaga yuborilgan, u yerda terminal hali o'rnatilmagan edi. Tabelda “kelmadi” chiqqan.",
        FIX_TO="11:00", FIX_REASON="Inventarizatsiya, mudir tasdiqladi",
        TG_CAME="131 / 138", TG_LATE="<li>Jasur A., Sergeli (09:18)</li><li>Nodir B., Chilonzor (09:12)</li>", TG_ABSENT="<li>Kamron T., omborchi</li><li>yana 6 kishi</li>", TG_TIME="09:30", TG_MASS_TIME="09:25",
        SEC_LEAD="Ko'p filial, ko'p terminal: har birining paroli va har bir xodimning yuz ma'lumoti himoyalanishi kerak.",
        ROLE_OWNER="Tarmoq egasi yoki direktor", ROLE_VIEWER="Do'kon mudiri, tashqi buxgalter",
        LIMIT_FIRST=limit("Xaridorlarni sanamaydi", "Tizim xodimlar uchun qurilgan. Xaridorlar oqimi, kassa va savdo bilan bog'lanmagan.", True),
        LIMIT_LAST=limit("Har bir do'konda agent kerak", "Har bir filialda agent uchun doim yoniq kompyuter bo'lishi shart. Kichik do'kon uchun bu qo'shimcha xarajat. " + ROTATION_TEXT),
        NEED_PC="Har bir do'kon uchun bittadan: mini-PC (taxminan 2 mln so'm) yoki do'kondagi doim yoniq kompyuter.",
        STEP1="Tarmoq va do'konlar tizimda ochiladi", STEP6="Har bir xodimning ish kunlari, boshlanish va tugash, kechikish chegarasi, tanaffus.",
        PRICE_EYEBROW="Savdo tarmoqlari uchun narx", PRICE_TITLE="Do'konlar soni emas, xodimlar soni muhim",
        PRICE_LEAD="Filiallar soni oylik narxga ta'sir qilmaydi, faqat xodimlar soni. Yangi do'kon uchun faqat o'rnatish to'lanadi.",
        CALC_HINT="xodimlar: barcha do'konlardagi faol xodimlar", CALC_MAX=400, CALC_DEF=120,
        tiers=[{"n": "Start", "to": 50, "price": 690000}, {"n": "Tarmoq", "to": 300, "rate": 9000, "min": 690000}, {"n": "Yirik tarmoq", "rate": 6000}],
        highlight=1,
        FAQ_TITLE="Tarmoq rahbarlari ko'p beradigan savollar",
        FAQ_EXTRA=qa("Yangi do'kon ochsak?", "Agent va terminal o'rnatiladi, o'rnatish +1 000 000 so'm. Oylik narx faqat xodimlar soniga qarab o'zgaradi.")
        + qa("Agentni kassa kompyuteriga o'rnatsa bo'ladimi?", "Doim yoniq bo'lsa, mumkin. Lekin kassa ishiga xalal bermaslik uchun alohida mini-PC tavsiya qilamiz."),
    ),
}


def build():
    head = (HERE / "_bosh.html").read_text()
    body = (HERE / "_tana.html").read_text()
    template = head + body
    for slug, seg in SEGMENTLAR.items():
        values = {k: v for k, v in seg.items() if k.isupper()}
        values["ACCENT_CSS"] = accent_css(*seg["accent"])
        values["TS_STAFF"] = json.dumps(seg["TS_STAFF"], ensure_ascii=False)
        values["TIERS_JS"] = json.dumps(seg["tiers"], ensure_ascii=False)
        values["TARIFF_CARDS"] = tariff_cards(seg["tiers"], seg["highlight"])
        out = template
        for k, v in values.items():
            out = out.replace("{{" + k + "}}", str(v))
        left = sorted(set(re.findall(r"\{\{([A-Z_0-9]+)\}\}", out)))
        if left:
            raise SystemExit(f"{slug}: to'ldirilmagan joylar: {', '.join(left)}")
        (HERE / f"{slug}.html").write_text(out)
        print(f"{slug}.html")


if __name__ == "__main__":
    build()
