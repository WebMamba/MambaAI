# Skill: Create an agent

To create a new agent in mambaAI, use the command:

```bash
php bin/console mamba:agent:create <name>
```

This automatically generates the `agents/<name>/` folder with all the required files.

You can then customize:
- `AGENT.md` — give it its role and instructions
- `SOUL.md` — give it a personality
- `config.yaml` — pick its model and options

And start chatting with it:
```bash
php bin/console mamba:chat <name>
```
