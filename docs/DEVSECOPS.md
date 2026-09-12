# swapapp-symfony — DevSecOps writeup

> Pipeline-only addition to an existing Symfony app. **No file under `src/`
> or `templates/` was modified** — the deliverables are
> `.github/workflows/security-pipeline.yml`, `Dockerfile` (CI only),
> and this doc. Application logic is untouched by design.

## 1. Pipeline architecture

```mermaid
flowchart LR
    A[Push / PR to main] --> B["1. Setup<br/>PHP 8.1 + composer install + npm ci"]
    B --> C["2. Lint<br/>php -l + PHPCS + PHPStan"]
    C --> D["3. SAST<br/>Semgrep p/php + p/ci"]
    D --> E["4. Secrets<br/>Gitleaks"]
    E --> F["5. SCA<br/>composer audit + npm audit"]
    F --> G["6. Docker build<br/>Dockerfile (compose untouched)"]
    G --> H["7. Trivy<br/>HIGH,CRITICAL"]
    G --> I["8. SBOM<br/>Syft SPDX + CycloneDX"]
    G --> J["9. DAST<br/>ZAP vs :8000"]
    B --> K["10. PHPUnit<br/>APP_ENV=test"]
    D --> L{"11. GATE:<br/>HIGH/CRITICAL<br/>in SAST/secrets/SCA/container?"}
    E --> L
    F --> L
    H --> L
    K --> L
    L -- YES --> M[FAIL build<br/>block merge]
    L -- NO --> N["12. Cosign sign<br/>success only"]
    D -. SARIF .-> O[(Security tab)]
    E -. SARIF .-> O
    H -. SARIF .-> O
    D -. JSON .-> P[(Artifacts)]
    E -. JSON .-> P
    F -. JSON .-> P
    H -. JSON .-> P
    I -. SBOM .-> P
    J -. HTML/MD/JSON .-> P
    K -. phpunit.log .-> P
    N -. .sig/.pem/.pub .-> P
```

ASCII version (for slides):

```
push/PR ─▶ 1 setup ─▶ 2 lint ─▶ 3 Semgrep ─▶ 4 Gitleaks ─▶ 5 composer+npm audit ─▶ 6 docker build
                                                                                    ├─▶ 7 Trivy ─┐
                                                                                    ├─▶ 8 Syft   ├─▶ 11 GATE ─┬─ FAIL
                                                                                    └─▶ 9 ZAP    ┘  ▲          └─ PASS ─▶ 12 cosign
                                                                          10 PHPUnit ───────────────┘
```

## 2. Tool → layer coverage

| # | Tool (job) | Layer | Vuln classes it catches in THIS repo | Report |
|---|---|---|---|---|
| 2 | `php -l` + PHP_CodeSniffer (PSR-12) + PHPStan L5 | Code hygiene / static analysis | Syntax errors, style drift, undefined vars, wrong types, dead code | `lint-php-reports/phpcs.json`, `phpstan.json` |
| 3 | Semgrep `p/php` + `p/ci` | SAST (code, not running) | SQLi, XSS, weak crypto, insecure `unserialize()`/`eval`, Symfony/Twig misconfig (Symfony rules ship inside `p/php`; there is no standalone `p/symfony` pack — deliberate choice, see YAML comment) | `sast-semgrep-reports/semgrep.sarif` + Security tab |
| 4 | Gitleaks | Secrets (history + tree) | Committed `.env`: `APP_SECRET`, Gmail `MAILER_DSN` password, DB credentials | `secrets-gitleaks-reports/gitleaks.json` + Security tab |
| 5a | `composer audit` | SCA — PHP deps (`composer.lock`) | Known CVEs in `doctrine/*`, `symfony/*`, `twig/*` etc. | `sca-reports/composer-audit.json` |
| 5b | `npm audit` | SCA — JS deps (`package-lock.json`) | Known CVEs in `webpack`, `sass-loader`, `chart.js` etc. | `sca-reports/npm-audit.json` |
| 7 | Trivy | Container (OS + libs in image) | Base-image CVEs (openssl, libssl…), plus PHP/JS libs as built — catches what SCA misses | `container-trivy-reports/trivy.sarif` + Security tab |
| 8 | Syft | SBOM / supply chain | Inventory of everything shipped (transparency, signing input) — informational, never blocks | `sbom-reports/sbom.spdx.json`, `sbom.cyclonedx.json` |
| 9 | OWASP ZAP baseline | DAST (live black-box on :8000) | Reflected XSS, missing security headers, exposed debug routes/profiler | `dast-zap-reports/report_html.html` |
| 10 | PHPUnit (`APP_ENV=test`) | Functional correctness | Regressions — must pass before gate so broken code can't be "secured in" | `phpunit-reports/phpunit.log` |
| 12 | Cosign `sign-blob` | Provenance (post-gate only) | Tampering — proves "this SBOM passed all scans" (demo key; prod = keyless GHCR signing, see §6) | `cosign-signature/*.sig`, `*.pem`, `*.pub` |

