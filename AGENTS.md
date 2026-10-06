# Instructions & Guardrails for AI Agents

## CRITICAL SAFETY RULES (MUST BE STRICTLY FOLLOWED AT ALL TIMES)

1. **PROHIBITION ON TEST COMMANDS**:
   - DO NOT autonomously run `php artisan test`, `pest`, or `phpunit`.
   - Never run test commands unless explicitly and specifically requested by the user.

2. **DATABASE SAFETY**:
   - The MySQL database (`jetze`) contains active development, user, and flight booking data.
   - Tests are strictly prohibited from touching MySQL. If tests are ever requested by the user, they must run on `:memory:` SQLite only.
   - A hard fatal intercept is implemented in `tests/TestCase.php` that will terminate tests immediately if the connection is not `sqlite`. Never remove or weaken this check.
   - Never run `migrate:fresh`, `db:wipe`, `migrate:reset`, or any destructive database operations.

3. **PASSWORDS**:
   - NEVER input or execute passwords (database or system) on your own.
   - Always ask the user directly whenever credentials or passwords are required.

   # Rules for this project (Laravel)

## Scope
- Only open and edit files I explicitly name or @-mention.
- Do NOT read, search, or analyze other files unless I ask. If you think another file is needed, ask me first.
- Do not explore the whole codebase. Do not summarize the project.
- Make the smallest change that solves the task. No refactors, no cleanup, no renaming.

## Never run
- php artisan test, migrate:fresh, migrate:refresh, db:wipe, or any command that touches the database.
- Do not run any command without asking me. Only edit code.

## Never open
- .env, storage/logs/*, vendor/, node_modules/, any *.sql dump

## Large files
- app/Services/AtApiService.php is ~2700 lines. Never read it whole.
  Search for the function name, then view only that function.

## Replies
- Short answers. Show a diff or the changed function only, not whole files.
