# khartasia_deploy_master.ps1 — Synchronisation sécurisée Khartasia
# Local DDEV (11.3.11) ↔ Hetzner (11.3.11)
#
# RÈGLES ABSOLUES (leçons Wallpaper Canton) :
#   1. Jamais composer update/require directement sur Hetzner
#   2. Hetzner = source de vérité pour les modules custom
#   3. Synchro DB : toujours Hetzner → local SAUF restore explicite confirmé
#   4. Backup OBLIGATOIRE avant toute opération sur Hetzner
#   5. Modules : toujours Hetzner → local (jamais l'inverse)
#   6. Mise à jour core : local → Hetzner via vendor compressé uniquement
#
# CHANGELOG :
#   - Les synchros de dossiers (modules, thèmes, modules/contrib) utilisent
#     désormais rsync --delete au lieu de scp -rp, pour éviter que d'anciens
#     fichiers restent sur la destination après un changement de structure
#     d'un module/thème (cause du bug ui_icons "should not implement theme
#     more than once" du 02/09/2026).
#   - Option 3 : la synchro des thèmes ne dépend plus de la présence de
#     modules custom (bug corrigé — le bloc entier était sauté si
#     $MODULES_CUSTOM était vide).
#   - Option 5 : synchronise désormais aussi web/modules/contrib/ vers
#     Hetzner après la mise à jour du core, pour éviter tout décalage
#     core/contrib comme celui du 02/09/2026 (erreurs bibcite/Twig).

# === VARIABLES — à adapter ===
$HETZNER_HOST   = "hetzner-khartasia"           # alias SSH dans ~/.ssh/config (utilisé par ssh/scp natifs Windows)
$HETZNER_ROOT   = "/var/www/khartasia"
$HETZNER_WEB    = "$HETZNER_ROOT/web"
$LOCAL_ROOT     = "C:\var\www\@projects\khartasia"
$BACKUP_DIR     = "$LOCAL_ROOT\db"
$DDEV_PROJECT   = "khartasia"                   # nom du projet DDEV
$DDEV_CONTAINER = "ddev-khartasia-web"          # nom du container Docker

# rsync tourne via WSL (distro Ubuntu-24.04, précisée explicitement pour
# éviter toute ambiguïté avec d'autres distros installées). WSL a son
# PROPRE client SSH : l'alias hetzner-khartasia de ~/.ssh/config Windows
# n'y est pas reconnu, donc IP/utilisateur/clé sont fournis explicitement.
# La clé /home/wojtek/.ssh/id_rsa (native WSL, même empreinte que la clé
# Windows) est utilisée directement — pas de copie ni de /mnt/c/ pour la
# clé, afin d'éviter les soucis de permissions Unix sur les montages
# drvfs (cf. incident du 02/09/2026 : chmod sans effet sur /mnt/c/).
$HETZNER_IP       = "178.104.189.18"
$HETZNER_SSH_USER = "root"
$WSL_DISTRO       = "Ubuntu-24.04"
$WSL_SSH_KEY      = "/home/wojtek/.ssh/id_rsa"

# Modules custom — à compléter quand créés sur Hetzner
# $MODULES_CUSTOM = @("khartasia_dashboard", "khartasia_plante_form", ...)
$MODULES_CUSTOM = @()                           # vide pour l'instant

function Write-Banner {
    Write-Host ""
    Write-Host "╔══════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║   KHARTASIA — DEPLOY MASTER                              ║" -ForegroundColor Cyan
    Write-Host "║   Local DDEV : 11.3.11  ·  Hetzner : 11.3.11             ║" -ForegroundColor Cyan
    Write-Host "╚══════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
}

function Confirm-Action($message) {
    $r = Read-Host "$message (oui/non)"
    return $r -eq "oui"
}