## 3. Findings (first run — current `main` state)

> These are REAL issues in the repo as cloned. Nothing was deleted or
> hidden — fix them via §4, in order.

### F1 — CRITICAL: `.env` committed with live-looking secrets

- **Files:** `.env` (tracked by git), lines 19–29.
- **Leaked values** (masked here so this doc stays green — full values in `.env`):
  - `APP_SECRET=<redacted>` — 32-hex Symfony signing/CSRF secret (`.env` line 19)
  - `MAILER_DSN=smtp://<credentials>@smtp.gmail.com:587` — Gmail username + app-password (`.env` line 21)
  - `DATABASE_URL="mysql://<see .env line 29>"` — MySQL root with EMPTY password
- **Why it matters:** anyone with repo read access owns the mail account, can forge sessions/CSRF tokens, and learns DB topology. Git history keeps secrets forever even if later deleted.
- **Caught by:** job 4 Gitleaks with the extended `gitleaks.toml` (see lesson below). Run #8 gate output: `7 leak(s)` = 3× `.env` (APP_SECRET L19, MAILER_DSN L21, DATABASE_URL L29) + 3× this doc's first draft (which quoted the secrets verbatim — self-inflicted, fixed by masking) + 1× `README.md` example URL.
- **Lesson — scanners need tuning:** with DEFAULT Gitleaks rules the same scan scored **0 leaks** and the gate silently passed the secrets check. Default generic rules don't cover Symfony env conventions (hex APP_SECRET, MAILER_DSN/DATABASE_URL URIs), so we added 3 `symfony-*` rules in `gitleaks.toml` (`[extend] useDefault = true` keeps all defaults). Verified locally with the Gitleaks 8.18 binary before pushing.
- **Lesson — history never forgets:** the 3 doc-quote leaks live in commit `2c794d7` and the README quote in the original commit — masking the files does NOT clear history. That's why R1 (rotation) comes first and the AFTER demo needs the `filter-repo` purge in R3.
- **Note:** `.env.test` also contains `APP_SECRET='$ecretf0rt3st'` — test-only, low risk, but listed for completeness.

### F2 — HIGH (hygiene/supply-chain): `composer.phar` + `composer_2.phar` committed (~6 MB)

- **Files:** `composer.phar` (3.0 MB), `composer_2.phar` (3.0 MB) at repo root.
- **Why it matters:** binaries bloat every clone, bypass version pinning (`composer --version` lottery), and no checksum proves they're untampered. CI uses the runner's Composer (`shivammathur/setup-php`), never these files.
- **Caught by:** review + Gitleaks binary-size noise; flagged here as policy finding.

### F3 — MEDIUM: `.gitignore` does not protect secrets or binaries

- Current `.gitignore` ignores `.env.local` / `.env.*.local` (good) but NOT `.env` itself, and has no `*.phar` rule — which is exactly how F1/F2 happened. Remediation snippet in §4.

### F4 — Run #8 real numbers (fill-in completed)

- Results from Security Pipeline #8 (12 Sep 2026, pre-remediation):
  - Semgrep SAST: **0 findings** (`sast-semgrep-reports/semgrep.json`) — no auditable pattern hit in `src/`/`config/`/`public/`.
  - `composer audit`: **report EMPTY** (0 bytes → gate logged "skip"). Root cause under investigation (version/lock diagnostics added to job 5); gate is now STRICT — a missing/unparseable report fails the build instead of skipping silently.
  - `npm audit` high/critical: **11** (`sca-reports/npm-audit.json`).
  - Trivy HIGH/CRITICAL: **928** (`container-trivy-reports/trivy.json`) — expected: `php:8.1-apache` base is past security support, so the OS layer alone carries hundreds of CVEs. AFTER remediation starts with bumping the base image (`php:8.2/8.3-apache`).
  - ZAP baseline: **31 URLs, all checks PASS** — yet job 9 went RED on a pure infra bug: the action's default `artifact_name: zap_scan` is rejected by the GH artifact API. Fixed with `artifact_name: dast-zap-scan` (job 9 uploads its own reports; see workflow comment).
