# PROC-009 - Partage SMB inaccessible ou accès refusé à un dossier

## Catégorie

Réseau

## Périmètre et prérequis

Poste Windows et serveur de fichiers de démonstration. Symptômes : « Chemin réseau introuvable », « Accès refusé », demande répétée d'identifiants ou lecteur réseau déconnecté. Le serveur `files.demo.example` et le partage `Equipe-Test` sont fictifs. Utiliser la session de l'utilisateur affecté pour les tests côté client ; une session administrateur différente ne reproduit pas nécessairement ses droits.

## Diagnostic par couche

1. Relever le chemin UNC complet, le message exact et l'action demandée : lecture, création, modification ou suppression. Vérifier si le problème concerne le partage entier ou un seul sous-dossier. Demander si un autre utilisateur habilité atteint la même ressource.
2. Tester DNS puis TCP 445 :

```powershell
Resolve-DnsName files.demo.example -DnsOnly
Test-NetConnection files.demo.example -Port 445
```

Un échec TCP conduit à vérifier le réseau ou le service, avant les permissions. Une réussite prouve seulement que le port répond ; elle ne valide ni l'ouverture de session SMB ni les droits sur le répertoire.

3. Examiner les connexions existantes et le contexte utilisateur :

```powershell
whoami
whoami /groups
Get-SmbConnection | Select-Object ServerName, ShareName, UserName, Dialect
Get-SmbMapping | Select-Object LocalPath, RemotePath, Status
```

Un compte inattendu peut expliquer un refus. Ne pas supprimer toutes les connexions avec une commande globale : des documents ouverts peuvent utiliser ces sessions. Une appartenance récemment ajoutée à un groupe peut ne pas être présente dans le jeton de la session actuelle.

4. Côté serveur, un administrateur habilité vérifie séparément les permissions de partage et les ACL du système de fichiers. L'accès effectif dépend des deux niveaux, du jeton et des règles applicables ; ne pas conclure qu'un groupe présent sur le partage suffit à autoriser tous les sous-dossiers. Examiner les accès effectifs sur le dossier précis.

## Correction contrôlée

Si le chemin est erroné, rétablir le chemin publié. Si une modification de groupe vient d'être appliquée, faire fermer les documents et renouveler la session utilisateur selon les règles du laboratoire. Pour un problème d'autorisation avéré, faire corriger le groupe ou l'ACL ciblée par le propriétaire du partage. Noter les valeurs avant modification et prévoir leur restauration si le test échoue.

Ne pas accorder `Everyone: Full Control`, réactiver SMBv1 ou désactiver la signature pour résoudre un incident d'accès. Un accès par adresse IP ne constitue pas un remplacement durable du chemin par nom et peut changer le mécanisme d'authentification.

## Validation et escalade

Tester la lecture d'un document fictif, puis une création uniquement si le rôle doit permettre l'écriture, dans un dossier de test prévu. Vérifier aussi qu'un compte non habilité reste refusé. Transmettre le chemin, l'identité de démonstration, les droits attendus, le résultat TCP et la portée du défaut ; éviter les copies de documents réels.

## Référence technique

[Microsoft : diagnostic SMB](https://learn.microsoft.com/troubleshoot/windows-server/networking/troubleshoot-smb-guidance).
