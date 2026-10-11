# Mise en ligne sur alwaysdata (offre gratuite)

Adresse : https://akir.alwaysdata.net

## Une seule fois

1. Panneau alwaysdata > **Accès distant > SSH** : modifier l'utilisateur `akir` et cocher **connexion par mot de passe**.
2. Dans PowerShell, installer la clé SSH du PC (le mot de passe alwaysdata est demandé une dernière fois) :

   ```powershell
   Get-Content $env:USERPROFILE\.ssh\id_ed25519_alwaysdata.pub | ssh akir@ssh-akir.alwaysdata.net "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"
   ```

3. Panneau > **Avancé > Tâches planifiées** > Ajouter :
   - commande : `bash /home/akir/stagepilot/deploy/cron.sh`
   - fréquence : toutes les 10 minutes

## À chaque mise à jour

```powershell
.\deploy\publier.ps1
```

Le script construit le CSS/JS, envoie le code (jamais la base locale), installe les dépendances et applique les migrations.

## Inviter un ami

Remplacer la liste complète (toujours garder sa propre adresse), depuis PowerShell :

```powershell
'aliakir679@gmail.com,adresse.ami@gmail.com' | ssh alwaysdata "cd stagepilot && php deploy/set_env.php INVITED_EMAILS && php artisan optimize"
```

## Changer une clé (Groq) sans l'afficher

```powershell
$key = (Select-String -Path .env -Pattern '^GROQ_API_KEY=(.+)$').Matches[0].Groups[1].Value
$key | ssh alwaysdata "cd stagepilot && php deploy/set_env.php GROQ_API_KEY && php artisan optimize"; Remove-Variable key
```

## Déploiement automatique (facultatif)

Le workflow `.github/workflows/deploy.yml` publie le site après chaque série de tests réussie sur `main` (**actif depuis le 2026-10-11**). Il serait inactif sans le secret `ALWAYSDATA_SSH_KEY`, créé ainsi :

1. Créer une clé réservée au déploiement et l'autoriser sur le serveur :

   ```powershell
   ssh-keygen -t ed25519 -N '""' -C "github-deploy" -f $env:USERPROFILE\.ssh\stagepilot_github_deploy
   Get-Content $env:USERPROFILE\.ssh\stagepilot_github_deploy.pub | ssh alwaysdata "cat >> ~/.ssh/authorized_keys"
   ```

2. Sur GitHub : **Settings > Secrets and variables > Actions > New repository secret**, nom `ALWAYSDATA_SSH_KEY`, valeur = le contenu du fichier `stagepilot_github_deploy` (la clé privée, sans `.pub`).

Pour couper le déploiement automatique : supprimer le secret (et la ligne `github-deploy` de `~/.ssh/authorized_keys` sur le serveur).