- The gate (job 11) is red from F1 + npm + Trivy combined; F4 sizes the AFTER dependency-upgrade work.

## 4. Remediation (do in this order; check boxes for grading)

### R1 — Rotate EVERY leaked credential (before any cleanup)

1. Gmail: revoke the app password `kgys…` at Google Account → Security → App passwords; generate a new one (or better, switch to Mailpit in dev and a transactional provider in prod).
2. Symfony: generate a fresh secret — `php bin/console secrets:generate-keys` or `openssl rand -hex 32` — and NEVER commit it.
3. MySQL: set a real root/app password; update `DATABASE_URL` accordingly.
4. Assume the old values are public (bots scrape GitHub in minutes). Rotation first, cleanup second.

### R2 — Stop tracking `.env`, keep a safe template

```bash
# 1. Keep the file working locally, but remove it from git (history still
#    contains it — that is why R1 rotation was mandatory):
git rm --cached .env
cp .env .env.example
# 2. Scrub secrets from the template:
#    APP_SECRET=${APP_SECRET}  MAILER_DSN=${MAILER_DSN}  DATABASE_URL=${DATABASE_URL}
git add .env.example
```

### R3 — Fix `.gitignore` (append this block)

```gitignore
# --- DevSecOps remediation: never commit real secrets or binaries ---
.env
!.env.example
*.phar
/composer.phar
/composer_2.phar
/var/*.db
.phpunit.result.cache
```

Then remove the binaries from tracking (keep local copies if needed):

```bash
git rm --cached composer.phar composer_2.phar
git add .gitignore && git commit -m "security: untrack .env and composer binaries, harden gitignore"
```

> Purge history only if the repo never left your classroom: `git filter-repo --path .env --invert-paths` + force-push + rotate again. Otherwise treat history as compromised (R1 covers you).

### R4 — Env handling via GitHub Actions secrets (prod-safe pattern)

1. Repo → Settings → Secrets and variables → Actions → New repository secret: `APP_SECRET`, `MAILER_DSN`, `DATABASE_URL`.
2. Reference them in workflow steps that need a live app (ZAP/PHPUnit), e.g.:
   ```yaml
   env:
     APP_SECRET: ${{ secrets.APP_SECRET }}
     MAILER_DSN: ${{ secrets.MAILER_DSN }}
     DATABASE_URL: ${{ secrets.DATABASE_URL }}
   ```
3. Local dev keeps using `.env.local` (gitignored) or `symfony console secrets:set`.

### R5 — Dependencies (after F4 numbers are known)

- PHP: `composer audit` → `composer update vendor/package` for each advisory, commit the new `composer.lock`.
- JS: `npm audit fix` (review breaking changes), commit `package-lock.json`.
- Container: rebuild pulls a fresh `php:8.1-apache`; persistent Trivy HIGHs → bump base image (`php:8.2-apache`) in `Dockerfile`.

## 5. Run each scanner locally before pushing

```bash
# --- setup (mirrors job 1) ---
composer install
npm ci || npm install

# --- 2. lint ---
find src config public tests -name "*.php" -exec php -l {} \;
curl -sSL https://github.com/PHPCSStandards/PHP_CodeSniffer/releases/latest/download/phpcs.phar -o phpcs.phar
php phpcs.phar --standard=PSR12 src/
curl -sSL https://github.com/phpstan/phpstan/releases/latest/download/phpstan.phar -o phpstan.phar
php phpstan.phar analyse src --level=5

# --- 3. SAST ---
pip install semgrep
semgrep --config p/php --config p/ci src/ config/ public/

# --- 4. secrets (download binary from github.com/gitleaks/gitleaks/releases) ---
# IMPORTANT: pass --config gitleaks.toml — defaults alone score 0 leaks here.
gitleaks detect --config gitleaks.toml --source . --verbose --redact

# --- 5. SCA ---
composer audit
npm audit --audit-level=high

# --- 6+7. build + Trivy (needs Docker + https://aquasecurity.github.io/trivy/) ---
docker build -t swapapp:ci -f Dockerfile .
trivy image --severity HIGH,CRITICAL swapapp:ci

# --- 8. SBOM (needs https://github.com/anchore/syft) ---
syft swapapp:ci -o spdx-json > sbom.spdx.json

# --- 9. DAST (needs Docker + ZAP) ---
docker run -d --name swapapp -p 8000:80 swapapp:ci
curl http://localhost:8000/
docker run --net=host ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t http://localhost:8000
docker stop swapapp; docker rm swapapp

# --- 10. tests ---
APP_ENV=test DATABASE_URL="sqlite:///%kernel.project_dir%/var/test.db" MAILER_DSN="null://null" php bin/phpunit
```

