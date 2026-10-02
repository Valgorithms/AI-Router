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

## Use from GitHub Copilot Chat (VS Code)

Start the OpenAI-compatible server:

```powershell
composer serve
```

It listens on `http://127.0.0.1:8787/v1` (`ROUTER_HOST`, `ROUTER_PORT`) and exposes `GET /v1/models` and `POST /v1/chat/completions` (streaming is emulated). Each request tries your local Ollama model first and falls back to Claude (if `ANTHROPIC_API_KEY` is set) and then the Copilot CLI, which uses its own automatic model selection. Without Jev configured, a fallback only happens on transport failures (timeouts, errors, empty output); with `TYPESAFE_API_KEY` set, Jev also judges response quality.

In VS Code, run **Chat: Manage Language Models** → **Add Models** → **OpenAI Compatible**, use the URL above and model id `ai-router` (set `ROUTER_API_KEY` to require a bearer token). Select it in the Chat model picker.

Limitations: the router is text-only. Tool calls and agent mode are not supported, so use Ask-style chat. The prompt is the flattened conversation, and Copilot CLI fallback runs headless.

Run:

```powershell
php bin/ai-router "Explain this repository" --workdir="C:\path\to\repo"
```

Metrics are appended to `var/metrics.jsonl`.

## ReactPHP design

All network calls return promises. The CLI uses `React\Async\await()` at the process boundary. Ollama and Claude use `react/http`; Copilot uses `react/child-process`; the HTTP server uses `react/http` and `react/socket`.
