<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\ReplyType;
use App\Enums\SentEmailType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\Program;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The public demo account ("Essayer la démo" button): a student with fictional companies and applications
 * at every stage, so that a visitor can see the whole app without signing up.
 *
 * Safety: the account has no Gmail password, is_demo blocks sending (User::canSendEmails) and Gmail itself
 * (GmailMailbox), the morning tasks skip it, and every company uses a ".test" address that cannot exist.
 * Everything is rebuilt every night (stagepilot:demo-reset), so visitors cannot break it.
 */
class DemoAccount
{
    public const EMAIL = 'demo@stagepilot.test';

    /** Fictional SIRENs start with 000: no real company has one. */
    private const COMPANIES = [
        ['000000001', 'Logivia', '58.29C', 'Édition de logiciels applicatifs', 'Chalon-sur-Saône', 2.1],
        ['000000002', 'Pixelune', '73.11Z', 'Agence web', 'Saint-Rémy', 4.3],
        ['000000003', 'Datacep', '62.01Z', 'Programmation informatique', 'Chalon-sur-Saône', 1.4],
        ['000000004', 'Codaxe Solutions', '62.02A', 'Conseil en systèmes et logiciels', 'Châtenoy-le-Royal', 3.8],
        ['000000005', 'Vignoble Connect', '62.01Z', 'Programmation informatique', 'Beaune', 29.5],
        ['000000006', 'Atelier Kerno', '62.01Z', 'Programmation informatique', 'Givry', 9.2],
        ['000000007', 'Sôneo Digital', '73.11Z', 'Agence web', 'Chalon-sur-Saône', 0.9],
        ['000000008', 'Mobilia Apps', '58.29C', 'Édition de logiciels applicatifs', 'Tournus', 26.7],
        ['000000009', 'Infogest 71', '62.03Z', 'Gestion d\'installations informatiques', 'Montceau-les-Mines', 33.1],
        ['000000010', 'Bureau Numérique Bressan', '62.02A', 'Conseil en systèmes et logiciels', 'Louhans', 34.0],
        ['000000011', 'Signalétique Morvan', '62.01Z', 'Programmation informatique', 'Le Creusot', 30.2],
        ['000000012', 'Quadrant Informatique', '62.03Z', 'Gestion d\'installations informatiques', 'Chagny', 15.6],
    ];

    /** siren => [status, phrase of the site (proof), hook, days since sent] */
    private const APPLICATIONS = [
        '000000001' => ['pending_review', 'Logivia édite un logiciel de gestion pour les cabinets d\'architectes, utilisé chaque jour par plus de 200 agences.',
            'Votre logiciel de gestion pour les cabinets d\'architectes m\'intéresse particulièrement, car j\'ai participé en stage à la migration d\'une application de gestion vers Laravel.', null],
        '000000002' => ['pending_review', 'Pixelune conçoit des sites vitrines et des boutiques en ligne pour les commerçants de Saône-et-Loire.',
            'Vos boutiques en ligne pour les commerçants de Saône-et-Loire m\'intéressent particulièrement, car j\'aimerais travailler sur des projets utilisés au quotidien par des clients proches.', null],
        '000000003' => ['pending_review', 'Datacep développe des applications métier sur mesure en PHP et Laravel pour les PME industrielles.',
            'Vos applications métier en PHP et Laravel pour les PME industrielles m\'intéressent particulièrement, car c\'est la technologie sur laquelle j\'ai contribué pendant mon stage.', null],
        '000000004' => ['ready', 'Codaxe Solutions accompagne les entreprises dans la modernisation de leurs anciens logiciels.',
            'Votre travail de modernisation des anciens logiciels m\'intéresse particulièrement, car j\'ai contribué à la migration module par module d\'une application PHP vers Laravel.', null],
        '000000005' => ['ready', 'Vignoble Connect propose une application mobile de suivi des parcelles pour les viticulteurs de Bourgogne.',
            'Votre application mobile de suivi des parcelles pour les viticulteurs m\'intéresse particulièrement, car j\'ai participé à une application Android utilisée par des employés.', null],
        '000000006' => ['sent', 'Atelier Kerno crée des outils internes pour automatiser la facturation et les devis des artisans.',
            'Vos outils d\'automatisation de la facturation et des devis pour les artisans m\'intéressent particulièrement, car j\'ai travaillé en stage sur une application de devis et de factures.', 3],
        '000000007' => ['followed_up', 'Sôneo Digital réalise des sites internet accessibles pour les collectivités et les associations.',
            'Vos sites internet accessibles pour les collectivités m\'intéressent particulièrement, car j\'aimerais apprendre à concevoir des interfaces utilisables par tous.', 10],
        '000000008' => ['reply_received', 'Mobilia Apps développe des applications mobiles de réservation pour les loisirs et le tourisme.',
            'Vos applications de réservation pour le tourisme m\'intéressent particulièrement, car j\'ai adapté en stage un plugin de recherche pour un site touristique.', 6],
        '000000009' => ['interview', 'Infogest 71 assure la maintenance du parc informatique et le développement d\'outils de suivi pour les mairies.',
            'Vos outils de suivi développés pour les mairies m\'intéressent particulièrement, car j\'ai participé à des applications utilisées au quotidien par des employés.', 12],
        '000000010' => ['negative', 'Le Bureau Numérique Bressan conseille les PME dans le choix et le paramétrage de leurs logiciels de gestion.',
            'Votre accompagnement des PME dans leurs logiciels de gestion m\'intéresse particulièrement, car j\'ai travaillé en stage sur une application de gestion clients et factures.', 15],
    ];