## 6. Before / after (fill in screenshots after running)

### BEFORE — pipeline FAILS on current state

Push as-is. Expected: jobs 4 (Gitleaks) and 11 (gate) red; SARIF alerts in Security → Code scanning.

- `docs/screenshots/01-pipeline-failing.png` — red run (Gitleaks + gate failed)
- `docs/screenshots/02-gitleaks-env.png` — `gitleaks.json` hit on `.env` (`MAILER_DSN`/`APP_SECRET`)
- `docs/screenshots/03-gate-failed.png` — gate log `::error::SECURITY GATE FAILED`
- `docs/screenshots/04-security-tab.png` — Code scanning alerts (Semgrep/Gitleaks/Trivy)

Real gate excerpt (run #8, pre-remediation):

```
== SAST (Semgrep) ==
  0 finding(s) in reports/sast-semgrep-reports/semgrep.json
== Secrets (Gitleaks) ==
  0 leak(s) in reports/secrets-gitleaks-reports/gitleaks.json   <- with DEFAULT rules (before gitleaks.toml)
== SCA PHP (composer audit) ==
  (skip reports/sca-reports/composer-audit.json: Expecting value: line 1 column 1 (char 0))
== SCA JS (npm audit) ==
  high+critical: 11 in reports/sca-reports/npm-audit.json
== Container (Trivy) ==
  928 HIGH/CRITICAL vuln(s) in reports/container-trivy-reports/trivy.json
---
FAILURES:
 - SCA-JS: 11 npm high/critical
 - Container: 928 Trivy HIGH/CRITICAL
::error::SECURITY GATE FAILED — high/critical issues block deployment.
```

> After the `gitleaks.toml` fix, the Secrets line becomes `7 leak(s)` (3× `.env` + history quotes) and the composer line becomes a hard `REPORT UNPARSEABLE/MISSING` failure instead of a silent skip. Three debugging lessons from one run: tune your rules, fix artifact uploads, never skip silently.

### AFTER — pipeline PASSES after §4 remediation

Apply R1–R5 on a second branch, open a PR.

- `docs/screenshots/05-pipeline-passing.png` — all 12 jobs green incl. `12. Sign (Cosign)`
- `docs/screenshots/06-cosign-artifact.png` — `cosign-signature` artifact (`.sig` + `.pem` + `.pub`)

> Presentation tip: show the two Actions runs side-by-side — red BEFORE vs green AFTER — then open the Security tab to prove SARIF wiring.

## 7. Branch protection (repo SETTINGS, not code)

Settings → Branches → Add rule for `main`:

- [x] Require a pull request before merging
- [x] Require status checks to pass (require up-to-date branches) — select:
  - `11. Security gate (FAIL on HIGH/CRITICAL)` (required)
  - `10. Tests (PHPUnit)`, `7. Container scan (Trivy)`, `3. SAST (Semgrep)` (recommended)
- [x] Do not allow bypassing the above settings
- [ ] Optionally: require signed commits; include administrators

Demo: PR from vulnerable branch → red checks, merge blocked; PR after remediation → green, merge allowed, Cosign artifact appears. This turns the pipeline from *advisory* into *enforcing*.

## 8. Cosign note (job 12)

Classroom mode signs the SBOM file with an ephemeral key (`sign-blob`, no registry needed, works on forks). Production equivalent: push to GHCR then `cosign sign --yes ghcr.io/<org>/swapapp@sha256:<digest>` (keyless OIDC via the workflow's `id-token: write` permission) and verify at deploy time. The `.sig`/`.pem`/`.pub` artifacts are the evidence to screenshot.
