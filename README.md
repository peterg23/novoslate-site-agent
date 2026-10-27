# Novoslate Site Agent

WordPress plugin that reports pending updates and WordPress/PHP versions to [Novoslate Ops](https://ops.novoslate.com) once a day. Runs alongside ManageWP and changes nothing on the site.

Updates ship through GitHub releases (Plugin Update Checker), the same way as Project Estimator.

## Release
1. Bump `Version:` in `novoslate-site-agent.php`, the `NSA_VERSION` constant and `Stable tag` in `readme.txt`, and add a changelog entry.
2. Commit, then push a matching tag: `git tag v1.1.1 && git push origin main --tags`.
3. The Release workflow builds `novoslate-site-agent.zip` and attaches it to the GitHub release. Sites see the update within about 12 hours (or right away from Dashboard > Updates > Check again).
