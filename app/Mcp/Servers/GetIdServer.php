<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListIdentifiersTool;
use App\Mcp\Tools\NextIdTool;
use App\Mcp\Tools\ResolveProjectTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The registry's read and issue operations only. Administration stays off this surface on purpose: the
 * registry grows by a person's decision, not an assistant's (contracts/mcp-tools.md, FR-011).
 */
#[Name('get-id')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
    get-id выдаёт порядковые номера ADR и спецификаций так, что один номер не достаётся двум документам.

    1. Прочитайте адрес origin на своей стороне: `git remote get-url origin`. Сервер к вашему репозиторию доступа не имеет.
    2. Вызовите resolve_project с этим адресом. Из ответа возьмите project_key и перечень включённых типов.
    3. Вызовите next_id с этим project_key, кодом типа и темой документа. Повтор с той же темой безопасен: вернётся тот же номер. Сервис выдаёт только номер, имя документа собирает репозиторий: номер дополняется нулями до трёх цифр — `docs/adr/adr-043-<slug>.md`, `specs/043-<slug>/`. Для Spec Kit передайте номер явно и остановитесь, если созданный каталог начинается не с этого трёхзначного номера.

    Если проект не зарегистрирован или тип в нём не включён, номер не выдаётся. Передайте человеку текст отказа: проекты заводит и типы включает администратор get-id.
    TEXT)]
final class GetIdServer extends Server
{
    protected array $tools = [
        ResolveProjectTool::class,
        NextIdTool::class,
        ListIdentifiersTool::class,
    ];
}
