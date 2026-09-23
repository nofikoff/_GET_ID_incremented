<?php

namespace App\Mcp\Tools;

use App\Domain\Sequence\Exceptions\DomainRejection;
use App\Domain\Sequence\SequenceIssuer;
use App\Http\Requests\Api\ListSequenceRequest;
use App\Http\Resources\IdentifierListResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * GET /api/v1/sequence/list.
 */
#[Name('list_identifiers')]
#[Description('Показать уже выданные номера в проекте по типу ключа, от новых к старым. Используйте, чтобы проверить, не заводился ли документ на эту тему раньше.')]
#[IsReadOnly]
#[IsIdempotent]
final class ListIdentifiersTool extends RegistryTool
{
    public function handle(Request $request, SequenceIssuer $issuer): Response|ResponseFactory
    {
        $this->validate($request, (new ListSequenceRequest)->rules());

        try {
            return $this->result(new IdentifierListResource($issuer->list(
                $request->string('project_key')->value(),
                $request->string('type')->value(),
            )));
        } catch (DomainRejection $rejection) {
            return $this->refused($rejection);
        }
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_key' => $schema->string()
                ->description('Ключ проекта из resolve_project.')
                ->required(),
            'type' => $schema->string()
                ->description('Код типа ключа.')
                ->required(),
        ];
    }
}
