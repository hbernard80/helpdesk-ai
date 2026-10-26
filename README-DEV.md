# HelpDesk AI — Documentation développeur

HelpDesk AI est un prototype PHP de gestion de tickets intégrant une IA locale.
Il combine classification, recherche sémantique dans des procédures Markdown et
génération d'une proposition de résolution avec références documentaires.

Le pipeline RAG (*Retrieval-Augmented Generation*, ou génération augmentée par
recherche documentaire) est implémenté. Le prototype reste destiné à une
démonstration avec des données fictives, sans exposition publique en l'état.

Voir aussi le [README principal](README.md), le [guide utilisateur](README-USER.md)
et les [exemples de tickets et contrôles manuels](README-EXAMPLES.md).

## Stack et architecture

| Composant | Implémentation actuelle |
| --- | --- |
| Application | PHP 8.5, Apache, pages PHP rendues côté serveur, sans framework |
| Chargement des classes | Composer, PSR-4 : `App\` vers `src/` |
| Persistance des tickets | MySQL 8.4, PDO et requêtes préparées |
| Génération et classification | LLPhant / Ollama, modèle `qwen3:4b` |
| Embeddings | LLPhant / Ollama, modèle `nomic-embed-text` |
| Recherche | Qdrant, vecteurs persistants et similarité cosinus |
| Exécution | Docker Compose : services `app`, `db`, `ollama`, `qdrant` et `phpmyadmin` |
| Interface | HTML et CSS, formulaires HTTP POST |

Les services communiquent sur le réseau de Compose. L'application Apache est
publiée à l'adresse `127.0.0.1:8081`. MySQL et Ollama ne publient pas de port sur
l'hôte. phpMyAdmin permet d'administrer MySQL sur `http://localhost:8082/` avec
les identifiants de la base ; sa liaison `8082:80` n'est pas limitée à l'interface
locale. Aucun GPU n'est configuré dans Compose ; l'inférence fonctionne sur CPU.

### Organisation du code

```text
src/
├── config.php                     # Paramètres communs et valeurs par défaut
├── templates/
│   ├── shared/                    # En-tête et pied de page communs
│   ├── tickets/                   # Page, formulaire, liste, ticket et actions
│   └── resolution/                # Page, proposition et procédures retrouvées
├── bin/index-procedures.php       # Construction de l'index documentaire
├── Services/
│   ├── Config.php                 # Accès à la configuration commune
│   ├── Database.php               # Connexion PDO
│   ├── TicketRepository.php       # Lecture et écriture des tickets
│   ├── TicketValidator.php        # Validation des saisies, identifiants et catégories
│   ├── TicketCategories.php       # Liste commune des catégories autorisées
│   ├── Http.php                   # Méthodes, statuts, formats et redirections
│   ├── Csrf.php                   # Jetons et cookies de session
│   ├── Utils.php                  # Échappement HTML
│   ├── LlmClient.php              # Adaptateur de génération LLPhant
│   ├── LlphantOllamaChat.php      # Conservation des métadonnées de génération
│   ├── LlphantFactory.php         # Configuration et validation des réponses LLPhant
│   ├── TicketClassifier.php       # Catégorie et résumé du ticket
│   ├── ProcedureRepository.php    # Lecture des fichiers Markdown
│   ├── EmbeddingClient.php        # Génération des vecteurs
│   ├── EmbeddingVector.php        # Structure, valeurs et dimensions des vecteurs
│   ├── QdrantStore.php            # API REST, collections et alias Qdrant
│   ├── ProcedureIndexer.php       # Vectorisation puis publication dans Qdrant
│   ├── ProcedureSearch.php        # Recherche Qdrant ; JSON explicite pour compatibilité
│   └── ResolutionGenerator.php    # Génération et contrôle des réponses
└── public/
    ├── index.php                  # Création et liste des tickets
    ├── analyze-ticket.php         # Classification IA
    ├── validate-ticket.php        # Validation humaine de la catégorie
    ├── resolve-ticket.php         # Recherche et proposition de résolution
    ├── test-*.php                 # Diagnostics manuels
    └── assets/styles/app.css
database/migrations/001_create_tickets.sql
documents/fictifs/                 # Corpus de procédures
storage/procedures-index.json      # Ancien index JSON, conservé pour compatibilité
tests/                            # Huit scripts sans MySQL ni modèle réel
tools/check.php                   # Syntaxe et conventions des sources PHP
```

