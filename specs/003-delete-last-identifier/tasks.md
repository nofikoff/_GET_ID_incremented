---
description: "Tasks: удаление последнего выданного номера"
---

# Tasks: Удаление последнего выданного номера

**Input**: `specs/003-delete-last-identifier/` — spec.md, plan.md, research.md, data-model.md, contracts/web-console.md, quickstart.md

**Tests**: обязательны — конституция (§Порядок работы: каждый endpoint закрывается feature-тестом на успех и отказ; принцип V: гонка доказывается параллельным тестом). Тест пишется до кода в каждой задаче.

**Gate каждой задачи**: `docker compose exec -T app ./vendor/bin/pint --test`, `docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress`, затем тест задачи. Финальный гейт — `make test`, все четыре suite.

Исполнитель — `/speckit-implement`: пакет на одну сессию.

## Phase 1: Foundational — домен снятия

**Purpose**: правило хвоста и откат счётчика без HTTP; на них стоят все истории.

- [X] T001 Добавить `withdraw(): int` (вызывает `parent::delete()`) в `app/Models/Builders/AppendOnlyQueryBuilder.php`; docblock класса и `app/Models/Identifier.php` назвать `withdraw()` единственным открытым путём удаления со ссылкой на `SequenceWithdrawer` (research.md R1). Тест: в `tests/Feature/Models/IdentifierImmutabilityTest.php` все прежние записи по-прежнему бросают `RegistryIsAppendOnly`, новый случай — `Identifier::query()->whereKey($id)->toBase()->withdraw()` возвращает 1 и удаляет ровно одну строку (без `toBase()` вызов уходит в `Eloquent\Builder::__call()` и возвращает builder — research.md R1).
- [X] T002 Создать `app/Domain/Sequence/Exceptions/NotTheLastIdentifier.php` (`final`, `RuntimeException`, не `DomainRejection` — research.md R5; фабрика `::tail(string $lastFormattedId)`, текст «Удалить можно только последний номер пары: сейчас это {id}.», поле `lastFormattedId`) и `app/Domain/Sequence/WithdrawnIdentifier.php` по data-model.md.
- [X] T003 Тесты домена в `tests/Feature/Sequence/WithdrawTest.php` (сначала красные): снятие хвоста уменьшает `last_sequence` до нового хвоста; снятие единственного номера → `last_sequence` 0, `nextSequence()` = seed+1; seed выше выданных (1..10, seed 50, выдан 51): снятие 51 → last 10, next 51, затем снятие 10 → last 9, next 51; снятие не хвоста → `NotTheLastIdentifier` с хвостом, ничего не удалено, счётчик тот же; снятие уже удалённой записи → `ModelNotFoundException` — и когда пара пуста, и когда в ней остались другие номера (второй администратор успел снять тот же номер: не `NotTheLastIdentifier`, research.md R2 шаг 2); счётчик, разошедшийся с перечнем (`last_sequence` 40 при максимуме 12), после снятия 12 равен 11; погашенный проект, выведенный тип, выключенная пара — снятие проходит; снятая тема через `SequenceIssuer::issue()` выдаётся с `isNew: true` и номером снятого.
- [X] T004 Реализовать `app/Domain/Sequence/SequenceWithdrawer.php` по research.md R2: `withdraw(Identifier $identifier): WithdrawnIdentifier`, `DB::transaction(…, 3)`; первый запрос — `lockForUpdate` строки `project_key_type` пары; затем consistent read: запись по ключу (нет → `ModelNotFoundException`), максимум пары (больше → `NotTheLastIdentifier`), `toBase()->withdraw()`, новый `last_sequence` из оставшихся. Без locking read по `identifiers`. Docblock: инвариант «до блокировки пары в транзакции нет чтений» и ссылка на research.md R2, без пересказа. T003 зелёный.

**Checkpoint**: домен снимает хвост и отказывает на середине.

## Phase 2: User Story 1 — снятие тестовых номеров с хвоста (P1) 🎯 MVP

**Goal**: крестик у последнего номера пары на карточке проекта снимает его и откатывает счётчик.

**Independent Test**: seed 31, выданы 32 и 33; два снятия через веб → следующая выдача 32 с `is_new: true`.

