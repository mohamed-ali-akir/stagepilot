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

Sur le serveur, ajouter son adresse dans `INVITED_EMAILS` du fichier `~/stagepilot/.env` (séparées par des virgules), puis `php artisan optimize`.
