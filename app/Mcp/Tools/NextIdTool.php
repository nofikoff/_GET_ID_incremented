<?php

namespace App\Mcp\Tools;

use App\Domain\Sequence\Exceptions\DomainRejection;
use App\Domain\Sequence\SequenceIssuer;
use App\Http\Requests\Api\NextSequenceRequest;
use App\Http\Resources\IssuedIdentifierResource;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * POST /api/v1/sequence/next. Issuance is SequenceIssuer's alone (principle VI); this class only transports it.
 */
#[Name('next_id')]
#[Description('Получить следующий свободный номер для документа в проекте get-id. Идемпотентно: повторный вызов с той же темой вернёт тот же номер и `is_new: false`, поэтому безопасно повторять после сбоя. `project_key` берите из ответа `resolve_project`, не составляйте сами.')]
#[IsIdempotent]
final class NextIdTool extends RegistryTool
{
    public function handle(Request $request, SequenceIssuer $issuer): Response|ResponseFactory
    {
        $this->validate($request, NextSequenceRequest::class);
        $author = $request->user();

        try {
            $issued = $issuer->issue(
                $request->string('project_key')->value(),
                $request->string('type')->value(),
                $request->string('name')->value(),
                $author instanceof User ? $author : null,
            );
        } catch (DomainRejection $rejection) {
            return $this->refused($rejection);
        }

        return $this->result(new IssuedIdentifierResource($issued));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_key' => $schema->string()
                ->description('Ключ проекта из resolve_project. Не угадывайте его по имени каталога.')
                ->required(),
            'type' => $schema->string()
                ->description('Код типа ключа, включённого в проекте: ADR, spec и т. п. Перечень — в ответе resolve_project.')
                ->required(),
            'name' => $schema->string()
                ->description('Тема документа, например add-oauth-auth. Регистр и вид разделителя значения не имеют.')
                ->required(),
        ];
    }
}
