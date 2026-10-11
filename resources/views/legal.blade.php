<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => 'Mentions légales et confidentialité'])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 dark:bg-zinc-900 dark:text-white">
        <header class="mx-auto max-w-3xl p-6">
            <x-app-logo href="{{ route('home') }}" />
        </header>

        <main class="mx-auto max-w-3xl space-y-8 px-6 pb-16">
            <flux:heading size="xl" level="1">Mentions légales et confidentialité</flux:heading>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">Éditeur</flux:heading>
                <flux:text>
                    StagePilot est un projet étudiant non commercial, réalisé par Mohamed-Ali Akir dans le cadre du BTS SIO option SLAM
                    (Lycée Mathias, Chalon-sur-Saône). Contact : <flux:link href="mailto:{{ config('stagepilot.contact_email') }}">{{ config('stagepilot.contact_email') }}</flux:link>.
                </flux:text>
            </section>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">Hébergeur</flux:heading>
                <flux:text>
                    ALWAYSDATA, SARL, 91 rue du Faubourg Saint-Honoré, 75008 Paris, France ·
                    <flux:link href="https://www.alwaysdata.com" target="_blank" rel="noopener">alwaysdata.com</flux:link>.
                    Les données sont stockées en France.
                </flux:text>
            </section>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">Données collectées et pourquoi</flux:heading>
                <flux:text>Le site sert uniquement à automatiser ta recherche de stage, à ta demande. Il conserve :</flux:text>
                <ul class="list-disc space-y-1 ps-6 text-sm text-zinc-600 dark:text-zinc-300">
                    <li>ton compte : nom, email, mot de passe (stocké sous forme d'empreinte, illisible) ;</li>
                    <li>ton profil : formation, établissement, dates de stage, adresse ou ville (pour calculer les distances), expériences, compétences, téléphone, liens GitHub et portfolio, CV en PDF ;</li>
                    <li>le mot de passe d'application Gmail, chiffré dans la base, pour envoyer depuis ta boîte et y lire les réponses des entreprises (lecture seule, plus un brouillon de réponse) ;</li>
                    <li>tes candidatures : entreprises, accroches, dates d'envoi, réponses repérées ;</li>
                    <li>les entreprises : données publiques (répertoire SIRENE de l'État) et informations publiées sur leur propre site.</li>
                </ul>
                <flux:text>Rien n'est revendu, il n'y a ni publicité ni outil de mesure d'audience.</flux:text>
            </section>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">Avec qui elles sont partagées</flux:heading>
                <ul class="list-disc space-y-1 ps-6 text-sm text-zinc-600 dark:text-zinc-300">
                    <li>les entreprises que tu valides reçoivent ton mail de candidature et ton CV, envoyés depuis ton Gmail (Google) ;</li>
                    <li>le service d'écriture des accroches (Groq, États-Unis) reçoit ta formation, ton établissement, tes expériences et tes compétences, jamais ton nom, ton email, ton téléphone ni ton adresse ;</li>
                    <li>les API publiques de l'État reçoivent ton adresse (pour la situer) et sa position (pour trouver les entreprises proches).</li>
                </ul>
            </section>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">Durée et suppression</flux:heading>
                <flux:text>
                    Les données sont gardées tant que ton compte existe. « Paramètres › Supprimer le compte » efface tout : profil, CV,
                    candidatures et accès à l'API. Une sauvegarde de la base est faite chaque nuit et gardée 14 jours, puis effacée.
                </flux:text>
            </section>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">Tes droits</flux:heading>
                <flux:text>
                    Tu peux consulter, corriger ou supprimer tes données à tout moment depuis le site, ou en écrivant à l'adresse de contact.
                    Une entreprise qui ne souhaite plus être contactée peut l'écrire à la même adresse : elle sera exclue de toutes les recherches.
                    En cas de désaccord, tu peux t'adresser à la <flux:link href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">CNIL</flux:link>.
                </flux:text>
            </section>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">Cookies</flux:heading>
                <flux:text>
                    Seulement les cookies nécessaires au fonctionnement : la session (rester connecté) et la protection des formulaires.
                    Pas de cookie de suivi, donc pas de bandeau à accepter.
                </flux:text>
            </section>
        </main>

        @fluxScripts
    </body>
</html>
