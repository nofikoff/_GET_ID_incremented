<?php

namespace App\Models;

use App\Models\Builders\AppendOnlyQueryBuilder;
use Database\Factories\IdentifierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An issued number. Rows are inserted and read, never rewritten or removed (FR-004, principle II).
 *
 * @property int $sequence_number
 */
#[Fillable(['name', 'name_slug', 'sequence_number'])]
class Identifier extends Model
{
    /** @use HasFactory<IdentifierFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
        ];
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function newBaseQueryBuilder(): AppendOnlyQueryBuilder
    {
        $connection = $this->getConnection();

        return new AppendOnlyQueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
    }
}
