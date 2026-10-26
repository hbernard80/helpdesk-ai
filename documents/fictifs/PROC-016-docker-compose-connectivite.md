# PROC-016 - Application Docker Compose inaccessible ou service dépendant introuvable

## Catégorie

Applications métier

## Périmètre et prérequis

Environnement HelpDesk AI de démonstration avec Docker Desktop, conteneurs Linux et Compose. Symptômes : page locale inaccessible, nom `db` introuvable, connexion MySQL refusée ou service Ollama injoignable. Exécuter les commandes depuis la racine du projet. Les noms `app`, `db` et `ollama` correspondent aux services de ce laboratoire ; les adapter pour un autre déploiement.

## Diagnostic par niveau

1. Distinguer moteur arrêté et conteneur arrêté :

```powershell
docker info
docker compose ps -a
docker compose config --quiet
```

Si Docker indique un socket ou un canal nommé absent, le moteur n'est pas accessible ; examiner Docker Desktop et le contexte sélectionné avant de modifier l'application. Si la configuration est invalide, corriger l'erreur signalée dans le fichier concerné. Ne pas joindre un rendu complet de `docker compose config`, qui peut contenir des secrets interpolés.

2. Lire les journaux récents des seuls services affectés :

```powershell
docker compose logs --tail 80 app
docker compose logs --tail 80 db
docker compose logs --tail 80 ollama
```

Relever les heures et distinguer une erreur de démarrage d'une dépendance pas encore prête. L'ordre `depends_on` ne démontre pas que MySQL accepte déjà des connexions. Ne pas répéter une création de base pour corriger un simple délai de démarrage.

3. Vérifier la résolution et TCP depuis le conteneur applicatif, sans afficher les variables secrètes :

```powershell
docker compose exec app php -r 'echo gethostbyname("db"), PHP_EOL;'
docker compose exec app php -r '$s = @fsockopen("db", 3306, $code, $message, 3); echo $s ? "TCP OK\n" : "TCP indisponible\n";'
```

Une résolution renvoyant littéralement `db` indique un échec de résolution dans ce test PHP. Un TCP ouvert ne valide pas le compte MySQL. Entre services, utiliser le nom du service et son port interne ; `localhost` dans `app` désigne `app`, pas le conteneur `db`.

4. Depuis l'hôte, vérifier le port publié :

```powershell
Test-NetConnection 127.0.0.1 -Port 8081
```

Dans ce projet, l'accès web est publié sur `127.0.0.1:8081`. L'absence de publication de MySQL ou d'Ollama sur l'hôte est normale. Un test hôte vers `localhost:11434` n'est donc pas un test de la communication entre `app` et `ollama`.

## Correction et retour arrière

Démarrer le moteur s'il est arrêté dans le cadre de l'intervention autorisée, puis relancer uniquement le service requis. Après correction d'une variable Compose, recréer le service concerné avec `docker compose up -d app`. Conserver les valeurs précédentes sans les copier dans le ticket. Ne pas utiliser `down -v`, supprimer les volumes ou lancer un nettoyage global pour une panne de connectivité.

## Validation et escalade

Vérifier les journaux, l'ouverture de la page et une opération applicative adaptée. Pour les appels IA, contrôler séparément la présence des modèles dans Ollama. Transmettre le contexte Docker, l'état des services, les tests DNS/TCP et les erreurs expurgées ; préserver les volumes existants.

## Référence technique

[Docker : réseau et découverte des services Compose](https://docs.docker.com/compose/how-tos/networking/).
