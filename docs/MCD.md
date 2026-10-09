# StagePilot : modèle conceptuel de données (MCD)

Application de recherche de stage : l'étudiant répond à un questionnaire (formation, dates,
localisation, expériences), l'application trouve les entreprises autour de lui, écrit des
accroches vérifiées, envoie les candidatures depuis son Gmail, suit les réponses et relance.

> Statut : **proposition à valider** avant d'écrire les migrations.
> Convention : **tout le code est en anglais** (tables, colonnes, modèles, valeurs d'enum) ;
> les explications de ce document restent en français.

## Schéma

```mermaid
erDiagram
    USER ||--|| PROFILE : "has"
    PROGRAM ||--o{ PROFILE : "followed in"
    PROGRAM }o--o{ NAF_CODE : "TARGETS (priority)"
    USER ||--o{ EXPERIENCE : "has"
    USER }o--o{ SKILL : "MASTERS (level)"
    NAF_CODE ||--o{ COMPANY : "classifies"
    USER ||--o{ JOB_APPLICATION : "sends"
    COMPANY ||--o{ JOB_APPLICATION : "receives"
    JOB_APPLICATION ||--o{ SENT_EMAIL : "produces"
    JOB_APPLICATION ||--o{ REPLY : "gets"
    USER ||--o{ ACTIVITY_LOG : "logs"

    USER {
        int id PK
        string name
        string email UK
        string password
        text gmail_app_password "encrypted cast"
    }
    PROFILE {
        int id PK
        int user_id FK "unique"
        int program_id FK
        string school
        date internship_start
        date internship_end
        string address
        decimal latitude
        decimal longitude
        int radius_km
        bool has_driving_licence
        string phone
        string github_url
        string cv_path
        int daily_quota "max 20"
        enum validation_mode "manual | auto_with_veto"
        bool paused
        datetime internship_found_at
    }
    PROGRAM {
        int id PK
        string name "BTS SIO SLAM, BTS SIO SISR..."
    }
    NAF_CODE {
        string code PK "62.01Z"
        string label
    }
    EXPERIENCE {
        int id PK
        int user_id FK
        string title
        string organization
        date started_at
        date ended_at
        text description
        enum role "solo | team"
        bool in_production
        string technologies
    }
    SKILL {
        int id PK
        string name UK
        string category "language, framework, tool, spoken"
    }
    COMPANY {
        string siren PK
        string name
        string naf_code FK
        string headcount
        string address
        string postal_code
        string city
        decimal latitude
        decimal longitude
        string website
        string website_proof "SIREN or address found on the site"
        string email
        string email_note "personal, sales..."
        string phone
        bool blocked "do not contact (for everyone)"
        datetime verified_at
    }
    JOB_APPLICATION {
        int id PK
        int user_id FK
        string company_siren FK
        enum status
        string priority "A | B | C"
        decimal distance_km
        text hook
        text proof_excerpt "copied word for word from the site"
        string proof_url
        bool hook_generated
        datetime approved_at
        datetime sent_at
        datetime followed_up_at
        text notes
    }
    SENT_EMAIL {
        int id PK
        int job_application_id FK
        enum type "application | follow_up"
        string message_id UK "Message-ID header"
        datetime sent_at
    }
    REPLY {
        int id PK
        int job_application_id FK
        enum type "reply | auto_reply | bounce"
        string sender
        string subject
        datetime received_at
        bool draft_created
    }
    ACTIVITY_LOG {
        int id PK
        int user_id FK
        int job_application_id FK "nullable"
        string action
        text details
        datetime created_at
    }
```

## Associations et cardinalités (Merise)

| Association | Cardinalités | Remarque |
|---|---|---|
| USER — has — PROFILE | 1,1 — 1,1 | créé à la fin du questionnaire |
| PROFILE — follows — PROGRAM | 1,1 — 0,n | |
| PROGRAM — **TARGETS** — NAF_CODE | 0,n — 0,n | porteuse : `priority` (A/B/C). Ex. SLAM → 62.01Z en A, 95.11Z en C |
| USER — has — EXPERIENCE | 0,n — 1,1 | |
| USER — **MASTERS** — SKILL | 0,n — 0,n | porteuse : `level` (basic / good / very_good) |
| COMPANY — classified by — NAF_CODE | 1,1 — 0,n | |
| USER — **APPLIES TO** — COMPANY | 0,n — 0,n | porteuse → entité JOB_APPLICATION (statut, accroche, preuve, dates) |
| JOB_APPLICATION — produces — SENT_EMAIL | 0,n — 1,1 | 1 candidature + 0 ou 1 relance |
| JOB_APPLICATION — gets — REPLY | 0,n — 1,1 | |
| USER — logs — ACTIVITY_LOG | 0,n — 1,1 | |

## Règles de gestion

1. Un utilisateur ne candidate **qu'une fois** par entreprise (clé unique `user_id + company_siren`).
2. Le catalogue COMPANY est **partagé** : un site ou un email trouvé sert à tous les utilisateurs.
3. Une entreprise reçoit **au plus 2 candidatures de la plateforme par mois** (tous utilisateurs confondus).
4. Une entreprise `blocked` (demande « ne plus me contacter ») n'est plus jamais contactée.
5. Statuts (`status`) : `pending_review` → `ready` → `sent` → `followed_up`, puis `reply_received` →
   `positive` | `negative` | `interview` | `internship_secured` ; ou `ignored` / `do_not_contact`.
6. Une accroche (`hook`) n'est valable que si elle respecte les règles d'honnêteté **et** si
   `proof_excerpt` est retrouvé sur la page `proof_url` du site de l'entreprise.
7. En mode `auto_with_veto`, une accroche écrite automatiquement ne part pas le jour même.
8. Au plus `daily_quota` (≤ 20) mails par utilisateur et par jour ; pas d'envoi le week-end, les jours
   fériés, ni dans les 15 jours avant `internship_start`.
9. Une seule relance, 7 jours après l'envoi, si aucune réponse.
10. Dès qu'une candidature passe en `internship_secured`, plus aucun envoi pour cet utilisateur.
11. Chaque `message_id` est unique : registre anti-doublon, et lien entre une réponse et sa candidature.
12. Une EXPERIENCE avec `role = team` ne peut jamais être présentée comme un travail fait seul
    (« j'ai développé seul… » est refusé par le vérificateur d'accroches).

## MLD (modèle logique)

- users (**id**, name, email, password, gmail_app_password*) — *chiffré (cast `encrypted`)
- programs (**id**, name)
- naf_codes (**code**, label)
- naf_code_program (**#program_id, #naf_code**, priority)
- profiles (**id**, #user_id UNIQUE, #program_id, school, internship_start, internship_end, address, latitude, longitude, radius_km, has_driving_licence, phone, github_url, cv_path, daily_quota, validation_mode, paused, internship_found_at)
- experiences (**id**, #user_id, title, organization, started_at, ended_at, description, role, in_production, technologies)
- skills (**id**, name UNIQUE, category)
- skill_user (**#user_id, #skill_id**, level)
- companies (**siren**, name, #naf_code, headcount, address, postal_code, city, latitude, longitude, website, website_proof, email, email_note, phone, blocked, verified_at)
- job_applications (**id**, #user_id, #company_siren, status, priority, distance_km, hook, proof_excerpt, proof_url, hook_generated, approved_at, sent_at, followed_up_at, notes) UNIQUE(user_id, company_siren)
- sent_emails (**id**, #job_application_id, type, message_id UNIQUE, sent_at)
- replies (**id**, #job_application_id, type, sender, subject, received_at, draft_created)
- activity_logs (**id**, #user_id, #job_application_id NULL, action, details, created_at)

Modèles Eloquent : `User`, `Profile`, `Program`, `NafCode`, `Experience`, `Skill`, `Company`,
`JobApplication`, `SentEmail`, `Reply`, `ActivityLog`.
Noms évités volontairement : `Application` et `Message` (déjà utilisés par Laravel).

## Correspondance avec le prototype Python (projet stage_bts)

| Prototype | StagePilot |
|---|---|
| `prospects_chalon.xlsx` (onglet Suivi) | `companies` + `job_applications` |
| `envois.csv` | `sent_emails` |
| `preuves_accroches.csv` | `job_applications.proof_excerpt / proof_url` |
| `journal.txt` | `activity_logs` |
| `config.py` (infos perso, quotas) | `profiles` |
| profil « ne jamais exagérer » | `experiences.role` + `in_production` |
| `prospects.py`, `sites.py`, `emails.py` | jobs `SearchCompanies`, `FindWebsite`, `FindEmail` |
| `regles_accroches.py` + tâche Claude | job `GenerateHook` (API Claude) + service `HookVerifier` |
| `envoi.py`, `reponses.py`, `matin.py` | jobs `SendJobApplication`, `CheckReplies`, `SendDailyDigest` + planificateur |
