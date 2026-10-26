# PROC-011 - File d'impression bloquée, port incorrect ou pilote défaillant

## Catégorie

Matériel

## Périmètre et prérequis

Poste Windows 11, imprimante réseau de démonstration et éventuel serveur d'impression Windows. Symptômes : travaux en attente, impression impossible depuis un seul poste, erreur après mise à jour d'un pilote ou état hors connexion incohérent. Les diagnostics utilisent Windows PowerShell avec le module PrintManagement disponible. `DEMO-IMP-02` désigne un nom fictif de file locale, à remplacer par le nom exact affiché sur le poste.

## Qualification et diagnostic

1. Déterminer si la file est locale ou partagée. Tester une page interne depuis le panneau de l'imprimante : un défaut visible sur cette page oriente vers le matériel ou les consommables, indépendamment du poste. Ne pas utiliser un document métier réel comme page d'essai.
2. Relever les paramètres sans les modifier :

```powershell
Get-Printer | Select-Object Name, DriverName, PortName, PrinterStatus
Get-PrintJob -PrinterName 'DEMO-IMP-02' |
    Select-Object ID, JobStatus, SubmittedTime, Size
Get-Service Spooler
```

Comparer le premier travail bloqué avec les suivants. Éviter d'inclure les noms de documents ou leurs contenus dans le ticket. Un spouleur actif n'exclut pas un défaut du pilote ou du port.

3. Relever le port de la file avec `Get-PrinterPort`. Pour un port TCP/IP standard, comparer l'adresse à celle indiquée sur la page de configuration matérielle. Tester uniquement le port du protocole réellement configuré ; TCP 9100 n'est pas pertinent pour toutes les files, notamment IPP, WSD ou une file partagée.
4. Consulter les journaux PrintService disponibles dans l'Observateur d'événements, autour de l'heure du premier échec. Si un collègue imprime correctement sur la même file, comparer pilote et port côté poste. Si tous les postes échouent, examiner la file serveur et l'imprimante avant de réinstaller les postes.

## Correction et retour arrière

Après accord du propriétaire, annuler seulement son travail bloqué via la file d'impression, puis essayer une page de test. Si le service est défaillant, un administrateur peut redémarrer le spouleur sur le poste concerné ; sur un serveur partagé, planifier l'impact sur tous les utilisateurs avant toute intervention.

Ne pas supprimer en bloc le répertoire du spouleur. Si le pilote est identifié comme cause, installer le paquet validé par l'organisation et conserver la référence de la version précédente. Pour un port erroné, enregistrer les paramètres initiaux puis corriger la file ciblée ; restaurer cette configuration si la validation échoue.

## Validation et escalade

Vérifier la disparition du travail de test, la sortie physique et l'absence de nouveau blocage. Sur une file partagée, demander un second essai depuis un autre poste. Transmettre le nom de file, le pilote, le protocole, l'adresse prévue, l'état du spouleur et les événements pertinents. Escalader au matériel si la page autonome est incorrecte, au support impression si le défaut dépend de la file ou du pilote.

## Référence technique

[Microsoft : inspection des travaux d'impression](https://learn.microsoft.com/powershell/module/printmanagement/get-printjob).
