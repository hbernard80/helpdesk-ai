# Corpus de procédures de démonstration

Le dossier `fictifs/` contient 17 procédures Markdown chargées par le pipeline RAG.
Les cinq premières fiches donnent les premiers contrôles ; les douze suivantes
approfondissent les diagnostics techniques avec environ 460 à 490 mots chacune.
Tous les noms de domaine, serveurs, comptes et services de démonstration sont à
adapter au laboratoire. Les commandes documentées ne sont jamais exécutées par
l'assistant lorsqu'il propose une résolution.

## Fiches de premier diagnostic

| Référence | Sujet |
| --- | --- |
| [PROC-001](fictifs/PROC-001-authentification.md) | Identité, compte verrouillé et authentification multifacteur |
| [PROC-002](fictifs/PROC-002-imprimante.md) | Imprimante inaccessible et qualité d'impression |
| [PROC-003](fictifs/PROC-003-reseau.md) | Connectivité Ethernet, Wi-Fi et accès réseau |
| [PROC-004](fictifs/PROC-004-messagerie.md) | Envoi, réception et synchronisation de messagerie |
| [PROC-005](fictifs/PROC-005-logiciel-metier.md) | Qualification d'un incident applicatif |

## Procédures techniques détaillées

Chaque fiche précise le périmètre, les prérequis, les commandes de diagnostic,
l'interprétation des résultats, les corrections conditionnelles, le retour arrière
ou ses limites, les critères de validation et les éléments d'escalade. Les
références techniques renvoient aux documentations des éditeurs.

| Référence | Sujet | Exemples d'outils et d'observations |
| --- | --- | --- |
| [PROC-006](fictifs/PROC-006-diagnostic-dns.md) | Résolution DNS interne | `Resolve-DnsName`, suffixes, cache et réponse du résolveur |
| [PROC-007](fictifs/PROC-007-dhcp-adressage-ip.md) | DHCP et adressage | Bail, interface active, VLAN et renouvellement ciblé |
| [PROC-008](fictifs/PROC-008-vpn-routage.md) | VPN connecté sans accès interne | Routes, DNS, adresse source et chevauchement de sous-réseaux |
| [PROC-009](fictifs/PROC-009-partage-smb-droits.md) | Partages SMB et autorisations | TCP 445, connexions, jeton utilisateur et ACL |
| [PROC-010](fictifs/PROC-010-active-directory-kerberos.md) | Kerberos et domaine Active Directory | Tickets, horloge, découverte du domaine et canal sécurisé |
| [PROC-011](fictifs/PROC-011-impression-spouleur.md) | Spouleur, ports et pilotes d'impression | Files, travaux bloqués, journaux et comparaison des postes |
| [PROC-012](fictifs/PROC-012-windows-update-integrite.md) | Windows Update et intégrité | KB, journaux de maintenance, DISM et SFC |
| [PROC-013](fictifs/PROC-013-certificat-tls.md) | Certificats HTTPS et TLS | curl, OpenSSL, SNI, SAN et chaîne de confiance |
| [PROC-014](fictifs/PROC-014-linux-service-systemd.md) | Service Linux indisponible | systemd, journal, ports, mémoire et inodes |
| [PROC-015](fictifs/PROC-015-mysql-lenteurs-verrous.md) | Lenteurs et verrous MySQL 8.4 | Connexions, transactions, attentes et verrous de métadonnées |
| [PROC-016](fictifs/PROC-016-docker-compose-connectivite.md) | Docker Compose et dépendances | Moteur, journaux, DNS interne et ports publiés |
| [PROC-017](fictifs/PROC-017-nginx-erreurs-502-504.md) | Nginx et erreurs 502/504 | Proxy, upstream, mesures de durée et test de configuration |

## Utilisation dans le RAG

Le lecteur charge les fichiers `.md` directement présents dans `fictifs/`.
Ce catalogue reste en dehors du dossier indexé afin de ne pas être proposé comme
une procédure de résolution. Les scénarios Linux, Nginx et Active Directory
décrivent des environnements que le support peut rencontrer ; leur présence dans
le corpus n'indique pas qu'ils sont déployés par ce projet.

Après tout ajout ou changement, reconstruire l'index lorsque les conteneurs
`app` et `ollama` sont démarrés et que le modèle d'embeddings est disponible :

```powershell
docker compose exec app php bin/index-procedures.php
```

La commande doit afficher `17 procédures indexées.` pour ce corpus. Les nouvelles
fiches ne deviennent accessibles à la recherche qu'après cette reconstruction.
Un vecteur est actuellement calculé par fichier ; les documents sont conservés
entiers et les trois résultats les plus proches alimentent la proposition.

Pour un premier essai, créer un ticket « VPN connecté mais intranet inaccessible »
avec une description précisant que la navigation Internet fonctionne et que le
nom interne ne se résout plus. Vérifier que les documents retrouvés sont
pertinents, puis que les étapes proposées sont effectivement justifiées par les
passages cités. Le classement exact dépend du modèle d'embeddings ; ce scénario
ne constitue pas une garantie de résultat.
