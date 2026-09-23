<?php

namespace App\Models;

use Database\Factories\ProjectKeyTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The numbering state of one project and key type pair, and the row issuance locks
 * (research.md R1). Its own incrementing id is what lets it be locked and loaded standalone.
 *
 * @property int $id
 * @property int $project_id
 * @property int $key_type_id
 * @property int $seed_sequence
 * @property int $last_sequence
 * @property bool $is_enabled
 */
#[Table('project_key_type', incrementing: true)]
class ProjectKeyType extends Pivot
{
    /** @use HasFactory<ProjectKeyTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed_sequence' => 'integer',
            'last_sequence' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * FR-014a: the seed marks numbers taken outside the service, so issuance resumes past whichever is higher.
     */
    public function nextSequence(): int
    {
        return max($this->seed_sequence ?? 0, $this->last_sequence ?? 0) + 1;
    }

    /**
     * Pairs that issue numbers: enabled in the project and of a type not retired globally.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function issuable(Builder $query): void
    {
        $query->where('is_enabled', true)->whereHas('keyType', fn (Builder $keyType) => $keyType->where('is_active', true));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function orderByTypeCode(Builder $query): void
    {
        $query->orderBy(KeyType::query()->select('code')->whereColumn('key_types.id', 'project_key_type.key_type_id'));
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<KeyType, $this>
     */
    public function keyType(): BelongsTo
    {
        return $this->belongsTo(KeyType::class);
    }
}
