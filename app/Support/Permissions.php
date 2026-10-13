<?php

namespace App\Support;

/**
 * The permission catalogue of docs/project.md §5.4.
 */
final class Permissions
{
    /** @var array<string, array<string, string>> module => [slug => label] */
    public const CATALOGUE = [
        'alumni' => [
            'alumni.view' => 'Consulter les alumni',
            'alumni.create' => 'Ajouter un alumni',
            'alumni.update' => 'Modifier un alumni',
            'alumni.delete' => 'Supprimer un alumni',
            'alumni.invite' => 'Envoyer les identifiants du portail',
            'alumni.portal' => 'Gerer l acces des alumni au portail',
            'alumni.import' => 'Importer des alumni depuis un fichier',
            'alumni.export' => 'Exporter la liste des alumni',
        ],
        'events' => [
            'events.view' => 'Consulter les evenements',
            'events.create' => 'Creer un evenement',
            'events.update' => 'Modifier un evenement',
            'events.delete' => 'Supprimer un evenement',
            'events.registrations' => 'Gerer les inscriptions et la presence',
        ],
        'campaigns' => [
            'campaigns.view' => 'Consulter les campagnes',
            'campaigns.create' => 'Creer une campagne',
            'campaigns.send' => 'Envoyer une campagne',
        ],
        'users' => [
            'users.view' => 'Consulter les utilisateurs',
            'users.create' => 'Creer un utilisateur',
            'users.update' => 'Modifier un utilisateur',
            'users.delete' => 'Supprimer un utilisateur',
        ],
        'roles' => [
            'roles.view' => 'Consulter les roles',
            'roles.manage' => 'Gerer les roles et permissions',
        ],
        'assistant' => [
            'assistant.use' => 'Utiliser Aeterna Assistant',
        ],
        'dashboard' => [
            'dashboard.view' => 'Consulter le tableau de bord',
        ],
        'organization' => [
            'organization.update' => 'Modifier les informations de l organisation',
        ],
        'subscription' => [
            'subscription.view' => 'Consulter l abonnement',
        ],
    ];

    /** @var array<string, array<string, string>> module => [slug => label] */
    public const PORTAL_CATALOGUE = [
        'portal' => [
            'portal.events.view' => 'Consulter les evenements',
            'portal.events.register' => 'S inscrire aux evenements',
            'portal.messages.view' => 'Lire les messages recus',
            'portal.notifications.view' => 'Consulter les notifications',
            'portal.jobs.view' => 'Consulter les offres d emploi',
            'portal.jobs.apply' => 'Enregistrer et postuler aux offres',
            'portal.assistant.use' => 'Utiliser Aeterna Assistant',
            'portal.profile.update' => 'Modifier son profil',
        ],
    ];

    public const ALUMNI_ROLE_SLUG = 'alumni';

    /**
     * Every STAFF permission slug, flattened.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::CATALOGUE)));
    }

    /**
     * Every PORTAL permission slug, flattened.
     *
     * @return list<string>
     */
    public static function portalSlugs(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::PORTAL_CATALOGUE)));
    }

    /**
     * The catalogue a role of this guard is built from.
     *
     * @return array<string, array<string, string>>
     */
    public static function catalogueFor(string $guard): array
    {
        return $guard === 'alumni' ? self::PORTAL_CATALOGUE : self::CATALOGUE;
    }

    /**
     * Every permission slug of the given guard.
     *
     * @return list<string>
     */
    public static function slugsFor(string $guard): array
    {
        return $guard === 'alumni' ? self::portalSlugs() : self::slugs();
    }

    /**
     * Read-only permissions — what the staff system role is seeded with (docs/database-schema.md §9).
     *
     * @return list<string>
     */
    public static function viewOnlySlugs(): array
    {
        return array_values(array_filter(
            self::slugs(),
            fn (string $slug): bool => str_ends_with($slug, '.view'),
        ));
    }

    /**
     * Rows for the permissions table.
     *
     * @return list<array{name: string, slug: string, module: string}>
     */
    public static function rows(): array
    {
        $rows = [];

        foreach ([...self::CATALOGUE, ...self::PORTAL_CATALOGUE] as $module => $permissions) {
            foreach ($permissions as $slug => $label) {
                $rows[] = ['name' => $label, 'slug' => $slug, 'module' => $module];
            }
        }

        return $rows;
    }
}