    /** Deletes the demo account (and everything linked to it) and builds it again. */
    public function reset(): User
    {
        return DB::transaction(function () {
            User::where('is_demo', true)->get()->each->delete();

            $user = $this->student();
            $companies = $this->companies();
            $this->applications($user, $companies);

            return $user->fresh(['profile', 'experiences', 'skills']);
        });
    }

    /** The demo account, created on first use. */
    public function user(): User
    {
        return User::where('is_demo', true)->first() ?? $this->reset();
    }

    private function student(): User
    {
        $user = User::create(['name' => 'Camille (démo)', 'email' => self::EMAIL, 'password' => Str::random(40)]);
        $user->forceFill(['is_demo' => true, 'email_verified_at' => now()])->save();

        $user->profile()->create([
            'program_id' => Program::firstOrCreate(['name' => 'BTS SIO option SLAM'])->id,
            'school' => 'Lycée de démonstration, Chalon-sur-Saône',
            'internship_start' => now()->addMonths(3)->startOfWeek()->toDateString(),
            'internship_end' => now()->addMonths(3)->startOfWeek()->addWeeks(6)->subDays(3)->toDateString(),
            'address' => 'Place de l\'Hôtel de Ville, 71100 Chalon-sur-Saône',
            'city' => 'Chalon-sur-Saône',
            'latitude' => 46.7806,
            'longitude' => 4.8539,
            'radius_km' => 35,
            'has_driving_licence' => true,
            'phone' => '06 00 00 00 00',
            'daily_quota' => 10,
            'validation_mode' => 'manual',
            'paused' => true,
        ]);

        $user->experiences()->createMany([
            ['title' => 'Stage de 1re année', 'organization' => 'Entreprise fictive', 'role' => 'team', 'in_production' => true,
                'started_at' => now()->subMonths(5)->toDateString(), 'ended_at' => now()->subMonths(4)->toDateString(),
                'description' => 'Migration module par module d\'une application de gestion PHP vers Laravel : clients, devis, factures.',
                'technologies' => 'PHP, Laravel, MySQL'],
            ['title' => 'Application de réservation', 'organization' => 'Projet de cours', 'role' => 'solo', 'in_production' => false,
                'description' => 'Petite application web de réservation de salles avec authentification et tableau de bord.',
                'technologies' => 'PHP, JavaScript, SQL'],
        ]);

        foreach (['PHP' => 'good', 'Laravel' => 'basic', 'SQL' => 'good', 'JavaScript' => 'basic'] as $name => $level) {
            $user->skills()->attach(Skill::firstOrCreate(['name' => $name]), ['level' => $level]);
        }

        return $user;
    }