# Convertit un chemin Windows (C:\foo\bar) en chemin WSL (/mnt/c/foo/bar),
# nécessaire puisque rsync tourne dans WSL et ne comprend pas les chemins
# Windows natifs ni les lettres de lecteur. Utilisé uniquement pour les
# chemins LOCAUX (source ou destination côté Windows) — jamais pour la clé
# SSH, qui reste native WSL (/home/wojtek/.ssh/id_rsa).
function ConvertTo-WslPath($winPath) {
    $p = $winPath -replace '\\', '/'
    if ($p -match '^([A-Za-z]):(.*)$') {
        $drive = $matches[1].ToLower()
        return "/mnt/$drive$($matches[2])"
    }
    return $p
}

# rsync push : local → Hetzner, avec suppression côté distant de ce qui
# n'existe plus en local (--delete). $localRel est un chemin RELATIF à
# $LOCAL_ROOT (ex: "web/modules/contrib"), sans slash final pour que le
# dossier lui-même soit synchronisé dans $remoteParent (qui doit finir
# par un slash). Exécuté via WSL ($WSL_DISTRO), avec IP/clé natives WSL.
function Invoke-RsyncPush($localRel, $remoteParent) {
    $localFull = Join-Path $LOCAL_ROOT $localRel
    $wslLocal  = ConvertTo-WslPath $localFull
    wsl -d $WSL_DISTRO rsync -avz --delete -e "ssh -i $WSL_SSH_KEY -o StrictHostKeyChecking=accept-new" $wslLocal "${HETZNER_SSH_USER}@${HETZNER_IP}:${remoteParent}"
}

# rsync pull : Hetzner → local, avec suppression côté local de ce qui
# n'existe plus côté distant. $remoteRel avec slash final synchronise le
# CONTENU du dossier distant dans $localParent (qui doit finir par un
# slash côté local).
function Invoke-RsyncPull($remoteRel, $localParent) {
    if (!(Test-Path $localParent)) { New-Item -ItemType Directory -Path $localParent -Force | Out-Null }
    $wslLocal = ConvertTo-WslPath $localParent
    wsl -d $WSL_DISTRO rsync -avz --delete -e "ssh -i $WSL_SSH_KEY -o StrictHostKeyChecking=accept-new" "${HETZNER_SSH_USER}@${HETZNER_IP}:${remoteRel}" $wslLocal
}

function Backup-Hetzner($prefix) {
    $DATE = Get-Date -Format "yyyyMMdd_HHmm"
    if (!(Test-Path $BACKUP_DIR)) { New-Item -ItemType Directory -Path $BACKUP_DIR | Out-Null }

    Write-Host "  [backup] DB Hetzner..." -ForegroundColor Gray
    ssh $HETZNER_HOST "cd $HETZNER_ROOT && vendor/bin/drush sql:dump --gzip --result-file=/tmp/${prefix}_${DATE}.sql && echo DUMP_OK"
    scp "${HETZNER_HOST}:/tmp/${prefix}_${DATE}.sql.gz" "$BACKUP_DIR\${prefix}_${DATE}.sql.gz"

    Write-Host "  [backup] composer.lock..." -ForegroundColor Gray
    scp "${HETZNER_HOST}:${HETZNER_ROOT}/composer.lock" "$BACKUP_DIR\${prefix}_composer_${DATE}.lock"

    if ($MODULES_CUSTOM.Count -gt 0) {
        Write-Host "  [backup] modules custom..." -ForegroundColor Gray
        ssh $HETZNER_HOST "tar czf /tmp/${prefix}_modules_${DATE}.tar.gz -C $HETZNER_ROOT web/modules/custom/ && echo TAR_OK"
        scp "${HETZNER_HOST}:/tmp/${prefix}_modules_${DATE}.tar.gz" "$BACKUP_DIR\${prefix}_modules_${DATE}.tar.gz"
    }

    Write-Host "  ✓ Backup complet → $BACKUP_DIR" -ForegroundColor Green
    return $DATE
}

