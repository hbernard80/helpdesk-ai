# _HelpDesk AI_ — Un assistant pour le support informatique

_HelpDesk AI_ aide un technicien à traiter les problèmes informatiques signalés par les utilisateurs. L'application rassemble les demandes, propose de les classer et s'appuie sur des fiches pratiques pour suggérer des pistes de résolution.

**Il s'agit d'un prototype de démonstration fonctionnel**, réalisé dans le cadre d'un projet personnel. Les exemples et les documents utilisés sont fictifs.

## À quoi sert le projet ?

Lorsqu'une personne rencontre un problème, comme une messagerie qui ne fonctionne plus, elle doit expliquer la situation au support informatique. Cette demande est appelée un **ticket** : c'est simplement une fiche décrivant le problème.

Le technicien doit ensuite comprendre la demande, identifier le domaine concerné
et chercher les bonnes consignes. _HelpDesk AI_ vise à faciliter ce travail en lui
présentant une première analyse et des étapes possibles à vérifier.

L'objectif est de réduire le temps consacré à la recherche d'informations et
d'aider à appliquer les consignes disponibles. Le gain de temps réel reste à
évaluer sur un plus grand nombre de situations.

## Un exemple concret

Une personne signale : _Je ne peux plus envoyer de mails depuis Outlook_.

Avec _HelpDesk AI_, le technicien peut :

1. Enregistrer cette demande et la retrouver dans la liste des tickets.
2. Demander à l'assistant de proposer une catégorie, par exemple « Messagerie ».
3. Vérifier cette catégorie, la corriger si nécessaire, puis la valider.
4. Demander une proposition de résolution fondée sur les fiches pratiques disponibles.
5. Lire les étapes proposées et consulter les documents cités avant d'agir.

L'assistant peut, par exemple, suggérer de vérifier la connexion réseau,
d'identifier le message d'erreur et de tester la messagerie depuis un navigateur.
La réponse dépend de la demande et du contenu des documents disponibles.

## Comment l'intelligence artificielle intervient-elle ?

L'application utilise une intelligence artificielle appelée **Qwen3** pour comprendre la demande et rédiger une proposition.

Avant de lui demander une réponse, _HelpDesk AI_ recherche les fiches pratiques
dont le sens se rapproche du problème décrit. Il peut ainsi retrouver une fiche
sur la messagerie même si l'utilisateur n'emploie pas exactement les mêmes mots.

Les fiches sélectionnées sont ensuite transmises à l'assistant avec la demande.
La proposition présente des étapes accompagnées de références aux documents.
Le technicien peut ouvrir les fiches retrouvées pour vérifier les conseils.

L'application contrôle que les noms des documents cités font bien partie des
fiches transmises. **Ce contrôle ne garantit pas que chaque conseil est correct :
une vérification humaine reste indispensable.**

## Ce qui fonctionne aujourd'hui

- La création et la consultation des tickets.
- La proposition d'une catégorie par l'intelligence artificielle.
- La correction et la validation de cette catégorie par un technicien.
- La recherche de fiches pratiques pertinentes pour une demande.
- La génération d'une proposition de résolution avec des références par étape.
- La consultation des fiches retrouvées et l'affichage des limites indiquées par l'assistant.

Le parcours de recherche et de génération a été testé avec succès sur un problème de messagerie Outlook. Ce premier résultat ne constitue pas une validation de tous les types d'incidents.

## Comment essayer l'application ?

Une fois l'application démarrée sur l'ordinateur de démonstration :

1. Ouvrir [_HelpDesk AI_](http://localhost:8081/) dans un navigateur.
2. Saisir un titre et une description, puis cliquer sur **Créer le ticket**.
3. Sous le ticket, cliquer sur **Analyser avec l'IA** pour obtenir une catégorie.
4. Vérifier la catégorie proposée, puis cliquer sur **Valider la catégorie**.
5. Cliquer sur **Proposer une résolution avec les procédures** pour afficher les conseils et leurs sources.

La génération peut prendre plusieurs minutes selon la puissance de l'ordinateur.
L'adresse ci-dessus fonctionne sur l'ordinateur qui exécute le projet ; elle ne
correspond pas à un site public accessible depuis n'importe où.

## Contrôles de saisie et messages d'erreur

Le titre et la description sont obligatoires. Le titre accepte jusqu'à
255 caractères ; la description jusqu'à 30 000 caractères, dans la limite de
stockage de la base. Une saisie composée uniquement d'espaces est refusée.
En cas d'erreur de saisie, le formulaire indique les champs à corriger et
conserve les textes exploitables.

Si l'enregistrement échoue, un message indique que la saisie est conservée dans
le formulaire affiché. Attendre que le service soit disponible, puis réessayer.
Il ne s'agit pas d'une sauvegarde : quitter ou recharger la page peut perdre cette saisie.

Les actions utilisent une protection liée à la session du navigateur. Autoriser
les cookies ; si le message « Jeton de sécurité invalide ou expiré » apparaît,
copier d'abord une éventuelle saisie, recharger la page des tickets, puis réessayer.

L'application refuse les propositions de résolution mal formées, tronquées ou
citant des documents inconnus. Elle contrôle aussi les données utilisées pour
la recherche documentaire. Si la génération échoue, un message d'erreur est
affiché ; les détails techniques sont réservés aux journaux du serveur.
Ces contrôles ne remplacent pas la vérification des conseils par le technicien.

## La place du technicien

L'assistant propose des pistes ; le technicien décide des actions à effectuer.
_HelpDesk AI_ ne modifie pas les ordinateurs des utilisateurs et n'exécute pas les
étapes proposées. Une réponse affichée ne signifie donc pas que le problème est résolu.

La validation disponible aujourd'hui concerne **la catégorie du ticket**.
L'enregistrement, la correction et la validation des propositions de résolution,
ainsi que leur historique, restent à développer. La proposition de résolution
actuelle n'est pas conservée après avoir quitté la page.

## Où sont traitées les informations ?

Dans la configuration actuelle, l'application, les tickets, les documents et
l'intelligence artificielle fonctionnent sur l'ordinateur qui héberge le projet.
Les demandes et les fiches sont traitées localement, sans appel à un service
externe d'intelligence artificielle pour produire les réponses.

Ce fonctionnement local ne suffit pas, à lui seul, à garantir la sécurité ou la
confidentialité. Le prototype ne dispose pas encore de comptes utilisateurs ni
de contrôle d'accès : il est destiné à une démonstration avec des données fictives.

## Les limites à connaître

Comme tout projet faisant appel à un LLM, l'assistant peut se tromper, mal interpréter une demande ou proposer une action peu adaptée. La qualité de ses réponses dépend notamment des fiches disponibles. Si elles ne couvrent pas le problème, la proposition peut être incomplète et
nécessiter l'intervention d'un support spécialisé.

Pour les informations d'installation et les détails techniques, consulter le
[README du projet](README.md).
