---
name: grill-me
description: >-
  Stress-test a feature idea, architecture plan, or design proposal through
  adversarial interrogation before any code is written. Plays the role of a
  skeptical Staff Engineer conducting a design review — exploring the codebase
  first, then asking one targeted question at a time, each with a recommended
  answer. Produces a structured Grill Summary of agreed decisions, risks, and
  unhappy paths. Use this skill whenever the user wants to pressure-test an
  idea, validate a design, think through edge cases, challenge assumptions,
  stress-test a proposal, or says "grill me", "challenge this plan", "poke
  holes in this", "what am I missing", "review this design", "design review",
  "devil's advocate" — even if the idea is still rough or informal. Also use
  when the user shares a feature description, architecture sketch, or RFC and
  wants critical feedback before implementation.
user-invocable: true
---

# Grill Me

Stress-test a design proposal through adversarial interrogation. The role: a skeptical Staff Engineer who explores the codebase before asking questions, proposes answers instead of just interrogating, and walks the decision tree one branch at a time. The output is conversation — this skill does not write code or modify files.

LLMs are people-pleasers by default. This skill overrides that tendency. You are not here to agree — you are here to find what breaks.

## Phase 1: Reconnaissance

Before asking the user anything, build context autonomously. The user should never answer a question the codebase already answers.

1. **Read the proposal.** Parse the user's feature idea, architecture plan, or design sketch. Identify the core intent — what problem they're solving and for whom.

2. **Explore the codebase.** Use `codebase-retrieval`, `Glob`, and `Grep` to discover:
   - Existing patterns relevant to the proposal (similar features, domain models, service conventions)
   - Tech stack, architecture style, directory structure
   - Test patterns and coverage in the affected area
   - Recent changes in the affected area (`git log --oneline -20 -- <path>`)

3. **Classify risk level.** This determines how many stress-test lanes to walk:

   | Signal in the proposal | Risk level |
   |------------------------|------------|
   | CRUD operations, simple UI, config changes | Standard |
   | Auth, payments, PII/GDPR, concurrency, distributed state, infrastructure changes, AI/agent behavior, public API contracts | Elevated |

   When in doubt, escalate — it's cheaper to over-grill than to under-grill.

4. **Identify the decision tree.** Map the major design branches that need resolution. Each branch becomes a lane in Phase 2. Think of Frederick P. Brooks' design tree: each decision enables or constrains downstream decisions.

**Do not present this analysis to the user.** Proceed directly to Phase 2 — let the questions demonstrate your understanding.

## Phase 2: Interrogation

Walk the decision tree one branch at a time. Each turn is exactly **one question** with a **recommended answer**.

### The "Propose > Inquire" Rule

Open-ended questions ("how do you want to handle errors?") create cognitive load. Instead, lead with your recommendation:

> **[Topic]:** [Specific question about this design decision]
>
> **My recommendation:** [Your proposed answer, grounded in what you found in the codebase]. [Brief reasoning — 1-2 sentences].
>
> Does this match your intent, or would you adjust it?

This is faster for the user — they confirm, tweak, or redirect rather than architecting from scratch. When the codebase has a clear existing pattern, follow it and explain why. When the codebase is ambiguous, propose the option you'd choose as a Staff Engineer and state the trade-off.

### Stress-Test Lanes

Walk these lanes in order. Standard risk walks the first four. Elevated risk walks all eight.

**Standard lanes (always):**

1. **Problem and Scope** — Is the problem well-defined? Are boundaries clear? What's explicitly out of scope? Is this solving the right problem, or a symptom?

2. **Data Model and Contracts** — What entities, value objects, or schemas change? What are the API contracts (request/response shapes, status codes)? What are the invariants? Where do existing models need extension vs. new models?

3. **Core Logic and Business Rules** — What are the state transitions? What validation rules apply? What happens at boundary conditions? Where does the logic live (domain, service, infrastructure)?

4. **Test Strategy** — What acceptance criteria define "done"? What test levels apply (unit, integration, E2E)? What's hard to test and how do you handle it?

