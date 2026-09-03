# `hikvision-isapi` — C4 arxitektura modeli

Bu hujjat paketning arxitekturasini [C4 modeli](https://c4model.com) bo'yicha
to'rt darajada tasvirlaydi: **Context → Container → Component → Code**.
Diagrammalar Mermaid'da yozilgan (GitHub va ko'pchilik IDE'lar to'g'ridan-to'g'ri
render qiladi). Xuddi shu model [`workspace.dsl`](workspace.dsl) faylida
Structurizr DSL ko'rinishida ham bor — uni <https://structurizr.com/dsl> ga
qo'yib interaktiv ko'rish mumkin.

Hujjat `src/` dagi haqiqiy koddan yozilgan (v1.5.x, `master`). Rejadagi mahsulot
(edge agent, bulut) uchun [`../startup/02-arxitektura.md`](../startup/02-arxitektura.md)
ga qarang — bu yerda faqat paketning o'zi.

---

## 0. Qamrov: kutubxona uchun C4

`hikvision-isapi` — mustaqil ishlaydigan xizmat emas, **Composer kutubxonasi**.
U doim boshqa Laravel ilovasining protsessi ichida yashaydi. Shuning uchun C4
darajalari quyidagicha talqin qilinadi:

| C4 darajasi | Bu paketda nima |
|---|---|
| **System** | `hikvision-isapi` paketi — host ilova bilan Hikvision terminallari orasidagi adapter |
| **Container** | Ikkita ishga tushadigan birlik: (1) host ilova protsessiga yuklanadigan kutubxona, (2) `bin/hikvision-probe` CLI protsessi |
| **Component** | PHP namespace'lari: `Client`, `Services`, `Authentication`, `Probe`, `DTOs`, `Exceptions`, … |
| **Code** | Sinflar, so'rov oqimi, istisno ierarxiyasi |

---

## 1-daraja. System Context

Paket kimga xizmat qiladi va tashqarida nima bilan gaplashadi.

```mermaid
C4Context
    title 1-daraja — System Context: hikvision-isapi

    Person(dev, "Laravel dasturchisi", "Davomat / kirish nazorati ilovasini yozadi. Paketni composer orqali o'rnatadi, servislarni IoC'dan oladi.")
    Person(ops, "Integrator / ops", "Obyektga chiqib terminalni ulaydi. bin/hikvision-probe ni ishga tushiradi.")

    System(sdk, "hikvision-isapi", "Laravel paketi (PHP 8.2+). Hikvision ISAPI uchun tipli, ko'p-qurilmali SDK: odam, karta, yuz, barmoq izi, eshik, voqealar, webhook sozlash.")

    System_Ext(host, "Host Laravel ilovasi", "Paketni ichiga olgan ilova: IoC container, config/hikvision.php, DB, cache, webhook endpoint.")
    System_Ext(terminal, "Hikvision terminal(lar)", "Yuz tanish / kirish nazorati qurilmasi. ISAPI HTTP serveri, Digest auth, JSON yoki XML.")

    Rel(dev, host, "Ishlab chiqadi, deploy qiladi")
    Rel(dev, sdk, "Servis API'sini chaqiradi", "app(PersonService::class), Hikvision::device('exit')")
    Rel(ops, sdk, "Qurilmani tekshiradi", "php vendor/bin/hikvision-probe --host=…")
    Rel(host, sdk, "Yuklaydi va sozlaydi", "Composer, ServiceProvider, config, hikvision.device.provider binding")
    Rel(sdk, terminal, "ISAPI so'rovlari", "HTTP(S) GET/POST/PUT/DELETE, Digest auth, JSON/XML, multipart")
    Rel(terminal, host, "Voqealarni push qiladi (webhook)", "HTTP POST, XML — SDK sozlaydi, host qabul qiladi")

    UpdateLayoutConfig($c4ShapeInRow="3", $c4BoundaryInRow="1")
```

**Asosiy nuqtalar**

- Paket **faqat chiquvchi** so'rov qiladi: `HikvisionClient → terminal`. U hech
  qanday HTTP endpoint ochmaydi, route yoki controller bermaydi.
