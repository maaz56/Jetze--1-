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
