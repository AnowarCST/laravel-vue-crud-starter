# CI/CD

This project uses GitHub Actions for CI. The fast checks run on every
change. The slower checks are opt-in: they run when you ask for them, with a
label, a manual run or a repository variable. Templates for deploying with
GitHub Actions, AWS and Azure are in `deploy/`, fully commented, so nothing
in them runs until you enable it.

## Workflows and pipelines

Times are from recent runs of this repository with a warm Docker build
cache. The first run on a new fork or branch, without that cache, takes
longer. GitHub Actions minutes are free for public repositories; for private
repositories they count against your plan.

| Workflow | What it runs | When it runs | Time | How to turn it on or off |
| --- | --- | --- | --- | --- |
| `.github/workflows/ci.yml`, job **php** | `composer validate --strict`, `composer install`, `composer audit`, Pint, Larastan (level 9), PHPUnit | Every pull request, every push to `main`, manual runs | under 1 min | Always on |
| `.github/workflows/ci.yml`, job **frontend** | `npm ci`, ESLint, Prettier check, `npm audit --audit-level=moderate`, Vite build | Same as **php** | under 1 min | Always on |
| `.github/workflows/codeql.yml` | CodeQL (`security-extended`) for JavaScript/TypeScript and for the workflows themselves | Every pull request, every push to `main`, Mondays, manual runs | about 1 min per language | Always on |
| `.github/workflows/docker.yml` | Builds the `prod` image and checks that `/up` responds | Opt-in, see below | 1 to 2 min | Variable `RUN_DOCKER_BUILD`, label `run-docker-build`, or **Run workflow** |
| `.github/workflows/e2e.yml` | Builds the `prod` image, starts the E2E stack (app, MySQL, Mailpit), runs the Playwright tests | Opt-in, see below | about 3 min | Variable `RUN_E2E`, label `run-e2e`, or **Run workflow** |
| `.github/workflows/zap.yml` | Builds the `prod` image, starts the same stack, runs an OWASP ZAP baseline scan of the signed-out pages | Opt-in, see below | about 3 min | Variable `RUN_ZAP`, label `run-zap`, or **Run workflow** |
| `.github/dependabot.yml` | Weekly update pull requests for Composer, npm, Docker images, Docker Compose and GitHub Actions | Mondays | | Always on (see [Dependabot](#dependabot)) |
| `azure-pipelines.yml` | Backend checks (Composer, PHPUnit) in the `dev` image, on Azure Pipelines | Manual runs only (`trigger: none`, `pr: none`) | | See [Azure Pipelines](#azure-pipelines) |
| `deploy/github-actions/deploy.yml` | Template: push the `prod` image to GitHub Container Registry, optionally deploy over SSH | Never, until enabled | | Copy and uncomment, see the file's header |
| `deploy/aws/buildspec.yml` | Template: CI checks, then push the `prod` image to Amazon ECR | Never, until enabled | | Copy and uncomment, see the file's header |
| `deploy/aws/ecs-task-definition.yml` | Template: Amazon ECS (Fargate) task definition for the `prod` image | Never, until enabled | | Uncomment and register, see the file's header |
| `deploy/azure/azure-pipelines.yml` | Template: CI checks, then push the `prod` image to Azure Container Registry | Never, until enabled | | Copy and uncomment, see the file's header |

## Opt-in workflows

The Docker, E2E and ZAP workflows each have three switches. Any one of them
is enough.

| Switch | Docker | E2E | ZAP | Effect |
| --- | --- | --- | --- | --- |
| Manual run | **Actions** > **Docker** > **Run workflow** | **Actions** > **E2E** > **Run workflow** | **Actions** > **ZAP baseline** > **Run workflow** | Runs once, on the branch you pick. |
| Pull request label | `run-docker-build` | `run-e2e` | `run-zap` | Adding the label runs it on that pull request; every later push to the pull request runs it again until the label is removed. |
| Repository variable set to `true` | `RUN_DOCKER_BUILD` | `RUN_E2E` | `RUN_ZAP` | Runs on every pull request and every push to `main`. For ZAP: every pull request to `main` and every Monday (there is no push trigger). |

Set variables under **Settings** > **Secrets and variables** > **Actions** >
**Variables**. Delete the variable, or set it to anything other than
`true`, to turn the workflow off again. Create the labels once under
**Issues** > **Labels** > **New label**, or with the GitHub CLI:
`gh label create run-docker-build`, `gh label create run-e2e` and
`gh label create run-zap`.

How it works and what to expect:

- When none of the switches applies, the workflow still starts but its job
  is skipped within seconds. GitHub reports a skipped job as a success, so
  it never blocks a pull request.
- Adding any other label to a pull request also starts these workflows,
  and their jobs are skipped. Such runs use their own concurrency group,
  so they never cancel a real run.
- Only people with triage or write access to the repository can add
  labels, so a label is a maintainer's decision, also for pull requests
  from forks.
- The label triggers use the `pull_request` event, not
  `pull_request_target`. Pull requests from forks therefore run with a
  read-only `GITHUB_TOKEN` and without repository secrets, like any other
  pull request. None of these workflows needs a secret.
- GitHub does not copy repository variables to forks, so a fork runs only
  the always-on checks on its own branches until its owner sets the
  variables.
- GitHub's documentation does not say clearly whether repository variables
  are passed to runs for Dependabot pull requests and pull requests from
  forks. If they are not, the opt-in jobs are skipped on those runs; the
  labels and manual runs still work.
- The ZAP workflow used to run on pull requests that changed certain paths
  (`app/`, `config/`, `docker/` and others). That path filter is gone,
  because a workflow-level path filter would also stop the `run-zap` label
  from working on other pull requests. Add the label to pull requests that
  change HTTP headers, cookies, the Content-Security-Policy or the Apache
  configuration.

## Branch protection

Mark these checks as required (**Settings** > **Branches** or **Rules** >
the rule for `main` > **Require status checks to pass**):

- `PHP (style, static analysis, audit, tests)`
- `Frontend (lint, format, audit, build)`
- `Analyze (javascript-typescript)`
- `Analyze (actions)`

Do not mark the opt-in jobs (`Docker (prod image)`, `Playwright
(Chromium)`, `ZAP baseline scan`) as required. They are skipped on most
pull requests, and a skipped job counts as passed, so a required opt-in
check would not protect anything; it would only suggest that it does. If
you want the E2E tests on every pull request, set `RUN_E2E` to `true`
first, then make `Playwright (Chromium)` required.

Remove `AnowarCST.laravel-vue-crud-starter` (Azure Pipelines) from the
required checks if it is there. With `pr: none`, Azure Pipelines should no
longer run on pull requests, so the check would never arrive.

Never use workflow-level `paths` or `branches` filters on a workflow whose
job is a required check. When such a filter skips a whole workflow, the
check is never created and the pull request waits for it forever.

## Deployment templates

The templates are listed in the order to consider them: GitHub Actions
first (no other account needed), then AWS, then Azure. Each one has a
header that explains what it does, what to create first (registry,
roles, service connections, secrets) and how to enable it. Every line is a
comment; remove the `# ` prefix from the lines below the line of dashes to
enable it.

1. **GitHub Actions**: `deploy/github-actions/deploy.yml`. On a version
   tag (`v1.2.3`) or a manual run, builds the `prod` image and pushes it to
   GitHub Container Registry with the workflow's own `GITHUB_TOKEN`.
   Optionally deploys it to one server over SSH with `docker compose`. Copy
   it to `.github/workflows/deploy.yml` to enable it. For cloud providers
   the header recommends OpenID Connect instead of long-lived keys.
2. **AWS**: `deploy/aws/buildspec.yml` for CodeBuild (the same checks as
   CI, then the image pushed to Amazon ECR, plus `imagedefinitions.json`
   for a CodePipeline ECS deploy action) and
   `deploy/aws/ecs-task-definition.yml` (a minimal Fargate task
   definition). CodeBuild needs privileged mode for the Docker builds.
3. **Azure**: `deploy/azure/azure-pipelines.yml`. The same checks as CI,
   then the image pushed to Azure Container Registry for commits on `main`.

What was checked, and what was not:

- The GitHub Actions template, uncommented, passes `actionlint` (including
  `shellcheck`). It has not been run against a real registry or server.
- The AWS buildspec, uncommented, parses as YAML and matches the documented
  buildspec version 0.2 layout (checked with a local script, as AWS
  publishes no schema). Its build-phase commands were run locally with
  Docker, and all checks and the `prod` image build passed. The task
  definition passes the AWS CLI's local parameter validation. Neither has
  been run on AWS.
- The Azure template, uncommented, passes Microsoft's Azure Pipelines YAML
  schema. It runs the same check commands as the AWS template. It has not
  been run on Azure DevOps.
- Action versions in the templates are pinned to full commit SHAs, but
  Dependabot only updates files in `.github/workflows/`. Check for newer
  versions when you enable a template.
- None of the templates runs database migrations. Take a backup, then run
  `php artisan migrate --force` by hand (see the production checklist in
  [SECURITY.md](../SECURITY.md#production-checklist)).

## Azure Pipelines

This repository is connected to Azure Pipelines, which reads
`azure-pipelines.yml` at the repository root. While that connection exists
the file must stay a valid pipeline, so it keeps its backend check job but
sets `trigger: none` and `pr: none`: it only runs when someone starts it in
Azure DevOps (**Run pipeline**). With `pr: none`, Azure Pipelines should no
longer report the `AnowarCST.laravel-vue-crud-starter` status check on pull
requests. This could not be confirmed before the change reached `main`.
Trigger settings made in the Azure DevOps web interface (**Edit** >
**Triggers**) override the YAML; if the check keeps appearing, look there.

The header of `azure-pipelines.yml` explains how to turn the triggers back
on, and how to disconnect Azure Pipelines completely (disable or delete the
pipeline in Azure DevOps and remove the Azure Pipelines GitHub app from the
repository). After that, the file can be deleted.

## Dependabot

`.github/dependabot.yml` opens grouped pull requests every Monday for
Composer, npm, the Docker images in `docker/Dockerfile`, the images in
`docker-compose.yml` and the GitHub Actions in `.github/workflows/`.

- Dependabot pull requests get the always-on checks (PHP, frontend,
  CodeQL). For updates that can change the running app, such as the PHP,
  Node or MySQL images, Apache, or Laravel itself, add the `run-e2e` label
  (and `run-zap` for anything that can change HTTP responses) before you
  merge.
- GitHub treats Dependabot pull requests like pull requests from forks:
  the token is read-only and only Dependabot secrets are available. The
  workflows here need no secrets.
- Dependabot keeps the actions in `.github/workflows/` pinned to full commit
  SHAs. It does not read the commented templates in `deploy/`.
- Node stays on LTS majors and MySQL on the 9.7 LTS line; the comments in
  `.github/dependabot.yml` explain these limits.
