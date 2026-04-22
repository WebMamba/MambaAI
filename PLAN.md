# Gap Analysis : Prompt Claude Code vs mambaAI

## Context

L'objectif est de comparer tous les composants qui constituent le prompt dans Claude Code (source disponible dans `claude-code-source/`) avec ce qui est déjà implémenté dans le framework mambaAI, afin d'identifier ce qui reste à implémenter.

---

## Ce que Claude Code injecte dans le prompt

### Bloc system prompt (assemblé dans cet ordre)

1. **Prefix CLI** — `"You are Claude Code, Anthropic's official CLI for Claude."`
2. **Instructions principales** — sécurité, slash commands, mémoire, ton, verbosité, conventions de code, utilisation des outils
3. **Bloc `<env>`** — contexte d'exécution :
   - Working directory (`getCwd()`)
   - Is git repo (oui/non)
   - Platform (darwin, linux, etc.)
   - Date du jour
   - Modèle utilisé
4. **Instructions sécurité répétées** (anti-malware, renforcé deux fois)

### Contexte additionnel (injecté comme `<context name="...">`)

5. **`codeStyle`** — lecture des `CLAUDE.md` depuis le répertoire courant jusqu'à la racine FS (remontée récursive)
6. **`directoryStructure`** — snapshot de l'arborescence du projet (via LSTool, memoized)
7. **`gitStatus`** — état git détaillé : branche courante, branche main, output `git status`, 5 derniers commits, 5 derniers commits de l'utilisateur
8. **`claudeFiles`** — liste de tous les `CLAUDE.md` dans les sous-dossiers (chemins seulement, pour awareness)
9. **`readme`** — contenu intégral du `README.md` de la racine
10. **`projectConfig.context`** — paires clé/valeur custom définies par l'utilisateur dans `.claude/config.json`

### Mécanique des outils

11. **Tool definitions** — JSON schema généré depuis Zod pour chaque outil, avec name + description + input_schema
12. **Permission model par outil** — `isReadOnly()`, `needsPermissions()`, `isEnabled()` — demande de permission utilisateur à l'exécution
13. **Tool results normalization** — fusion des tool_result consécutifs, filtrage des messages "progress" (UI-only)

### Optimisation et gestion de contexte

14. **Prompt caching** — `cache_control: { type: 'ephemeral' }` sur chaque bloc système + cache breakpoints sur les 3 derniers messages
15. **Conversation compaction** (`/compact`) — résumé LLM de la conversation, reset de l'historique, injection du résumé comme nouveau contexte
16. **Message normalization** — filtrage des progress messages, merge des tool_result adjacents avant envoi API

### Paramétrage fin du modèle

17. **Temperature** — fixée à 1.0 (`MAIN_QUERY_TEMPERATURE`)
18. **Max tokens** — calculé dynamiquement selon le modèle + thinking budget
19. **Extended thinking** — `thinking: { type: 'enabled', budget_tokens: N }` (ant-only)
20. **Beta features** — `betas` array (ex: extended prompt caching)
21. **Metadata** — `user_id` + session ID envoyés à chaque requête

### Sub-agent

22. **Agent sub-prompt** — prompt système séparé et allégé pour l'outil Agent : concision, chemins absolus, pas d'actions destructives

---

## Ce que mambaAI implémente déjà

| Composant | Fichier(s) | Statut |
|---|---|---|
| AGENT.md (instructions agent) | `AgentSystemPart` | ✅ |
| SOUL.md (personnalité/ton) | `SoulSystemPart` | ✅ |
| Listing knowledge/ | `KnowledgeSystemPart` | ✅ (listing seulement, pas contenu) |
| Mémoire persistante (MEMORY.md) | `MemorySystemPart` + `MemoryWriteTool` | ✅ |
| Historique conversation (history.jsonl) | `ConversationHistoryPart` + listener | ✅ (100 entrées max) |
| Liste des skills | `SkillsSystemPart` | ✅ |
| Instructions memory_write | `MemoryInstructionSystemPart` | ✅ |
| Date/heure courante | `CurrentDatePart` | ✅ (partiel — pas de platform, cwd, modèle) |
| Outils custom (AsTool) | `FolderAgentBuilder` + `Toolbox` | ✅ |
| Streaming | `Agent::call()` | ✅ |
| Multi-agents + routing | `AgentResolver` | ✅ |
| Canaux (CLI, Slack, HTTP) | `ChannelInterface` | ✅ |
| Event pipeline extensible | `AgentKernel` + 8 events | ✅ |

---

## Ce qui reste à implémenter dans mambaAI

