# Qdrant dans HelpDesk AI : installation et intégration

Qdrant stocke les vecteurs des procédures et retrouve les documents proches du
problème décrit dans un ticket. Ollama continue à créer les embeddings ; LLPhant
continue à piloter Ollama. MySQL conserve les tickets.

## 1. Installer le service

Le fichier `compose.yaml` ajoute `qdrant/qdrant:v1.17.0`. Cette version est fixée
pour rendre l'installation reproductible. Le volume nommé `qdrant_data` est monté
dans `/qdrant/storage` : arrêter ou recréer le conteneur conserve les données.

Depuis la racine du projet, avec Docker Desktop démarré en mode Linux :

```powershell
docker compose config --quiet
docker compose up -d qdrant
docker compose up -d app
docker compose ps
```

La première commande contrôle Compose sans afficher les mots de passe. La deuxième
télécharge l'image si nécessaire et démarre Qdrant. La troisième applique les
nouvelles variables au service PHP et démarre aussi ses dépendances.

Qdrant est disponible dans le réseau Docker à `http://qdrant:6333` et sur
l'hôte à `http://localhost:6333`. Le port publié est limité à `127.0.0.1`.
Son tableau de bord est accessible à <http://localhost:6333/dashboard>.
Il n'y a pas d'authentification Qdrant dans cette configuration locale.

## 2. Relier PHP à Qdrant

Compose transmet ces paramètres à `app` ; leurs valeurs par défaut fonctionnent
avec le `.env` existant, sans le modifier :

```dotenv
QDRANT_URL=http://qdrant:6333
QDRANT_COLLECTION=helpdesk_procedures
```

`src/config.php` expose `qdrant.url` et `qdrant.collection`. La seconde valeur
désigne un **alias** : un nom stable pointant vers une collection physique.
Utiliser des lettres, chiffres, tirets et underscores pour ce nom.

`QdrantStore` appelle l'API REST avec Guzzle, déjà installé avec LLPhant : aucune
dépendance Composer supplémentaire n'est nécessaire. Le client limite la connexion
à 5 secondes et chaque requête à 30 secondes ; il vérifie les statuts HTTP, le JSON,
l'enveloppe Qdrant et les résultats. Les erreurs sont journalisées par les pages
existantes et ne sont pas affichées en détail aux visiteurs.

## 3. Construire l'index des procédures

Vérifier d'abord que le modèle d'embedding est disponible :

```powershell
docker compose exec ollama ollama list
# Seulement si le modèle manque :
docker compose exec ollama ollama pull nomic-embed-text
docker compose exec app php bin/index-procedures.php
```

Le script effectue les opérations suivantes :

1. Lire les fichiers Markdown dans `documents/fictifs`, montés dans `/var/www/procedures`.
2. Envoyer chaque texte entier à Ollama via LLPhant pour obtenir son vecteur.
3. Vérifier que les vecteurs sont numériques, finis, non nuls et de même dimension.
4. Créer une collection physique `helpdesk_procedures_<suffixe aléatoire>` avec
   la dimension réellement observée et la distance `Cosine`.
5. Charger les points par lots de 64 avec `wait=true`. Chaque point contient le
   vecteur et un payload : nom du fichier, texte et modèle d'embedding.
6. Basculer atomiquement l'alias `helpdesk_procedures` vers cette collection.
7. Afficher le nombre de procédures indexées (17 pour le corpus actuel).

Le nom du vecteur est dérivé du nom du modèle. Un changement de modèle est ainsi
détecté même à dimension identique. Après changement de modèle, réindexer ; si
le contenu d'un modèle change tout en gardant le même nom, réindexer également.

Un corpus vide est refusé. Une erreur de vectorisation ou de chargement avant la
bascule laisse l'ancien alias intact. En cas de réponse réseau perdue pendant
la bascule, vérifier l'alias : la bascule peut avoir réussi côté serveur.
Les anciennes collections et les collections de construction incomplètes sont
conservées ; elles peuvent être supprimées manuellement après vérification que
plus aucun alias ne les référence. Éviter plusieurs indexations simultanées.

## 4. Comprendre la recherche et la résolution

```text
Fichiers Markdown → Ollama / embeddings → collections Qdrant
Ticket → Ollama / embedding → recherche Qdrant → 3 procédures
Ticket + procédures → Ollama / génération → proposition avec sources
```

`ProcedureSearch` envoie le vecteur de la question à
`POST /collections/helpdesk_procedures/points/query`. Qdrant calcule le classement
cosinus et retourne les payloads. PHP fournit au générateur les mêmes champs
qu'avant : `source`, `content`, `score`. Le parcours utilisateur reste identique.

Le score indique une proximité entre vecteurs, pas une probabilité de résolution.
Cette intégration conserve un vecteur par fichier et trois résultats, sans seuil
minimal ni découpage en passages.

## 5. Vérifier l'installation

```powershell
Invoke-RestMethod http://localhost:6333/readyz
Invoke-RestMethod http://localhost:6333/aliases
Invoke-RestMethod http://localhost:8081/test-search.php
composer check
```

La recherche utilise un exemple Outlook et doit renvoyer des sources avec leurs
scores. Dans l'application, créer ou ouvrir un ticket puis demander une résolution
pour vérifier aussi la génération et l'affichage des références.

`tests/qdrant.php` utilise des réponses Guzzle simulées : il contrôle les paramètres,
les payloads, l'attente du chargement, la bascule d'alias, le format des résultats,
les réponses invalides et l'absence de bascule après un échec de chargement.
La suite `composer check` n'a pas besoin d'un vrai service Qdrant.

## 6. Maintenance et compatibilité

Après modification, ajout ou suppression d'un document, relancer l'indexation :
la nouvelle collection reflète tout le corpus et exclut les fichiers supprimés.
Un redémarrage normal ne nécessite pas de réindexation.

```powershell
docker compose logs --tail 50 qdrant
docker compose logs --tail 50 app
```

`depends_on` impose l'ordre de démarrage sans garantir que Qdrant soit déjà prêt.
Si l'indexation échoue juste après un démarrage, attendre sa disponibilité puis
relancer la commande. Ne pas utiliser `docker compose down -v` pour un simple
redémarrage : cette option efface aussi les volumes MySQL et Ollama.

L'ancien `storage/procedures-index.json` est conservé, sans import automatique.
L'indexation reconstruit les vecteurs depuis les documents sources. Les classes
acceptent encore un chemin JSON explicitement en second/troisième argument pour
compatibilité et pour les tests historiques ; les points d'entrée applicatifs
utilisent désormais Qdrant et ne font aucun repli silencieux sur le JSON.

Pour exécuter les classes PHP directement sur l'hôte, définir `QDRANT_URL` à
`http://localhost:6333` et fournir une URL Ollama accessible depuis l'hôte.
PHP ne charge pas `.env` automatiquement.

Références officielles : [collections et alias](https://qdrant.tech/documentation/manage-data/collections/),
[chargement des points](https://api.qdrant.tech/api-reference/points/upsert-points),
[recherche de points](https://api.qdrant.tech/api-reference/search/query-points).
