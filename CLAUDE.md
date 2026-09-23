# get-id

Что сервис обязан делать — `specs/001-incremental-id-registry/` (spec, contracts, data-model). Решения,
закрывающие альтернативы, — `docs/adr/`. Принципы — `.specify/memory/constitution.md`, они указывают
сюда и в ADR. Ниже — то, чего из кода не видно или что легко сломать.

## Выдача номера

- Номер выдаёт только `App\Domain\Sequence\SequenceIssuer`. REST-контроллер и MCP-tool его
  транспортируют и не держат своей логики выдачи, нормализации или проверки реестра. Блокировка,
  повтор по `name_slug` и дефект по `sequence_number` — [ADR-001](docs/adr/adr-001-sequence-locking.md).
  Вызывать вне открытой транзакции.
- Повтор тройки «проект + тип + тема» возвращает выданный номер с `is_new: false` и нового не
  создаёт — в том числе после гашения проекта, типа или пары: гашение останавливает новые номера, а
  выданные не отнимает (FR-015). Идемпотентность держат тесты, а не review:
  `tests/Feature/Sequence/IdempotencyTest.php`, `tests/Concurrency/ConcurrentSameNameTest.php`.
- Номер переиспользуется только после того, как администратор снял его с хвоста пары, — это делает
  `App\Domain\Sequence\SequenceWithdrawer` ([ADR-003](docs/adr/adr-003-withdraw-last-identifier.md)).
  Снятая тема выдаётся заново как первая выдача. Удаления из середины нет.
- `identifiers` — append-only: `AppendOnlyQueryBuilder` бросает на любую запись Eloquent, кроме
  вставки; открыт только `toBase()->withdraw()`, и зовёт его только `SequenceWithdrawer`. `DB::table()`
  и `truncate` открыты — ими чистит набор `Concurrency`. `down()` миграции реестра на непустой таблице
  бросает исключение. Проекты и типы гасятся `is_active`, а не `DELETE`.
- `project_key_type` — не pivot, а счётчик пары: строки не удаляются (ADR-001), выключение —
  `is_enabled`. Следующий номер считает только `ProjectKeyType::nextSequence()`.
- Сервис выдаёт только номер: имя документа — конвенция репозитория-потребителя, и правило трёх цифр
  живёт только в текстах для него — справке, описании `next_id` и instructions MCP-сервера
  ([ADR-004](docs/adr/adr-004-index-only-numbers.md)).

## Нормализация

- Ключ проекта всегда выводится из origin (`App\Domain\Project\ProjectKey`) и руками не задаётся.
  `project_key` в запросах проходит ту же нормализацию, поэтому ключ идемпотентен. Незарегистрированный
  ключ и не включённый в проекте тип — отказ, который называет ключ и следующий шаг; проект по факту
  обращения не заводится.
- SCP-форму `git@host:path` `parse_url` не разбирает, поэтому разбор ручной и следует правилу git:
  двоеточие до первого `/`.
- Нормализуют `ProjectKey` и `App\Domain\KeyType\DocumentName`, а база сравнивает побайтно:
  `projects.key` и `identifiers.name_slug` — `utf8mb4_bin`, потому что `unicode_ci` склеивает
  `cafe`/`café` и `елка`/`ёлка` и слил бы разные темы. `key_types.code`, наоборот, сравнивается без
  учёта регистра — `ADR` и `adr` один тип.

## REST и MCP

- `apiPrefix` пуст (`bootstrap/app.php`): REST сам объявляет `api/v1`, а MCP живёт на `/mcp` в
  `routes/api.php` и наследует группу `api` целиком — токен, limiter `getid` с бюджетом на токен,
  общим для обеих поверхностей, и журнал.
- Тело, закрытое в контракте (`additionalProperties: false`), принимает наследник
  `App\Http\Requests\Api\ClosedBodyRequest`: `#[FailOnUnknownFields]` фреймворка плюс
  `#[MinProperties]` для PATCH, чья ошибка приходит под ключом `body`. Query string открыт, как и в
  контракте.
- MCP-tool проверяет аргументы через FormRequest своего endpoint (`RegistryTool::validate`) и отвечает
  его JsonResource, поэтому правило, добавленное в FormRequest, действует на обеих поверхностях. Строки
  tool обрезает сам: `TrimStrings` тело JSON-RPC не видит. Отказ — `{code}: {message}`, где `code`
  равен `error.code` REST.
