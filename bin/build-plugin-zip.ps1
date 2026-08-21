<#
.SYNOPSIS
    Builds an installable WordPress plugin zip, correct by construction.

.DESCRIPTION
    Do not use Compress-Archive for this. Windows PowerShell 5.1 writes the
    entry names with backslashes ("plug\sub\file.php"), which the ZIP
    specification forbids -- APPNOTE 4.4.17.1 requires forward slashes. WordPress
    unzips such an archive into a single flat directory full of files literally
    named "includes\Admin\Admin.php", so every require fails and the plugin
    fatals or never appears in the list. .NET Framework's
    [IO.Compression.ZipFile]::CreateFromDirectory has the same defect on this
    platform. Both were verified broken here; only building the entries by hand,
    as below, is reliable without installing anything.

    Two further rules this script enforces, both of which have bitten before:

      * Exactly one root directory inside the zip, and no second copy of the
        slug nested inside it. WordPress only scans plugins/*/*.php -- one level
        deep -- so a doubly nested main file makes the plugin vanish silently.

      * That root directory is named for the text domain with no version
        suffix. WordPress installs into the directory name it finds in the zip,
        so "...-1.2.0/" lands beside the old "...-1.1.0/" as a second plugin
        instead of updating the existing one.

    The file list comes from `git ls-files` filtered through .distignore, so a
    local build ships exactly what the GitHub Actions deploy ships -- untracked
    scratch files cannot leak in.

.PARAMETER OutputDirectory
    Where to write the zip. Defaults to the repository root.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File bin\build-plugin-zip.ps1
#>

[CmdletBinding()]
param(
    [string] $OutputDirectory
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$Slug     = 'diflowrin-ai-content-generation'
$MainFile = "$Slug.php"

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if (-not $OutputDirectory) { $OutputDirectory = $repoRoot }
$OutputDirectory = (Resolve-Path $OutputDirectory).Path

Push-Location $repoRoot
try {
    # --- version, taken from the one place that is authoritative -------------

    $header = Select-String -Path (Join-Path $repoRoot $MainFile) -Pattern '^\s*\*\s*Version:\s*(.+?)\s*$' |
              Select-Object -First 1
    if (-not $header) { throw "No '* Version:' header found in $MainFile." }
    $version = $header.Matches[0].Groups[1].Value

    Write-Host "Building $Slug $version" -ForegroundColor Cyan

    # --- which files ship ----------------------------------------------------

    $tracked = & git ls-files
    if ($LASTEXITCODE -ne 0) { throw 'git ls-files failed -- is this a git checkout?' }
    if (-not $tracked) { throw 'git ls-files returned nothing.' }

    # .distignore holds rsync exclude patterns. rsync matches a pattern with no
    # slash in it against every path segment, not just the leading one, so
    # "*.md" drops docs at any depth and ".github" drops the whole directory.
    $distignore = Join-Path $repoRoot '.distignore'
    $patterns = @()
    if (Test-Path $distignore) {
        $patterns = Get-Content $distignore |
                    ForEach-Object { $_.Trim() } |
                    Where-Object { $_ -and -not $_.StartsWith('#') }
    }

    function Test-Excluded {
        param([string] $RelativePath)
        foreach ($segment in $RelativePath.Split('/')) {
            foreach ($pattern in $patterns) {
                if ($segment -like $pattern) { return $true }
            }
        }
        return $false
    }

    $files = @($tracked | Where-Object { -not (Test-Excluded $_) } | Sort-Object)
    if (-not $files) { throw 'Every tracked file was excluded by .distignore.' }

    if ($files -notcontains $MainFile) {
        throw "$MainFile is not in the shipping file list -- check .distignore."
    }

    # A tracked file that was deleted locally would otherwise fail mid-write.
    $missing = @($files | Where-Object { -not (Test-Path (Join-Path $repoRoot $_)) })
    if ($missing) { throw "Tracked but missing from disk:`n  $($missing -join "`n  ")" }

    # --- write it ------------------------------------------------------------

    $zipPath = Join-Path $OutputDirectory "$Slug-$version.zip"
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $archive = [IO.Compression.ZipFile]::Open($zipPath, [IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($file in $files) {
            # $file already uses forward slashes -- git reports paths that way on
            # every platform, and this is the one string that must not be
            # touched by Windows path handling.
            $entryName = "$Slug/$file"
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive,
                (Join-Path $repoRoot $file),
                $entryName,
                [IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
    finally {
        $archive.Dispose()
    }

    # --- verify what was actually written, not what we meant to write --------

    $check = [IO.Compression.ZipFile]::OpenRead($zipPath)
    try {
        $entries = @($check.Entries | ForEach-Object { $_.FullName })
    }
    finally {
        $check.Dispose()
    }

    $problems = @()

    $backslashed = @($entries | Where-Object { $_.Contains('\') })
    if ($backslashed) {
        $problems += "Backslash separators in $($backslashed.Count) entries, e.g. $($backslashed[0])"
    }

    $roots = @($entries | ForEach-Object { $_.Split('/')[0] } | Sort-Object -Unique)
    if ($roots.Count -ne 1) { $problems += "Expected one root directory, found: $($roots -join ', ')" }
    elseif ($roots[0] -ne $Slug) { $problems += "Root directory is '$($roots[0])', expected '$Slug'" }

    if ($entries -notcontains "$Slug/$MainFile") {
        $problems += "$MainFile is not at the top level of the archive"
    }
    $nested = @($entries | Where-Object { $_ -like "$Slug/*/$Slug*" })
    if ($nested) { $problems += "Slug appears nested a second level deep, e.g. $($nested[0])" }

    if ($problems) {
        Remove-Item $zipPath -Force
        throw "Archive was malformed and has been deleted:`n  $($problems -join "`n  ")"
    }

    $sizeKb = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
    Write-Host "OK  $($entries.Count) entries, $sizeKb KB" -ForegroundColor Green
    Write-Host "    $zipPath"
    Write-Host "    root: $Slug/  (no version suffix -- updates land on top of the old install)"
}
finally {
    Pop-Location
}