### Templates PHP

`public/index.php` et `public/resolve-ticket.php` conservent la validation, les
appels aux services et la préparation des réponses HTTP. Le rendu HTML est
chargé par un `require` vers `templates/tickets/page.php` ou
`templates/resolution/page.php`. Ces templates assemblent des fragments PHP
simples et partagent l'en-tête et le pied de page, sans moteur de templates.

Les variables nécessaires sont préparées par la page et documentées par des
annotations `@var` dans les templates. Les chemins d'inclusion sont fixes et
basés sur `__DIR__`. Les fragments restent hors de la racine web `public/` ; ils
n'effectuent aucune requête à la base ou à l'IA et conservent l'échappement HTML
via `Utils::escape()` ainsi que les champs anti-CSRF des formulaires.

### Montages Docker et autoload

| Chemin local | Chemin dans `app` | Usage |
| --- | --- | --- |
| `src/` | `/var/www/html` | Application, racine Apache `/var/www/html/public` |
| `src/` | `/var/www/src` (lecture seule) | Résolution des chemins PSR-4 de Composer |
| `vendor/` | `/var/www/vendor` (lecture seule) | Dépendances et autoload générés sur l'hôte |
| `documents/fictifs/` | `/var/www/procedures` (lecture seule) | Corpus documentaire |
| `storage/` | `/var/www/storage` | Ancien index JSON (compatibilité) |

Les scripts de `src/public/` et `src/bin/` chargent
`__DIR__ . '/../../vendor/autoload.php'`. Le double montage de `src/` permet au
code servi par Apache et à l'autoload situé dans `/var/www/vendor` de retrouver
les mêmes classes. Le Dockerfile seul ne constitue pas un paquet autonome :
les dépendances et les données sont fournies par les montages Compose.

## Installation locale

Les commandes suivantes s'exécutent depuis la racine du dépôt. Elles utilisent
PowerShell, Docker avec les conteneurs Linux, Docker Compose et Composer sur
l'hôte. Utiliser PHP 8.5 pour rester aligné sur l'image applicative.

### 1. Configuration et dépendances

Pour une nouvelle installation, copier le fichier d'exemple, puis personnaliser
`DB_PASSWORD` et `DB_ROOT_PASSWORD`. Ne pas écraser un `.env` existant.

```powershell
Copy-Item .env.example .env
composer install
composer dump-autoload --optimize --strict-psr
```

Installer les dépendances de développement pour cette configuration : elles
comprennent notamment un polyfill de `mbstring`, utilisé par la validation des
titres. Les appels à Ollama nécessitent cURL ; PDO MySQL est installé par le
Dockerfile. Les extensions réellement disponibles peuvent être contrôlées après
le démarrage avec `docker compose exec app php -m`.

### Configuration commune

`src/config.php` regroupe les paramètres de connexion, les modèles Ollama et les
chemins documentaires. `App\Services\Config::get()` charge ce fichier une seule
fois par exécution. Les clients Ollama utilisent cette configuration par défaut ;
leurs paramètres de constructeur permettent toujours une surcharge explicite.

| Clé PHP | Variable d'environnement | Valeur par défaut hors surcharge |
| --- | --- | --- |
| `db.host` | `DB_HOST` | `db` |
| `db.name` | `DB_NAME` | `helpdesk` |
| `db.user` | `DB_USER` | `helpdesk` |
| `db.password` | `DB_PASSWORD` | Vide ; la connexion refuse un mot de passe absent |
| `ollama.url` | `OLLAMA_URL` | `http://ollama:11434` |
| `ollama.model` | `OLLAMA_MODEL` | `qwen3:4b` |
| `ollama.embedding_model` | `OLLAMA_EMBEDDING_MODEL` | `nomic-embed-text` |
| `qdrant.url` | `QDRANT_URL` | `http://qdrant:6333` |
| `qdrant.collection` | `QDRANT_COLLECTION` | `helpdesk_procedures` (alias stable) |
| `procedures.directory` | `PROCEDURES_DIRECTORY` | `documents/fictifs/` depuis la racine du projet |
| `procedures.index_path` | `PROCEDURES_INDEX_PATH` | `storage/procedures-index.json` depuis la racine du projet |

