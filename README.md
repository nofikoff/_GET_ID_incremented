# get-id

Сервис централизованной выдачи инкрементальных номеров для ADR и спецификаций. Снимает гонку,
когда несколько разработчиков или AI-агентов заводят документы в одном репозитории одновременно.

Потребители — Claude Code через MCP и REST-клиенты. Ключ проекта выводится из origin репозитория,
сам проект и типы ключей заводит администратор.

Проектирование идёт через Spec Kit: `specs/NNN-<slug>/`.

## Развёртывание

| Что | Значение |
|-----|----------|
| Публичный адрес | `https://id.x3mal.com` |
| Сервер хостинга | `ruslan.pogonyalo.com` (CentOS 7, Apache под Virtualmin) |
| Каталог на сервере | `/home/develop/domains/id.x3mal.com` |
| Код приложения | `/home/develop/domains/id.x3mal.com/app` |
| DocumentRoot | `/home/develop/domains/id.x3mal.com/app/public` |
| PHP | 8.3 (remi) через fcgid |
| База | MariaDB 10.4, база `id`, пользователь `develop` |
| DNS и TLS | Cloudflare (проксирование включено) |

Хост отличается от окружения разработки в трёх местах. Все три — ловушки, и ни одна не
сообщает о себе понятной ошибкой:

- **СУБД.** Тесты гоняются на MySQL 8, а production работает на MariaDB 10.4 (`DB_CONNECTION=mariadb`).
- **PHP в консоли.** `php` в консоли хоста — это 8.2, а `disable_functions` в ini домена
  запрещает `proc_open`. Composer, artisan и cron поэтому запускаются через
  `bin/php-cli` каталога домена: он берёт ini домена и снимает запрет только для CLI.
- **Composer.** Системный composer на хосте версии 1.x. Laravel 13 ставится только через
  `bin/composer.phar` 2.x каталога домена.

`APP_URL=https://id.x3mal.com`, redirect URI в Google Cloud Console —
`https://id.x3mal.com/auth/google/callback`. Адрес должен совпадать с `APP_URL` дословно, иначе
вход через Google не проходит.

OAuth-клиент `get-id web` живёт в отдельном GCP-проекте `get-id-509510` под личным аккаунтом
владельца, а не в организации `cas.ai` и не в проекте teamlead-crm: у каждого продукта свой
`client_secret`. Аудитория External в статусе In production с 2026-09-24, branding подтверждён Google:
войти через Google может любой аккаунт, а домен `cas.ai` Google не ограничивает, его проверяет сервис
(`ALLOWED_EMAIL_DOMAIN`). Внутренней аудитории быть не может: проект не в организации.

Brand verification держится на публичных `/`, `/privacy` и `/terms` — их адреса записаны в Google
Auth Platform → Branding. Переименование маршрута или закрытие страницы входом снимает branding
при следующей проверке Google; новый логотип требует повторной проверки.

**Cloudflare стоит перед приложением, поэтому `trustProxies` в `bootstrap/app.php` обязателен.**
Без него `url()` генерирует `http://`, redirect URI перестаёт совпадать с зарегистрированным в
Google, и вход ломается с ошибкой, которая про прокси не говорит. Доверяется только
`X-Forwarded-Proto`: адрес клиента сервис нигде не использует (журнал обращений пишет учётную
запись и токен), а `X-Forwarded-Host` из доверенных исключён, чтобы запрос в обход Cloudflare не
мог подменить host.

Доступы, ключи и учётные данные в репозитории не хранятся.