    /** @return array<string, Company> */
    private function companies(): array
    {
        $companies = [];
        foreach (self::COMPANIES as [$siren, $name, $naf, $activity, $city]) {
            $domain = Str::slug($name).'.test';
            $companies[$siren] = Company::updateOrCreate(['siren' => $siren], [
                'name' => $name,
                'naf_code' => $naf,
                'activity' => $activity,
                'headcount' => '10-19',
                'city' => $city,
                'postal_code' => '71100',
                'website' => 'https://www.'.$domain.'/',
                'website_searched' => true,
                // the Le Creusot company has no email: shown in "à appeler"
                'email' => $siren === '000000011' ? null : 'contact@'.$domain,
                'email_searched' => true,
                'phone' => '03 85 00 00 '.substr($siren, -2),
                'verified_at' => now(),
            ]);
        }

        return $companies;
    }

    /** @param  array<string, Company>  $companies */
    private function applications(User $user, array $companies): void
    {
        foreach (self::COMPANIES as [$siren, , , , , $distance]) {
            $company = $companies[$siren];
            [$status, $proof, $hook, $sentDaysAgo] = self::APPLICATIONS[$siren] ?? [null, null, null, null];

            $application = $user->jobApplications()->create([
                'company_siren' => $siren,
                'status' => $status ?? ApplicationStatus::Ignored->value,
                'priority' => in_array($company->naf_code, ['58.29C', '62.01Z', '62.02A'], true) ? 'A' : 'B',
                'distance_km' => $distance,
                'hook' => $hook,
                'proof_excerpt' => $proof,
                'proof_url' => $proof ? $company->website : null,
                'hook_generated' => $hook !== null,
                'approved_at' => $sentDaysAgo !== null || $status === 'ready' ? now()->subDays(($sentDaysAgo ?? 0) + 1) : null,
                'sent_at' => $sentDaysAgo !== null ? now()->subDays($sentDaysAgo)->setTime(9, 4) : null,
                'followed_up_at' => $status === 'followed_up' ? now()->subDays(3)->setTime(9, 2) : null,
                'notes' => match ($siren) {
                    '000000011' => '['.now()->format('d/m').'] aucun email trouvé : à appeler',
                    '000000012' => '['.now()->format('d/m').'] hors sujet : maintenance de matériel uniquement, pas de développement',
                    '000000009' => '['.now()->subDays(2)->format('d/m').'] Entretien le '.now()->addDays(4)->translatedFormat('l j F').' à 14 h',
                    default => null,
                },
            ]);

            $this->history($user, $application);
        }
    }

    /** Sent emails, replies and activity lines matching the status, so the whole story is visible. */
    private function history(User $user, JobApplication $application): void
    {
        $company = $application->company;
        $log = fn (string $action, string $details, int $daysAgo) => ActivityLog::record($user, $action, $details, $application)
            ->forceFill(['created_at' => now()->subDays($daysAgo)->setTime(9, 5)])->save();

        if ($application->hook) {
            $log('hook_written', $application->hook, ($application->sent_at?->diffInDays(now()) ?? 0) + 2);
        }
        if ($application->sent_at) {
            $application->sentEmails()->create([
                'type' => SentEmailType::Application, 'recipient' => $company->email,
                'message_id' => '<demo-'.$company->siren.'@stagepilot.test>', 'sent_at' => $application->sent_at,
            ]);
            $log('application_sent', 'Candidature envoyée à '.$company->email.' (simulation de démo)', (int) $application->sent_at->diffInDays(now()));
        }
        if ($application->followed_up_at) {
            $application->sentEmails()->create([
                'type' => SentEmailType::FollowUp, 'recipient' => $company->email,
                'message_id' => '<demo-relance-'.$company->siren.'@stagepilot.test>', 'sent_at' => $application->followed_up_at,
            ]);
            $log('follow_up_sent', 'Relance envoyée (simulation de démo)', 3);
        }
        if (in_array($application->status, [ApplicationStatus::ReplyReceived, ApplicationStatus::Interview, ApplicationStatus::Negative], true)) {
            $application->replies()->create([
                'type' => ReplyType::Reply, 'sender' => $company->email,
                'subject' => 'Re: Candidature spontanée - stage développeur web',
                'message_id' => '<demo-reponse-'.$company->siren.'@stagepilot.test>',
                'received_at' => now()->subDays(1)->setTime(16, 20), 'draft_created' => true,
            ]);
            $log('reply_received', 'Réponse de '.$company->name.' : brouillon de réponse préparé dans Gmail (simulation de démo)', 1);
        }
    }
}