Compose lit `.env` et transmet ces variables au conteneur `app`. Il définit les
chemins par défaut adaptés aux montages Docker : `/var/www/procedures` et
`/var/www/storage/procedures-index.json`. Une variable absente ou vide utilise la
valeur par défaut. PHP ne charge pas lui-même le fichier `.env` : pour lancer un
script directement sur l'hôte, définir les variables dans l'environnement du
processus et utiliser des chemins locaux si nécessaire.

Après modification de l'environnement Compose, recréer le service applicatif avec
`docker compose up -d app` pour appliquer les nouvelles valeurs. Les changements
du fichier PHP sont accessibles directement grâce aux montages existants.
Changer le modèle d'embedding nécessite de reconstruire l'index documentaire.

Les valeurs par défaut de Compose servent à préparer l'environnement des
conteneurs ; les valeurs PHP sont toutes définies dans `src/config.php`.
`DB_ROOT_PASSWORD` reste réservé à l'initialisation MySQL dans Compose. Les options
`APP_ENV` et `APP_DEBUG` du fichier d'exemple ne sont pas encore exploitées.

Pour vérifier la configuration sans connexion à MySQL ou Ollama :

```powershell
php tests/config.php
```

### 2. Démarrage des services

```powershell
docker compose config --quiet
docker compose up -d --build
docker compose ps
```

`depends_on` ne garantit pas que MySQL est prêt à accepter des connexions.
Attendre son démarrage avant d'importer le schéma.

### 3. Modèles et base de données

