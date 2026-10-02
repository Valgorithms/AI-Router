# VZG Coders AI Router

ReactPHP-based local-first AI model router. Jev provides the routing/evaluation signal; PHP owns orchestration and escalation.

## Model order

By default:

1. Ollama (`gemma4-agent-32k`)
2. Claude
3. GitHub Copilot CLI

Jev can promote Claude or Copilot to the first attempt when its confidence exceeds `CLOUD_CONFIDENCE_THRESHOLD`. A failed evaluation then ratchets forward without bouncing back to an earlier model.

## Setup

```powershell
composer install
Copy-Item .env.example .env
```

Set `TYPESAFE_API_KEY` and `ANTHROPIC_API_KEY`. Authenticate GitHub Copilot CLI separately and ensure `copilot` is on `PATH`.

Check syntax/tests:

```powershell
composer cs
composer unit
```

Build a standalone executable with `composer phpacker`. The build output is written under `bin/build/ai-router`.

Run:

```powershell
php bin/ai-router "Explain this repository" --workdir="C:\path\to\repo"
```

Metrics are written asynchronously to `var/metrics.jsonl`.

## ReactPHP design

All network calls return promises. The CLI uses `React\Async\await()` at the process boundary. Ollama and Claude use `react/http`; Copilot uses `react/child-process`; metrics use `react/filesystem`.