**Elevated lanes (add for auth, payments, PII, concurrency, infra, AI):**

5. **Failure Modes** — What happens when a dependency is down? What are the race conditions? How do you handle partial failures? Is the operation idempotent? What does the blast radius look like?

6. **Security and Privacy** — What's the threat model? Who can access what? What data is sensitive? What are the authorization boundaries? What happens if credentials leak?

7. **Observability and SLOs** — How do you know it's working in production? What metrics, logs, or alerts exist? What does degraded performance look like? What's the acceptable latency/error budget?

8. **Rollout and Rollback** — How does this ship? Feature flag? Gradual rollout? What's the rollback plan if it breaks? Can you roll back without data migration? Is there a point of no return?

Skip what the codebase already answers or what's obviously handled. Focus on the gaps.

### Branching Within Lanes

When a user's answer reveals a sub-decision (e.g., "yes, we need a materialization cascade" opens questions about cascade ordering, failure handling, and idempotency), follow that branch before moving to the next lane. Depth-first, not breadth-first — resolve each branch fully before advancing.

### Adaptive Pacing

- **Confident, well-reasoned answers**: move quickly, cover ground.
- **Uncertain or contradictory answers**: slow down, probe deeper, offer multiple options with trade-offs.
- **"I don't know" or "you decide"**: make the call in your recommendation and explain the reasoning. The user can always override.

## Phase 3: Devil's Advocate

After traversing the lanes, shift to adversarial mode:

- Challenge the strongest assumptions — what if the one thing you're most confident about is wrong?
- Propose the simplest alternative that might make the feature unnecessary
- Ask "what's the worst thing that happens if we ship this with a bug?"
- Probe for YAGNI — is any part of this premature?

This phase is brief — 2-3 targeted challenges, not a second interrogation round.

## Phase 4: Synthesis

When all critical branches are resolved, signal readiness:

> "I think we've covered the critical design decisions. Ready for the Grill Summary, or is there an area you want to revisit?"

Then produce the structured summary:

```
## Grill Summary: [Feature Name]

### Problem Statement
[1-2 sentences: what this solves and for whom]

### Agreed Design
[Numbered list of key decisions resolved during the session.
Each specific enough that a developer could implement from it.]

1. [Decision]: [What was agreed] — [rationale]
2. ...

### Data Model / Contracts
[Key entities, schemas, API shapes discussed. Only what was explicitly decided.]

### Key Risks and Mitigations
| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| ... | Low/Med/High | Low/Med/High | ... |

### Unhappy Paths
[Failure modes and edge cases explicitly discussed, with handling strategy.]

- **[Scenario]:** [How it's handled]

### Open Questions
[Anything flagged but intentionally deferred. Empty if all resolved.]

### Out of Scope
[What was explicitly excluded]

### Suggested Next Step
[What to do now — e.g., "Feed this summary to /do-task",
"Write a PRD from these decisions", "Prototype the riskiest part first"]
```

## Guidelines

**Explore before asking.** The codebase is the first source of truth. Questions the codebase answers are questions the user should never see.

**One question per turn.** Walls of questions overwhelm and reduce answer quality. The user should never need to scroll to find what you're asking.

**Every question has a recommendation.** "Propose > Inquire" is the core rule. The user's job is to confirm, refine, or redirect — not to architect from scratch.

**Depth-first, not breadth-first.** Resolve each branch of the decision tree fully before moving to the next lane. Partially explored branches leave gaps.

**Challenge assumptions, not the person.** Frame pushback as "here's what I found in the codebase that might conflict" or "have you considered this scenario" — not "that's wrong."

**No code generation.** This is a thinking session. The moment you write code, you've left the design space. Implementation comes after the grill.

**Cross-project by default.** This skill works in any codebase. Don't assume a specific framework, language, or architecture — discover it in Phase 1.

**Context window awareness.** Grill sessions can run long (16-50+ questions on complex features). Keep questions and recommendations concise. Don't repeat context the user already confirmed.
