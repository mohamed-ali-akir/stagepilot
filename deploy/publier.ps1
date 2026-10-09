# Publishes StagePilot to alwaysdata from this PC: .\deploy\publier.ps1
# Needs the SSH key set up once (see deploy/README.md). Pushes the committed code only, never the local database.
# not 'Stop': Windows PowerShell treats any warning written by npm / git as an error; exit codes are checked instead
$ErrorActionPreference = 'Continue'
Set-Location (Split-Path $PSScriptRoot -Parent)

if (git status --porcelain) { throw "Des modifications ne sont pas enregistrées (git commit) : publication annulée." }

Write-Host ">> Construction du CSS / JS"
npm run build
if ($LASTEXITCODE) { throw "npm run build a échoué" }

Write-Host ">> Envoi du code"
ssh alwaysdata "test -d stagepilot.git || git init --bare -q stagepilot.git; mkdir -p stagepilot"
git push alwaysdata main --force
if ($LASTEXITCODE) { throw "git push a échoué" }

Write-Host ">> Envoi des fichiers CSS / JS construits"
ssh alwaysdata "rm -rf stagepilot/public/build; mkdir -p stagepilot/public"
scp -r -q public/build alwaysdata:stagepilot/public/build
if ($LASTEXITCODE) { throw "scp a échoué" }

Write-Host ">> Installation sur le serveur"
ssh alwaysdata "git --git-dir=stagepilot.git --work-tree=stagepilot checkout -f main && bash stagepilot/deploy/deploy.sh"
if ($LASTEXITCODE) { throw "deploy.sh a échoué" }

Write-Host ">> En ligne : https://akir.alwaysdata.net"
