# PLAN — mambaAI ↔ Claude Code parity

## Contexte

Reproduire dans mambaAI le payload qu'envoie Claude Code à l'API Anthropic, pour que les agents construits avec le framework aient la même qualité de contexte qu'un agent Claude Code.

Source de référence : `claude-code-source/` (TypeScript non-minifié, ère 3.7).
Cible : framework mambaAI (`src/`).

---

## Référence : ce que Claude Code envoie

Toutes citations dans `claude-code-source/`.

### Payload `messages.create` (`src/services/claude.ts:516`)

```js
{
  model,
  max_tokens,                // 20000 pour 3-7
  temperature: 1,            // MAIN_QUERY_TEMPERATURE
  system: [TextBlockParam, …],
  tools: [...toolSchemas],
  messages: [...withCacheBreakpoints],
  betas: ['claude-code-20250219', …],
  metadata: { user_id: `${userID}_${SESSION_ID}` },
  thinking: { budget_tokens, type: 'enabled' }, // si USER_TYPE=ant
}
```

### Bloc `system` (concaténation, chaque bloc avec `cache_control: ephemeral`)

| Ordre | Source | Contenu |
|---|---|---|
| 1 | `prompts.ts` | Préfixe CLI : `"You are Claude Code, Anthropic's official CLI for Claude."` |
| 2 | `prompts.ts:18-122` | Bloc principal (rôle, slash commands, mémoire, ton, tool usage) |
| 3 | `prompts.ts:124-125` | Refus malware (court) |
| 4 | `prompts.ts:129-142` (`getEnvInfo()`) | Bloc XML `<env>` : Working directory, Is git repo, Platform, Today's date, Model |
| 5 | `claude.ts:426-441` (`formatSystemPromptWithContext`) | Une `<context name="…">…</context>` par clé : `directoryStructure`, `gitStatus`, `codeStyle`, `claudeFiles`, `readme`, `projectConfig.context` |

### Tools (`src/tools.ts:23-38`)

`AgentTool`, `BashTool`, `GlobTool`, `GrepTool`, `LSTool`, `FileReadTool`, `FileEditTool`, `FileWriteTool`, `NotebookReadTool`, `NotebookEditTool`, `ThinkTool`, `MemoryReadTool`, `MemoryWriteTool` (ant), `StickerRequestTool` + `getMCPTools()`. Schémas envoyés intégralement à chaque tour (pas de deferred).

### Messages (`utils/messages.tsx:772`)

- Normalisation : fusion `tool_result` consécutifs.
- `cache_control: ephemeral` uniquement sur les **2 derniers messages** (`claude.ts:642`).
- Tags spéciaux dans le user message : `<command-name>`, `<command-args>`, `<command-contents>`, `<bash-input>`.

### Sub-agent (`tools/AgentTool/AgentTool.tsx`)

- System prompt distinct via `getAgentPrompt()` (`prompts.ts:144`)
- Tools : tous sauf `AgentTool` lui-même, filtrés read-only sans permissions
- Stateless (pas de contexte) — retourne le dernier assistant message

---

## État actuel mambaAI

### Déjà implémenté

| Composant | Fichier | Notes |
|---|---|---|
| AGENT.md | `Prompt/Part/AgentSystemPart.php` (300) | ✅ |
| SOUL.md | `Prompt/Part/SoulSystemPart.php` (200) | ✅ |
| Listing knowledge/ | `Prompt/Part/KnowledgeSystemPart.php` (100) | listing seul, pas le contenu |
| MEMORY.md | `Prompt/Part/MemorySystemPart.php` (75) + `Tool/MemoryWriteTool.php` | ✅ |
| Historique | `Prompt/Part/ConversationHistoryPart.php` (60) + listener | 100 entrées max |
| Skills listing | `Prompt/Part/SkillsSystemPart.php` (50) | wrappé `<system-reminder>` |
| Memory write instructions | `Prompt/Part/MemoryInstructionSystemPart.php` (25) | ✅ |
| Date/heure | `Prompt/Part/CurrentDatePart.php` (20) | partiel — manque cwd, OS, modèle |
| Outils custom | `FolderAgentBuilder.php:74-95` | discovery `#[AsTool]` dans `agents/{name}/tools/` |
| Streaming | `Agent.php` | ✅ |
| Routing multi-agent | `AgentResolver.php` | ✅ |
| Channels (CLI, Slack, HTTP, TUI) | `Channel/*` | ✅ |
| Event pipeline | `AgentKernel.php` | 8 events extensibles |