- Webhook oqimi ikki qismli: `EventNotificationService` terminalga "voqealarni
  mana bu URL'ga yubor" deb **sozlaydi** (`/ISAPI/Event/notification/httpHosts/{id}`),
  lekin voqeani **qabul qilish** host ilovaning ishi. Paket XML parse qilish uchun
  faqat `HttpClient::xmlToArray` kabi yordamchini beradi, tayyor receiver yo'q.
- Qurilma ro'yxati host ilovadan keladi — config, DB jadvali, Eloquent, cache
  yoki ixtiyoriy callback orqali (`DeviceProviderInterface`).

---

## 2-daraja. Container

Paket ichidagi ishga tushadigan birliklar va ularning host ilova / qurilma bilan
aloqasi.

```mermaid
C4Container
    title 2-daraja — Container: hikvision-isapi va uning atrofi

    Person(dev, "Laravel dasturchisi")
    Person(ops, "Integrator / ops")

    System_Boundary(sdk, "hikvision-isapi (Composer paketi)") {
        Container(lib, "Kutubxona", "PHP 8.2+, illuminate/support, Guzzle 7", "Host protsessiga yuklanadi. ServiceProvider, DeviceManager, HikvisionClient, 8 ta servis, DTO/Enum, istisnolar.")
        Container(probe, "hikvision-probe CLI", "PHP skript, bin/", "Faqat o'qiydigan diagnostika: sig'im, paging xatti-harakati, rad javoblari. JSON hisobot yozadi. Laravel'siz ishlaydi.")
    }

    System_Boundary(hostb, "Host Laravel ilovasi (mijoz kodi)") {
        Container(app, "Ilova protsessi", "PHP-FPM / queue worker / artisan", "Kutubxonani IoC orqali ishlatadi. Webhook endpoint shu yerda.")
        ContainerDb(db, "Ilova DB", "MySQL / PostgreSQL", "Ixtiyoriy: terminals jadvali (DatabaseDeviceProvider, CallbackDeviceProvider::fromEloquent)")
        Container(cache, "Cache", "Redis / file", "Ixtiyoriy: CallbackDeviceProvider::fromCache")
        Container(cfg, "config/hikvision.php + .env", "Laravel config", "Standart qurilma ro'yxati, format (json/xml), timeout, verify_ssl")
    }

    System_Ext(terminal, "Hikvision terminal(lar)", "ISAPI HTTP server, Digest auth")
    Container_Ext(report, "hikvision-probe.json", "Fayl", "Qurilma identiteti va xatti-harakati. Shaxsiy ma'lumot yo'q.")

    Rel(dev, app, "Yozadi va ishga tushiradi")
    Rel(app, lib, "app()->make(...), Facade", "PHP chaqiruv")
    Rel(app, cfg, "O'qiydi")
    Rel(lib, cfg, "config('hikvision')", "ServiceProvider::register")
    Rel(lib, db, "Qurilma sozlamalarini yuklaydi", "DB::table / Eloquent, ixtiyoriy")
    Rel(lib, cache, "cache()->remember", "ixtiyoriy")
    Rel(lib, terminal, "ISAPI", "HTTP(S), Digest, JSON/XML/multipart")
    Rel(terminal, app, "Webhook voqealari", "HTTP POST XML")

    Rel(ops, probe, "Ishga tushiradi", "--host, HIKVISION_PASSWORD")
    Rel(probe, lib, "HikvisionClient + HttpClient + DigestAuthenticator ni to'g'ridan-to'g'ri quradi")
    Rel(probe, terminal, "Faqat GET/POST-search so'rovlari", "ISAPI")
    Rel(probe, report, "Yozadi", "JSON")

    UpdateLayoutConfig($c4ShapeInRow="3", $c4BoundaryInRow="2")
```

**Konteynerlar haqida**

| Konteyner | Kirish nuqtasi | Ishga tushish sharti |
|---|---|---|
| Kutubxona | `HikvisionIsapiServiceProvider` (composer `extra.laravel.providers` orqali auto-discovery) | Laravel 12/13 ilovasi |
| `hikvision-probe` | `bin/hikvision-probe` (composer `bin`) | Faqat PHP + Composer autoload. Laravel container **kerak emas** — clientni qo'lda `new` qiladi |

