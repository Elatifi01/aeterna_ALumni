<?php

namespace App\Services\EmploiMa;

use Illuminate\Support\Str;

/**
 * One search, normalised (docs/project.md F11).
 */
final class JobQuery
{
    /**
     * Create a new instance.
     */
    public function __construct(
        public readonly string $keywords,
        public readonly ?string $categoryId = null,
        public readonly int $page = 1,
        public readonly string $city = '',
    ) {}

    /**
     * Build from request input, clamping everything into range.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $category = isset($input['category']) && is_scalar($input['category'])
            ? trim((string) $input['category'])
            : '';

        return new self(
            keywords: self::normalise($input['keywords'] ?? ''),
            categoryId: preg_match('/^\d{1,10}$/', $category) === 1 ? $category : null,
            page: min(10, max(1, (int) ($input['page'] ?? 1))),
            city: self::normalise($input['city'] ?? ''),
        );
    }

    /**
     * Whether there is anything to ask emploi.ma.
     */
    public function isEmpty(): bool
    {
        return $this->keywords === '' && $this->categoryId === null;
    }

    /**
     * The query string emploi.ma expects.
     *
     * @return array<string, string>
     */
    public function toParameters(): array
    {
        $parameters = [
            'query' => $this->keywords,
            'page' => (string) ($this->page - 1),
        ];

        if ($this->categoryId !== null) {
            $parameters['metier_id'] = $this->categoryId;
        }

        return $parameters;
    }

    /**
     * A stable key for the upstream request.
     */
    public function fingerprint(): string
    {
        return md5(json_encode([
            $this->keywords,
            $this->categoryId,
            $this->page,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Lower-cased, accent-folded, collapsed whitespace.
     */
    private static function normalise(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return Str::limit(
            Str::of($value)->squish()->lower()->ascii()->value(),
            80,
            '',
        );
    }
}
