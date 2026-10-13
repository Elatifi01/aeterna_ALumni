<?php

namespace App\Services\Jobs;

use App\Services\EmploiMa\JobQuery;

/**
 * One place job offers come from (docs/project.md F11).
 */
interface JobSource
{
    /**
     * The short key the interface and the URL use, e.g. `emploima`.
     */
    public function key(): string;

    /**
     * The name shown to people, e.g. `emploi.ma`.
     */
    public function label(): string;

    /**
     * Whether the source is enabled and has what it needs to be called.
     */
    public function isConfigured(): bool;

    /**
     * Search this source, serving from cache when possible.
     *
     * @return array{offers: list<array<string, mixed>>, total: int, degraded: bool, reason: string|null, cached: bool}
     */
    public function search(JobQuery $query): array;
}
