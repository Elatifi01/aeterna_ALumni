<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores and removes an organization's logo.
 */
class OrganizationLogoService
{
    public const DISK = 'public';

    public const DIRECTORY = 'organization-logos';

    public const MIMES = ['png', 'jpg', 'jpeg', 'webp'];

    public const MAX_KILOBYTES = 2048;

    /**
     * Replace the organization's logo, deleting whatever it had.
     */
    public function store(Organization $organization, UploadedFile $file): void
    {
        $previous = $organization->logo_path;

        $path = $file->storeAs(
            self::DIRECTORY,
            Str::uuid()->toString().'.'.$file->extension(),
            self::DISK,
        );

        $organization->forceFill(['logo_path' => $path])->save();

        $this->deleteFile($previous);
    }

    /**
     * Remove the logo and fall back to the Aeterna mark.
     */
    public function remove(Organization $organization): void
    {
        $previous = $organization->logo_path;

        $organization->forceFill(['logo_path' => null])->save();

        $this->deleteFile($previous);
    }

    /**
     * Remove a logo file from storage if it exists.
     */
    private function deleteFile(?string $path): void
    {
        if ($path !== null && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
