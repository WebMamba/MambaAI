# Mamba AI — Framework

## But du projet

Mamba AI est un framework PHP pour constituer et déployer une **équipe d'agents IA**, chacun avec sa propre identité et ses propres capacités.

L'idée centrale : au lieu d'avoir un seul agent généraliste, on configure plusieurs agents spécialisés (un expert en code, un assistant RH, un commercial, etc.) qui peuvent être sollicités depuis différentes surfaces de communication.

Pour chaque agent, on peut configurer indépendamment :
- le **modèle LLM** utilisé (OpenAI, Anthropic, Mistral, etc.)
- la **personnalité** (system prompt, ton, comportement)
- les **tools** (fonctions que l'agent peut appeler)
- les **skills** (capacités métier composables)
- la **mémoire** (court terme, long terme, vectorielle)
- et d'autres paramètres propres à l'agent

Les agents sont joignables via des **channels** : Slack, Discord, WhatsApp, ligne de commande, endpoint HTTP, etc. Un même agent peut être accessible sur plusieurs channels simultanément.

Le framework est conçu pour être **déployé dans un projet Symfony existant** (via un bundle) ou **utilisé en standalone** sur un serveur.

---

## Architecture

### Flux de traitement — `AgentKernel`

Tout part d'une `Request` Symfony (HTTP ou synthétique pour CLI/webhooks) et suit un pipeline linéaire entièrement event-driven :

```
Request
  → [RequestEvent]
  → ChannelResolver      — quel channel a reçu ce message ?
  → [ChannelEvent]
  → Channel::receive()   — extrait un Message du Request
  → [MessageEvent]       — hook idéal pour enrichir avec la mémoire
  → AgentResolver        — quel agent doit traiter ce message ?
  → [AgentEvent]
  → PromptBuilder        — construit le Prompt (system + historique + message)
  → [PromptEvent]
  → Agent::call()        — appelle le LLM, retourne un iterable (streaming)
  → Channel::send()      — renvoie chaque chunk via le channel d'origine
  → [TerminateEvent]
```

Chaque étape émet un événement Symfony. Les listeners sur ces événements sont le mécanisme principal d'extension : mémoire, logging, routing, guard, etc., s'ajoutent sans toucher au pipeline central.

### Classes et interfaces principales

| Interface | Implémentation par défaut | Rôle |
|---|---|---|
| — | `AgentKernel` | Orchestre le pipeline complet |
| `ChannelInterface` | `CliChannel` | Contrat d'un channel : `supports()`, `receive()`, `send()`, `finalize()` |
| `ChannelResolverInterface` | `ChannelResolver` | Parcourt les channels enregistrés, retourne le premier qui `supports()` la request |
| `AgentResolverInterface` | `AgentResolver` | Sélectionne l'agent cible à partir du `Message`, avec lazy-loading |
| `AgentLoaderInterface` | `AgentLoader` | Scanne `agentsDir` avec Finder et délègue au builder |
| `AgentBuilderInterface` | `FolderAgentBuilder` | Lit un dossier d'agent et retourne un `Agent` complet |
| `PromptBuilderInterface` | `PromptBuilder` | Assemble le `Prompt` final (system + user + options) |
| — | `Message` | DTO : agent cible (`string $agent`), contenu, contexte |
| — | `Prompt` | Contient `$userMessages`, `$systemMessages`, `$options` |
| — | `Agent` | Encapsule la config d'un agent et son appel LLM (`call()` → iterable) |

### Découverte des agents — `FolderAgentBuilder`

Les agents sont configurés dans des **dossiers** (un dossier par agent) :

```
agents/
  default/
    config.yaml    — modèle, stream, nom
    AGENT.md       — system prompt
    SOUL.md        — personnalité / ton (optionnel)
    knowledge/     — fichiers de connaissance (optionnel)
```

`AgentLoader` scanne `agents/` avec `Symfony\Component\Finder\Finder`, détecte les sous-dossiers de profondeur 0, et délègue la construction à `AgentBuilderInterface::build(name, path)`.

`AgentResolver` charge tous les agents de façon **lazy** (au premier appel de `resolve()`) et résout par nom depuis `Message::$agent`, avec fallback sur `'default'`.

### Prompt construction — `PromptBuilder`

Le `PromptBuilder` assemble :

1. **System prompt** (dans cet ordre, concaténés) :
   - `AGENT.md` (system prompt de l'agent)
   - `SOUL.md` (personnalité, optionnel)
   - Structure du dossier `knowledge/` listée avec `Finder` (si présent)

2. **User message** : un seul `UserMessage` avec plusieurs blocs `Text` :
   - Le contenu du message utilisateur
   - La date/heure courante

3. **Options** : `['stream' => $agent->stream]`, extensibles via `BuildOptionPrompt` event.

Chaque étape émet un événement (`BuildSystemPrompt`, `BuildUserPrompt`, `BuildOptionPrompt`) permettant d'injecter du contexte supplémentaire sans modifier le builder.

### Events du cycle de vie

| Event | Payload | Usage typique |
|---|---|---|
| `RequestEvent` | `Request` | Authentification, normalisation |
| `ChannelEvent` | `ChannelInterface` | Logging, override de channel |
| `MessageEvent` | `Message` | Injection de mémoire, enrichissement de contexte |
| `AgentEvent` | `Agent`, `Message` | Fallback d'agent, routing conditionnel |
| `PromptEvent` | `Prompt`, `Agent`, `Message` | Injection de contexte supplémentaire dans le prompt |
| `BuildSystemPrompt` | `Agent`, `Message`, `MessageBag` | Ajout de blocs dans le system prompt |
| `BuildUserPrompt` | `Agent`, `Message`, `MessageBag` | Ajout de blocs dans le message utilisateur |
| `BuildOptionPrompt` | `Agent`, `Message`, `array $options` | Configuration LLM (temperature, max_tokens, etc.) |
| `TerminateEvent` | `array $answers` | Persistance mémoire, analytics |

### Symfony Bundle — `MambaAiBundle`

Le bundle est configuré via `config/packages/mamba_ai.yaml` :

```yaml
mamba_ai:
    agents_dir: '%kernel.project_dir%/agents'
    default_platform: anthropic.platform
    default_model: claude-3-haiku-20240307
```

`MambaAiExtension::load()` :
- Charge `config/services.yaml` (services de base)
- Définit `FolderAgentBuilder` programmatiquement avec `new Reference($config['default_platform'])` (résolution dynamique de la plateforme)
- Enregistre les 5 aliases d'interface (`AgentBuilderInterface` → `FolderAgentBuilder`, etc.)

---

## Décisions de conception

### Runtime service vs CompilerPass

**Constat** : l'approche initiale utilisait un `CompilerPass` pour découvrir les agents au moment de la compilation du container Symfony. Avantages : erreurs détectées à la compilation, pas de I/O au runtime.

**Problème** : le `CompilerPass` s'exécute avant que l'injection de dépendances soit résolue, ce qui l'empêche d'être lui-même un vrai service. On ne peut pas injecter la plateforme LLM dans le builder via DI.

**Décision** : approche **runtime service**. Le builder est un service Symfony normal qui reçoit ses dépendances (`PlatformInterface`, `$defaultModel`) via le constructeur. La découverte se fait au premier appel. La latence LLM domine largement le coût du scan de dossier.

**Conséquence** : `MambaAiBundle::build()` supprimé, `AgentDiscoveryPass` supprimé.

### Interfaces sur tout ce qui est swappable

Chaque composant principal dispose d'une interface (`AgentBuilderInterface`, `AgentLoaderInterface`, `AgentResolverInterface`, `PromptBuilderInterface`, `ChannelResolverInterface`). L'utilisateur du framework peut surcharger n'importe quel composant dans son `services.yaml` :

```yaml
MambaAi\Version_2\AgentBuilderInterface: '@App\MonBuilderCustom'
```

### Le builder reçoit ses defaults via DI, pas via les méthodes

`FolderAgentBuilder` reçoit `$platform` et `$defaultModel` par le constructeur, injectés par le container Symfony. La méthode `build(name, path)` ne prend que les paramètres spécifiques à l'agent. Cela respecte le SRP et évite de polluer l'interface publique.

### Symfony Finder pour toutes les opérations fichier

`Symfony\Component\Finder\Finder` est utilisé systématiquement (pas de `glob()`, `scandir()`, `file_get_contents()` direct). Raison : API fluente, gestion des erreurs cohérente, tri déterministe, filtrage par type (fichier/dossier, profondeur).

Pattern pour lire un fichier unique optionnel :
```php
$finder = (new Finder())->files()->in($path)->name('AGENT.md')->depth(0);
foreach ($finder as $file) {
    return $file->getContents(); // foreach vide = pas de fichier, pas d'erreur
}
```

### Un seul UserMessage avec plusieurs blocs Text

L'API Anthropic rejette les messages consécutifs de même rôle. `PromptBuilder` construit un seul `UserMessage` avec plusieurs blocs `Text` (contenu + date + autres contextes), plutôt que plusieurs `UserMessage` séparés.

### Modèles : utiliser les versions épinglées

Les alias "latest" (`claude-3-5-sonnet-latest`) peuvent ne pas être reconnus selon la clé API utilisée. Toujours préférer les versions épinglées (`claude-3-haiku-20240307`, `claude-3-7-sonnet-20250219`).

### Le mode streaming masque silencieusement les erreurs API

En mode `stream: true`, les erreurs HTTP de l'API Anthropic (400, 404…) produisent un flux SSE vide : le générateur ne yield rien, aucune exception n'est levée, la commande se termine sans output. Pour débugger, forcer `stream: false` et observer l'exception.

---

## Ce qui reste à implémenter

Analyse basée sur le fonctionnement de Claude Code — ce que le framework n'implémente pas encore :

### Contexte environnement dans le prompt utilisateur
- Date/heure courante : **fait** (`PromptBuilder`)
- Répertoire de travail courant (`cwd`)
- Statut git (`git status`, fichiers modifiés)
- Structure du projet (arborescence, README.md)
- Contenu des fichiers de configuration (CLAUDE.md, etc.)

### Historique multi-tour
Le `PromptBuilder` actuel envoie uniquement le message courant. Il n'y a pas de gestion de l'historique de conversation (messages précédents user/assistant). À implémenter via un listener sur `BuildUserPrompt` ou `MessageEvent`.

### Tools (appels de fonction)
`Agent::call()` ne supporte pas encore les tool calls. Il faudrait :
- Définir des tools sur un agent (via `config.yaml` ou attributs PHP)
- Gérer la boucle tool-call / tool-result dans `AgentKernel` ou dans `Agent::call()`
- Normaliser les messages `tool_use` / `tool_result` pour éviter les orphelins

### Skills
Mécanisme pour associer des "skills" composables à un agent, listés dans le prompt système (`<system-reminder>You have the following skills: ...</system-reminder>`).

### Mémoire
- Court terme : historique de la conversation
- Long terme : persistance entre sessions
- Points d'intégration : `MessageEvent` (injection au départ) + `TerminateEvent` (persistance à la fin)

### Configuration LLM fine
`config.yaml` d'un agent ne supporte pas encore :
- `temperature`
- `max_tokens`
- `thinking` (extended thinking / budget_tokens pour Sonnet)
- `tool_choice`

### Prompt caching
Ajouter `cache_control: { type: 'ephemeral' }` sur les blocs système stables (system prompt, knowledge) pour réduire les coûts. Disponible via le header beta Anthropic `prompt-caching-2024-07-31`.

### Channels supplémentaires
Seul `CliChannel` est implémenté. À venir : HTTP (webhook), Slack, Discord, etc.