- [X] T005 [US1] Добавить `withdrawn(User $admin, Project $project, KeyType $keyType, WithdrawnIdentifier $withdrawn)` в `app/Actions/Registry/RegistryChangeLog.php`: `write()` с операцией `withdraw_identifier`, форма `changes` по data-model.md §След. Тест в `tests/Feature/Admin/RegistryChangeLogTest.php`: состав записи; сбой `Log` не бросает.
- [X] T006 [US1] Создать `app/Actions/Registry/WithdrawIdentifier.php`: `__invoke(User $admin, Identifier $identifier): WithdrawnIdentifier` — `SequenceWithdrawer`, затем `RegistryChangeLog::withdrawn()`.
- [X] T007 [US1] Тесты `tests/Feature/Admin/Web/WithdrawIdentifierTest.php` (сначала красные): сценарии US1 1–3 spec; flash «Номер «{formatted_id}» удалён. Следующий номер — {next}.»; после снятия крестик у нового хвоста; у пары без номеров и на второй странице перечня крестика нет; погашенный проект и выключенная пара — снятие проходит; форма содержит подтверждение с `formatted_id` и «может быть выдан снова».
- [X] T008 [US1] Маршрут в `routes/web.php` внутри группы `admin`: `Route::resource('projects.identifiers', IdentifierController::class)->only(['destroy'])->scoped()` (research.md R4).
- [X] T009 [US1] Создать `app/Http/Controllers/Web/Admin/IdentifierController.php`: `destroy(Project $project, Identifier $identifier, WithdrawIdentifier $withdraw, #[CurrentUser] User $admin)`; redirect на `admin.projects.show` с flash; `NotTheLastIdentifier` → `ValidationException` под `identifiers.<key_type_id>` (research.md R5), `ModelNotFoundException` из домена пробрасывается как 404.
- [X] T010 [US1] В `resources/views/admin/projects/show.blade.php`: колонка действия; форма `DELETE` с `@csrf`, `@method('DELETE')` и `onsubmit="return confirm(@js(…))"` только при `$loop->first && $identifiers->onFirstPage()` (research.md R6); `@include('admin.partials.error', ['field' => "identifiers.{$pair->key_type_id}"])` над таблицей пары. T007 зелёный.

**Checkpoint**: US1 проходит quickstart.md §2.

## Phase 3: User Story 2 — номер из середины удалить нельзя (P1)

**Goal**: запрос на не последний номер отвергается при любом способе отправки.

**Independent Test**: выданы 5, 6, 7; прямой DELETE на 6 → ошибка с «7», перечень и счётчик те же.

- [X] T011 [US2] Дописать в `tests/Feature/Admin/Web/WithdrawIdentifierTest.php`: DELETE не хвоста → redirect back, ошибка под `identifiers.<key_type_id>` с `formatted_id` хвоста, ничего не удалено; устаревшая страница (хвост 7, затем выдан 8, DELETE 7) → та же ошибка с 8; повторный DELETE удалённого номера → 404. Зелёные на коде Phase 2 — иначе правка T009.

## Phase 4: User Story 4 — сотрудник без роли удалить не может (P1)

**Goal**: 403 до поиска номера, 404 на чужой проект.

- [X] T012 [US4] Дописать в `tests/Feature/Admin/Web/WithdrawIdentifierTest.php`: сотрудник без роли — 403 на существующий и несуществующий `{identifier}`, одинаковые тела, ничего не удалено; гость → redirect `login`; номер другого проекта в пути → 404. Обязательно добавить `admin.projects.identifiers.destroy` в перечень `operations` в `tests/Feature/Admin/Web/ConsoleAccessTest.php`: тест `every console route is in the list the access tests walk` сверяет все `admin.*`-маршруты и без этого падает. Замыканиям `operations` нужен id выданного номера — завести `Identifier` в `beforeEach` и расширить сигнатуру (analyze F5).

## Phase 5: User Story 3 — снятие не пересекается с выдачей (P2)

**Goal**: гонка снятия и выдачи не даёт дубля и не рассинхронизирует счётчик.

- [X] T013 [US3] Создать `app/Console/Commands/WithdrawIdentifierCommand.php` — `getid:withdraw {project_key} {type} {number} {--at=}`, `#[Hidden]`, контракт по contracts/web-console.md §Команда; ожидание метки — как в `IssueIdentifier` (вынести общий `waitUntil` не нужно, если это единственный второй потребитель — решить по коду).
- [X] T014 [US3] Обобщить `tests/Concurrency/IssueRace.php` так, чтобы пул запускал и `getid:issue`, и `getid:withdraw` с одной меткой `--at`; существующие тесты `Concurrency` не меняют ожиданий.
- [X] T015 [US3] Создать `tests/Concurrency/ConcurrentWithdrawTest.php` по research.md R7: выданы 1..10, одновременно снятие 10 и пять выдач; номера подряд без повторов, `last_sequence` = максимум; ветка «снятие прошло» → выдачи 10..14, иначе 11..15. Второй тест: две пары одного проекта, снятие хвоста в первой одновременно с выдачей первого номера во второй, пустой — оба проходят, и выдача не ждёт снятия (FR-005, research.md R2 про gap lock). `make test` в части `Concurrency` зелёный.

