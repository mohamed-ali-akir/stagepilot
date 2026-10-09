# StagePilot

Application Laravel de recherche de stage automatisée, projet de BTS SIO option SLAM.

L'étudiant remplit un questionnaire : formation, dates de stage, adresse, expériences, compétences et CV. L'application fait ensuite le reste :

1. elle **trouve les entreprises** autour de lui avec l'API publique `recherche-entreprises.api.gouv.fr`, filtrées sur les codes NAF de sa formation ;
2. elle **trouve leur site web**, qu'elle ne garde que s'il affiche le SIREN ou l'adresse de l'entreprise, puis leur **email** ;
3. elle **écrit une accroche** personnalisée avec Claude (API Anthropic). Chaque accroche est accompagnée d'un **extrait copié du site**, retrouvé automatiquement sur la page : c'est ce qui empêche d'inventer ;
4. l'étudiant **valide** les accroches (ou choisit le mode automatique, avec une nuit pour annuler) ;
5. elle **envoie** les candidatures depuis son Gmail : 20 par jour au maximum, pas le week-end, 1 à 3 minutes entre deux mails, une seule relance au bout de 7 jours ;
6. elle **lit les réponses** (IMAP, en lecture seule). Elle repère les vraies réponses, les réponses automatiques et les adresses invalides, et prépare un **brouillon de réponse** dans Gmail, sans jamais l'envoyer ;
7. elle envoie un **résumé chaque matin**.

Le modèle de données est décrit dans [docs/MCD.md](docs/MCD.md) (MCD, règles de gestion, MLD).

## Technologies

Laravel 13 · Livewire 4 (composants classe) · Flux UI · Fortify (connexion) · Pest (tests) · SQLite en développement (MySQL en production) · SDK PHP Anthropic · webklex/php-imap · Symfony Mailer.

## Installation

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # crée les tables + les formations et codes NAF
```

Dans `.env` :

- `ANTHROPIC_API_KEY` : la clé de l'API Claude (sur https://console.anthropic.com). Sans clé, tout fonctionne sauf l'écriture automatique des accroches : l'étudiant peut alors les écrire lui-même.
- `ANTHROPIC_MODEL` : le modèle utilisé (par défaut `claude-opus-5-5`).
- `MAIL_MAILER` : pour les mails de vérification de compte et de mot de passe oublié.

Chaque étudiant enregistre son propre **mot de passe d'application Gmail** dans le questionnaire. Il est **chiffré** en base (cast `encrypted`).

## Lancer l'application

```bash
php artisan serve          # l'application, sur http://localhost:8000
php artisan queue:work     # exécute les tâches en arrière-plan (préparation, envois espacés)
php artisan schedule:work  # déclenche la préparation à 8h et la routine à 9h
```

Commandes utiles :

| Commande | Effet |
|---|---|
| `php artisan stagepilot:prepare --user=1 --now` | Prépare les prochaines candidatures d'un étudiant, tout de suite |
| `php artisan stagepilot:morning --user=1` | Lit les réponses, programme les envois du jour, envoie le résumé |
| `php artisan test` | Lance les tests (Gmail et Claude sont simulés, aucun mail n'est envoyé) |

## Architecture

| Dossier | Rôle |
|---|---|
| `app/Livewire` | Les écrans : `Onboarding` (questionnaire), `Dashboard`, `Review` (à valider), `Applications` (suivi) |
| `app/Services` | La logique métier : `CompanySearch`, `WebsiteFinder`, `EmailFinder`, `ApplicationPreparer`, `ApplicationSender`, `ReplyChecker`, `DailyDigest` |
| `app/Services/Hooks` | Les accroches : `HookRules` (règles d'honnêteté), `HookVerifier` (preuve sur le site), `ClaudeHookGenerator` (API Claude) |
| `app/Services/Mail` | L'accès à Gmail derrière une interface `Mailbox` (remplacée par un faux dans les tests) |
| `app/Jobs`, `app/Console/Commands`, `routes/console.php` | Les tâches en arrière-plan et le planning quotidien |
| `app/Enums` | Les statuts et types (candidature, expérience, réponse…) |
| `tests` | Les tests Pest : règles, vérification, recherche, envoi, réponses, écrans |

## Garde-fous

- Une seule candidature par étudiant et par entreprise (clé unique), et chaque mail envoyé est enregistré avec son `Message-ID` unique.
- Au maximum 2 candidatures de la plateforme par entreprise et par mois.
- « Ne pas contacter » bloque l'entreprise pour tous les étudiants.
- Les envois sont **en pause** à la création du compte : l'étudiant les active lui-même.
- Le statut « stage obtenu » arrête tout.
- L'accroche est revérifiée juste avant chaque envoi.

Projet réalisé avec un développement assisté par IA (Claude Code).