```powershell
docker compose exec ollama ollama pull qwen3:4b
docker compose exec ollama ollama pull nomic-embed-text
docker compose exec ollama ollama list

Get-Content -Raw database/migrations/001_create_tickets.sql |
    docker compose exec -T db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

Les modèles et les données MySQL sont conservés dans les volumes nommés
`ollama_data` et `mysql_data`. Modifier les mots de passe dans `.env` après
l'initialisation de MySQL ne change pas automatiquement les comptes déjà créés.

### 4. Indexation documentaire

```powershell
docker compose exec app php bin/index-procedures.php
```

La commande vectorise les procédures, crée une collection Qdrant avec la dimension
observée et la distance `Cosine`, charge les points puis bascule l'alias
`helpdesk_procedures`. Le volume `qdrant_data` conserve les données. Le fichier JSON
historique reste intact et n'est plus utilisé par les pages ni par cette commande.
Relancer l'indexation après toute modification du corpus ou du modèle d'embeddings.
Voir le [guide Qdrant détaillé](documents/QDRANT.md) pour la migration et les contrôles.

### 5. Vérification dans le navigateur

Ouvrir [la liste des tickets](http://localhost:8081/), créer un ticket, puis tester
les boutons **Analyser avec l'IA** et
**Proposer une résolution avec les procédures**.

La génération est synchrone et peut prendre plusieurs minutes sur CPU.
Le client Ollama applique un délai maximal de 180 secondes par appel de
génération ; le client d'embeddings applique un délai de 120 secondes.

## Fonctionnement du pipeline IA

### Intégration LLPhant

La dépendance `theodo-group/llphant` fournit les clients `OllamaChat` et
`OllamaEmbeddingGenerator`. `LlmClient` convertit les conversations en messages
LLPhant et délègue la génération à `generateChat()`. `EmbeddingClient` utilise
`embedText()` pour vectoriser les procédures et les requêtes.

LLPhant est une bibliothèque PHP : Ollama reste le serveur d'inférence local,
Qwen3 le modèle de génération et `nomic-embed-text` celui des embeddings. Aucune
clé API externe n'est nécessaire et les variables `OLLAMA_*` restent identiques.
`LlphantFactory` ajoute le suffixe `/api/` à l'URL de base pour LLPhant.

`LlphantOllamaChat` conserve la réponse complète, notamment `done_reason`, afin
que les services continuent de refuser les réponses tronquées. Le schéma JSON,
`think: false`, la température et le budget sont transmis dans `modelOptions`.
La méthode de compatibilité `generate()` passe désormais par `/api/chat`.

`LlphantFactory` configure Guzzle et valide les réponses HTTP/JSON, les erreurs
API et les vecteurs avant leur traitement par LLPhant. Les redirections sont
refusées et les réponses ne sont pas diffusées en streaming. Les erreurs réseau,
HTTP, JSON et API remontent sous forme de `RuntimeException` avec leur cause.

Le délai de connexion est limité à 10 secondes (ou au délai total s'il est plus
court). Les délais totaux restent de 180 secondes pour la génération et de
120 secondes pour les embeddings. Les modèles et l'URL viennent de la
configuration commune, avec possibilité de surcharge dans les constructeurs.
`EmbeddingClient` exige exactement un vecteur pour le texte envoyé.
`EmbeddingVector` vérifie que ce vecteur est une liste non vide de nombres finis,
avec au moins une composante non nulle. Les chaînes numériques, booléens, valeurs
nulles, structures imbriquées et nombres infinis sont refusés avec une erreur
contrôlée. Un vecteur nul ne peut pas servir au calcul de similarité cosinus.

L'indexation vérifie que toutes les procédures ont la même dimension avant
d'écrire l'index ; une réponse incompatible conserve donc le fichier existant.
La recherche contrôle les entrées de l'index et leurs vecteurs avant l'appel à
Ollama, puis vérifie que le vecteur de la requête a la dimension attendue. La
dimension n'est pas fixée à un modèle particulier : après un changement de
modèle, reconstruire l'index reste nécessaire, y compris si le nouveau modèle
utilise la même dimension.

Le calcul de similarité met les composantes à l'échelle avant les multiplications
pour éviter débordements et sous-débordements avec des valeurs extrêmes. Les
scores restent finis et compris entre -1 et 1.

Le test suivant utilise un faux serveur HTTP local temporaire, sans télécharger
de modèle ni contacter le service Ollama réel :

```powershell
php tests/llphant-clients.php
```

### Classification

`TicketClassifier` transmet le titre et la description à `/api/chat` d'Ollama.
Le modèle doit retourner un objet JSON contenant `category` et `summary`.
La catégorie est contrôlée contre une liste fermée : Authentification,
Messagerie, Réseau, Matériel, Applications métier et Autre.

La requête impose un schéma JSON avec ces catégories comme valeurs autorisées
et un résumé obligatoire. Le budget est de 300 tokens. PHP rejette également les
réponses tronquées, absentes, non textuelles et les résumés vides. Les erreurs
d'analyse sont journalisées avec l'identifiant du ticket, sans exposer le détail
technique au navigateur.

`analyze-ticket.php` enregistre la catégorie proposée dans `ai_suggestion` et le
modèle dans `ai_model`, puis réinitialise `validated_by_human`. Le résumé produit
par le classificateur n'est pas enregistré. Le technicien valide ou corrige la
catégorie via `validate-ticket.php` ; la valeur retenue est stockée dans `category`.

### Indexation et retrieval

`ProcedureRepository` lit les fichiers `*.md` du corpus. `ProcedureIndexer`
envoie le contenu entier de chaque procédure à `/api/embed`, puis `QdrantStore`
charge les vecteurs et les payloads (`source`, `content`, `embedding_model`)
dans une nouvelle collection. L'alias de recherche bascule après le chargement complet.

Il y a actuellement **un vecteur par fichier**, sans découpage en passages.
`ProcedureSearch` calcule l'embedding de la requête ; Qdrant recherche les voisins
par distance cosinus et renvoie les résultats par score décroissant. Le nom du
vecteur dépend du modèle, pour refuser une recherche avec un autre modèle sans
réindexation, même si les deux modèles ont la même dimension.

```text
similarité(a, b) = produit_scalaire(a, b) / (norme(a) × norme(b))
```

Pour la résolution, la requête est la concaténation du titre et de la description,
et les trois premières procédures sont retenues. Il n'y a pas de seuil minimal
de similarité, de reclassement supplémentaire ni de recherche
hybride. Le score exprime une proximité vectorielle, pas une probabilité que le
document soit pertinent.

### Génération augmentée et références

`resolve-ticket.php` lit le ticket, récupère les procédures, puis appelle
`ResolutionGenerator`. Le modèle reçoit le texte des documents et leurs noms.

La requête à Ollama utilise `stream: false`, `think: false`, une température de
`0`, un budget de `1 000` tokens de sortie et un schéma JSON imposé via `format`.
La classification utilise un budget de `300` tokens.

La structure attendue du modèle est :

```json
{
  "summary": "Résumé de la proposition",
  "steps": [
    {
      "instruction": "Vérifier la connexion réseau.",
      "sources": ["PROC-004-messagerie.md"]
    }
  ],
  "limitations": "Précisions sur les informations manquantes, si nécessaire."
}
```

Le schéma limite les références aux noms des documents transmis. Le code PHP
contrôle ensuite le résumé, le format des étapes, la limite de cinq étapes et
la présence d'au moins une référence connue par étape. Une réponse tronquée ou
invalide est rejetée. Une liste d'étapes vide est acceptée uniquement avec des
limites explicites. Le service ajoute une liste dédupliquée `sources` au résultat.

Les références et les procédures retrouvées sont affichées avec échappement HTML.
Le contrôle garantit l'appartenance des références au corpus transmis, **pas la
justification sémantique de chaque instruction**. Le prompt demande une réponse
fondée sur les procédures et traite le ticket et les documents comme des données
non fiables ; ce cadrage n'élimine pas tous les risques d'injection de prompt.

## Données et configuration

La table `tickets` conserve le titre, la description, la catégorie, la priorité,
le statut, la suggestion IA, le modèle, la validation humaine et les horodatages.
Les champs `priority` et `status` existent, mais l'interface actuelle n'implémente
pas de calcul de priorité ni de parcours complet de changement de statut.

Les résolutions RAG et leurs références sont affichées sans persistance. Leur
validation, leur correction et leur historique restent à implémenter. La validation
humaine existante porte uniquement sur la catégorie.

| Paramètre | Comportement actuel |
| --- | --- |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Lus depuis `.env` par Compose et transmis aux services concernés |
| `DB_HOST`, `DB_NAME`, `DB_USER` | Configurables via Compose ; valeurs par défaut `db`, `helpdesk`, `helpdesk` |
| `OLLAMA_URL` | Transmis par Compose ; défaut `http://ollama:11434` |
| `OLLAMA_MODEL` | Transmis par Compose à la configuration commune ; défaut `qwen3:4b` |
| `OLLAMA_EMBEDDING_MODEL` | Transmis par Compose ; défaut `nomic-embed-text` |
| `QDRANT_URL`, `QDRANT_COLLECTION` | API REST Qdrant et alias stable ; défauts `http://qdrant:6333` et `helpdesk_procedures` |
| `PROCEDURES_DIRECTORY`, `PROCEDURES_INDEX_PATH` | Transmis par Compose ; chemins Docker par défaut indiqués dans la section Configuration commune |
| `APP_ENV`, `APP_DEBUG` | Présents dans `.env.example`, sans pilotage applicatif implémenté |

