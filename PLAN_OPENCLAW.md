# Gap Analysis : OpenClaw vs mambaAI

## Context

OpenClaw est un framework TypeScript multi-canaux (WhatsApp, Telegram, Slack, Discord, Signal, etc.) beaucoup plus mature que mambaAI. Ce document liste les features identifiées dans OpenClaw qui ne sont pas encore dans mambaAI, et qui ne figuraient pas non plus dans PLAN.md (issu de l'analyse Claude Code).

---

## Priorité haute

**1. Prompt cache boundary (sections statiques vs dynamiques)**
- OpenClaw marque explicitement chaque section du prompt comme "au-dessus" ou "en-dessous" de la limite de cache
- Sections statiques (AGENT.md, SOUL.md, skills) → toujours en cache, jamais régénérées
- Sections dynamiques (date, historique) → sous la limite, exclues du cache
- Sans cette séparation, la moindre modification d'une section dynamique invalide tout le cache du system prompt
- Fichier de référence : `src/agents/system-prompt-cache-boundary.ts`
- À implémenter : tagger chaque `SystemPromptPartInterface` avec un enum `CacheBoundary::ABOVE | BELOW`, assembler dans cet ordre

**2. Model fallover chain**
- Si le modèle primaire échoue (rate limit, erreur 500, model unavailable), tente le suivant dans une chaîne de fallback
- La raison du fallback est enregistrée dans le contexte de session
- Fichier de référence : `src/agents/model-fallback.ts`
- À implémenter dans `config.yaml` : `fallback_models: [claude-3-haiku-20240307, claude-3-5-sonnet-20241022]`

**3. Multi-provider (pas seulement Anthropic)**
- OpenClaw supporte : Claude, OpenAI (GPT-4o), Vertex AI, AWS Bedrock, Ollama (local), OpenRouter
- mambaAI est couplé à Anthropic via `symfony/ai` — le bundle supporte d'autres providers mais le framework n'expose pas ce choix facilement par agent
- À implémenter : `provider: openai` ou `provider: ollama` dans `config.yaml`, avec résolution dynamique de la plateforme dans `FolderAgentBuilder`

---

## Priorité moyenne

**4. Transcript repair & normalization**
- Répare automatiquement les transcripts malformés : appels d'outils sans réponse, réponses sans appel correspondant, tool results trop longs tronqués
- Critique pour la robustesse en production (crashes, timeouts interrompant un échange)
- Fichier de référence : `src/agents/session-transcript-repair.ts`
- À implémenter : validation et réparation du `history.jsonl` au chargement dans `ConversationHistoryPart`

**5. Context engine pluggable (interface complète)**
- OpenClaw définit un contrat `ContextEngine` complet : `bootstrap`, `maintain`, `ingest`, `assemble`, `compact`, `afterTurn`
- Permet des stratégies avancées : retrieval sémantique, pruning sélectif, résumés partiels, mémoire vectorielle
- mambaAI a une assemblage statique du prompt — impossible de brancher un moteur de mémoire externe
- À implémenter : `ContextEngineInterface` avec implémentation par défaut (`SimpleWindowEngine`), injectable par agent dans `config.yaml`

**6. Injection des capacités du channel dans le prompt**
- Le system prompt inclut les capacités du canal actif : `inlineButtons`, `react`, `edit`, `unsend`, etc.
- L'agent adapte ses réponses en fonction (ex : propose des boutons si le canal les supporte)
- Fichier de référence : `src/agents/system-prompt-params.ts`
- À implémenter : `ChannelCapabilitiesSystemPart` qui lit `Message::$context['channel_capabilities']`

**7. Thinking levels configurables**
- Dépasse le simple `thinking: enabled/disabled`
- Niveaux : `off`, `low`, `medium`, `high`, `max`, `deepresearch`
- Chaque niveau correspond à un `budget_tokens` différent et des instructions prompt adaptées
- À étendre dans `config.yaml` : `thinking_level: medium` au lieu d'un booléen

**8. Execution bias injection**
- Section courte dans le system prompt qui instruit l'agent de commencer à travailler immédiatement plutôt que de planifier longuement
- Réduit les réponses introductives inutiles
- Fichier de référence : `src/agents/system-prompt.ts` (section "Execution Bias")
- À ajouter comme part de faible priorité (~10) dans `PromptBuilder`

---

## Priorité basse

**9. Multi-modal (image, audio)**
- Analyse d'images, génération d'images (via provider configurable), TTS/STT
- Fichier de référence : `src/tools/image.ts`, `src/tools/image_generate.ts`
- Dépend de providers externes (ElevenLabs TTS, DALL-E génération, etc.)
- mambaAI peut déjà le faire via tools custom (`#[AsTool]`) — l'architecture le supporte

**10. Session JSONL append-only (pas de réécriture)**
- OpenClaw n'écrit jamais l'historique complet, seulement des appends — permet de brancher à n'importe quel point
- mambaAI réécrit `history.jsonl` complet à chaque trim → risque de corruption si crash pendant l'écriture
- À implémenter : écriture append-only dans `ConversationHistoryListener`, trim au chargement uniquement

**11. Bootstrap budget tracking**
- Surveille combien de tokens les fichiers de contexte (AGENT.md, SOUL.md, knowledge/) consomment
- Avertit si un fichier dépasse un seuil configurable
- Évite les surprises de coût dues à des fichiers de contexte qui grossissent silencieusement
- Fichier de référence : `src/agents/bootstrap-budget.ts`

---

## Résumé des priorités combinées (PLAN.md + ce fichier)

| # | Feature | Source | Priorité |
|---|---|---|---|
| 1 | Bloc environnement complet (cwd, platform, modèle) | Claude Code | Haute |
| 2 | Git status détaillé | Claude Code | Haute |
| 3 | Arborescence du projet | Claude Code | Haute |
| 4 | CLAUDE.md récursif / code style | Claude Code | Haute |
| 5 | README.md injection | Claude Code | Haute |
| 6 | Prompt cache boundary statique/dynamique | OpenClaw | Haute |
| 7 | Model fallover chain | OpenClaw | Haute |
| 8 | Multi-provider (OpenAI, Ollama…) | OpenClaw | Haute |
| 9 | Contenu knowledge/ (pas juste listing) | Claude Code | Moyenne |
| 10 | Conversation compaction | Claude Code | Moyenne |
| 11 | Paramétrage modèle (temperature, max_tokens) | Claude Code | Moyenne |
| 12 | Context custom utilisateur | Claude Code | Moyenne |
| 13 | Transcript repair & normalization | OpenClaw | Moyenne |
| 14 | Context engine pluggable | OpenClaw | Moyenne |
| 15 | Capacités channel dans le prompt | OpenClaw | Moyenne |
| 16 | Thinking levels (off/low/medium/high/max) | OpenClaw | Moyenne |
| 17 | Execution bias injection | OpenClaw | Moyenne |
| 18 | Prompt caching (cache_control) | Claude Code | Basse |
| 19 | Permission model pour les outils | Claude Code | Basse |
| 20 | Extended thinking / budget_tokens | Claude Code | Basse |
| 21 | Metadata / session tracking | Claude Code | Basse |
| 22 | Sub-agent prompt séparé | Claude Code | Basse |
| 23 | Multi-modal (image, audio) | OpenClaw | Basse |
| 24 | Session JSONL append-only | OpenClaw | Basse |
| 25 | Bootstrap budget tracking | OpenClaw | Basse |