---

## 3-daraja. Component

Kutubxona bir necha qatlamdan iborat. Katta bitta diagramma o'qib bo'lmaydigan
darajada zich bo'lgani uchun uchga bo'lingan: **(3a)** bootstrap va qurilma
tanlash, **(3b)** servis qatlami, **(3c)** probe.

### 3a. Bootstrap, qurilma tanlash va transport

```mermaid
C4Component
    title 3a — Component: bootstrap, DeviceManager, transport

    Container_Boundary(lib, "Kutubxona") {
        Component(sp, "HikvisionIsapiServiceProvider", "Laravel ServiceProvider", "Config merge, interface→impl bindinglar, DeviceManager va HikvisionClient singleton, 7 ta servis singleton, config publish")
        Component(fac1, "Hikvision (Facade)", "Facade → DeviceManager", "Hikvision::device('exit'), ::availableDevices()")
        Component(fac2, "HikvisionIsapi (Facade)", "Facade → HikvisionClient", "Standart qurilma clienti")

        Component(dm, "DeviceManager", "Client\\DeviceManager", "Qurilma nomi → HikvisionClient. Clientlarni keshlaydi, provider almashtiradi, runtime'da qurilma qo'shadi")
        Component(dpi, "DeviceProviderInterface", "Contract", "getDeviceNames, getDeviceConfig, getDefaultDevice, hasDevice, getGlobalConfig")
        Component(pcfg, "ConfigDeviceProvider", "Providers", "config('hikvision') massividan. Standart, o'zgarmas")
        Component(pdb, "DatabaseDeviceProvider", "Providers", "DB::table(terminals) + ustun map + TTL kesh. fromModel()")
        Component(pcb, "CallbackDeviceProvider", "Providers", "Closure'lar. fromEloquent(), fromArray(), fromCache()")

        Component(hc, "HikvisionClient", "Client\\HikvisionClient", "Bitta qurilma: baseUrl, auth opsiyalari, format. get/post/put/putXml/delete/postMultipart/putMultipart. URI va header quradi")
        Component(hci, "HttpClientInterface", "Contract", "Transportni almashtirish nuqtasi (testlarda RecordingHttpClient)")
        Component(http, "HttpClient", "Client\\HttpClient (Guzzle)", "So'rov yuboradi, JSON/XML → array, array → XML, Guzzle istisnolarini domen istisnolariga aylantiradi")
        Component(ai, "AuthenticatorInterface", "Contract", "buildAuthOptions(username, password)")
        Component(auth, "DigestAuthenticator", "Authentication", "['auth' => [user, pass, 'digest']]")
        Component(exc, "Exceptions", "HikvisionException + 5 subclass", "statusCode(), responseBody(), isRetryable()")
    }

    System_Ext(terminal, "Hikvision terminal", "ISAPI")
    Container_Ext(app, "Host ilova", "IoC, config, DB")

    Rel(app, sp, "Auto-discovery, register/boot")
    Rel(app, fac1, "Chaqiradi")
    Rel(app, fac2, "Chaqiradi")
    Rel(sp, dm, "singleton", "hikvision.device.provider bo'lsa uni, aks holda config('hikvision')")
    Rel(sp, hc, "singleton", "DeviceManager::default()")
    Rel(fac1, dm, "resolve")
    Rel(fac2, hc, "resolve")

    Rel(dm, dpi, "Qurilma sozlamasini so'raydi")
    Rel(pcfg, dpi, "implements")
    Rel(pdb, dpi, "implements")
    Rel(pcb, dpi, "implements")
    Rel(dm, hc, "Har qurilma uchun bittadan yaratadi va keshlaydi")

    Rel(hc, ai, "Auth opsiyalari")
    Rel(auth, ai, "implements")
    Rel(hc, hci, "So'rov yuboradi")
    Rel(http, hci, "implements")
    Rel(http, exc, "throw", "401→Authentication, connect→DeviceUnreachable, 408/429/5xx→DeviceBusy")
    Rel(http, terminal, "Guzzle request", "HTTP(S) Digest")

    UpdateLayoutConfig($c4ShapeInRow="4", $c4BoundaryInRow="1")
```

