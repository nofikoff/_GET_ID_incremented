<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name', 'format_template', 'description', 'is_active'])]
class KeyType extends Model
{
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
     * @return BelongsToMany<Project, $this, ProjectKeyType>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_key_type')
            ->using(ProjectKeyType::class)
            ->withPivot(['id', 'seed_sequence', 'last_sequence', 'is_enabled'])
            ->withTimestamps();
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
