**AI Collaboration & Runbook**

Indonesian TL;DR: Catatan kolaborasi AI singkat di bawah.

Purpose
- Define roles, handoffs, and operational runbook for working with the AI agent.

Participants & Roles
- Product: defines success criteria and prioritization.
- ML/AI engineer: prompt design, evaluation, model vetting.
- Backend engineer: integration, monitoring, deployment.
- On-call / Ops: incident response, toggles, metrics.

Communication & Handoffs
- Where to share artifacts (PRs, docs, evaluation reports).
- Expected SLAs for triage and fixes.

Development Rules (high level)
- Follow rules in [app/Services/AiAgent/aturan.md](app/Services/AiAgent/aturan.md).
- Add Form Request validation for new endpoints; include tests and factories.

Runbook — Deploy / Rollback / Toggle
1. Deploy flow: run CI, run `php artisan migrate`, enable feature flag.
2. Rollback: disable feature flag, revert migration if necessary, run smoke tests.

Testing & Validation Checklist
- Unit tests passing, integration tests passing, evaluation metrics meet thresholds.
- Manual smoke test steps to confirm behavior.

Data Governance
- PII handling rules, logging sanitization, and retention policies.

Templates for Collaboration Artifacts
- Bug report template, evaluation summary template, post-mortem template.

Links & Contacts
- Key docs: [docs/AI_AGENT_OPTIMIZATION_SUMMARY.md](docs/AI_AGENT_OPTIMIZATION_SUMMARY.md)
- Operational rules: [app/Services/AiAgent/aturan.md](app/Services/AiAgent/aturan.md)

---
Concrete operational excerpts from `app/Services/AiAgent/aturan.md`:

- Folder structure guidance: place services under `app/Services/ai-agent/` with domain subfolders (e.g., `agent-prompt`, `agent-reply`).

- Service `.md` contract required for non-obvious services. Minimum contract fields:

```
# <ServiceName>

## Tanggung jawab
Satu kalimat: apa yang service ini lakukan.

## Input → Output
Diagram pendek atau bullet list.

## Dependencies
Service lain yang dipanggil.

## State (kalau ada)
Field DB / cache yang dibaca/ditulis.

## Edge cases yang penting
Hal yang tidak obvious dari kode.
```

- Logging rules (production-safe):
	- Use structured context: `Log::info('msg', ['contact_id'=>$id,'agent_id'=>$aid])`.
	- No PII in production logs; use preview/hash instead of full message or phone number.
	- `debug` logs only local; `info` for milestones; `warning` for recoverable issues; `error` for failures.

Runbook commands (from docs):

```bash
php artisan migrate
php artisan cache:clear
php artisan config:clear
php artisan test --filter=AiAgent
```