**Mas'uliyatlar qanday bo'lingan**

| Komponent | Biladi | Bilmaydi |
|---|---|---|
| `DeviceManager` | Qurilma nomlari, provider, client keshi | HTTP, ISAPI yo'llari |
| `DeviceProviderInterface` impl'lari | Sozlama qayerdan keladi (config/DB/closure) | Client qanday quriladi |
| `HikvisionClient` | Bitta qurilmaning baseUrl'i, format (`json`/`xml`), auth opsiyalari, `?format=` query, `Accept`/`Content-Type` | Guzzle, XML serializatsiya, istisno turlari |
| `HttpClient` | Guzzle, JSON/XML kodlash, istisno tasnifi | Qurilma, auth usuli, ISAPI semantikasi |
| `DigestAuthenticator` | Guzzle `auth` opsiyasi | Hammasi boshqa |

Bog'liqlik yo'nalishi bir tomonlama: `Services → HikvisionClient → {HttpClientInterface, AuthenticatorInterface}`.
Ikkala interfeys ham ServiceProvider'da bind qilingan, shuning uchun test yoki
boshqa transport (masalan, Laravel `Http` facade) uchun almashtirish mumkin.

### 3b. Servis qatlami (domen operatsiyalari)

Har bir servis `HikvisionClient` ni konstruktor orqali oladi va ISAPI yo'llarini
`private const ENDPOINT_*` sifatida saqlaydi. Servislar bir-biriga bog'liq emas.

```mermaid
C4Component
    title 3b — Component: servislar, DTO, enum

    Container_Boundary(svc, "Services (Shaykhnazar\\HikvisionIsapi\\Services)") {
        Component(sDev, "DeviceService", "System", "getInfo, getCapabilities, getStatus, isOnline — /ISAPI/System/*, /AccessControl/capabilities")
        Component(sPer, "PersonService", "UserInfo", "count, search, all(), add, update, apply, delete, uploadFace — /AccessControl/UserInfo/*")
        Component(sCard, "CardService", "CardInfo", "count, search, all(), add, update, delete, deleteAll, batchAdd — /AccessControl/CardInfo/*")
        Component(sFace, "FaceService", "FDLib", "libraries, uploadFace, uploadFaceDataRecord (multipart), searchFace, count, setup, modify, delete — /Intelligent/FDLib/*")
        Component(sFp, "FingerprintService", "FingerPrint", "capabilities, search, add, capture, delete — /AccessControl/FingerPrint/*")
        Component(sAcs, "AccessControlService", "RemoteControl", "openDoor, closeDoor, getDoorStatus — /AccessControl/RemoteControl/door, DoorStatus")
        Component(sEv, "EventService", "AcsEvent", "search, between, count, subscribe — /AccessControl/AcsEvent*, /Event/notification/subscribeEvent")
        Component(sEn, "EventNotificationService", "httpHosts (XML)", "configureWebhook, configureHttpHost, enable/disable/remove, test — /Event/notification/httpHosts/{id}")
        Component(pag, "PagesSearchResults", "trait (Concerns)", "walkSearch(): bitta searchID, position bo'yicha yuradi, responseStatusStrg=MORE to'xtaguncha")
    }

    Container_Boundary(model, "Model") {
        Component(dto, "DTOs", "final readonly", "Person, Card, Face — toArray() ISAPI shakli, fromArray()")
        Component(enum, "Enums", "string-backed", "UserType (normal/visitor/blackList), EventType (major/minor kodlari)")
    }

    Component(hc, "HikvisionClient", "Client", "get/post/put/putXml/delete/postMultipart/putMultipart")
    Container_Ext(app, "Host ilova", "app(PersonService::class) …")

    Rel(app, sPer, "chaqiradi")
    Rel(app, sEn, "chaqiradi")
    Rel(app, dto, "quradi", "new Person(...)")

    Rel(sPer, pag, "uses")
    Rel(sCard, pag, "uses")
    Rel(sPer, dto, "Person::toArray()")
    Rel(sCard, dto, "Card::toArray()")
    Rel(dto, enum, "UserType")

    Rel(sDev, hc, "get")
    Rel(sPer, hc, "get/post/put")
    Rel(sCard, hc, "get/post/put")
    Rel(sFace, hc, "get/post/put/delete/postMultipart/putMultipart")
    Rel(sFp, hc, "get/post/put")
    Rel(sAcs, hc, "get/put")
    Rel(sEv, hc, "post")
    Rel(sEn, hc, "get/putXml/post/delete")

    UpdateLayoutConfig($c4ShapeInRow="4", $c4BoundaryInRow="1")
```

