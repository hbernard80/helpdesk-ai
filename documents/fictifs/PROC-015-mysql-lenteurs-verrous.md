# PROC-015 - Application lente : diagnostiquer connexions et verrous MySQL

## Catégorie

Applications métier

## Périmètre et prérequis

MySQL 8.4 de démonstration, tables InnoDB et application utilisant une base relationnelle. Symptômes : formulaire qui attend indéfiniment, erreurs de délai de verrou, deadlock ou connexions refusées. Disposer d'un compte de diagnostic habilité : un compte applicatif ne doit pas recevoir des privilèges supplémentaires pour cette intervention. Les requêtes ci-dessous sont en lecture ; leurs résultats peuvent néanmoins exposer des informations applicatives.

## Qualification

Noter l'action exacte, sa durée, l'heure, le nombre d'utilisateurs et la présence d'un déploiement ou traitement planifié. Distinguer une erreur de connexion d'une requête acceptée mais lente. Si les pages sans base sont également lentes, examiner l'application et l'infrastructure avant d'attribuer le défaut à MySQL.

## Diagnostic technique

1. Depuis une session administrée, identifier le serveur et l'état global :

```sql
SELECT VERSION(), NOW();
SHOW GLOBAL STATUS LIKE 'Threads_connected';
SHOW VARIABLES LIKE 'max_connections';
SHOW FULL PROCESSLIST;
```

Comparer les connexions à la limite et observer les états des sessions. Les droits du compte déterminent les sessions visibles. Un nombre de connexions élevé ne démontre pas, seul, un besoin d'augmenter la limite ; rechercher une fuite, une saturation ou un traitement exceptionnel.

2. Observer les attentes de verrou de données :

```sql
SELECT ENGINE, REQUESTING_ENGINE_TRANSACTION_ID,
       BLOCKING_ENGINE_TRANSACTION_ID
FROM performance_schema.data_lock_waits
LIMIT 20;

SELECT trx_id, trx_started, trx_state, trx_mysql_thread_id
FROM information_schema.innodb_trx
ORDER BY trx_started;
```

Rapprocher une attente de la transaction bloquante et de sa durée. Éviter de copier les textes SQL contenant des données réelles. Une transaction longue peut bloquer d'autres requêtes tout en étant simplement oubliée par un client.

3. Pour une attente de changement de schéma, examiner séparément les verrous de métadonnées :

```sql
SELECT OBJECT_SCHEMA, OBJECT_NAME, LOCK_TYPE, LOCK_STATUS, OWNER_THREAD_ID
FROM performance_schema.metadata_locks
WHERE LOCK_STATUS = 'PENDING'
LIMIT 20;
```

Une table vide ne prouve pas qu'il n'y a jamais eu de blocage : le phénomène peut avoir cessé ou ne pas être visible avec cette instrumentation. Corréler les observations avec les heures des erreurs applicatives.

## Correction et retour arrière

Faire identifier le propriétaire de la transaction et demander sa terminaison normale. Ne pas lancer `KILL`, redémarrer MySQL ou augmenter globalement les délais pour masquer une attente. Une annulation peut entraîner un rollback long et affecter une opération métier ; elle doit être décidée par le DBA avec le responsable applicatif.

Pour une lenteur reproductible sans verrou, transmettre la requête normalisée au DBA pour analyse du plan en environnement adapté. Ne pas lancer `EXPLAIN ANALYZE` sur une requête coûteuse de production comme simple lecture : cette commande exécute la requête.

## Validation et escalade

Rejouer l'action sur des données fictives, comparer sa durée et vérifier que l'attente a disparu sans nouvelle erreur. Transmettre les heures, identifiants techniques de sessions, durées et changements récents. Toute modification d'index ou de paramètres nécessite son propre plan de validation et de retour arrière.

## Références techniques

[MySQL : verrous de données](https://dev.mysql.com/doc/refman/8.4/en/performance-schema-data-locks-table.html) et [verrous de métadonnées](https://dev.mysql.com/doc/refman/8.4/en/metadata-locking.html).