### Manquant (vs Claude Code)

- Bloc `<env>` (cwd, git bool, platform, model)
- `<context name="…">` blocks : gitStatus, directoryStructure, codeStyle (CLAUDE.md remontée), readme, claudeFiles
- Prompt caching (`cache_control: ephemeral`) sur system + 2 derniers messages
- `betas`, `metadata`, `temperature`, `max_tokens`, `thinking.budget_tokens` non configurables
- Toolset built-in (`Read`, `Write`, `Edit`, `Glob`, `Grep`, `Ls`, `Bash`, `Think`)
- `TaskTool` (sub-agent récursif)
- Tool permission model (`isReadOnly()`, `needsPermissions()`)
- Conversation compaction
- Tools comme services (autowiring DI)

---

## Phases d'implémentation

Chaque phase est autonome : finie = projet fonctionnel.

### Phase 1 — Bloc env + contexte projet (add-on, no framework change) ✅ DONE

**Goal** : faire arriver dans le system prompt l'équivalent de `<env>` + `<context name="…">` de Claude Code.

**Scope** : 5 nouveaux services tagués `mamba_ai.system_prompt_part`, signature inchangée (`getContent(): ?string`). Wrapping XML cohérent.

**Files créés**
- `src/Prompt/Part/EnvironmentSystemPart.php` (priorité 90) → bloc `<env>` (working dir, git repo bool, platform, today's date, model)
- `src/Prompt/Part/GitStatusContextPart.php` (priorité 85) → `<context name="gitStatus">` (current branch, main branch, git user, status, recent commits) via `Symfony\Component\Process\Process`
- `src/Prompt/Part/DirectoryStructureContextPart.php` (priorité 80) → `<context name="directoryStructure">` (Finder, depth ≤ 2, exclude `vendor/`, `node_modules/`, `.git/`, `var/`, `cache/`, `build/`, `dist/`)
- `src/Prompt/Part/ProjectInstructionsContextPart.php` (priorité 95) → `<context name="codeStyle">` (contenu de `<projet>/CLAUDE.md` + `~/.claude/CLAUDE.md`)
- `src/Prompt/Part/ReadmeContextPart.php` (priorité 70) → `<context name="readme">` (contenu README.md racine)

**Files modifiés**
- `config/services.yaml` — 5 nouveaux services tagués + `$projectDir: '%kernel.project_dir%'`
- `composer.json` — ajout `symfony/process: ^8.0`

**Tests ajoutés**
- `tests/Unit/Prompt/Part/EnvironmentSystemPartTest.php`
- `tests/Unit/Prompt/Part/GitStatusContextPartTest.php` (init un repo git temp + commit)
- `tests/Unit/Prompt/Part/DirectoryStructureContextPartTest.php`
- `tests/Unit/Prompt/Part/ProjectInstructionsContextPartTest.php`
- `tests/Unit/Prompt/Part/ReadmeContextPartTest.php`

90 tests / 243 assertions, CS clean.

---

### Phase 2 — Params LLM dans `config.yaml` (framework, light)

**Goal** : `temperature`, `max_tokens`, `thinking.budget_tokens`, `tool_choice`, `metadata` lisibles depuis le YAML d'un agent.

**Scope** : champs sur `Agent`, lecture dans `FolderAgentBuilder`, propagation via options.

**Files à modifier**
- `src/Agent.php` — ajouter `temperature: ?float`, `maxTokens: ?int`, `thinkingBudget: ?int`, `toolChoice: ?string`, `metadata: array`
- `src/FolderAgentBuilder.php:34` — lire les nouvelles clés
- `src/DependencyInjection/Configuration.php` — déclarer les options bundle si nécessaire
- `src/Prompt/Part/CurrentDatePart.php` ou `BuildOptionPrompt` listener — propager les options
- Doc : `src/Resources/agent-templates/default/config.yaml`

**Done-when**
- `temperature: 0.5` dans un `config.yaml` arrive bien à `messages.create`
- Idem `max_tokens`, `thinking`

**À vérifier en amont**
- `symfony/ai-anthropic-platform` accepte-t-il ces options dans `$options` ? Si non, escalade en upstream PR ou wrapper custom.

---

### Phase 3 — Prompt caching (framework, structurel)

**Goal** : `cache_control: ephemeral` sur les blocs system stables + 2 derniers messages, beta `prompt-caching-2024-07-31` injecté.

**Scope** : refactor du `Prompt` DTO et de la chaîne d'assemblage. **Ce phase casse les signatures actuelles** des PromptParts.

**Refactor**
- `src/Prompt.php` — `$systemMessages` devient `array<SystemBlock>` où `SystemBlock = { string $text, bool $cacheable }` (ou DTO dédié)
- `src/Prompt/SystemPromptPartInterface.php` — `getContent(): ?string` → `getBlocks(): iterable<SystemBlock>` (ou ajouter `isCacheable(): bool` à côté de `getContent`)
- Toutes les implementations (`*SystemPart`) — ajuster signature
- `src/PromptBuilder.php:78` — au lieu de `implode("\n\n", …)`, émettre un tableau de blocs
- `src/Agent.php::call()` — mapper les blocs vers `TextBlockParam[]` Anthropic avec `cache_control` sur les blocs `cacheable: true`
- Listener sur `BuildOptionPrompt` → injecter `betas: ['prompt-caching-2024-07-31']`

**Cacheable par défaut** : AgentSystemPart, SoulSystemPart, KnowledgeSystemPart, MemorySystemPart (statiques).
**Non-cacheable** : EnvironmentSystemPart, GitStatusContextPart, ConversationHistoryPart, CurrentDatePart (volatils).

**Messages**
- Hook côté `Agent::call()` ou nouveau mapping qui pose `cache_control: ephemeral` sur les 2 dernières entrées de `$prompt->userMessages` / `$messages`.

**Done-when**
- Un tour API contient `cache_control: ephemeral` sur ≥ 1 bloc system
- À 2e tour avec mêmes parts statiques, métriques d'usage montrent `cache_read_input_tokens > 0`

**Risque principal**
- `symfony/ai-anthropic-platform` peut ne pas exposer `cache_control` sur les blocs system. Vérifier avant de coder ; possiblement upstream PR.

---

### Phase 4 — Toolset built-in (add-on + framework léger)

**Goal** : embarquer `Read`, `Write`, `Edit`, `Glob`, `Grep`, `Ls`, `Bash`, `Think` comme outils opt-in.

**Scope** : 8 classes PHP `#[AsTool]` + activation par config + autowiring DI pour les tools.

**Framework change minimal**
- `src/FolderAgentBuilder.php:88` — `new $className()` ne permet pas d'injecter des dépendances. Passer à un mécanisme : tools-services tagués `mamba_ai.tool` récupérés du container, et seulement les tools dans `agents/{name}/tools/*.php` continuent d'être instanciés sans DI.

**Files à créer**
- `src/Tool/Builtin/ReadTool.php`
- `src/Tool/Builtin/WriteTool.php`
- `src/Tool/Builtin/EditTool.php`
- `src/Tool/Builtin/GlobTool.php`
- `src/Tool/Builtin/GrepTool.php`
- `src/Tool/Builtin/LsTool.php`
- `src/Tool/Builtin/BashTool.php` (généralisation du BashTool de Mambi)
- `src/Tool/Builtin/ThinkTool.php`

**Files à modifier**
- `src/FolderAgentBuilder.php` — résolution des builtin tools depuis container
- `src/Agent.php` — champ `builtinTools: array` (liste des noms)
- `config.yaml` template :
  ```yaml
  builtin_tools: [bash, read, edit, write, glob, grep, ls, think]
  ```

**Done-when**
- Un agent avec `builtin_tools: [read, bash]` peut lire un fichier et exécuter une commande shell.

---

### Phase 5 — TaskTool (sub-agents) (framework)

**Goal** : un agent peut invoquer un autre agent via un tool, drainer son iterable et récupérer la réponse comme `tool_result`.

**Files à créer**
- `src/Tool/Builtin/TaskTool.php` — `#[AsTool]`, prend `agent: string` et `prompt: string`, instancie un `Request` synthétique, appelle `AgentKernel::handle()` (DI), agrège les `Message::Text` en string et la retourne.

**Files à modifier**
- `src/Agent.php` — flag `private: bool` (agent non exposé via channels)
- `src/AgentResolver.php` — filtre les agents privés sur la résolution publique, mais permet `resolveByName()` pour le TaskTool
- `src/FolderAgentBuilder.php` — propager `private` depuis `config.yaml`
- Container — `TaskTool` doit recevoir `AgentKernel` (DI obligatoire — dépend de Phase 4 pour le mécanisme tools-services)

**Done-when**
- Un agent A équipé de `TaskTool` peut déléguer à un agent B (privé) et récupérer son output.

---

### Phase 6 — Permissions outils + compaction (avancé)

**Goal** : modèle de permissions par outil + compaction de l'historique.

**Permissions**
- Interface `PermissionedToolInterface { isReadOnly(): bool; needsPermission(array $args): bool }`
- Hook avant exécution (event `BeforeToolCall`) qui prompt l'utilisateur via le channel courant
- `CliChannel` / `TuiChannel` proposent un `confirm(string $question): bool`

**Compaction**
- Listener qui, sur `BuildSystemPrompt`, si `history.jsonl` > N entrées, appelle un LLM résumeur et remplace l'historique par le résumé
- Configurable : `compaction: { threshold: 100, model: claude-haiku-4-5 }`

**Files**
- `src/Tool/PermissionedToolInterface.php` (nouveau)
- `src/Event/BeforeToolCall.php` (nouveau)
- `src/EventListener/CompactionListener.php` (nouveau)
- `src/Channel/*Channel.php` — méthode `confirm()`

---

### Phase 7 — Polish (metadata, hooks, deferred tools)

**Goal** : `metadata: { user_id, session_id }`, hooks `<user-prompt-submit-hook>`, deferred tool loading via `ToolSearch`.

**Scope** : surtout des conventions et un nouveau tool `ToolSearch` qui charge les schémas à la demande quand >50 tools.

---

## Vérification end-to-end

1. **Phase 1** : `php bin/console mamba:chat default` dans un repo git → system prompt contient `<env>` + `<context name="gitStatus">` etc. (vérifier via dump dans un listener `BuildSystemPrompt`).
2. **Phase 2** : `temperature: 0.2` dans `config.yaml` → réponses moins créatives, vérifier en mode `stream: false` que la requête contient bien le param.
3. **Phase 3** : 2e tour, vérifier `cache_read_input_tokens > 0` dans les `usage` retournées (ajouter listener `TerminateEvent` pour logger).
4. **Phase 4** : `builtin_tools: [bash, read]` activé, l'agent exécute `ls` et `cat`.
5. **Phase 5** : agent A délègue à agent B via TaskTool, agent B est privé (pas exposé sur Slack).
6. **Phase 6** : tool destructif demande confirmation avant exécution ; après 100 tours, l'historique est résumé.

---

## Open questions à résoudre avant de coder

1. **Symfony AI** supporte-t-il `cache_control` sur blocs system ? `betas` array ? `metadata` ? `thinking` ? → grep dans `vendor/symfony/ai-anthropic-platform/` ou tester sur un PR sandbox. Bloque Phase 2 et 3.
2. **Tools comme services** : changer `FolderAgentBuilder` pour autowire les tools casse-t-il les agents existants qui dépendent de `new $className()` ? → policy : si la classe a un constructeur sans args, instantiation directe ; sinon, lookup container. Gradual.
3. **Sub-agents privés** : faut-il introduire `agents/_private/` ou juste un flag `private: true` dans `config.yaml` ?