- Административных операций в MCP нет намеренно — [ADR-002](docs/adr/adr-002-mcp-surface-boundary.md).

## Доступ

- Новый административный маршрут — только в группе `EnsureAdministrator`. Он стоит в priority list
  перед `SubstituteBindings`, поэтому 403 приходит до поиска сущности и не выдаёт, существует ли она
  (FR-017). Группа `/admin` в `routes/web.php` закрыта тем же `EnsureAdministrator`, а не `can:`, —
  `can:` это `Authorize`, который фреймворк ставит после `SubstituteBindings`
  (specs/002-admin-web-console/research.md R2).
- Правила справочника (`ProjectRules`, `KeyTypeRules`, `ProjectKeyTypeRules` в `app/Http/Validation/`)
  и запись следа изменений (`app/Actions/Registry/`) общие для REST-админки и веб-консоли; MCP
  административных операций не несёт вовсе — [ADR-002](docs/adr/adr-002-mcp-surface-boundary.md).
  Почему правила не живут в самих FormRequest — specs/002-admin-web-console/research.md R1.
- API без токена отвечает 401 DomainError, а не redirect (`redirectGuestsTo` и `App\Http\ApiSurface`).
  Веб-страница входа обязана называться `login`.
- Пользователями управляют только `user:role` и `user:deactivate`; `ADMIN_EMAILS` действует лишь при
  создании учётной записи. Провайдер `active-users` отсекает деактивированного и в открытой сессии.

## Журнал

- `LogApiRequest` пишет в `terminate()`, после ответа, и переживает собственный сбой (FR-026).
- 401 и 429 в журнал не попадают: middleware стоит в группе после `auth:sanctum` и `throttle:getid`.
- У MCP `status_code` — HTTP 200 даже при отказе инструмента; вызов и аргументы лежат в `payload`.

## Тесты и окружение

- Всё запускается в контейнере `app` (`Makefile`): платформа PHP закреплена в `composer.json`, и
  локальный PHP ей не является. Готовность — зелёный `make test` в этой же сессии, все четыре suite,
  включая `Concurrency`.
- Тесты идут на MySQL `getid_test`, потому что sqlite не умеет `FOR UPDATE`. `DB_*` в `phpunit.xml`
  заданы дважды намеренно, причина — в комментарии там: без этого набор уходит в рабочую базу, а
  `Concurrency` её усекает.
- Гонку доказывает только `tests/Concurrency`: отдельные процессы `getid:issue`, общая метка старта
  `--at`, `DatabaseTruncation` вместо транзакции на тест. Последовательный цикл зелёный и на сломанной
  реализации.
- Приложение выложено 2026-09-23 и в production-базе есть данные: схема меняется только новыми
  миграциями, исходные не правятся.

## Document numbers (ADR, specs)

ADR and spec numbers are issued by the get-id service, never derived from listing `docs/adr/` or `specs/`: two sessions listing the same directory take the same number.

- Before creating an ADR or a spec package, call the `get-id` MCP tools: `resolve_project` with the output of `git remote get-url origin`, then `next_id` with the returned `project_key`, `type` (`ADR` or `spec`) and `name` — the document's slug in ASCII kebab-case.
- The service issues only the number (`sequence_number`), never a document name: the repository builds the name, and you never count the number yourself. Zero-pad it to three digits: 43 → `docs/adr/adr-043-<slug>.md`, `specs/043-<slug>/`; a number of 1000 or more is written in full.
- Spec Kit: pass the number explicitly — `create-new-feature.sh --number <sequence_number> --short-name <slug>`. When that prefix already exists the script takes another number and only prints a warning to stderr — if the created directory does not start with the issued three-digit number, stop and tell the user.
- Repeating `next_id` with the same slug returns the same number (`is_new: false`), so it is safe after a retry or a crash. Never change the slug to get a fresh number.
- A number taken by mistake is released only by a get-id admin: stop and tell the user to ask one to delete it. Only the last issued number of the type in the project can be deleted — once a later one is issued, the mistaken number stays a gap for good, so ask right away. Do not reuse it for another document and do not work around it with a new slug.
- `project_not_registered` or `type_not_enabled`: stop and tell the user to ask an admin to register the project at https://id.x3mal.com/admin/projects. Do not fall back to listing the directory.
- One-time setup per machine: sign in at https://id.x3mal.com with the corporate Google account, create a token on https://id.x3mal.com/tokens, run the `claude mcp add --scope user …` command shown there, then `/reload-plugins`.
