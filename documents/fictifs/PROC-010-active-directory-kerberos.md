# PROC-010 - Échec Kerberos, horloge incorrecte ou relation de confiance défaillante

## Catégorie

Authentification

## Périmètre et prérequis

Poste membre d'un domaine Active Directory de laboratoire. Symptômes : authentification réseau refusée malgré un mot de passe correct, SSO défaillant, message de relation de confiance ou accès aux partages impossible après sortie de veille. Le domaine `demo.example` est fictif. Les commandes de diagnostic n'autorisent pas la réparation du compte machine ; celle-ci relève d'un administrateur du domaine.

## Diagnostic

1. Distinguer session Windows locale, session de domaine et accès à une application. Une ouverture de session hors réseau peut utiliser des informations en cache et ne prouve pas qu'un contrôleur de domaine est actuellement joignable. Noter si plusieurs services réseau échouent simultanément.
2. Relever l'identité, l'heure et la source de synchronisation :

```powershell
whoami /upn
Get-Date
w32tm /query /status
w32tm /query /source
klist
```

Le nom du compte, les dates des tickets et la source de temps aident à distinguer une session ancienne d'un défaut de synchronisation. Une dérive importante est incompatible avec une authentification Kerberos normale ; comparer avec la politique effective du domaine plutôt que présumer un seuil personnalisé.

3. Vérifier la découverte du domaine :

```powershell
Resolve-DnsName -Type SRV _kerberos._tcp.demo.example
nltest /dsgetdc:demo.example
```

Si la découverte échoue, contrôler d'abord les DNS et l'accès au réseau de domaine ou au VPN. Ne pas remplacer les DNS internes par des DNS publics. Si un seul service échoue alors que les autres fonctionnent, relever son nom complet : un problème de SPN ou de configuration du service doit être examiné côté serveur.

4. Sur un poste membre et avec les droits nécessaires, `Test-ComputerSecureChannel -Verbose` peut contrôler le canal sécurisé. Ne pas utiliser ce test comme diagnostic d'un contrôleur de domaine et ne pas ajouter `-Repair` à cette étape. Un échec requiert des vérifications réseau avant de conclure que le compte machine est endommagé.

## Correction et retour arrière

Si la source de temps est correcte et la connectivité restaurée, un administrateur peut demander `w32tm /resync`, puis comparer à nouveau les horloges. Ne pas choisir un serveur NTP externe arbitraire sur un poste de domaine. Pour un jeton ancien, une fermeture de session après sauvegarde du travail permet une authentification propre.

Éviter une sortie puis réintégration du domaine comme première action. Une réparation du canal sécurisé ou d'un SPN doit faire l'objet d'une intervention distincte, avec accès administrateur de secours et plan de restauration validé.

## Validation et escalade

Tester l'accès par nom à deux ressources autorisées et vérifier qu'un nouveau ticket de service apparaît lorsque Kerberos est utilisé. Transmettre l'heure, la source NTP, le contrôleur découvert, le service concerné et les codes d'événement utiles. Ne pas collecter de secrets, de hachages ou de tickets exportés pour une simple demande helpdesk.

## Référence technique

[Microsoft : diagnostic de l'authentification Kerberos](https://learn.microsoft.com/troubleshoot/windows-server/windows-security/kerberos-authentication-troubleshooting-guidance).
