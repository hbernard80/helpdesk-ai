# PROC-007 - Diagnostiquer un bail DHCP absent ou une adresse IP incorrecte

## Catégorie

Réseau

## Périmètre et prérequis

Poste Windows 11 en adressage dynamique sur un réseau de démonstration. Symptômes : réseau non identifié, adresse IPv4 `169.254.x.x`, absence de passerelle ou accès limité après déplacement entre deux bureaux. Une adresse de lien local n'est un indice de panne DHCP que si l'interface devait obtenir sa configuration par DHCP. Disposer du plan d'adressage attendu et d'un accès physique ou d'une assistance locale avant toute manipulation susceptible de couper la connexion.

## Diagnostic

1. Préciser si le problème suit le poste, la prise réseau ou le réseau Wi-Fi. Noter le dernier fonctionnement, un éventuel changement de station d'accueil et le nombre de postes touchés.
2. Examiner l'état physique et la configuration :

```powershell
Get-NetAdapter | Select-Object Name, Status, LinkSpeed, MacAddress
Get-NetIPConfiguration
ipconfig /all
```

Relever l'interface active, l'activation de DHCP, l'adresse, le masque, la passerelle, le serveur DHCP et les dates du bail. Ne pas interpréter une interface virtuelle déconnectée comme une panne du réseau physique.

3. Si le lien est coupé, tester un câble ou une station d'accueil connus comme fonctionnels. Si le lien est actif mais l'adressage appartient à un autre segment, comparer avec le plan réseau et un poste voisin. Un mauvais VLAN ou une affectation de port incorrecte nécessite l'équipe réseau.
4. Consulter les journaux DHCP disponibles :

```powershell
Get-WinEvent -ListLog '*Dhcp*' | Select-Object LogName, IsEnabled, RecordCount
```

Ouvrir le journal pertinent dans l'Observateur d'événements et limiter la collecte à l'heure de l'incident. L'absence de journal activé n'est pas une preuve d'absence d'échange DHCP. Si plusieurs postes du même segment échouent, demander le contrôle de l'étendue, des baux disponibles et du relais DHCP côté infrastructure.

## Correction contrôlée

Si l'adressage dynamique est bien attendu et qu'un renouvellement est justifié, lancer en console élevée `ipconfig /renew "Ethernet"`, après avoir remplacé le nom par celui de l'interface concernée. Cette opération peut interrompre l'assistance distante. Ne pas lancer un renouvellement global sur toutes les interfaces et ne pas commencer par libérer un bail encore utilisable.

En présence d'un défaut collectif, corriger la cause côté serveur ou réseau avant de multiplier les renouvellements. Ne pas inventer une adresse statique, copier celle d'un collègue ou modifier le masque pour obtenir temporairement un accès : cela peut créer un conflit et masquer le défaut.

## Validation, retour arrière et escalade

Vérifier que le bail, la passerelle et les DNS correspondent au segment attendu, puis tester une ressource interne connue. Contrôler que le service reste accessible après reconnexion normale de l'interface. Si un câble a été remplacé sans effet, remettre le raccordement initial et tracer le résultat.

Transmettre l'adresse MAC, le nom du poste, le port ou SSID, la configuration reçue, l'heure du renouvellement et le périmètre des utilisateurs affectés. Ne pas redémarrer un serveur DHCP ni modifier son étendue depuis le support poste de travail.

## Référence technique

[Microsoft : ipconfig et renouvellement DHCP](https://learn.microsoft.com/windows-server/administration/windows-commands/ipconfig).
