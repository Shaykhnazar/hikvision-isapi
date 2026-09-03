// C4 modeli — Structurizr DSL.
// Ko'rish: https://structurizr.com/dsl ga qo'ying yoki `structurizr-cli export -w workspace.dsl -f mermaid`.
// Mermaid ko'rinishi va izohlar: c4-model.md

workspace "hikvision-isapi" "Hikvision ISAPI uchun Laravel SDK — C4 arxitektura modeli" {

    !identifiers hierarchical

    model {
        dev = person "Laravel dasturchisi" "Davomat / kirish nazorati ilovasini yozadi. Paketni composer orqali o'rnatadi."
        ops = person "Integrator / ops" "Obyektda terminalni ulaydi, bin/hikvision-probe ni ishga tushiradi."

        terminal = softwareSystem "Hikvision terminal(lar)" "Yuz tanish / kirish nazorati qurilmasi. ISAPI HTTP serveri, Digest auth, JSON yoki XML." {
            tags "External"
        }

        host = softwareSystem "Host Laravel ilovasi" "Paketni ichiga olgan mijoz ilovasi." {
            tags "External"
            app = container "Ilova protsessi" "PHP-FPM / queue worker / artisan. Kutubxonani IoC orqali ishlatadi; webhook endpoint shu yerda." "PHP, Laravel 12/13"
            cfg = container "config/hikvision.php + .env" "Standart qurilma ro'yxati, format, timeout, verify_ssl." "Laravel config"
            db = container "Ilova DB" "Ixtiyoriy: terminals jadvali." "MySQL / PostgreSQL" {
                tags "Database"
            }
            cache = container "Cache" "Ixtiyoriy: CallbackDeviceProvider::fromCache." "Redis / file"
        }

        sdk = softwareSystem "hikvision-isapi" "Laravel paketi (PHP 8.2+). Hikvision ISAPI uchun tipli, ko'p-qurilmali SDK." {
            lib = container "Kutubxona" "Host protsessiga yuklanadi: ServiceProvider, DeviceManager, HikvisionClient, servislar, DTO/Enum, istisnolar." "PHP 8.2+, illuminate/support, Guzzle 7" {
                // bootstrap
                sp = component "HikvisionIsapiServiceProvider" "Config merge, interface→impl bindinglar, DeviceManager va HikvisionClient singleton, servis singletonlari, config publish." "Laravel ServiceProvider"
                facHik = component "Hikvision (Facade)" "Hikvision::device('exit'), ::availableDevices()." "Facade → DeviceManager"
                facIsapi = component "HikvisionIsapi (Facade)" "Standart qurilma clienti." "Facade → HikvisionClient"

                // device selection
                dm = component "DeviceManager" "Qurilma nomi → HikvisionClient. Clientlarni keshlaydi, provider almashtiradi, runtime'da qurilma qo'shadi." "Client\\DeviceManager"
                dpi = component "DeviceProviderInterface" "getDeviceNames, getDeviceConfig, getDefaultDevice, hasDevice, getGlobalConfig." "Contract"
                pCfg = component "ConfigDeviceProvider" "config('hikvision') massividan; standart, o'zgarmas." "Providers"
                pDb = component "DatabaseDeviceProvider" "DB::table(terminals) + ustun map + TTL kesh; fromModel()." "Providers"
                pCb = component "CallbackDeviceProvider" "Closure'lar; fromEloquent(), fromArray(), fromCache()." "Providers"

                // transport
                hc = component "HikvisionClient" "Bitta qurilma: baseUrl, auth opsiyalari, format. get/post/put/putXml/delete/postMultipart/putMultipart." "Client\\HikvisionClient"
                hci = component "HttpClientInterface" "Transportni almashtirish nuqtasi." "Contract"
                http = component "HttpClient" "Guzzle; JSON/XML ↔ array; Guzzle istisnolarini domen istisnolariga aylantiradi." "Client\\HttpClient"
                ai = component "AuthenticatorInterface" "buildAuthOptions(username, password)." "Contract"
                auth = component "DigestAuthenticator" "['auth' => [user, pass, 'digest']]." "Authentication"
                exc = component "Exceptions" "HikvisionException + Authentication, DeviceUnreachable, DeviceBusy(retryable), DeviceNotFound, InvalidResponse." "Exceptions"

                // services
                sDev = component "DeviceService" "getInfo, getCapabilities, getStatus, isOnline." "Services — /ISAPI/System/*"
                sPer = component "PersonService" "count, search, all(), add, update, apply, delete, uploadFace." "Services — /AccessControl/UserInfo/*"
                sCard = component "CardService" "count, search, all(), add, update, delete, deleteAll, batchAdd." "Services — /AccessControl/CardInfo/*"
                sFace = component "FaceService" "libraries, uploadFace, uploadFaceDataRecord, searchFace, count, setup, modify, delete." "Services — /Intelligent/FDLib/*"
                sFp = component "FingerprintService" "capabilities, search, add, capture, delete." "Services — /AccessControl/FingerPrint/*"
                sAcs = component "AccessControlService" "openDoor, closeDoor, getDoorStatus." "Services — /AccessControl/RemoteControl/door"
                sEv = component "EventService" "search, between, count, subscribe." "Services — /AccessControl/AcsEvent*"
                sEn = component "EventNotificationService" "configureWebhook, configureHttpHost, enable/disable/remove, test." "Services — /Event/notification/httpHosts (XML)"
                pag = component "PagesSearchResults" "walkSearch(): bitta searchID, position bo'yicha, MORE to'xtaguncha." "trait"

                // model
                dto = component "DTOs" "Person, Card, Face — toArray() ISAPI shakli, fromArray()." "final readonly"
                enum = component "Enums" "UserType, EventType." "string-backed enum"
            }

            probe = container "hikvision-probe CLI" "Faqat o'qiydigan diagnostika: sig'im, paging xatti-harakati, rad javoblari. JSON hisobot yozadi. Laravel'siz." "PHP skript, bin/" {
                bin = component "bin/hikvision-probe" "Argumentlar, parol (env yoki TTY), client qurish, faylga yozish." "PHP skript"
                dp = component "DeviceProbe" "run(): capabilities → person paging → event total → rad javoblari. Faqat o'qiydi." "Probe\\DeviceProbe"
            }
        }

        report = softwareSystem "hikvision-probe.json" "Qurilma identiteti va xatti-harakati. Shaxsiy ma'lumot yo'q." {
            tags "External" "File"
        }

        // ---- context-level relationships
        dev -> host "Ishlab chiqadi, deploy qiladi"
        dev -> sdk "Servis API'sini chaqiradi" "app(PersonService::class), Hikvision::device()"
        ops -> sdk "Qurilmani tekshiradi" "php vendor/bin/hikvision-probe"
        host -> sdk "Yuklaydi va sozlaydi" "Composer, ServiceProvider, config, hikvision.device.provider"
        sdk -> terminal "ISAPI so'rovlari" "HTTP(S), Digest, JSON/XML/multipart"
        terminal -> host "Voqealarni push qiladi (webhook)" "HTTP POST XML"

        // ---- container-level
        dev -> host.app "Yozadi va ishga tushiradi"
        host.app -> sdk.lib "app()->make(...), Facade" "PHP"
        host.app -> host.cfg "O'qiydi"
        sdk.lib -> host.cfg "config('hikvision')" "ServiceProvider::register"
        sdk.lib -> host.db "Qurilma sozlamalarini yuklaydi" "DB::table / Eloquent"
        sdk.lib -> host.cache "cache()->remember"
        sdk.lib -> terminal "ISAPI" "HTTP(S), Digest, JSON/XML/multipart"
        terminal -> host.app "Webhook voqealari" "HTTP POST XML"
        ops -> sdk.probe "Ishga tushiradi" "--host, HIKVISION_PASSWORD"
        sdk.probe -> sdk.lib "HikvisionClient + HttpClient + DigestAuthenticator ni to'g'ridan-to'g'ri quradi"
        sdk.probe -> terminal "Faqat GET / POST-search" "ISAPI"
        sdk.probe -> report "Yozadi" "JSON"

        // ---- component-level: bootstrap & transport
        host.app -> sdk.lib.sp "Auto-discovery, register/boot"
        host.app -> sdk.lib.facHik "Chaqiradi"
        host.app -> sdk.lib.facIsapi "Chaqiradi"
        sdk.lib.sp -> sdk.lib.dm "singleton" "hikvision.device.provider yoki config('hikvision')"
        sdk.lib.sp -> sdk.lib.hc "singleton" "DeviceManager::default()"
        sdk.lib.facHik -> sdk.lib.dm "resolve"
        sdk.lib.facIsapi -> sdk.lib.hc "resolve"
        sdk.lib.dm -> sdk.lib.dpi "Qurilma sozlamasini so'raydi"
        sdk.lib.pCfg -> sdk.lib.dpi "implements"
        sdk.lib.pDb -> sdk.lib.dpi "implements"
        sdk.lib.pCb -> sdk.lib.dpi "implements"
        sdk.lib.pDb -> host.db "DB::table" "SQL"
        sdk.lib.pCb -> host.db "Eloquent / DB" "SQL"
        sdk.lib.pCb -> host.cache "cache()->remember"
        sdk.lib.dm -> sdk.lib.hc "Har qurilma uchun bittadan yaratadi va keshlaydi"
        sdk.lib.hc -> sdk.lib.ai "Auth opsiyalari"
        sdk.lib.auth -> sdk.lib.ai "implements"
        sdk.lib.hc -> sdk.lib.hci "So'rov yuboradi"
        sdk.lib.http -> sdk.lib.hci "implements"
        sdk.lib.http -> sdk.lib.exc "throw" "401→Authentication, connect→DeviceUnreachable, 408/429/5xx→DeviceBusy"
        sdk.lib.http -> terminal "Guzzle request" "HTTP(S) Digest"

        // ---- component-level: services
        host.app -> sdk.lib.sPer "chaqiradi"
        host.app -> sdk.lib.sEn "chaqiradi"
        host.app -> sdk.lib.dto "quradi" "new Person(...)"
        sdk.lib.sPer -> sdk.lib.pag "uses"
        sdk.lib.sCard -> sdk.lib.pag "uses"
        sdk.lib.sPer -> sdk.lib.dto "Person::toArray()"
        sdk.lib.sCard -> sdk.lib.dto "Card::toArray()"
        sdk.lib.dto -> sdk.lib.enum "UserType"
        sdk.lib.sDev -> sdk.lib.hc "get"
        sdk.lib.sPer -> sdk.lib.hc "get/post/put"
        sdk.lib.sCard -> sdk.lib.hc "get/post/put"
        sdk.lib.sFace -> sdk.lib.hc "get/post/put/delete/multipart"
        sdk.lib.sFp -> sdk.lib.hc "get/post/put"
        sdk.lib.sAcs -> sdk.lib.hc "get/put"
        sdk.lib.sEv -> sdk.lib.hc "post"
        sdk.lib.sEn -> sdk.lib.hc "get/putXml/post/delete"

        // ---- component-level: probe
        ops -> sdk.probe.bin "php vendor/bin/hikvision-probe --host=…"
        sdk.probe.bin -> sdk.probe.dp "new DeviceProbe(client, out)->run()"
        sdk.probe.bin -> sdk.lib.hc "Qo'lda quradi (Laravel'siz)"
        sdk.probe.dp -> sdk.lib.hc "get / post (Search)"
        sdk.probe.bin -> report "file_put_contents" "JSON"

        // ---- deployment
        deploymentEnvironment "Ishlab chiqarish" {
            deploymentNode "Mijoz LAN'i" "192.168.x.x, NAT ortida" {
                deploymentNode "Hikvision terminal" "Firmware, ISAPI HTTP :80/:443" {
                    softwareSystemInstance terminal
                }
                deploymentNode "Edge host (ixtiyoriy)" "Docker, PHP" {
                    containerInstance sdk.lib
                }
                deploymentNode "Integrator noutbuki" "PHP CLI" {
                    containerInstance sdk.probe
                }
            }
            deploymentNode "Server / bulut" "Linux, PHP-FPM, queue" {
                containerInstance host.app
                containerInstance host.db
            }
        }
    }

    views {
        systemContext sdk "Context" "1-daraja — System Context" {
            include *
            autolayout lr
        }

        container sdk "Containers" "2-daraja — Container" {
            include *
            include host.app host.cfg host.db host.cache
            autolayout lr
        }

        component sdk.lib "Components_Bootstrap" "3a — bootstrap, DeviceManager, transport" {
            include sdk.lib.sp sdk.lib.facHik sdk.lib.facIsapi
            include sdk.lib.dm sdk.lib.dpi sdk.lib.pCfg sdk.lib.pDb sdk.lib.pCb
            include sdk.lib.hc sdk.lib.hci sdk.lib.http sdk.lib.ai sdk.lib.auth sdk.lib.exc
            include host.app host.db host.cache terminal
            autolayout lr
        }

        component sdk.lib "Components_Services" "3b — servislar, DTO, enum" {
            include sdk.lib.sDev sdk.lib.sPer sdk.lib.sCard sdk.lib.sFace sdk.lib.sFp sdk.lib.sAcs sdk.lib.sEv sdk.lib.sEn
            include sdk.lib.pag sdk.lib.dto sdk.lib.enum sdk.lib.hc
            include host.app
            autolayout lr
        }

        component sdk.probe "Components_Probe" "3c — hikvision-probe" {
            include *
            include sdk.lib.hc terminal report
            autolayout lr
        }

        deployment sdk "Ishlab chiqarish" "Deployment" "5 — paket qayerda ishlaydi" {
            include *
            autolayout lr
        }

        styles {
            element "Person" {
                shape person
                background #08427b
                color #ffffff
            }
            element "Software System" {
                background #1168bd
                color #ffffff
            }
            element "Container" {
                background #438dd5
                color #ffffff
            }
            element "Component" {
                background #85bbf0
                color #000000
            }
            element "External" {
                background #999999
                color #ffffff
            }
            element "Database" {
                shape cylinder
            }
            element "File" {
                shape folder
            }
        }
    }
}
