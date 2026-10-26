# Exemples de tickets

Ces 10 exemples fictifs peuvent être copiés dans les champs **Titre** et
**Description** du formulaire de création d'un ticket. Ils couvrent les cinq
domaines des procédures de démonstration disponibles.

Pour préparer l'environnement, voir le [guide développeur](README-DEV.md).
Le [guide utilisateur](README-USER.md) décrit le parcours dans l'interface.
Ces exemples permettent des essais manuels ; ils ne constituent pas un jeu de
réponses attendues garantissant la qualité de l'IA.

## Parcours de vérification

1. Créer un ticket avec l'un des exemples ci-dessous et vérifier son apparition
   dans la liste après la redirection.
2. Demander une analyse, relire la catégorie proposée, puis la corriger ou la valider.
3. Demander une résolution après avoir indexé les procédures.
4. Vérifier que la proposition comprend un résumé et au maximum cinq étapes,
   chacune accompagnée de références parmi les procédures retrouvées. Une
   abstention sans étape doit expliquer les limites.
5. Lire les documents cités pour vérifier que chaque conseil est justifié.

La catégorie validée est enregistrée. La proposition de résolution reste affichée
sans être conservée et n'entraîne aucune action automatique.

## Vérifier les contrôles du formulaire

- Soumettre un titre ou une description vide : un message doit demander une
  correction. Si le navigateur bloque l'envoi, c'est le contrôle HTML ; les
  validations côté serveur sont couvertes par `tests/ticket-validator.php`.
- Essayer un champ composé uniquement d'espaces : le serveur refuse la création
  et conserve les autres textes exploitables dans le formulaire.
- Si un échec d'enregistrement survient, vérifier que le message indique que
  la saisie est conservée ; la liste indisponible ne doit pas être présentée comme vide.

Les rejets CSRF, les limites de longueur et les erreurs HTTP, Ollama et de vecteurs
sont vérifiés automatiquement par `composer check`, sans provoquer de panne dans
l'environnement de démonstration. Voir le détail dans le [guide développeur](README-DEV.md).

## 1. Compte verrouillé

**Titre :** Impossible d'ouvrir ma session : compte verrouillé

**Description :** Depuis ce matin à 8 h 30, je ne peux plus ouvrir ma session
sur le poste DEMO-012. Le message « Le compte référencé est actuellement
verrouillé » apparaît après plusieurs tentatives de connexion. Un collègue
arrive à se connecter sur ce même poste avec son propre compte. Je suis bloqué
pour commencer mon travail.

## 2. Authentification multifacteur

**Titre :** Je ne reçois plus la notification de validation de connexion

**Description :** Lorsque je me connecte au portail de démonstration, mon
identifiant est accepté, puis une validation sur mon téléphone est demandée.
Aucune notification n'arrive depuis mon changement de téléphone hier.
L'application d'authentification est installée sur le nouvel appareil. Le
problème se produit à chaque tentative et m'empêche d'accéder au portail.

## 3. Imprimante hors ligne

**Titre :** L'imprimante du bureau apparaît hors ligne

**Description :** Je ne peux plus imprimer sur l'imprimante DEMO-IMP-02 depuis
mon poste. Windows indique « Hors connexion », alors que l'imprimante est
allumée et que son écran n'affiche aucune erreur. Deux documents de test restent
dans ma file d'impression. Mon collègue arrive à imprimer sur cette même
imprimante depuis son ordinateur.

## 4. Qualité d'impression

**Titre :** Bandes blanches sur les documents imprimés

**Description :** Depuis cet après-midi, les pages imprimées sur DEMO-IMP-01
présentent de larges bandes blanches et certains mots sont illisibles. Le défaut
apparaît sur un PDF de test et sur une page de texte sans données sensibles.
Deux collègues constatent le même problème. Aucun message d'erreur n'apparaît
sur l'écran de l'imprimante.

## 5. Connexion Wi-Fi instable

**Titre :** Le Wi-Fi se déconnecte régulièrement sur mon portable

**Description :** Depuis ce matin, mon ordinateur portable perd la connexion
au Wi-Fi de démonstration toutes les cinq à dix minutes. Les pages web et les
applications réseau deviennent alors inaccessibles, puis la connexion revient
au bout d'une minute environ. Le redémarrage du poste n'a rien changé. Un
collègue installé à côté de moi ne rencontre pas ces coupures.

## 6. Incident réseau collectif

**Titre :** Internet et les dossiers partagés sont inaccessibles sur plusieurs postes

**Description :** Depuis 10 h 15, les quatre postes du bureau de démonstration
n'accèdent plus à Internet ni au dossier partagé commun. Les navigateurs
affichent « Ce site est inaccessible » et l'ouverture du partage indique
« Chemin réseau introuvable ». Les câbles Ethernet semblent branchés. Les
applications installées localement continuent à fonctionner, mais notre travail
sur les documents partagés est bloqué.

## 7. Envoi de courriels bloqué

**Titre :** Mes messages restent dans la boîte d'envoi Outlook

**Description :** Depuis 9 h, les courriels envoyés depuis Outlook restent dans
la boîte d'envoi. Le message « Outlook travaille hors connexion » apparaît en
bas de la fenêtre. Je peux consulter des sites web et envoyer un message de
test depuis la messagerie web avec le même compte de démonstration. J'ai fermé
puis rouvert Outlook, sans amélioration.

## 8. Réception des courriels interrompue

**Titre :** Je ne reçois plus de messages : boîte aux lettres pleine

**Description :** Je ne reçois plus de nouveaux courriels depuis hier
après-midi, dans Outlook comme dans la messagerie web. Un collègue qui m'a
envoyé un message de test a reçu un avis indiquant que ma boîte aux lettres est
pleine. Une alerte de stockage apparaît également dans la messagerie web.
L'accès aux anciens messages fonctionne encore. J'ai besoin de retrouver la
réception des demandes de l'équipe.

## 9. Logiciel métier indisponible

**Titre :** Erreur 503 à l'ouverture de l'application de planning

**Description :** Depuis 14 h, l'application fictive Planning Démo affiche
« 503 Service Unavailable » dès l'ouverture de sa page d'accueil. Trois
utilisateurs rencontrent le même problème sur des postes différents. Les
autres sites et le dossier partagé restent accessibles. Nous ne pouvons plus
consulter ni modifier le planning de démonstration.

## 10. Lenteur d'une application métier

**Titre :** La recherche dans Stock Démo prend plus d'une minute

**Description :** Depuis la reprise de cet après-midi, chaque recherche
d'article dans l'application fictive Stock Démo prend entre une et deux
minutes, alors qu'elle était presque immédiate ce matin. Le résultat finit par
s'afficher sans message d'erreur. Deux collègues observent la même lenteur.
Les autres applications fonctionnent normalement. Nous utilisons uniquement
des références d'articles fictives pour cette démonstration.
