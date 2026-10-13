<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One upload of an alumni spreadsheet (docs/project.md F12).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $user_id
 * @property string $original_name
 * @property string $path
 * @property int $size_bytes
 * @property string $status
 * @property int $rows_total
 * @property int $rows_valid
 * @property int $rows_invalid
 * @property int $rows_imported
 * @property int $rows_over_limit
 * @property array<int, array{line: int, message: string}>|null $errors
 * @property CarbonImmutable|null $imported_at
 * @property CarbonImmutable|null $undone_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization|null $organization
 * @property-read User|null $user
 */
class AlumniImport extends Model
{
    use BelongsToOrganization;

    public const DISK = 'local';

    public const DIRECTORY = 'alumni-imports';

    public const MAX_KILOBYTES = 5120;

    public const EXTENSIONS = ['xlsx', 'xls', 'csv'];

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'imported_at' => 'datetime',
            'undone_at' => 'datetime',
        ];
    }

    /**
     * The user who uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The alumni this import created — what an undo removes.
     *
     * @return HasMany<Alumni, $this>
     */
    public function alumni(): HasMany
    {
        return $this->hasMany(Alumni::class, 'import_id');
    }

    /**
     * Whether the file itself is still on disk.
     */
    public function fileExists(): bool
    {
        return Storage::disk(self::DISK)->exists($this->path);
    }

    /**
     * Remove the stored file, keeping the record of what it did.
     */
    public function deleteFile(): void
    {
        if ($this->fileExists()) {
            Storage::disk(self::DISK)->delete($this->path);
        }
    }
}