**Servis → ISAPI endpoint xaritasi** (koddagi konstantalardan):

| Servis | Endpointlar | Format / metod |
|---|---|---|
| `DeviceService` | `/ISAPI/System/deviceInfo`, `/ISAPI/System/status`, `/ISAPI/AccessControl/capabilities` | GET |
| `PersonService` | `/ISAPI/AccessControl/UserInfo/{Capabilities,Count,Search,Record,Modify,SetUp,Delete}` | GET, POST, PUT |
| `CardService` | `/ISAPI/AccessControl/CardInfo/{capabilities,Count,Search,Record,Modify,Delete}` | GET, POST, PUT |
| `FaceService` | `/ISAPI/Intelligent/FDLib`, `…/capabilities`, `…/{fdid}/picture[/{fpid}]`, `…/FDSearch[/Delete]`, `…/FaceDataRecord`, `…/Count`, `…/FDSetUp`, `…/FDModify` | GET, POST, PUT, DELETE, **multipart** |
| `FingerprintService` | `/ISAPI/AccessControl/FingerPrint/{capabilities,Search,Record,Delete}`, `/ISAPI/AccessControl/CaptureFingerPrint` | GET, POST, PUT |
| `AccessControlService` | `/ISAPI/AccessControl/RemoteControl/door/{n}`, `/ISAPI/AccessControl/DoorStatus/{n}` | PUT, GET |
| `EventService` | `/ISAPI/AccessControl/AcsEvent`, `/ISAPI/AccessControl/AcsEventTotalNum`, `/ISAPI/Event/notification/subscribeEvent` | POST |
| `EventNotificationService` | `/ISAPI/Event/notification/httpHosts[/{id}[/test]]`, `/ISAPI/Event/notification/capabilities` | GET, **PUT XML** (qurilma bu endpoint uchun XML talab qiladi), POST, DELETE |

### 3c. Probe

```mermaid
C4Component
    title 3c — Component: hikvision-probe

    Person(ops, "Integrator / ops")

    Container_Boundary(probe, "hikvision-probe CLI") {
        Component(bin, "bin/hikvision-probe", "PHP skript", "Argumentlarni o'qiydi, parolni env yoki TTY'dan oladi (argv'dan emas), clientni quradi, hisobotni faylga yozadi")
        Component(dp, "DeviceProbe", "Probe\\DeviceProbe", "run(): capabilities → person paging shakli → event total kaliti → rad javoblari (401, 404). Faqat o'qiydi, qator qiymatlarini tashlab yuboradi")
    }

    Component(hc, "HikvisionClient", "Kutubxona", "new HikvisionClient(new HttpClient, new DigestAuthenticator, config)")
    System_Ext(terminal, "Hikvision terminal", "ISAPI")
    Container_Ext(report, "hikvision-probe.json", "Fayl")

    Rel(ops, bin, "php vendor/bin/hikvision-probe --host=…")
    Rel(bin, dp, "new DeviceProbe(client, out)->run()")
    Rel(bin, hc, "Qo'lda quradi (Laravel'siz)")
    Rel(dp, hc, "get / post (Search)")
    Rel(hc, terminal, "ISAPI")
    Rel(bin, report, "file_put_contents", "JSON")

    UpdateLayoutConfig($c4ShapeInRow="3", $c4BoundaryInRow="1")
```

`DeviceProbe` va CLI ataylab ajratilgan: **so'rash** (`DeviceProbe`) test
qilinadi, **I/O** (parol, fayl) faqat skriptda. Probe `HikvisionException::statusCode()`
va `responseBody()` orqali qurilmaning `subStatusCode` ini hisobotga yozadi —
ya'ni 3a'dagi istisno dizayni shu yerda to'g'ridan-to'g'ri ishlatiladi.

