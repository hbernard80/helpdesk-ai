# PROC-006 - Diagnostiquer une résolution DNS interne défaillante

## Catégorie

Réseau

## Périmètre et prérequis

Poste Windows 11 connecté au réseau de démonstration ou à son VPN. Symptômes : nom de serveur introuvable, intranet inaccessible, résolution intermittente ou application joignable par adresse IP seulement. Les noms `app.demo.example` et `dns.demo.example` sont fictifs : les remplacer par les ressources du laboratoire. Les commandes de lecture s'exécutent dans PowerShell ; la purge du cache peut nécessiter une console élevée.

## Diagnostic

1. Noter le nom exact demandé, l'heure du dernier échec, le réseau utilisé et les utilisateurs concernés. Comparer avec un poste fonctionnel situé sur le même segment. Un navigateur fonctionnel sur Internet ne démontre pas que les zones DNS internes sont accessibles.
2. Relever les interfaces et serveurs DNS :

```powershell
Get-NetIPConfiguration
Get-DnsClientServerAddress -AddressFamily IPv4
ipconfig /all
```

Vérifier l'interface réellement utilisée, le suffixe DNS et la présence éventuelle d'une configuration fournie par le VPN. Une adresse de serveur inattendue constitue un indice de configuration ; ne pas la remplacer immédiatement.

3. Interroger le nom complet puis le serveur DNS interne explicitement :

```powershell
Resolve-DnsName app.demo.example -Type A -DnsOnly
Resolve-DnsName app.demo.example -Server dns.demo.example -Type A -DnsOnly
nslookup app.demo.example
```

4. Distinguer les cas. `NXDOMAIN` indique que le résolveur consulté déclare le nom inexistant ; vérifier la zone et l'orthographe. Une expiration indique une absence de réponse, compatible avec un filtrage ou un serveur indisponible. Une adresse différente selon le poste ou le résolveur peut indiquer un cache périmé, une vue DNS différente ou une modification incomplète.
5. Si le nom complet fonctionne et le nom court échoue, contrôler le suffixe de recherche. Si DNS réussit, poursuivre au niveau du service :

```powershell
Test-NetConnection app.demo.example -Port 443
```

Un port fermé ne justifie pas une modification DNS. Un ping refusé ne suffit pas non plus à déclarer le serveur indisponible.

## Correction et retour arrière

Après confirmation que l'enregistrement de référence est correct, purger uniquement le cache local avec `Clear-DnsClientCache`, puis refaire la requête. Si une configuration DNS manuelle erronée est démontrée, faire appliquer la configuration approuvée et conserver les valeurs précédentes pour restauration. Pour un problème de zone, transmettre à l'équipe DNS plutôt que modifier plusieurs postes. Ne pas ajouter une entrée permanente dans `hosts` pour masquer le défaut et ne pas utiliser un résolveur public pour une zone interne.

## Validation et escalade

Vérifier deux résolutions successives et l'ouverture de l'application avec son nom habituel. Documenter les adresses obtenues et le serveur interrogé. Si plusieurs postes sont concernés, escalader avec le nom complet, le segment réseau, l'heure, le résultat exact et la comparaison avec un poste sain. Exclure les captures contenant des noms ou informations de production inutiles au diagnostic.

## Référence technique

[Microsoft : diagnostic du client DNS](https://learn.microsoft.com/windows-server/networking/dns/troubleshoot/troubleshoot-dns-client).