## Phase 6: Контракт и документы

- [X] T016 Получить номер ADR через get-id (`resolve_project` с `git remote get-url origin`, затем `next_id`, тип `ADR`, тема «withdraw last identifier»); проект не заведён — остановиться и передать отказ автору. Создать `docs/adr/adr-NNN-withdraw-last-identifier.md`: Context 2–3 строки, Decision одна строка, Consequences — блокировка пары, `withdraw()` как единственный путь, отвергнутые варианты из spec §Assumptions.
- [X] T017 [P] Переписать spec 001 FR-002, FR-004, FR-016 в `specs/001-incremental-id-registry/spec.md` и spec 002 FR-013 в `specs/002-admin-web-console/spec.md` по spec FR-011, со ссылкой на пакет 003.
- [X] T018 [P] `CLAUDE.md` §Выдача номера: строки про повтор, «не переиспользуется никогда» и `AppendOnlyQueryBuilder` — под исключение `withdraw()`/`SequenceWithdrawer` и ADR-NNN, без пересказа ADR.
- [X] T019 Поправка конституции `.specify/memory/constitution.md`: преамбула, принципы I и II (строки-указатели + ADR-NNN), Sync Impact Report, версия 2.0.0, Last Amended. Отдельный commit, называющий принцип и причину (Governance).

## Phase 7: Закрытие

- [X] T020 `make test` — все четыре suite зелёные; pint и phpstan зелёные.
- [ ] T021 Пройти quickstart.md §2–§3 в локальной консоли. *Не пройдено*: вход в локальную консоль идёт через Google OAuth. Те же сценарии на уровне HTTP держит `tests/Feature/Admin/Web/WithdrawIdentifierTest.php`; проход в браузере — после выкладки.
- [X] T022 Закрыть пакет: отметить задачи, записать в plan.md/research.md то, что разошлось с планом при реализации.

## Что разошлось с планом (2026-09-23)

- `NotTheLastIdentifier` наследует `DomainException`, как соседний `SeedBelowIssued`, а не `RuntimeException` (T002).
- `WithdrawnIdentifier` получил `previousLastSequence`: след брал «было» из номера снятой записи и врал бы при уплывшем счётчике (T005, data-model.md).
- Ожидание метки `--at` вынесено в `app/Console/Commands/Concerns/WaitsForStartMark.php` — у него два потребителя (T013).
- `IssueRace::race()` принимает смесь `getid:issue` и `getid:withdraw`; `run()` остался для троек выдачи (T014).
- Мутация «убрать `lockForUpdate()` из `SequenceWithdrawer`» роняет `ConcurrentWithdrawTest` в трёх прогонах из трёх; тест на две пары доказывает, что обе операции проходят, но не отсутствие ожидания.
- `IssuedIdentifiersTest` «the card offers no way to change or remove» переписан под FR-001 этого пакета: одна форма, у хвоста.
- Описания `next_id` в MCP и REST-контракте («повтор вернёт тот же номер») не менялись: они верны, пока администратор не снял тему между двумя вызовами, а FR-010 держит поведение этих поверхностей.
- Номер ADR выдан get-id как `ADR-0003` (шаблон типа — 4 цифры); файл назван по конвенции репозитория `adr-003-…`.
- Проект репозитория заведён в production get-id (id 3, `ADR` и `spec` с начальным 2); номер пакета `003-delete-last-identifier` выдан сервисом и совпал с каталогом.

## Dependencies

- Phase 1 → все остальные. T003 до T004 (TDD).
- Phase 2 (US1) → Phase 3 (US2) и Phase 4 (US4): тесты на уже построенный маршрут.
- Phase 5 (US3) зависит только от Phase 1.
- Phase 6 после T016 (номер ADR нужен T018 и T019); T017 не зависит от кода.
- Phase 7 последней.

## Parallel

- Phase 5 параллельно Phase 2–4.
- T017 и T018 параллельно друг другу.

## Implementation Strategy

MVP — Phase 1 + Phase 2: снятие хвоста в консоли. US2 и US4 — тесты на границы того же кода, US3 — доказательство гонки. Документы контракта — до выкладки, потому что после неё `CLAUDE.md` и конституция иначе утверждают то, чего код уже не держит.
