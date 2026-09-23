<?php

namespace App\Models;

use Database\Factories\KeyTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name', 'description', 'is_active'])]
class KeyType extends Model
{
    /** @use HasFactory<KeyTypeFactory> */
    use HasFactory;

    /**
     * Mirrors the column default, so a key type answers is_active right after it is created.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

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
