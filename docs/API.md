# API REST StagePilot (v1)

Base : `https://akir.alwaysdata.net/api/v1` (en local : `http://localhost:8000/api/v1`).
Toutes les réponses sont en JSON. Chaque étudiant ne voit **que ses propres** candidatures.

## Authentification (Laravel Sanctum)

Deux façons, au choix :

1. **Cookie de session** : quand l'étudiant est connecté au site, le module Vue appelle l'API avec son cookie (mode « SPA » de Sanctum, protégé contre le CSRF par l'en-tête `X-XSRF-TOKEN`).
2. **Jeton personnel** (pour un script, Postman, une appli mobile…) :

   ```bash
   php artisan stagepilot:api-token etudiant@example.com --name=postman
   ```

   puis envoyer l'en-tête `Authorization: Bearer <jeton>`. Le jeton n'est affiché qu'une fois.

Limite : **60 requêtes par minute** et par étudiant.

## Codes de réponse

| Code | Quand |
|---|---|
| `200` | OK |
| `401` | pas connecté / jeton absent ou invalide |
| `403` | la candidature appartient à un autre étudiant (Policy `JobApplicationPolicy`) |
| `404` | la candidature n'existe pas |
| `422` | données invalides (le détail est dans `errors`) |
| `429` | plus de 60 requêtes dans la minute |

## Routes

### `GET /applications`

Liste paginée des candidatures, les plus récemment modifiées d'abord.

| Paramètre | Exemple | Rôle |
|---|---|---|
| `status` | `sent,followed_up` | un ou plusieurs statuts, séparés par des virgules |
| `search` | `beaune` | nom ou ville de l'entreprise |
| `per_page` | `20` | de 1 à 100 (20 par défaut) |

```json
{
  "data": [
    {
      "id": 12,
      "status": { "value": "interview", "label": "Entretien", "color": "lime" },
      "priority": "A",
      "distance_km": 33.1,
      "hook": "Vos outils de suivi développés pour les mairies m'intéressent…",
      "proof": { "excerpt": "Infogest 71 assure la maintenance…", "url": "https://www.infogest-71.test/" },
      "company": { "siren": "000000009", "name": "Infogest 71", "activity": "…", "naf_code": "62.03Z", "city": "Montceau-les-Mines", "website": "…", "email": "…", "phone": "…", "do_not_contact": false },
      "sent_at": "2026-09-28T09:04:00+02:00",
      "followed_up_at": null,
      "replies_count": 1,
      "updated_at": "2026-10-10T18:20:00+02:00"
    }
  ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta": { "current_page": 1, "per_page": 20, "total": 12, "last_page": 1 }
}
```

### `GET /applications/{id}`

Une candidature (même format, dans `data`). `403` si elle appartient à un autre étudiant.

### `PATCH /applications/{id}/status`

Change le statut **après une réponse de l'entreprise**. Seuls les statuts posés à la main sont acceptés : `positive`, `negative`, `interview`, `internship_secured`, `do_not_contact`. Les autres (`sent`, `ready`…) sont posés par l'application elle-même → `422`.

```bash
curl -X PATCH https://akir.alwaysdata.net/api/v1/applications/12/status \
  -H "Authorization: Bearer <jeton>" -H "Accept: application/json" \
  -H "Content-Type: application/json" -d '{"status": "interview"}'
```

Mêmes règles que l'écran « Candidatures » (classe `ApplicationStatusChanger`) : `do_not_contact` bloque l'entreprise pour tous les étudiants, `internship_secured` arrête tous les envois.

### `GET /companies`

Les entreprises auxquelles l'étudiant a candidaté (paginé, 20 par page).

### `GET /stats`

```json
{
  "data": {
    "by_status": { "pending_review": 3, "ready": 2, "sent": 1, "followed_up": 1, "reply_received": 1, "interview": 1, "negative": 1, "…": 0 },
    "sent": 5,
    "answered": 3,
    "reply_rate": 60.0,
    "average_reply_days": 4.5,
    "by_week": [ { "week": "2026-09-21", "sent": 1, "answered": 1, "reply_rate": 100.0 } ],
    "by_sector": [ { "sector": "Agence web", "sent": 2, "answered": 1, "reply_rate": 50.0 } ]
  }
}
```

- `answered` / `reply_rate` : seules les **vraies** réponses comptent (pas les réponses automatiques ni les adresses invalides).
- `average_reply_days` : délai moyen entre l'envoi et la première vraie réponse, en jours.
- `by_week` : les 8 dernières semaines (lundi de chaque semaine).
- Les taux valent `null` quand rien n'a été envoyé (pas de division par zéro).

## Où est le code

| Élément | Fichier |
|---|---|
| Routes | `routes/api.php` |
| Contrôleurs | `app/Http/Controllers/Api/V1/` |
| Format JSON | `app/Http/Resources/JobApplicationResource.php`, `CompanyResource.php` |
| Validation | `app/Http/Requests/UpdateApplicationStatusRequest.php` |
| Droits | `app/Policies/JobApplicationPolicy.php` |
| Statistiques | `app/Services/ApplicationStats.php` |
| Tests | `tests/Feature/Api/` |
