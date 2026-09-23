<?php

namespace App\Mcp\Tools;

use App\Domain\Project\ProjectResolver;
use App\Domain\Sequence\Exceptions\DomainRejection;
use App\Http\Requests\Api\ResolveProjectRequest;
use App\Http\Resources\ProjectResolutionResource;
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
 * GET /api/v1/projects/resolve. The description is contracts/mcp-tools.md verbatim: it is what stops the model
 * guessing the key from the directory name (FR-023).
 */
#[Name('resolve_project')]
#[Description('Определить ключ проекта get-id по адресу origin git-репозитория и узнать, зарегистрирован ли он. Вызывайте это первым, до запроса номера. Адрес получите на своей стороне: `git remote get-url origin` — сервер к вашему репозиторию доступа не имеет.')]
#[IsReadOnly]
#[IsIdempotent]
final class ResolveProjectTool extends RegistryTool
{
    public function handle(Request $request, ProjectResolver $resolver): Response|ResponseFactory
    {
        $this->validate($request, ResolveProjectRequest::class);

        try {
            return $this->result(new ProjectResolutionResource($resolver->resolve($request->string('origin')->value())));
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
            'origin' => $schema->string()
                ->description('Вывод `git remote get-url origin`, как есть. Принимаются SSH- и HTTPS-формы.')
                ->required(),
        ];
    }
}