Le fichier `.env` sert à l'interpolation Compose ; il n'est pas chargé directement
par PHP. Modifier une variable dans ce fichier ne la transmet donc pas au
conteneur si elle n'est pas référencée dans `compose.yaml`. Les pages, les
diagnostics et l'indexeur utilisent la configuration commune. Les valeurs par
défaut de PHP permettent aussi l'utilisation de chemins locaux hors Docker ;
les chemins définis par Compose correspondent aux montages décrits ci-dessus.

## Tests et diagnostic

### Tests automatisés sans modèle

```powershell
php tests/resolution-generator.php
```

Ce script emploie un faux client de génération. Il contrôle la transmission des documents,
le schéma avec les sources autorisées, une réponse valide, le rejet de références
inventées, d'étapes sans source, d'une absence de procédures, d'une réponse
tronquée et d'un JSON invalide, ainsi que l'abstention avec limites explicites.
Il ne mesure pas la pertinence des réponses du modèle réel.

### Diagnostics manuels

| Page | Contrôle |
| --- | --- |
| `/test-database.php` | Connexion à MySQL |
| `/test-ollama.php` | Premier appel au modèle de génération |
| `/test-classifier.php` | Classification d'un exemple Outlook |
| `/test-procedures.php` | Lecture du corpus |
| `/test-embedding.php` | Création d'un vecteur |
| `/test-search.php` | Recherche sémantique sur une demande Outlook |

