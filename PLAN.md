# Plan pour la construction du projet

## Résolution des agents

Je voudrais que que quelqu'un qui utilise le framework puisse simplement faire un dossier
qui contiendra tout ce dont on a besoin pour construire l'agent.
A partir du moment ou un fichier est mis dans le dossier de l'agent il sera utilisé dans la constrution de l'agent.

On peut imaginer que la resolution ce fait à partir du nom du dossier. 

Le dossier pourra contenir un fichier de configuration en yaml, qui permettra de définir le nom de l'agent,
le model qu'il utilise, si c'est du streaming, les channels sur lequel il est disponible, etc.

Le dossier pourra aussi contenir un fichier AGENT.md qui permettra de definir le prompt système de base le role de l'agent en lui même.

Le dossier pourra aussi contenir un fichier SOUL.md qui permettra de definir la personnalité de l'agent, son style de communication, etc.

Le dossier pourra contenir un dossier Tools qui contiendra tout les tools auquel l'agent aura accès

Le dossier pourra contenir un dossier Skills qui contiendra les skills de l'agent.

Le dossier pourra contenir un dossier knowledge la structure de ce dossier sera envoyé au prompt pas le contenu.

