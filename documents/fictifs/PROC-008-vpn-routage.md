# PROC-008 - VPN connecté mais ressources internes inaccessibles

## Catégorie

Réseau

## Périmètre et prérequis

Poste Windows 11 et client VPN d'entreprise validé pour le laboratoire. Symptômes : tunnel affiché comme connecté, intranet inaccessible, partage réseau introuvable ou accès fonctionnel au bureau mais impossible à domicile. Les commandes `Get-VpnConnection` concernent les profils VPN Windows ; un client tiers peut gérer ses profils autrement. Les cibles `vpn.demo.example` et `app.demo.example` sont des exemples à remplacer.

## Qualification

Relever le client et sa version, le type de connexion, l'heure de connexion et les ressources affectées. Distinguer un échec de connexion au tunnel d'un échec après connexion. Un refus de connexion ou une erreur MFA relève d'abord de l'authentification ; ne pas réinstaller le VPN pour contourner ce refus. Vérifier également que l'accès Internet fonctionnait avant l'ouverture du tunnel.

## Diagnostic technique

1. Examiner les interfaces, adresses et routes après connexion :

```powershell
Get-NetIPConfiguration
Get-NetRoute -AddressFamily IPv4 |
    Sort-Object DestinationPrefix, RouteMetric |
    Select-Object DestinationPrefix, NextHop, InterfaceAlias, RouteMetric
Get-DnsClientServerAddress -AddressFamily IPv4
```

2. Avec un profil Windows, consulter sa configuration sans la modifier :

```powershell
Get-VpnConnection | Select-Object Name, ConnectionStatus, SplitTunneling
```

Si aucun profil n'apparaît, vérifier le contexte utilisateur et l'utilisation d'un client tiers avant de conclure que le VPN n'existe pas.

3. Tester le nom interne et le port réellement utilisé :

```powershell
Resolve-DnsName app.demo.example -DnsOnly
Test-NetConnection app.demo.example -Port 443 -InformationLevel Detailed
```

Noter l'adresse source et l'interface choisies. Une résolution en échec avec un tunnel actif suggère un problème DNS ou de suffixe. Une résolution correcte avec un port inaccessible oriente vers le routage, le filtrage ou le service distant. Le test TCP 443 n'est pertinent pour la passerelle VPN que si cette passerelle utilise effectivement ce protocole.

4. Comparer les sous-réseaux du domicile et de l'entreprise. Un chevauchement peut faire sélectionner une route locale à la place du tunnel. Recueillir la route choisie et demander une correction de conception au réseau ; ne pas ajouter des routes persistantes au hasard.

## Correction et retour arrière

Après sauvegarde du travail, déconnecter puis reconnecter une fois le profil approuvé. Si la configuration publiée est incorrecte, faire corriger le profil par son équipe propriétaire. Tester sur un autre réseau autorisé permet de distinguer un défaut lié à l'accès local d'un défaut commun au compte. Ne pas désactiver le pare-feu, supprimer les certificats ou forcer tout le trafic dans le tunnel sans validation du réseau.

## Validation et escalade

Tester une ressource interne en lecture et confirmer le maintien des accès Internet attendus. Faire vérifier l'accès par l'utilisateur après quelques minutes. Transmettre le nom du profil, les routes pertinentes, le résultat DNS, l'adresse source, le port testé et les heures des essais. Ne jamais joindre de mot de passe, secret VPN ou code MFA.

## Référence technique

[Microsoft : Get-VpnConnection](https://learn.microsoft.com/powershell/module/vpnclient/get-vpnconnection).