Vérifications déjà effectuées pendant le développement : recherche HTTP 200 avec
la procédure de messagerie en première position ; résolution d'un ticket Outlook
en HTTP 200 avec cinq étapes et références ; rejet d'un GET sur la résolution
(405) et d'un identifiant nul (400). Ces vérifications ne remplacent pas une
campagne de tests multi-incidents.

Pour inspecter les services et le modèle chargé :

```powershell
docker compose logs --tail 50 app
docker compose logs --tail 50 ollama
docker compose exec ollama ollama ps
```

En cas de classe introuvable, vérifier le mapping `App\` dans `composer.json`,
les montages de `src/` et `vendor/`, puis régénérer l'autoload. En cas d'erreur de
retrieval, vérifier Qdrant, son alias et la présence de `nomic-embed-text`.
Les erreurs détaillées de résolution sont écrites dans les journaux Apache ;
le navigateur reçoit un message générique.

### Vérifications automatiques

Depuis la racine du projet, avec PHP 8.5 et les dépendances installées :

```powershell
composer lint
composer test
composer check
```

`composer lint` vérifie la syntaxe et les conventions de base de tous les fichiers
PHP dans `src/`, `tests/` et `tools/` : UTF-8 sans BOM, fins de ligne LF, saut de
ligne final, absence d'espaces en fin de ligne et d'indentation par tabulations.
Ce contrôle est en lecture seule et ne prétend pas vérifier l'ensemble de PSR-12.
`.editorconfig` configure les éditeurs compatibles ; `.gitattributes` conserve
les fins de ligne LF des sources PHP, JSON et YAML dans les checkouts Git.

`composer test` lance les huit scripts de test : configuration, validation des
tickets, classification simulée, génération de résolution simulée, protection CSRF, réponses HTTP et
clients LLPhant et stockage Qdrant simulé. `composer check` enchaîne lint et tests, et s'arrête dès qu'une
commande échoue. Les tests HTTP et LLPhant ouvrent des serveurs temporaires sur
`127.0.0.1`, automatiquement arrêtés en fin de test. Aucun serveur MySQL, modèle
Ollama réel ni Docker n'est nécessaire pour cette suite. PHP doit disposer de cURL
et autoriser le lancement de sous-processus ; le réseau local doit être accessible.

| Script | Contrôles principaux |
| --- | --- |
| `tests/config.php` | Valeurs par défaut, surcharges et utilisation de la configuration commune |
| `tests/ticket-validator.php` | Champs obligatoires, UTF-8, octets nuls, longueurs et capacité SQL, identifiants et catégories |
| `tests/ticket-classifier.php` | Schéma et budget, catégories autorisées, résumé, réponses absentes, invalides et tronquées |
| `tests/resolution-generator.php` | Schéma, sources autorisées, réponses invalides ou tronquées, abstention explicite |
| `tests/csrf.php` | Jetons de session et rejet des quatre traitements sans jeton valide |
| `tests/http.php` | Statuts, en-têtes, méthodes, erreurs génériques, saisie conservée après échec de création et redirection après succès |
| `tests/llphant-clients.php` | Requêtes et options, erreurs HTTP/JSON/API, réseau et délais, vecteurs invalides, dimensions, préservation de l'index lors d'une incompatibilité et stabilité du classement cosinus |
| `tests/qdrant.php` | Configuration, payload, chargement avant bascule atomique, recherche, erreurs et préservation de l'alias en cas d'échec |

La suite actuelle utilise des scripts PHP autonomes ; PHPUnit, PHPStan et
PHP_CodeSniffer ne sont pas configurés. Les simulations ne mesurent pas la
pertinence des réponses produites par un modèle réel.

Le workflow GitHub Actions utilise PHP 8.5 avec cURL, mbstring et PDO MySQL,
valide la configuration Composer, installe les dépendances verrouillées puis
exécute `composer check`. Un second job vérifie Compose et construit l'image PHP.
La CI ne démarre pas de service MySQL ni de modèle Ollama pour les tests.

## Protection des formulaires

Les réponses passent par `App\Services\Http` : `requirePost()` ou `requireMethods()` pour les méthodes autorisées, `redirect()` pour les redirections 303 après POST, `abort()` pour les erreurs, `html()` pour préparer un rendu HTML, et `text()` / `json()` pour envoyer une réponse complète et terminer la requête.

Les erreurs des formulaires sont en texte UTF-8 ; les erreurs de saisie de création conservent le formulaire HTML en 422. Les diagnostics JSON gardent leur format avec un objet `{"error": "..."}` en cas d'erreur. Les diagnostics acceptent GET et HEAD ; les autres méthodes reçoivent 405 avec `Allow`. Les erreurs serveur sont journalisées et seul un message générique est exposé au navigateur, y compris
sur la page principale et les diagnostics.

Le test suivant démarre un serveur PHP temporaire sur l'interface locale pour contrôler les vrais statuts et en-têtes, sans MySQL ni Ollama :

```powershell
php tests/http.php
```

`TicketValidator` centralise la validation des champs : chaînes UTF-8 sans octet nul, titre obligatoire de 255 caractères maximum et description obligatoire de 30 000 caractères maximum. La description respecte également les 65 535 octets du type `TEXT` utilisé par le schéma. Les textes sont nettoyés de leurs espaces de début et de fin ; une valeur contenant uniquement des espaces est refusée. Les limites en caractères sont aussi indiquées dans les champs HTML.

Les identifiants doivent être des chaînes décimales comprises entre 1 et 4 294 967 295 (`INT UNSIGNED`), sans signe, espace ni zéro initial. Les catégories doivent correspondre exactement à la liste de `TicketCategories`.

Une erreur de validation à la création renvoie HTTP 422 et réaffiche le formulaire avec les textes exploitables, échappés pour HTML, sans requête à la base. La liste des tickets est affichée à nouveau après correction ou rechargement de la page. Les traitements d'analyse, de résolution et de validation refusent les identifiants ou catégories invalides en HTTP 400 avant tout accès à MySQL ou Ollama.

Si la connexion MySQL ou l'insertion échoue après validation, la page renvoie HTTP 500 en HTML et réaffiche le formulaire avec le titre et la description échappés, ainsi que le jeton CSRF toujours utilisable. Un message générique indique que la saisie est conservée ; l'exception est journalisée avec le contexte de création. Aucune redirection ni nouvelle tentative automatique n'est effectuée. Un échec de lecture de la liste reçoit un message distinct et ne présente pas une fausse liste vide. La redirection 303 n'intervient qu'après une insertion réussie,
avant toute lecture de la liste.

`tests/http.php` couvre les échecs de connexion et d'insertion ainsi que le succès
de création, avec une connexion PDO simulée uniquement dans le serveur de test.

```powershell
php tests/ticket-validator.php
```

Les formulaires de création, d'analyse, de résolution et de validation des tickets transmettent un jeton anti-CSRF aléatoire conservé en session PHP. Chaque traitement POST vérifie ce jeton avant tout accès à la base ou appel à l'IA. Un jeton absent ou invalide entraîne une réponse HTTP 403 ; il faut alors recharger la page des tickets. Les cookies de session doivent être autorisés dans le navigateur.

Le cookie est `HttpOnly`, utilise `SameSite=Lax` et reçoit l'attribut `Secure` lorsque PHP détecte une connexion HTTPS. La session est fermée avant les appels IA pour permettre les requêtes concurrentes depuis plusieurs onglets.

Pour tester les jetons et les rejets des quatre traitements sans MySQL ni Ollama :

```powershell
php tests/csrf.php
```

## Limites et évolutions

- Absence d'authentification et de gestion des rôles.
- Pages de diagnostic accessibles dans la racine web.
- phpMyAdmin publié sur le port 8082 sans restriction à `127.0.0.1` dans Compose.
- Appels IA synchrones, sans file d'attente, limitation de débit ni suivi de tâche.
- Indexation complète en mémoire, un vecteur par fichier, sans découpage en passages.
- Anciennes collections Qdrant conservées après réindexation ; nettoyage manuel
  après vérification des alias. Éviter plusieurs indexations simultanées.
- Absence de persistance et d'historique des résolutions.
- Qualité documentaire, pertinence des réponses et robustesse à évaluer sur un corpus de tests plus large.

`.gitignore` exclut `vendor/`, `.env` et ses variantes locales, tout en conservant
`.env.example`. Le projet ne définit pas encore de licence de diffusion.

Avant une mise en ligne exécutable, prévoir les protections d'accès, le stockage persistant et les ressources d'inférence. La configuration locale actuelle ne constitue pas un déploiement de production prêt à exposer sur Internet.
