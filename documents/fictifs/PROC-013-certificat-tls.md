# PROC-013 - Certificat TLS expiré, nom incorrect ou chaîne incomplète

## Catégorie

Applications métier

## Périmètre et prérequis

Application web HTTPS de démonstration, navigateur ou client technique. Symptômes : alerte de certificat, échec de négociation TLS, erreur apparue après renouvellement ou accès différent selon le poste. La cible `portail.demo.example` est fictive. Utiliser un terminal Linux équipé de curl et OpenSSL ; les mêmes outils peuvent être employés dans un environnement technique autorisé sur Windows.

## Diagnostic

1. Noter le nom présent dans l'URL, l'heure du poste, le navigateur, le réseau utilisé et le message exact. Vérifier si l'alerte concerne tous les postes et si elle est apparue après un changement de certificat, de proxy ou d'équilibreur.
2. Tester la réponse HTTPS avec la validation normale active :

```bash
curl --head --connect-timeout 5 --max-time 15 https://portail.demo.example/
```

Un HTTP 405 peut simplement signifier que l'application refuse HEAD ; ce résultat est distinct d'un échec TLS. Ne pas ajouter `-k` pour présenter le service comme rétabli.

3. Examiner les certificats présentés avec SNI et contrôle du nom :

```bash
openssl s_client -connect portail.demo.example:443 \
  -servername portail.demo.example \
  -verify_hostname portail.demo.example -verify_return_error \
  -showcerts </dev/null
```

Relever le sujet, les noms alternatifs, l'émetteur, les dates et le résultat de vérification. `-showcerts` affiche ce que le serveur envoie ; cette liste n'est pas à elle seule une chaîne validée. Une autorité interne inconnue du terminal peut provoquer un défaut de confiance alors que sa distribution est correcte sur les postes gérés.

4. Distinguer expiration, nom absent des SAN, chaîne intermédiaire manquante et autorité non approuvée. Comparer aussi l'émetteur vu depuis deux réseaux autorisés : un équipement d'inspection peut présenter un autre certificat. Si un répartiteur possède plusieurs nœuds, demander une comparaison contrôlée des nœuds ; un seul nœud mal renouvelé explique parfois l'intermittence.

## Correction et retour arrière

Faire installer le certificat correspondant au nom publié, avec sa clé et la chaîne requise, sur le composant qui termine réellement TLS. Conserver la configuration précédente et vérifier la cohérence clé/certificat dans l'outillage d'administration. Pour une autorité interne, utiliser le mécanisme de distribution approuvé plutôt qu'un import manuel non contrôlé.

Tester la configuration avant rechargement du service. Le retour arrière doit restaurer un ensemble cohérent ; remettre un certificat déjà expiré ne rétablit pas la sécurité et ne constitue pas une solution acceptable. Ne jamais copier de clé privée dans un ticket ou désactiver durablement la validation TLS.

## Validation et escalade

Répéter le test depuis un poste affecté et contrôler le nom, les dates et la confiance après changement. Vérifier la réponse applicative, pas seulement la réussite de la négociation. Transmettre les métadonnées publiques du certificat, l'heure, le réseau et l'erreur de vérification à l'équipe PKI ou au propriétaire de la terminaison TLS.

## Référence technique

[OpenSSL : options et vérification de s_client](https://docs.openssl.org/4.0/man1/openssl-s_client/).
