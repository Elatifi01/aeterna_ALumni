<?php

namespace App\Services\EmploiMa;

use Illuminate\Support\Str;

/**
 * One offer as emploi.ma returns it (docs/project.md F11).
 */
final class JobOffer
{
    /**
     * Create a new instance.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $company,
        public readonly ?string $companyUrl,
        public readonly ?string $location,
        public readonly ?string $snippet,
        public readonly ?string $employmentType,
        public readonly ?string $experienceLevel,
        public readonly string $link,
    ) {}

    /**
     * Build from one entry of the API response.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromApi(array $row): ?self
    {
        $link = self::text($row['url'] ?? null) ?? '';
        $title = self::text($row['title'] ?? null) ?? '';

        if ($link === '' || $title === '') {
            return null;
        }

        return new self(
            id: self::identify($link),
            title: $title,
            company: self::text($row['company_name'] ?? null),
            companyUrl: self::text($row['company_url'] ?? null),
            location: self::text($row['location'] ?? null),
            snippet: self::snippet($row['description_snippet'] ?? null),
            employmentType: self::text($row['employment_type'] ?? null),
            experienceLevel: self::text($row['experience_level'] ?? null),
            link: $link,
        );
    }

    /**
     * Convert the object to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company' => $this->company,
            'company_url' => $this->companyUrl,
            'location' => $this->location,
            'snippet' => $this->snippet,
            'employment_type' => $this->employmentType,
            'experience_level' => $this->experienceLevel,
            'link' => $this->link,
        ];
    }

    /**
     * Keep the offers whose location mentions the city.
     *
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    public static function narrowToCity(array $offers, string $city): array
    {
        if ($city === '') {
            return $offers;
        }

        return array_values(array_filter($offers, function (array $offer) use ($city): bool {
            $location = is_string($offer['location'] ?? null) ? $offer['location'] : '';

            return str_contains(str($location)->lower()->ascii()->value(), $city);
        }));
    }

    /**
     * A stable identifier for the offer.
     */
    private static function identify(string $link): string
    {
        if (preg_match('/-(\d{4,})(?:[\/?#]|$)/', $link, $matches) === 1) {
            return $matches[1];
        }

        return substr(hash('sha256', $link), 0, 32);
    }

    /**
     * A trimmed string from a scalar value, or null.
     */
    private static function text(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * The snippet arrives as scraped text, with markup and runs of whitespace in it.
     */
    private static function snippet(mixed $value): ?string
    {
        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $text === '' ? null : Str::limit($text, 260);
    }
}