---

## 4-daraja. Code (tanlangan oqimlar)

### 4a. Bitta so'rovning yo'li

`PersonService::add()` misolida — har qanday servis chaqiruvi shu quvurdan o'tadi.

```mermaid
sequenceDiagram
    autonumber
    participant App as Host ilova
    participant PS as PersonService
    participant HC as HikvisionClient
    participant H as HttpClient (Guzzle)
    participant T as Terminal (ISAPI)

    App->>PS: add(Person $person)
    PS->>HC: post('/ISAPI/AccessControl/UserInfo/Record', $person->toArray())
    Note over HC: buildUri(): baseUrl + endpoint + ?format=json|xml<br/>buildOptions(): auth(digest), timeout, verify, Accept/Content-Type<br/>options['_format'] = format
    HC->>H: post($uri, $data, $options)
    alt format = json
        H->>H: options['json'] = $data
    else format = xml
        H->>H: options['body'] = arrayToXml($data)<br/>(bitta kalit → root element, xmlns isapi.org/ver20)
    end
    H->>T: POST … (Digest challenge/response Guzzle ichida)
    alt 2xx
        T-->>H: body + Content-Type
        H->>H: JSON → json_decode, XML → xmlToArray (libxml xatolari yutiladi), boshqa → ['raw' => body]
        H-->>HC: array
        HC-->>PS: array
        PS-->>App: array (ResponseStatus …)
    else xato
        T-->>H: 401 / 404 / 5xx yoki ulanish yo'q
        H->>H: classify(): ConnectException → DeviceUnreachable<br/>401 → Authentication, 408/429/5xx → DeviceBusy(retryable), boshqa → HikvisionException
        H-->>App: throw HikvisionException(statusCode, responseBody)
    end
```

### 4b. Ko'p qurilma: nom → client

```mermaid
sequenceDiagram
    autonumber
    participant App as Host ilova
    participant F as Hikvision (Facade)
    participant DM as DeviceManager
    participant P as DeviceProviderInterface
    participant HC as HikvisionClient

    App->>F: Hikvision::device('exit')
    F->>DM: device('exit')
    alt client keshda bor
        DM-->>App: clients['exit']
    else yo'q
        DM->>P: hasDevice('exit')
        P-->>DM: true
        DM->>P: getDeviceConfig('exit'), getGlobalConfig()
        P-->>DM: [ip, port, username, password, protocol, timeout, verify_ssl], [format, logging]
        DM->>HC: new HikvisionClient(httpClient, authenticator, merged config)
        Note over HC: initialize(): username/password bo'lishi shart,<br/>baseUrl = protocol://ip:port, authOptions, format
        DM->>DM: clients['exit'] = client
        DM-->>App: client
    end
    App->>App: new PersonService($client)->all()
```

`DeviceManager` **singleton**, `HikvisionClient` esa **qurilma boshiga bitta**
(ServiceProvider'dagi `HikvisionClient::class` singleton — bu faqat *standart*
qurilma). Servis singletonlari ham standart qurilmaga bog'langan; boshqa qurilma
uchun servisni qo'lda `new` qilish kerak (README shunday ko'rsatadi).

### 4c. Sahifalab yurish (`all()`)

```mermaid
sequenceDiagram
    autonumber
    participant App as Host ilova
    participant PS as PersonService (PagesSearchResults)
    participant T as Terminal

    App->>PS: foreach (all(pageSize: 30) as $person)
    PS->>PS: searchId = bin2hex(random_bytes(8)), position = 0
    loop responseStatusStrg == MORE va qator qaytgan ekan
        PS->>T: POST UserInfo/Search {searchID, searchResultPosition, maxResults}
        T-->>PS: UserInfoSearch {UserInfo[], responseStatusStrg}
        PS-->>App: yield Person::fromArray(row) …
        PS->>PS: position += count(rows)
    end
```

