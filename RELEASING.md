# Releasing

This repository is the source of truth for the plugin. WordPress.org is a publishing target, fed
automatically by GitHub Actions — do not hand-edit SVN unless the automation is broken.

**Push a `v*` tag and the release publishes itself.**

---

## One-time setup

Add two repository secrets under **Settings → Secrets and variables → Actions → New repository
secret**:

| Secret | Value |
| --- | --- |
| `SVN_USERNAME` | Your WordPress.org username — `diflowrin` |
| `SVN_PASSWORD` | The **SVN password** from wordpress.org → *Account & Security → SVN password*. This is a separate password from your wordpress.org login. |

Without these the workflow stops on its first step with "Set the SVN_USERNAME secret". Nothing is
sent anywhere until they exist, so it fails safe.

## Releasing a version

1. **Make the change** and test it on a real WordPress install.

2. **Bump the version in three places.** They must agree exactly, or the workflow refuses to deploy:
   - `diflowrin-ai-content-generation.php` → the `* Version:` header line
   - `diflowrin-ai-content-generation.php` → the `DIFLOWRIN_CG_VERSION` constant
   - `readme.txt` → `Stable tag:`

3. **Write the changelog** in `readme.txt` under `== Changelog ==`, as a new `= X.Y.Z =` section.
   This is the text users read on their update screen, so write it for them, not for developers. The
   workflow warns if the section is missing but still deploys.

4. **Run Plugin Check** against what will actually ship, not against your working copy. WordPress.org
   rejects on things like stray markdown files, so check a copy with `.distignore` applied:

   ```
   wp plugin check diflowrin-ai-content-generation
   ```

5. **Commit and push to `main`.**

6. **Tag and push the tag:**

   ```
   git tag -a v1.3.0 -m "Version 1.3.0"
   git push origin v1.3.0
   ```

7. **Watch the run** under the Actions tab. It will:
   - verify the three version numbers match the tag,
   - lint every PHP file (a parse error would fatal every site that auto-updates),
   - copy the plugin into SVN `trunk/`, honouring [`.distignore`](.distignore),
   - create `tags/1.3.0/`,
   - sync [`.wordpress-org/`](.wordpress-org) to the SVN `assets/` directory,
   - attach the built zip to the workflow run.

Updates reach users within roughly 15 minutes, once WordPress.org re-reads `Stable tag`.

## Things worth knowing

- **`Stable tag` is what ships.** Committing to trunk does not push anything to users on its own;
  only the `Stable tag` line in `readme.txt` decides which tag they receive.
- **Re-running is safe.** If `tags/<version>` already exists on WordPress.org the action notices and
  exits without committing, so a re-run of a finished release is a no-op.
- **Listing assets don't need a version.** Banner, icon and screenshots live in `.wordpress-org/` and
  are synced on every deploy. To refresh only artwork, either ship it with the next release or run
  the workflow manually.
- **Dry run.** Actions → *Deploy to WordPress.org* → **Run workflow**, give it a version and leave
  *Dry run* ticked. Everything runs, nothing is committed.
- **Only plugin code belongs here.** The SEO Content Architect desktop app is a separate, closed
  product; nothing from it should end up in this repository.

## Building a zip by hand

For testing an upload before tagging, or for handing someone a build directly:

```
powershell -ExecutionPolicy Bypass -File bin\build-plugin-zip.ps1
```

It takes the version from the plugin header, ships exactly the files `git ls-files` reports minus
[`.distignore`](.distignore) — so it matches what the workflow deploys, and untracked local files
cannot leak in — then verifies the finished archive and deletes it if anything is wrong.

**Never build this zip with `Compress-Archive`.** Windows PowerShell 5.1 writes the entry names with
backslashes, which the ZIP format forbids. WordPress then unpacks everything into one flat directory
holding files literally named `includes\Admin\Admin.php`, so the plugin fatals on its first
`require`. `[IO.Compression.ZipFile]::CreateFromDirectory` is broken the same way on .NET Framework.
Both were verified broken on the development machine. Working alternatives, if the script is
unavailable: `tar -a -c -f plugin.zip <dir>` (built into Windows), 7-Zip, `zip -r` under WSL, or
`Compress-Archive` under PowerShell 7 — where the bug is fixed.

Two structural rules the script also enforces, both previously hit:

- **One root directory, not two.** WordPress only scans `plugins/*/*.php`, one level deep. An archive
  with the slug nested inside itself puts the main file too deep and the plugin never appears in the
  list at all — no error, just absent.
- **No version in the root directory name.** WordPress installs into whatever directory name the zip
  contains, so `diflowrin-ai-content-generation-1.2.0/` lands *beside* the existing
  `diflowrin-ai-content-generation/` as a second copy of the plugin instead of updating it. The root
  must always equal the text domain.

The five-second check on any zip, whoever built it:

```
[IO.Compression.ZipFile]::OpenRead("plugin.zip").Entries | % FullName
```

Any `\` in the output means the archive is broken. So does more than one leading directory.

## If the automation is unavailable

The manual route still works — a checkout lives outside this repository, at
`d:\Content Architect Desktop\wp-svn-diflowrin\` on the development machine. Four traps, all
previously hit:

- **`svn update` first.** A stale working copy makes `svn cp` branch a tag off an old revision.
- **`^/trunk` does not exist.** WordPress.org keeps every plugin in one repository, so `^/` is the
  root of all of them. Use `^/diflowrin-ai-content-generation/trunk` or full URLs.
- **`svn add` new files explicitly.** A missing class file means a fatal error on every site that
  auto-updates.
- **Pass commit messages with `-F message.txt`.** A multi-line `-m` from PowerShell gets split and
  svn reads part of it as a path (`E020024`).

Tag creation is a server-to-server copy:

```
svn copy https://plugins.svn.wordpress.org/diflowrin-ai-content-generation/trunk \
         https://plugins.svn.wordpress.org/diflowrin-ai-content-generation/tags/1.3.0 \
         -F message.txt
```

After any manual commit, run the next release through the workflow as usual — it checks out SVN
fresh each time, so the two never drift.
