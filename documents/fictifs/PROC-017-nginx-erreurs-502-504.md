# PROC-017 - Reverse proxy Nginx : erreurs HTTP 502 et 504

## Catégorie

Applications métier

## Périmètre et prérequis

Serveur Linux de démonstration avec Nginx devant une application HTTP. Cette architecture est un scénario supplémentaire de support ; HelpDesk AI utilise Apache dans sa configuration actuelle. Symptômes : page 502 après déploiement, 504 pendant une opération longue ou comportement différent entre accès direct et accès par proxy. Les cibles `portail.demo.example`, `127.0.0.1:9000` et le chemin `/health` sont fictifs et doivent correspondre au laboratoire réel.

## Diagnostic

1. Noter l'URL, la méthode, l'heure, la durée avant erreur et l'éventuel identifiant de requête. Ne pas répéter automatiquement une opération POST : un délai côté client n'indique pas que le serveur a annulé l'action. Utiliser une route de lecture ou de santé prévue pour les essais.
2. Vérifier le service et la syntaxe de configuration avec les droits nécessaires :

```bash
systemctl status nginx --no-pager
sudo nginx -t
journalctl -u nginx --since '-20 minutes' --no-pager -n 100
```

Consulter également les journaux d'accès et d'erreur configurés sur cette instance. Ne pas présumer leur chemin. Rechercher le message associé à l'heure : connexion refusée, nom d'upstream introuvable, connexion fermée prématurément ou attente de réponse expirée.

3. Depuis l'environnement où Nginx s'exécute, tester le service amont autorisé :

```bash
curl --connect-timeout 3 --max-time 10 -o /dev/null -sS \
  -w 'HTTP=%{http_code} durée=%{time_total}\n' http://127.0.0.1:9000/health
ss -lnt
```

Le port 9000 n'est qu'un exemple de serveur HTTP ; ne pas utiliser curl sur un port FastCGI. Si Nginx est dans un conteneur, son adresse `127.0.0.1` désigne ce conteneur. Un amont en échec direct nécessite d'abord un diagnostic applicatif ; un amont sain oriente vers la configuration du proxy, le routage ou les en-têtes attendus.

4. Comparer l'upstream configuré au déploiement réel : nom, port, protocole HTTP/HTTPS et route transmise. Pour un défaut intermittent, vérifier chaque instance amont. Ne pas modifier simultanément la cible, les délais et les en-têtes : conserver une hypothèse testable par changement.

## Correction et retour arrière

Restaurer la bonne cible ou corriger le service amont après identification de la cause. Pour un traitement légitimement long, faire examiner l'ensemble des délais et le mode d'exécution ; augmenter `proxy_read_timeout` sans analyse peut seulement prolonger l'attente. Ce délai concerne l'intervalle entre lectures successives, pas une durée maximale globale du traitement.

Avant changement, conserver la configuration précédente. Exécuter `nginx -t` puis recharger Nginx uniquement si le test réussit. En cas d'échec fonctionnel, restaurer la configuration, la retester et recharger à nouveau. Ne pas masquer les 502/504 derrière une page HTTP 200 ni désactiver les protections de l'amont.

## Validation et escalade

Tester une route de lecture via le proxy et directement, comparer codes et durées, puis vérifier l'absence de nouveaux messages d'erreur. Transmettre la route expurgée, l'heure, la cible technique, les mesures et le message Nginx pertinent à l'équipe applicative ou d'exploitation.

## Référence technique

[Nginx : proxy HTTP et délais](https://nginx.org/en/docs/http/ngx_http_proxy_module.html).