Nima uchun trait: ISAPI `searchID` ni **qidiruv sessiyasi** deb hisoblaydi;
o'rtada o'zgartirilsa qurilma jimgina boshidan boshlaydi. `PersonService` va
`CardService` bir xil mexanizmni bo'lishadi, `EventService` esa o'zining
`between()` yuruvchisiga ega (u ham `searchID` ni ushlab turadi).

### 4d. Istisno ierarxiyasi

```mermaid
classDiagram
    class Exception
    class HikvisionException {
        -?int statusCode
        -?string responseBody
        +statusCode() ?int
        +responseBody() ?string
        +isRetryable() bool = false
    }
    class AuthenticationException
    class DeviceUnreachableException
    class DeviceBusyException {
        +isRetryable() bool = true
    }
    class DeviceNotFoundException
    class InvalidResponseException

    Exception <|-- HikvisionException
    HikvisionException <|-- AuthenticationException
    HikvisionException <|-- DeviceUnreachableException
    HikvisionException <|-- DeviceBusyException
    HikvisionException <|-- DeviceNotFoundException
    HikvisionException <|-- InvalidResponseException
```

Tasniflash **bitta joyda** — `HttpClient::classify()`. Chaqiruvchi kod uchun
shartnoma: `isRetryable()` true bo'lsa qayta urinish mumkin (408/429/5xx),
`statusCode()`/`responseBody()` orqali qurilmaning `subStatusCode` ini o'qish
mumkin (probe shunday qiladi). `DeviceNotFoundException` va
`InvalidResponseException` hozircha `src/` ichida throw qilinmaydi — tashqi
kod uchun zaxira.

---

## 5. Deployment ko'rinishi

Paket ikki xil joyda deploy bo'ladi. Rejadagi mahsulotda ikkalasi ham bor
(qarang [`../startup/02-arxitektura.md`](../startup/02-arxitektura.md)).

```mermaid
C4Deployment
    title 5 — Deployment: paket qayerda ishlaydi

    Deployment_Node(lan, "Mijoz LAN'i", "192.168.x.x, NAT ortida") {
        Deployment_Node(term, "Hikvision terminal", "Firmware, ISAPI HTTP :80/:443") {
            Container(isapi, "ISAPI server", "HTTP Digest", "UserInfo, CardInfo, FDLib, AcsEvent, httpHosts …")
        }
        Deployment_Node(edge, "Edge host (ixtiyoriy)", "Docker, PHP") {
            Container(agent, "Edge agent / lokal Laravel", "PHP + hikvision-isapi", "Qurilma bilan LAN ichida gaplashadi, webhook'ni lokalda qabul qiladi")
        }
        Deployment_Node(laptop, "Integrator noutbuki", "PHP CLI") {
            Container(probe, "hikvision-probe", "bin/", "Bir martalik diagnostika")
        }
    }

    Deployment_Node(cloud, "Server / bulut", "Linux, PHP-FPM, queue") {
        Container(host, "Host Laravel ilovasi", "PHP + hikvision-isapi", "Terminal to'g'ridan-to'g'ri ko'rinsa (VPN, port-forward) shu yerdan ISAPI chaqiradi")
        ContainerDb(db, "DB", "MySQL/PostgreSQL", "terminals jadvali")
    }

    Rel(agent, isapi, "ISAPI", "HTTP LAN")
    Rel(isapi, agent, "webhook", "HTTP POST XML")
    Rel(probe, isapi, "ISAPI (faqat o'qish)", "HTTP LAN")
    Rel(host, isapi, "ISAPI", "HTTP(S), VPN yoki port-forward orqali")
    Rel(host, db, "DatabaseDeviceProvider", "SQL")
```

Paketning o'zi bu ikki holatni farqlamaydi — u faqat `ip:port` ga HTTP qiladi.
NAT muammosi (bulutdan terminalni ko'rib bo'lmasligi) paketdan tashqarida,
edge agent bilan hal qilinadi.

---

## 6. Arxitektura qarorlari (koddan o'qilgan)

