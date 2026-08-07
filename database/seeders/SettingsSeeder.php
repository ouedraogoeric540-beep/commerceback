<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // --- Général ---
            [
                'key'         => 'platform_name',
                'value'       => 'Marketplace',
                'type'        => 'text',
                'group'       => 'general',
                'label'       => 'Nom de la plateforme',
                'description' => 'Le nom affiché dans les emails et l\'interface.',
            ],
            [
                'key'         => 'platform_email',
                'value'       => 'contact@marketplace.com',
                'type'        => 'text',
                'group'       => 'general',
                'label'       => 'Email de contact',
                'description' => 'L\'adresse email principale de la plateforme.',
            ],
            [
                'key'         => 'maintenance_mode',
                'value'       => 'false',
                'type'        => 'boolean',
                'group'       => 'general',
                'label'       => 'Mode maintenance',
                'description' => 'Active ou désactive la plateforme pour les utilisateurs.',
            ],

            // --- Finance ---
            [
                'key'         => 'commission_rate',
                'value'       => '10',
                'type'        => 'number',
                'group'       => 'finance',
                'label'       => 'Taux de commission (%)',
                'description' => 'Pourcentage prélevé sur chaque vente par la plateforme.',
            ],
            [
                'key'         => 'min_withdrawal_amount',
                'value'       => '1000',
                'type'        => 'number',
                'group'       => 'finance',
                'label'       => 'Montant minimum de retrait',
                'description' => 'Le montant minimum qu\'un vendeur peut demander à retirer.',
            ],
            [
                'key'         => 'default_currency',
                'value'       => 'XOF',
                'type'        => 'text',
                'group'       => 'finance',
                'label'       => 'Devise par défaut',
                'description' => 'La devise utilisée sur toute la plateforme (ISO 4217).',
            ],

            // --- CMS ---
            [
                'key'         => 'homepage_banner_title',
                'value'       => 'La marketplace qui vous connecte',
                'type'        => 'text',
                'group'       => 'cms',
                'label'       => 'Titre de la bannière d\'accueil',
                'description' => 'Le titre principal visible sur la page d\'accueil.',
            ],
            [
                'key'         => 'homepage_banner_subtitle',
                'value'       => 'Découvrez des milliers de produits auprès de vendeurs vérifiés.',
                'type'        => 'textarea',
                'group'       => 'cms',
                'label'       => 'Sous-titre de la bannière',
                'description' => 'Le texte secondaire affiché sous le titre.',
            ],
            [
                'key'         => 'homepage_cta_text',
                'value'       => 'Explorer le catalogue',
                'type'        => 'text',
                'group'       => 'cms',
                'label'       => 'Texte du bouton CTA',
                'description' => 'Le texte du bouton d\'appel à l\'action de la bannière.',
            ],
            [
                'key'         => 'social_facebook',
                'value'       => '',
                'type'        => 'text',
                'group'       => 'cms',
                'label'       => 'Lien Facebook',
                'description' => 'URL complète de la page Facebook (laisser vide pour masquer).',
            ],
            [
                'key'         => 'social_instagram',
                'value'       => '',
                'type'        => 'text',
                'group'       => 'cms',
                'label'       => 'Lien Instagram',
                'description' => 'URL complète du profil Instagram.',
            ],
            [
                'key'         => 'social_twitter',
                'value'       => '',
                'type'        => 'text',
                'group'       => 'cms',
                'label'       => 'Lien Twitter / X',
                'description' => 'URL complète du profil Twitter/X.',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