### Priorité haute (impact direct sur la qualité des réponses)

**1. Bloc environnement complet**
- Ajouter working directory, plateforme OS, nom du modèle utilisé dans `CurrentDatePart` ou un nouveau `EnvironmentSystemPart`
- Fichier à créer : `src/Prompt/Part/EnvironmentSystemPart.php`

**2. Git status détaillé**
- Nouveau `GitStatusSystemPart` qui injecte : branche courante, main branch, output `git status` (tronqué), 5 derniers commits
- Configurable via `exclude_parts: [GitStatusSystemPart]` pour les projets non-git
- Fichier à créer : `src/Prompt/Part/GitStatusSystemPart.php`

**3. Arborescence du projet (directory structure)**
- Snapshot de l'arborescence au démarrage de la conversation, injecté en contexte
- Nouveau `DirectoryStructureSystemPart`
- Fichier à créer : `src/Prompt/Part/DirectoryStructureSystemPart.php`

**4. Lecture récursive des CLAUDE.md / style files**
- Remonter depuis `cwd` jusqu'à la racine FS pour trouver des `CLAUDE.md`
- Injecter leur contenu comme "code style" dans le system prompt
- Fichier à créer : `src/Prompt/Part/CodeStyleSystemPart.php`

**5. README.md injection**
- Lire et injecter le README.md du projet courant
- Fichier à créer : `src/Prompt/Part/ReadmeSystemPart.php`

### Priorité moyenne (amélioration de la qualité)

**6. Contenu des fichiers knowledge/ (pas juste la liste)**
- `KnowledgeSystemPart` liste les fichiers mais n'injecte pas leur contenu
- Option : injecter le contenu des fichiers `.md` importants (ou les plus petits)

**7. Conversation compaction**
- Équivalent du `/compact` : résumer l'historique quand il dépasse N échanges
- Déclenché automatiquement ou via skill/commande
- Fichier à modifier : `src/EventListener/ConversationHistoryListener.php` (ou nouveau listener)

**8. Paramétrage modèle dans config.yaml**
- `temperature`, `max_tokens`, `tool_choice` non configurables aujourd'hui
- Fichier à modifier : `src/FolderAgentBuilder.php` + `src/Agent.php`

**9. Context custom utilisateur (comme projectConfig.context)**
- Permettre aux agents d'avoir des paires clé/valeur custom dans config.yaml, injectées dans le prompt
- Fichier à modifier : `src/FolderAgentBuilder.php` + nouveau `CustomContextSystemPart`

### Priorité basse (optimisation / avancé)

**10. Prompt caching**
- Ajouter `cache_control: ephemeral` sur les blocs système dans la couche Symfony AI
- Dépend du support dans `symfony/ai` — à vérifier

**11. Permission model pour les outils**
- `isReadOnly()`, `needsPermissions()` sur les outils custom
- Demande de confirmation utilisateur via canal avant exécution des outils destructifs

**12. Extended thinking / budget_tokens**
- Configurable dans `config.yaml` : `thinking: { enabled: true, budget_tokens: 10000 }`

**13. Metadata / session tracking**
- `user_id` et `session_id` envoyés à chaque requête pour analytics

**14. Sub-agent prompt séparé**
- Si un outil "lance un sous-agent", lui donner un system prompt allégé distinct

---

## Fichiers clés à modifier/créer

| Action | Fichier |
|---|---|
| Créer | `src/Prompt/Part/EnvironmentSystemPart.php` |
| Créer | `src/Prompt/Part/GitStatusSystemPart.php` |
| Créer | `src/Prompt/Part/DirectoryStructureSystemPart.php` |
| Créer | `src/Prompt/Part/CodeStyleSystemPart.php` |
| Créer | `src/Prompt/Part/ReadmeSystemPart.php` |
| Modifier | `src/FolderAgentBuilder.php` (temperature, max_tokens, custom context) |
| Modifier | `src/Agent.php` (passer les nouveaux params modèle) |
| Modifier | `config/services.yaml` (tagger les nouveaux parts) |
| Modifier | `src/EventListener/ConversationHistoryListener.php` (compaction optionnelle) |

---

## Vérification

Pour tester les changements :
1. Créer un agent de test, lancer `php bin/console mamba:chat {agent}`, vérifier que le system prompt contient les nouveaux blocs
2. Dans un repo git, vérifier que le git status apparaît dans le contexte
3. Placer un `CLAUDE.md` dans le répertoire courant, vérifier qu'il est injecté
4. Vérifier que `exclude_parts` dans config.yaml permet de désactiver chaque nouveau part
