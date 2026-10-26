# PROC-014 - Service Linux arrêté, boucle de redémarrage ou port absent

## Catégorie

Applications métier

## Périmètre et prérequis

Serveur Linux de laboratoire utilisant systemd. Symptômes : application indisponible, unité en échec, redémarrages répétés ou port non ouvert. `demo-api.service` est une unité fictive à remplacer par le service réel. Les permissions nécessaires varient selon la distribution ; consulter les journaux peut nécessiter une habilitation. Ne pas utiliser cette procédure telle quelle dans un conteneur qui n'exécute pas systemd.

## Diagnostic ordonné

1. Relever l'heure de l'incident, le dernier déploiement et les autres services affectés. Vérifier si le processus est arrêté ou si seule la route applicative échoue.
2. Inspecter l'unité et ses derniers journaux :

```bash
systemctl status demo-api.service --no-pager --full
systemctl show demo-api.service -p ActiveState -p SubState -p Result -p NRestarts
journalctl -u demo-api.service --since '-30 minutes' --no-pager -n 200
```

Le statut est une vue synthétique ; les journaux donnent la chronologie. Un état actif ne garantit pas qu'une application soit prête à accepter les requêtes. Rechercher un échec d'accès à un fichier, une configuration invalide, une dépendance indisponible ou un conflit de port.

3. Vérifier les ressources du serveur et les ports :

```bash
df -h
df -i
free -h
ss -lnt
```

Un disque peut manquer d'inodes alors que sa capacité n'est pas épuisée. L'absence du port attendu oriente vers le démarrage ou l'adresse d'écoute ; un port présent avec des erreurs HTTP oriente vers le fonctionnement applicatif. Pour identifier le processus propriétaire, employer `ss -lntp` avec les droits requis, sans publier la liste complète inutilement.

4. Examiner `systemctl cat demo-api.service` dans une session d'administration autorisée et comparer avec la configuration approuvée. Vérifier l'utilisateur du service, le répertoire de travail, les chemins et les dépendances. Les unités ou fichiers associés peuvent contenir des secrets : ne pas joindre leur contenu brut au ticket.

## Correction et retour arrière

Corriger uniquement la cause observée : fichier absent, permission ciblée, configuration ou dépendance. Ne pas appliquer `chmod -R 777` et ne pas relancer en root pour masquer un défaut de droits. Après modification d'une unité, l'administrateur exécute `systemctl daemon-reload`, puis redémarre le service dans la fenêtre prévue. Un simple changement de configuration applicative n'exige pas systématiquement ce rechargement de systemd.

Conserver le fichier et la version applicative précédents. Si la validation échoue, restaurer l'ensemble cohérent puis reprendre les contrôles ; une migration de données peut nécessiter un plan distinct avant tout retour de version.

## Validation et escalade

Vérifier le statut, le port attendu et une fonction de lecture de l'application. Attendre quelques minutes pour observer l'absence de nouvelle boucle. Transmettre le résultat de l'unité, les codes de sortie, les ressources et un extrait limité des journaux. Escalader les suspicions de panne disque ou d'arrêt par manque de mémoire à l'exploitation.

## Référence technique

[systemd : systemctl](https://www.freedesktop.org/software/systemd/man/latest/systemctl.html) et [journalctl](https://www.freedesktop.org/software/systemd/man/latest/journalctl.html).
