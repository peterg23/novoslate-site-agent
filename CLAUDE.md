# Novoslate Site Agent (WordPress plugin)

## Workflow
- Edit files directly in this repo. Never use or create zips in ~/Downloads.
- After finishing the requested changes, release them:
  1. Run `php -l` on every changed PHP file. Fix any errors before continuing.
  2. Add a short entry to the changelog in readme.txt for the new version.
  3. Run: `ship --bump X.Y.Z -m "Short summary of the change" --yes`
     - Use the next patch version (1.1.0 → 1.1.1) unless I ask for a minor or major bump.
     - If `ship` doesn't update `NSA_VERSION` in novoslate-site-agent.php, update it to match. The release workflow refuses a mismatch.
     - Releases publish from `main`: a push with a version that has no release yet gets tagged and released automatically.
- If I say "don't ship" or "just test", make the changes but skip step 3.

## Rules
- The plugin is read-only on client sites: it reports, it never changes settings, updates or content.
- Keep option names (`novoslate_agent`, `novoslate_agent_last`) and the cron hook (`novoslate_agent_daily`) stable so updates keep each site's token.
- Keep the summary in `-m` short and plain (it becomes the commit message).
- If `ship` fails, show me the error instead of retrying with different flags.
