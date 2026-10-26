# PROC-012 - Mise à jour Windows en échec et contrôle d'intégrité du système

## Catégorie

Autre

## Périmètre et prérequis

Poste Windows 11 de démonstration. Symptômes : installation d'une KB systématiquement refusée, retour arrière au redémarrage, erreur de maintenance ou composants Windows endommagés. Prévoir une fenêtre d'intervention, une alimentation stable et une sauvegarde du travail. Les opérations DISM et SFC nécessitent une console élevée ; elles ne doivent pas être exécutées automatiquement par l'assistant.

## Diagnostic initial

1. Relever la KB, le code d'erreur complet, la phase de l'échec et la version Windows. Consulter l'historique des mises à jour et noter si un redémarrage est déjà demandé. Distinguer téléchargement, installation et démarrage : ces phases n'ont pas les mêmes causes.
2. Vérifier l'espace et les services concernés :

```powershell
Get-Volume -DriveLetter C | Select-Object DriveLetter, SizeRemaining, Size
Get-Service wuauserv, BITS, CryptSvc
Get-WindowsUpdateLog -LogPath "$env:TEMP\WindowsUpdate-demo.log"
```

Le journal généré est un instantané des traces disponibles, pas un suivi continu. Un service configuré pour démarrer à la demande peut être arrêté sans être défaillant. Ne pas forcer son type de démarrage sur la seule base de cet état.

3. Examiner les erreurs à l'heure de la tentative dans les journaux Windows Update, `C:\Windows\Logs\CBS\CBS.log` et `C:\Windows\Logs\DISM\dism.log`. Rechercher le code exact et le composant concerné, plutôt que toutes les lignes contenant « error ». Si le téléchargement échoue, vérifier proxy et source de mise à jour avant de supposer une corruption.

## Réparation conditionnelle

Si les indices pointent vers le magasin de composants, commencer par un diagnostic :

```powershell
DISM.exe /Online /Cleanup-Image /ScanHealth
```

Si une corruption réparable est confirmée et l'intervention autorisée, lancer ensuite :

```powershell
DISM.exe /Online /Cleanup-Image /RestoreHealth
sfc.exe /scannow
```

Attendre la fin de chaque commande et conserver son résultat. DISM peut utiliser la source de réparation configurée ; si cette source est indisponible, ne pas répéter la commande indéfiniment. Une source alternative doit correspondre à la version et à la configuration du système et être validée par l'administration.

## Retour arrière et limites

Ces réparations modifient le système et n'ont pas d'annulation simple. Préparer les moyens de restauration avant de les lancer ; ne pas les présenter comme une opération réversible. Ne pas effacer `SoftwareDistribution`, désinstaller plusieurs KB ou désactiver la protection du poste comme première réponse. Si le poste ne redémarre plus, utiliser la procédure de récupération approuvée et escalader.

## Validation et escalade

Après un redémarrage planifié si nécessaire, vérifier l'historique de la KB et le fonctionnement de l'application affectée. Un SFC réussi ne prouve pas que la mise à jour est installée. Transmettre la version Windows, la KB, le code d'erreur, l'espace libre et les extraits CBS/DISM pertinents, en retirant les informations non nécessaires.

## Références techniques

[Microsoft : Get-WindowsUpdateLog](https://learn.microsoft.com/powershell/module/windowsupdate/get-windowsupdatelog), [SFC](https://learn.microsoft.com/windows-server/administration/windows-commands/sfc) et [réparation d'une image Windows](https://learn.microsoft.com/windows-hardware/manufacture/desktop/repair-a-windows-image).
