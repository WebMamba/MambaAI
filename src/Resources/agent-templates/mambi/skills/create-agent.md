# Skill : Créer un agent

Pour créer un nouvel agent dans mambaAI, utilise la commande :

```bash
php bin/console mamba:agent:create <nom>
```

Cela génère automatiquement le dossier `agents/<nom>/` avec tous les fichiers nécessaires.

Ensuite tu peux personnaliser :
- `AGENT.md` — donne-lui son rôle et ses instructions
- `SOUL.md` — donne-lui une personnalité
- `config.yaml` — choisis son modèle et ses options

Et discuter avec lui :
```bash
php bin/console mamba:chat <nom>
```