| # | Qaror | Nima uchun / oqibati |
|---|---|---|
| 1 | Transport (`HttpClientInterface`) va auth (`AuthenticatorInterface`) — kontrakt orqali | Testlar Guzzle'siz ishlaydi (`tests/Support/RecordingHttpClient`); boshqa auth (masalan, session token) qo'shish uchun bitta sinf yetadi |
| 2 | Qurilma manbai — `DeviceProviderInterface` | Config, DB, Eloquent, cache, multi-tenant closure — hammasi `DeviceManager` ga bir xil ko'rinadi. Host ilova `hikvision.device.provider` ni bind qilib almashtiradi |
| 3 | Bitta `HikvisionClient` = bitta qurilma | Client konstruktorda baseUrl/auth'ni hisoblab qo'yadi; `DeviceManager` ularni nom bo'yicha keshlaydi |
| 4 | Format global (`json`/`xml`), lekin endpoint bo'yicha bekor qilinadi (`putXml`) | Ko'p endpointlar JSON'ni qabul qiladi, `httpHosts` faqat XML — servis buni chaqiruvchidan yashiradi |
| 5 | Javob har doim `array` | Servislar Guzzle `Response` ni ko'rmaydi; JSON ham, XML ham bir xil shaklga keladi; noma'lum Content-Type `['raw' => …]` |
| 6 | Istisnolar transport qatlamida tasniflanadi, `isRetryable()` bilan | Chaqiruvchi retry siyosatini HTTP kodlarini bilmasdan yozadi |
| 7 | `#[\SensitiveParameter]` parol, yuz rasmi, so'rov tanasida | Stack trace'da PII/parol ko'rinmaydi (C5 tuzatishi) |
| 8 | Paging — trait, `searchID` sessiyasi bilan | ISAPI'ning "searchID o'zgarsa boshidan boshla" xususiyatiga to'g'ri munosabat |
| 9 | Probe faqat o'qiydi va qator qiymatlarini tashlaydi | Ishlab turgan obyektda xavfsiz; hisobotni PR'ga qo'shish mumkin |
| 10 | Route/controller/migratsiya yo'q | Paket SDK bo'lib qoladi; biznes mantiq (tenant, davomat) alohida repolarda |

## 7. Modelni o'qiganda ko'ringan nomuvofiqliklar

Bular arxitektura hujjatining bir qismi emas, lekin diagrammani kod bilan
solishtirganda chiqdi. Tuzatish alohida ish.

1. **`EventNotificationService` ServiceProvider'da ro'yxatga olinmagan.**
   `registerServices()` 7 ta servisni singleton qiladi, sakkizinchisi yo'q.
   `app(EventNotificationService::class)` Laravel auto-wiring tufayli ishlaydi
   (konstruktor `HikvisionClient` so'raydi, u bind qilingan), lekin har safar
   yangi nusxa bo'ladi. Diagramma 3a'da "7 ta servis" deb yozilgani shundan.
2. **`CallbackDeviceProvider::fromCache()` `getDeviceConfig` uchun `stdClass` qaytaradi**
   (`DB::table(...)->first()`), interfeys esa `?array` va'da qiladi.
   `HikvisionClient::initialize()` `$device['username']` deb o'qiydi — bu
   provider bilan birinchi so'rovdayoq `Cannot use object of type stdClass as array`
   bo'ladi. Jadval nomi ham `terminals` deb qotirilgan.
3. **`DeviceManager::registerDevice()` faqat `ConfigDeviceProvider` bilan ishlaydi**;
   boshqa provider bo'lsa jimgina hech narsa qilmaydi.
4. **`config('hikvision.logging')`** hamma providerda `getGlobalConfig()` orqali
   tashiladi, lekin `src/` da hech qayerda o'qilmaydi — hozircha o'lik sozlama.
5. **`DatabaseDeviceProvider::fromModel()`** `$query` callback'ni qabul qiladi
   va ishlatmaydi; `configColumns` bo'sh massiv berilsa qurilma sozlamasi bo'sh
   chiqadi (konstruktor default'i qo'llanmaydi).

---

## Fayllar

- [`c4-model.md`](c4-model.md) — shu hujjat (Mermaid).
- [`workspace.dsl`](workspace.dsl) — xuddi shu model Structurizr DSL'da:
  Context, Container, Component (kutubxona), Deployment ko'rinishlari.
