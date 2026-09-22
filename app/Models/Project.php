<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `key` is derived from `repo_url` by ProjectKey and is never typed in by hand, or it drifts from
 * the origin clients resolve it from.
 */
#[Fillable(['key', 'name', 'repo_url', 'description', 'is_active'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<KeyType, $this, ProjectKeyType>
     */
    public function keyTypes(): BelongsToMany
    {
        return $this->belongsToMany(KeyType::class, 'project_key_type')
            ->using(ProjectKeyType::class)
            ->withPivot(['id', 'seed_sequence', 'last_sequence', 'is_enabled'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Identifier, $this>
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(Identifier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
