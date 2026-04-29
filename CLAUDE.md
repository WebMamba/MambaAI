# Mamba AI — Framework

## Vision & Narrative

**mambaAI, c'est ta bande de copains IA.**

L'idée : plutôt que d'avoir un assistant généraliste qui fait tout en même temps, tu construis une **équipe** — chaque membre a son caractère, son domaine, ses capacités. Un dev qui connaît ton code sur le bout des doigts. Un assistant qui gère ton agenda. Un expert métier qui connaît tes process. Ils sont là, toujours disponibles, sur Slack, WhatsApp, en ligne de commande, où tu veux.

Ce qui guide chaque décision du framework :
- **Simple d'abord** — un débutant doit pouvoir créer son premier agent en moins de 5 minutes, sans lire 50 pages de doc
- **Imagé et concret** — on parle d'agents, de personnalités, de mémoire, de skills — pas de "processors", "transformers" ou "orchestrators"
- **Extensible ensuite** — un développeur expérimenté doit pouvoir aller aussi loin qu'il veut : tools custom, channels sur mesure, stratégies mémoire avancées

Cette narrative doit irriguer tout : les noms de commandes, les messages d'erreur, l'onboarding, la doc.

---

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
    config.yaml    — modèle, stream, provider, memory, exclude_parts
    AGENT.md       — system prompt
    SOUL.md        — personnalité / ton (optionnel)
    knowledge/     — fichiers de connaissance (optionnel)
    skills/        — fichiers .md de skills (optionnel)
    tools/         — fichiers .php de tools (optionnel, #[AsTool])
    memory/
      MEMORY.md    — mémoire persistante (écrite par le LLM via memory_write)
      history.jsonl — historique des échanges (géré par le framework)
```

`AgentLoader` scanne `agents/` avec `Symfony\Component\Finder\Finder`, détecte les sous-dossiers de profondeur 0, et délègue la construction à `AgentBuilderInterface::build(name, path)`.

`AgentResolver` charge tous les agents de façon **lazy** (au premier appel de `resolve()`) et résout par nom depuis `Message::$agent`, avec fallback sur `'default'`.

### Prompt construction — `PromptBuilder`

Le `PromptBuilder` délègue à des **parts taguées** (`mamba_ai.system_prompt_part` / `mamba_ai.user_prompt_part`). Chaque part implémente `SystemPromptPartInterface` ou `UserPromptPartInterface` et déclare :
- `getTargetAgent(): ?string` — `null` = s'applique à tous les agents, sinon nom de l'agent ciblé
- `getContent()` / `getBlocks()` — retourne le contenu, ou `null` pour être ignorée

Le builder filtre à l'exécution : il exclut les parts dont le nom court figure dans `$agent->excludedParts`.

**System prompt** (par ordre de priorité décroissante) :

| Priorité | Part | Contenu |
|---|---|---|
| 300 | `AgentSystemPart` | `AGENT.md` |
| 200 | `SoulSystemPart` | `SOUL.md` |
| 100 | `KnowledgeSystemPart` | Listing du dossier `knowledge/` |
| 75 | `MemorySystemPart` | Contenu de `memory/MEMORY.md` |
| 60 | `ConversationHistoryPart` | Derniers échanges depuis `memory/history.jsonl` |
| 50 | `SkillsSystemPart` | Liste des skills disponibles |
| 25 | `MemoryInstructionSystemPart` | Instructions au LLM pour mémoriser |

**User message** (par ordre de priorité décroissante) :

| Priorité | Part | Contenu |
|---|---|---|
| 200 | `MessageContentPart` | Contenu du message utilisateur |
| 100 | `CurrentDatePart` | Date/heure courante |

**Options** : `['stream' => $agent->stream]`, extensibles via `BuildOptionPrompt` event.

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
| `TerminateEvent` | `array $answers`, `Agent`, `Message` | Persistance mémoire, analytics |

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

### Mémoire — `memory/`

Activée par défaut (`memory: true` dans `config.yaml`). Quand activée :

- **`MemoryWriteTool`** : outil injecté automatiquement dans les tools de l'agent. Le LLM peut écrire la mémoire complète mise à jour dans `memory/MEMORY.md`.
- **`ConversationHistoryListener`** : listener sur `TerminateEvent`, append chaque échange dans `memory/history.jsonl` (format JSONL : `{role, content, at}`). Garde les 100 dernières entrées (50 échanges).
- Le dossier `memory/` est créé automatiquement au premier échange.

Pour désactiver :
```yaml
# agents/default/config.yaml
memory: false
```

### Skills — `skills/`

Fichiers `.md` dans `agents/{name}/skills/`, un fichier par skill. Le nom du fichier (sans extension) est le nom du skill. Injectés dans le system prompt via `SkillsSystemPart`.

### Tools — `tools/`

Fichiers `.php` dans `agents/{name}/tools/`, découverts via `get_declared_classes()` diff + `require_once`. Seules les classes avec `#[AsTool]` sont enregistrées. Instanciées directement (`new $className()`).

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
MambaAi\AgentBuilderInterface: '@App\MonBuilderCustom'
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

En mode `stream: true`, les erreurs HTTP de l'API Anthropic (400, 404…) produisent un flux SSE vide : le générateur ne yield rien, aucune exception n'est levée. Le `CliChannel` détecte ce cas dans `finalize()` (via `$hasSentContent`) et affiche un message d'aide. Pour voir l'erreur brute, forcer `stream: false` dans `config.yaml`.

---

## Commandes CLI

| Commande | Description |
|---|---|
| `mamba:welcome` | Point d'entrée pour un nouvel utilisateur — lance `mamba:setup` si besoin, puis crée l'agent Mambi dans `agents/mambi/` |
| `mamba:setup` | Configure le provider, la clé API et le modèle par défaut. Écrit `.env.local` et `config/packages/mamba_ai.yaml` |
| `mamba:agent:create <name>` | Scaffold un nouvel agent : crée `agents/<name>/` avec AGENT.md, SOUL.md, config.yaml, et les sous-dossiers |
| `mamba:chat` | Session de chat interactive (boucle jusqu'à Ctrl+C). Option `--agent=<name>` pour cibler un agent (défaut : `default`). Accepte aussi un message en argument pour un usage one-shot |

### mamba:chat — mode interactif vs one-shot

```bash
# Mode interactif (boucle)
php bin/console mamba:chat --agent=mambi

# Mode one-shot (single message, quitte après)
php bin/console mamba:chat --agent=mambi "Bonjour !"
```

### Gestion des erreurs silencieuses du streaming

En mode `stream: true`, les erreurs API ne lèvent pas d'exception — elles produisent un flux vide. Le `CliChannel` détecte cette situation dans `finalize()` et affiche un message d'aide. Pour voir l'erreur brute, passer `stream: false` dans le `config.yaml` de l'agent.

---

## Mambi — l'agent de démarrage

Mambi est l'agent livré avec le framework via `mamba:welcome`. Il est copié dans `agents/mambi/` du projet et peut être modifié librement.

**Fichiers** : `src/Resources/agent-templates/mambi/`

**Ce qu'il a de spécial** :
- Son `AGENT.md` contient la doc du framework et des exemples concrets
- Son `SOUL.md` lui donne un ton accueillant et pédagogue
- Il a accès à un `BashTool` (`tools/BashTool.php`) qui lui permet d'exécuter des commandes shell — et donc de créer des fichiers et dossiers d'agents directement

**Attention** : le `BashTool` donne un accès shell complet. Ne pas l'inclure dans des agents exposés à des utilisateurs externes.

### BashTool

Outil PHP avec `#[AsTool]` qui exécute une commande shell via `proc_open` et retourne stdout + stderr. Timeout configurable (défaut 30s). Utilisé par Mambi pour créer la structure des agents à la demande.

---

## Templates d'agents

Les templates sont dans `src/Resources/agent-templates/` :

| Dossier | Usage |
|---|---|
| `mambi/` | Template de l'agent guide, copié par `mamba:welcome` |
| `default/` | Template de base pour `mamba:agent:create`, avec placeholders `{{name}}` |

---

## Symfony TUI

`symfony/tui` (`8.1.x-dev`) est déjà dans `composer.json`. C'est un composant Symfony pour construire des interfaces CLI riches et interactives.

### Widgets disponibles
- **TextWidget** — texte statique, supporte les polices FIGlet (`font: 'big'`, `'small'`, `'slant'`…)
- **InputWidget** — champ texte monoligne avec curseur, prompt configurable (`setPrompt()`)
- **EditorWidget** — éditeur multiligne avec undo/redo, autocomplete
- **SelectListWidget** — liste de sélection scrollable avec filtre
- **LoaderWidget** / **CancellableLoaderWidget** — spinners animés (8 styles)
- **ProgressBarWidget** — barre de progression déterminée/indéterminée
- **MarkdownWidget** — rendu Markdown avec syntax highlighting
- **ContainerWidget** — layout vertical/horizontal avec gap, padding, border

### Styling
- Système CSS-like : `StyleSheet`, classes utilitaires Tailwind (`TailwindStylesheet`), inline `Style`
- Couleurs : ANSI nommées, 256 palette, hex/RGB true color (`Color::hex('#fff')`)
- Borders : `Border::all(1, 'rounded')`, patterns : normal, rounded, double, tall, wide
- Propriétés : `bold`, `dim`, `italic`, `underline`, `padding`, `gap`, `flex`, `align`, `verticalAlign`

### API principale
```php
$tui = new Tui();
$tui->add($widget);
$tui->setFocus($widget);
$tui->quitOn('ctrl+c');
$tui->run();     // bloque jusqu'à stop()
$tui->stop();    // libère le terminal
$tui->onTick(fn() => ...);  // hook sur chaque frame (pour travail async)
```
`$tui->run()` peut être appelé plusieurs fois sur la même instance.

### Contrainte avec le streaming agent
`CliChannel::send()` fait `echo` directement. Le TUI contrôle le terminal — on ne peut pas `echo` pendant que TUI tourne. Pattern à utiliser : **toggle** — `$tui->stop()` avant le streaming, `$tui->run()` après. Le streaming s'affiche normalement entre les deux sessions TUI.

### Ce qui est prévu
Refonte des 4 commandes CLI :
- **`mamba:chat`** — header TUI + `InputWidget` pour le prompt, streaming entre les sessions TUI
- **`mamba:setup`** — wizard en 3 étapes avec `SelectListWidget` (provider, modèle) + `InputWidget` (clé API)
- **`mamba:welcome`** — bannière ASCII art avec `TextWidget` (font FIGlet `big`)
- **`mamba:agent:create`** — rendre l'argument `name` optionnel, `InputWidget` si absent

---

## Ce qui reste à implémenter

### Channels supplémentaires
Seul `CliChannel` est implémenté.
- **Slack** : `SlackChannel` + `SlackController` (endpoint `/slack/events`). Routing agent = nom du canal Slack. Contrainte : répondre en < 3s (utiliser `fastcgi_finish_request()`). Voir plan en cours.
- HTTP/webhook, Discord : à venir.

### Configuration LLM fine
`config.yaml` d'un agent ne supporte pas encore :
- `temperature`
- `max_tokens`
- `thinking` (extended thinking / budget_tokens pour Sonnet)
- `tool_choice`

### Prompt caching
Ajouter `cache_control: { type: 'ephemeral' }` sur les blocs système stables (system prompt, knowledge) pour réduire les coûts. Disponible via le header beta Anthropic `prompt-caching-2024-07-31`.

### Contexte environnement avancé
- Répertoire de travail courant (`cwd`)
- Statut git (`git status`, fichiers modifiés)
- Structure du projet / README.md
Utile pour les agents de développement. À implémenter comme des `UserPromptPartInterface` ciblées (`getTargetAgent()` retourne le nom de l'agent concerné).

### Slack
Pour slack on va commencer simplement, avec un bot plusieurs canaux.
Si slack exige une réponse de 3s ce qu'on peut faire c'est qu'on a un controller qui renvoie tout de suite
la réponse à slack, puis dans un message messenger transmet la request qui sera handle par le KernelAgent
en asynchrone. Quand le kernel a fini de traiter la request on envoie la reponse dans Slack.