# === MENU ===
Write-Banner

Write-Host "  1. Sync DB Hetzner → local (import DDEV)" -ForegroundColor White
Write-Host "  2. Sync modules custom Hetzner → local" -ForegroundColor White
Write-Host "  3. Deploy code local → Hetzner (modules + themes)" -ForegroundColor White
Write-Host "  4. BACKUP COMPLET Hetzner (DB + composer.lock + modules)" -ForegroundColor Yellow
Write-Host "  5. MISE A JOUR CORE local → Hetzner (core + modules/contrib)" -ForegroundColor Yellow
Write-Host "  6. RESTORE DB locale → Hetzner (⚠ écrase production)" -ForegroundColor Red
Write-Host "  7. SSH Hetzner" -ForegroundColor White
Write-Host "  8. Vérifier versions (local vs Hetzner)" -ForegroundColor White
Write-Host "  9. Quitter" -ForegroundColor White
Write-Host ""
$choice = Read-Host "Choix (1-9)"

switch ($choice) {

    "1" {
        # Sync DB Hetzner → local — opération sûre, sens unique descendant
        Write-Host "`n[1] SYNC DB Hetzner → local" -ForegroundColor Cyan
        $DATE = Get-Date -Format "yyyyMMdd_HHmm"
        if (!(Test-Path $BACKUP_DIR)) { New-Item -ItemType Directory -Path $BACKUP_DIR | Out-Null }

        Write-Host "  Dump DB Hetzner..." -ForegroundColor Gray
        ssh $HETZNER_HOST "cd $HETZNER_ROOT && vendor/bin/drush sql:dump --gzip --result-file=/tmp/khartasia_sync_${DATE}.sql && echo DUMP_OK"
        scp "${HETZNER_HOST}:/tmp/khartasia_sync_${DATE}.sql.gz" "$BACKUP_DIR\khartasia_sync_${DATE}.sql.gz"
        Write-Host "  ✓ Dump téléchargé" -ForegroundColor Green

        if (Confirm-Action "Importer dans DDEV local maintenant ?") {
            ddev import-db --file="$BACKUP_DIR\khartasia_sync_${DATE}.sql.gz"
            ddev drush cr
            Write-Host "  ✓ DB importée en local" -ForegroundColor Green
        }
    }

    "2" {
        # Sync modules Hetzner → local — sens unique, sécurisé
        # rsync --delete garantit que les fichiers supprimés côté Hetzner
        # (ex: refonte d'un module) disparaissent aussi en local, plutôt
        # que de s'accumuler comme avec l'ancien scp -rp.
        Write-Host "`n[2] SYNC MODULES Hetzner → local" -ForegroundColor Cyan
        if ($MODULES_CUSTOM.Count -eq 0) {
            Write-Host "  Aucun module custom défini dans \$MODULES_CUSTOM." -ForegroundColor Yellow
            Write-Host "  → Éditez le script pour ajouter les modules quand ils seront créés sur Hetzner." -ForegroundColor Yellow
        } else {
            foreach ($m in $MODULES_CUSTOM) {
                Invoke-RsyncPull "${HETZNER_WEB}/modules/custom/$m/" "$LOCAL_ROOT\web\modules\custom\$m\"
                Write-Host "  ✓ $m" -ForegroundColor Green
            }
            # Thèmes custom
            Invoke-RsyncPull "${HETZNER_WEB}/themes/custom/" "$LOCAL_ROOT\web\themes\custom\"
            Write-Host "  ✓ themes/custom/" -ForegroundColor Green
            ddev drush cr
            Write-Host "  ✓ Cache rebuild local" -ForegroundColor Green
        }
    }

    "3" {
        # Deploy local → Hetzner — attention : sens montant, backup obligatoire
        # La synchro des thèmes est indépendante de $MODULES_CUSTOM (bug
        # corrigé : avant, un $MODULES_CUSTOM vide sautait tout le bloc,
        # y compris les thèmes).
        Write-Host "`n[3] DEPLOY CODE local → Hetzner" -ForegroundColor Cyan
        Write-Host "  ⚠ Sens montant : local → Hetzner" -ForegroundColor Yellow

        Write-Host "  Backup automatique avant deploy..." -ForegroundColor Gray
        Backup-Hetzner "pre_deploy"

        if ($MODULES_CUSTOM.Count -eq 0) {
            Write-Host "  Aucun module custom à déployer." -ForegroundColor Yellow
        } else {
            foreach ($m in $MODULES_CUSTOM) {
                $src = "$LOCAL_ROOT\web\modules\custom\$m"
                if (Test-Path $src) {
                    Invoke-RsyncPush "web/modules/custom/$m" "${HETZNER_WEB}/modules/custom/"
                    Write-Host "  ✓ $m" -ForegroundColor Green
                } else {
                    Write-Host "  ⚠ $m absent en local — ignoré" -ForegroundColor Yellow
                }
            }
        }

        if (Test-Path "$LOCAL_ROOT\web\themes\custom\") {
            Invoke-RsyncPush "web/themes/custom" "${HETZNER_WEB}/themes/"
            Write-Host "  ✓ themes/custom/" -ForegroundColor Green
        }

        ssh $HETZNER_HOST "cd $HETZNER_ROOT && vendor/bin/drush cr && echo CR_OK"
        Write-Host "  ✓ Cache rebuild Hetzner" -ForegroundColor Green
    }

    "4" {
        # Backup complet — opération sûre
        Write-Host "`n[4] BACKUP COMPLET Hetzner" -ForegroundColor Cyan
        $DATE = Backup-Hetzner "khartasia_full"
        Write-Host "`n  Fichiers disponibles :" -ForegroundColor Gray
        Get-ChildItem $BACKUP_DIR | Sort-Object LastWriteTime -Descending | Select-Object -First 8 | Format-Table Name, @{L="Taille";E={[math]::Round($_.Length/1MB,1).ToString()+"MB"}}, LastWriteTime
    }

    "5" {
        # Mise à jour core local → Hetzner — procédure stricte
        # Synchronise désormais AUSSI web/modules/contrib/ après le core,
        # pour éviter le décalage core/contrib qui a causé le 500 du
        # 02/09/2026 (bibcite incompatible avec le nouveau core, puis
        # ui_icons dupliqué suite à un scp -rp qui n'avait pas nettoyé
        # l'ancienne version).
        Write-Host "`n[5] MISE A JOUR DRUPAL CORE + MODULES/CONTRIB local → Hetzner" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "  PRÉREQUIS OBLIGATOIRES :" -ForegroundColor Red
        Write-Host "  a. Local DDEV fonctionne avec la nouvelle version"
        Write-Host "  b. Backup complet Hetzner (option 4) fait"
        Write-Host "  c. Site Hetzner accessible avant la mise à jour"
        Write-Host ""

        # Vérifier versions
        Write-Host "  Vérification des versions..." -ForegroundColor Gray
        $local_ver = ddev exec bash -c "grep 'const VERSION' web/core/lib/Drupal.php" 2>$null
        $hetzner_ver = ssh $HETZNER_HOST "grep 'const VERSION' $HETZNER_WEB/core/lib/Drupal.php" 2>$null
        Write-Host "  Local   : $local_ver" -ForegroundColor Cyan
        Write-Host "  Hetzner : $hetzner_ver" -ForegroundColor Cyan
        Write-Host ""

        if (!(Confirm-Action "Prérequis vérifiés — continuer ?")) {
            Write-Host "  Annulé." -ForegroundColor Yellow
            break
        }

        $DATE = Get-Date -Format "yyyyMMdd_HHmm"

        # Backup automatique avant mise à jour
        Write-Host "  [1/5] Backup sécurité Hetzner..." -ForegroundColor Gray
        Backup-Hetzner "pre_update"

        # Compresser vendor local
        Write-Host "  [2/5] Compression vendor local..." -ForegroundColor Gray
        ddev exec bash -c "tar czf /tmp/khartasia_vendor.tar.gz vendor/"
        docker cp "${DDEV_CONTAINER}:/tmp/khartasia_vendor.tar.gz" "$BACKUP_DIR\khartasia_vendor_${DATE}.tar.gz"
        Write-Host "  ✓ vendor : $([math]::Round((Get-Item "$BACKUP_DIR\khartasia_vendor_${DATE}.tar.gz").Length/1MB))MB" -ForegroundColor Green

        # Copier web/core
        Write-Host "  [3/5] web/core local → Hetzner..." -ForegroundColor Gray
        Set-Location $LOCAL_ROOT
        scp -rp "web\core\" "${HETZNER_HOST}:${HETZNER_WEB}/"
        Write-Host "  ✓ web/core copié" -ForegroundColor Green

        # Restaurer vendor + composer
        Write-Host "  [4/5] vendor + composer.json/lock → Hetzner..." -ForegroundColor Gray
        scp "$BACKUP_DIR\khartasia_vendor_${DATE}.tar.gz" "${HETZNER_HOST}:/tmp/vendor_update.tar.gz"
        ssh $HETZNER_HOST "cd $HETZNER_ROOT && rm -rf vendor && tar xzf /tmp/vendor_update.tar.gz && echo VENDOR_OK"
        scp "composer.json" "${HETZNER_HOST}:${HETZNER_ROOT}/"
        scp "composer.lock" "${HETZNER_HOST}:${HETZNER_ROOT}/"

        # Synchroniser web/modules/contrib (rsync --delete pour éviter les
        # doublons de fichiers d'une ancienne version d'un module, cf.
        # incident ui_icons du 02/09/2026)
        Write-Host "  [5/5] web/modules/contrib local → Hetzner (rsync --delete)..." -ForegroundColor Gray
        if (Confirm-Action "  Confirmer la synchro modules/contrib (supprime côté Hetzner ce qui n'est plus en local) ?") {
            Invoke-RsyncPush "web/modules/contrib" "${HETZNER_WEB}/modules/"
            Write-Host "  ✓ modules/contrib synchronisé" -ForegroundColor Green
        } else {
            Write-Host "  ⚠ Synchro modules/contrib ignorée — risque de décalage core/contrib" -ForegroundColor Yellow
        }

        ssh $HETZNER_HOST "cd $HETZNER_ROOT && vendor/bin/drush updb -y && vendor/bin/drush cr && echo DONE"

        Write-Host "`n  ✓ Mise à jour terminée" -ForegroundColor Green
        $new_ver = ssh $HETZNER_HOST "grep 'const VERSION' $HETZNER_WEB/core/lib/Drupal.php"
        Write-Host "  Hetzner maintenant : $new_ver" -ForegroundColor Cyan
    }

    "6" {
        # Restore DB locale → Hetzner — opération dangereuse, triple confirmation
        Write-Host "`n[6] RESTORE DB locale → Hetzner" -ForegroundColor Red
        Write-Host ""
        Write-Host "  ⚠⚠⚠ ATTENTION ⚠⚠⚠" -ForegroundColor Red
        Write-Host "  Cette opération ÉCRASE la base de données de production Hetzner." -ForegroundColor Red
        Write-Host "  Elle est irréversible sans backup préalable." -ForegroundColor Red
        Write-Host ""
        Write-Host "  Cas d'usage légitimes :" -ForegroundColor Yellow
        Write-Host "  - Local est plus avancé que Hetzner (nouvelles entités, config)"
        Write-Host "  - Restauration après incident Hetzner"
        Write-Host "  - Migration de structure (nouveaux content types)"
        Write-Host ""

        if (!(Confirm-Action "Confirmer le restore DB locale → Hetzner ?")) {
            Write-Host "  Annulé." -ForegroundColor Yellow; break
        }
        if (!(Confirm-Action "DERNIÈRE CONFIRMATION — écraser la DB Hetzner ?")) {
            Write-Host "  Annulé." -ForegroundColor Yellow; break
        }

        $DATE = Get-Date -Format "yyyyMMdd_HHmm"

        # Backup Hetzner automatique avant restore
        Write-Host "  [1/3] Backup Hetzner avant restore..." -ForegroundColor Gray
        Backup-Hetzner "pre_restore"

        # Export DB locale
        Write-Host "  [2/3] Export DB locale..." -ForegroundColor Gray
        ddev export-db --file="$BACKUP_DIR\khartasia_local_${DATE}.sql.gz"
        Write-Host "  ✓ Export local : khartasia_local_${DATE}.sql.gz" -ForegroundColor Green

        # Restore sur Hetzner
        Write-Host "  [3/3] Restore sur Hetzner..." -ForegroundColor Gray
        scp "$BACKUP_DIR\khartasia_local_${DATE}.sql.gz" "${HETZNER_HOST}:/tmp/"
        ssh $HETZNER_HOST "cd $HETZNER_ROOT && zcat /tmp/khartasia_local_${DATE}.sql.gz | vendor/bin/drush sql:cli && vendor/bin/drush cr && echo RESTORE_OK"
        Write-Host "  ✓ Restore terminé" -ForegroundColor Green
        Write-Host ""
        Write-Host "  → Vérifier le site : https://khartasia.org" -ForegroundColor Cyan
    }

    "7" {
        Write-Host "`n[7] SSH Hetzner..." -ForegroundColor Cyan
        ssh $HETZNER_HOST
    }

    "8" {
        # Vérification versions — diagnostic rapide
        Write-Host "`n[8] VÉRIFICATION VERSIONS" -ForegroundColor Cyan
        Write-Host ""

        Write-Host "  Local DDEV :" -ForegroundColor Gray
        $lv = ddev exec bash -c "grep 'const VERSION' web/core/lib/Drupal.php 2>/dev/null | head -1"
        Write-Host "    Drupal : $lv" -ForegroundColor White
        $lp = ddev exec bash -c "php -v 2>/dev/null | head -1"
        Write-Host "    PHP    : $lp" -ForegroundColor White

        Write-Host ""
        Write-Host "  Hetzner :" -ForegroundColor Gray
        $hv = ssh $HETZNER_HOST "grep 'const VERSION' $HETZNER_WEB/core/lib/Drupal.php 2>/dev/null | head -1"
        Write-Host "    Drupal : $hv" -ForegroundColor White
        $hp = ssh $HETZNER_HOST "php -v 2>/dev/null | head -1"
        Write-Host "    PHP    : $hp" -ForegroundColor White

        Write-Host ""
        # Vérifier si mise à jour nécessaire
        if ($lv -ne $hv) {
            Write-Host "  ⚠ Versions différentes — option 5 pour mettre à jour Hetzner" -ForegroundColor Yellow
        } else {
            Write-Host "  ✓ Versions identiques" -ForegroundColor Green
        }

        Write-Host ""
        Write-Host "  Derniers backups :" -ForegroundColor Gray
        if (Test-Path $BACKUP_DIR) {
            Get-ChildItem $BACKUP_DIR | Sort-Object LastWriteTime -Descending | Select-Object -First 5 | Format-Table Name, @{L="MB";E={[math]::Round($_.Length/1MB,1)}}, LastWriteTime
        } else {
            Write-Host "  Aucun backup trouvé dans $BACKUP_DIR" -ForegroundColor Yellow
        }
    }

    "9" { exit }

    default { Write-Host "Choix invalide" -ForegroundColor Red }
}
