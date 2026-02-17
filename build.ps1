$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# 0. Find Git from GitHub Desktop to satisfy 'gh' requirements
$githubDesktopPath = Join-Path $env:LOCALAPPDATA "GitHubDesktop"
$gitPath = Get-ChildItem -Path $githubDesktopPath -Filter "git.exe" -Recurse | Select-Object -First 1
if ($gitPath) {
    $gitDir = Split-Path $gitPath.FullName
    $env:Path = "$gitDir;$env:Path"
}

# Hämta nuvarande branch för att veta var vi gör release ifrån
$currentBranch = (git rev-parse --abbrev-ref HEAD).Trim()

$phpFile = "whmcs_price.php"
if (Test-Path $phpFile) {
    $phpContent = Get-Content $phpFile -Raw
    # Uppdaterad Regex: Fångar siffror + eventuella suffix som -b.2 eller -rc.3
    if ($phpContent -match 'Version:\s+([\d\.]+(?:-[\w\.]+)*)') {
        $version = $Matches[1]
        Write-Host "Detected version from ${phpFile}: $version (Branch: $currentBranch)" -ForegroundColor Green
    } else {
        throw "Could not find a valid SemVer string in $phpFile (e.g., 1.2.0 or 1.2.0-rc.1)"
    }
} else {
    throw "Main plugin file $phpFile not found!"
}

# --- NYTT: BRANCH & VERSION GUARD ---
$isPreRelease = $version -match "-(b|rc|beta|alpha)"

if ($currentBranch -eq "main" -and $isPreRelease) {
    Write-Host "WARNING: You are trying to build a PRE-RELEASE ($version) on the MAIN branch." -ForegroundColor Red
}

if ($currentBranch -eq "beta" -and -not $isPreRelease) {
    Write-Host "WARNING: You are building a STABLE version ($version) on the BETA branch." -ForegroundColor Yellow
}

# Configuration
$pluginName = "whmcs-price"
$zipName = "$pluginName-v$version.zip"
$buildDir = ".\build_temp"
$pluginDir = "$buildDir\$pluginName"

# --- 1. EXTRAHERA SENASTE NYHETER FRÅN CHANGELOG.MD ---
$changelogFile = "CHANGELOG.md"
$readmeFile = "readme.txt"
$latestNotes = ""

if (Test-Path $changelogFile) {
    $content = Get-Content $changelogFile -Raw
    # Regex för att hitta allt under den aktuella versionens rubrik fram till nästa rubrik
    $versionEscaped = [regex]::Escape($version)
    if ($content -match "(?s)## \[$versionEscaped\].*?(?=\n## \[|$)") {
        $latestNotes = $Matches[0].Trim()
        Write-Host "Extracted notes for v$version from CHANGELOG.md" -ForegroundColor Gray
    } else {
        Write-Host "Warning: Could not find changelog entry for v$version" -ForegroundColor Yellow
    }
}

# --- 2. UPPDATERA README.TXT AUTOMATISKT ---
if ($latestNotes -and (Test-Path $readmeFile)) {
    Write-Host "Syncing Changelog to readme.txt..." -ForegroundColor Cyan
    
    # Gör om Markdown-format till WordPress-format
    # 1. Rubrik: ## [2.2.2] -> = 2.2.2 =
    $wpFormattedNotes = $latestNotes -replace "## \[$versionEscaped\]", "= $version ="
    # 2. Ta bort eventuella tomma underrubriker som ### Fixed etc (valfritt, WordPress gillar rena listor)
    $wpFormattedNotes = $wpFormattedNotes -replace "### .*", ""
    # 3. Listpunkter: - punkt -> * punkt
    $wpFormattedNotes = ($wpFormattedNotes -split "`r?`n" | ForEach-Object {
        if ($_.Trim().StartsWith("- ")) {
            $_.Replace("- ", "* ")
        } else {
            $_
        }
    }) -join "`r`n"
    # 4. Rensa dubbla tomma rader
    $wpFormattedNotes = $wpFormattedNotes.Trim()

    $readmeContent = Get-Content $readmeFile -Raw
    
    # Hitta sektionen == Changelog == och ersätt eller lägg till överst
    if ($readmeContent -match "(?s)== Changelog ==.*") {
        # Kolla om versionen redan finns i readme för att undvika dubletter
        if ($readmeContent -notmatch [regex]::Escape("= $version =")) {
            $newChangelogBlock = "== Changelog ==`r`n$wpFormattedNotes`r`n`r`n"
            $newReadme = $readmeContent -replace "== Changelog ==\s*", $newChangelogBlock
            $newReadme | Set-Content $readmeFile -Encoding UTF8
            Write-Host "Updated readme.txt with latest changelog entries." -ForegroundColor Green
        } else {
            Write-Host "Changelog for $version already exists in readme.txt. Skipping update." -ForegroundColor Yellow
        }
    }
}

# --- KONTROLL AV SPRÅKFILER ---
Write-Host "Checking language files..." -ForegroundColor Cyan
$potFile = "languages/whmcs-price.pot"
if (Test-Path $potFile) {
    $potContent = Get-Content $potFile -Raw
    # Uppdatera versionen i POT-filen så den matchar projektet
    $updatedPot = $potContent -replace "Project-Id-Version: whmcs-price [\d\.]+", "Project-Id-Version: whmcs-price $version"
    $updatedPot | Set-Content $potFile -Encoding UTF8
    Write-Host "Updated version in $potFile to $version" -ForegroundColor Green
}

# --- NYTT: BUILD STEP FÖR BLOCK/ASSETS ---
$packageJson = "package.json"
if (Test-Path $packageJson) {
    Write-Host "Detected package.json — running npm build pipeline..." -ForegroundColor Cyan

    $npmCmd = Get-Command npm -ErrorAction SilentlyContinue
    if (-not $npmCmd) {
        throw "npm not found in PATH. Install Node.js (LTS) and retry."
    }

    # Prefer reproducible installs
    if (Test-Path "package-lock.json") {
        Write-Host "Running: npm ci" -ForegroundColor DarkGray
        npm ci
    } else {
        Write-Host "Running: npm install" -ForegroundColor DarkGray
        npm install
    }

    Write-Host "Running: npm run build" -ForegroundColor DarkGray
    npm run build

    # Validera att förväntad output faktiskt finns
    if (-not (Test-Path "blocks/build")) {
        throw "Build output saknas: blocks/build. Kontrollera webpack output-path och npm run build."
    }

    Write-Host "Build output verified: blocks/build" -ForegroundColor Green
} else {
    Write-Host "No package.json detected — skipping npm build." -ForegroundColor Gray
}

# För zip-steget: peka på rätt runtime-assets (ert case)
$assetOutputDir = $null
if (Test-Path "blocks/build") { $assetOutputDir = "blocks/build" }

if ($assetOutputDir) {
    Write-Host "Detected build output folder: $assetOutputDir" -ForegroundColor Green
} else {
    Write-Host "No build output folder detected. Expected: blocks/build" -ForegroundColor Yellow
}

Write-Host "--- Building Package v$version ---" -ForegroundColor Cyan

# 1. Clean up
if (Test-Path $buildDir) { Remove-Item -Recurse -Force $buildDir }
if (Test-Path $zipName) { Remove-Item -Force $zipName }

# 2. Create structure
New-Item -ItemType Directory -Path $pluginDir

# 3. Copy files
$filesToInclude = @(
    "includes",
    "languages",
    "blocks",
    "whmcs_price.php",
    "index.php",
    "readme.txt",
    "README.md",
    "CHANGELOG.md",
    "LICENSE"
)

# Fail fast: require LICENSE for WordPress.org distribution
if (-not (Test-Path "LICENSE")) {
    throw "LICENSE file is missing. Add LICENSE at repo root before publishing to WordPress.org."
}

foreach ($item in $filesToInclude) {
    if (Test-Path $item) {
        $dest = Join-Path $pluginDir $item
        Copy-Item -Path $item -Destination $dest -Recurse

        # Languages: keep .mo (runtime), remove only .po (source)
        if ($item -eq "languages") {
            Get-ChildItem -Path $dest -Include *.po -Recurse | Remove-Item -Force
            Write-Host "Cleaned .po files from languages folder (kept .mo)." -ForegroundColor Gray
        }
    } else {
        Write-Host "Skipped missing item: $item" -ForegroundColor DarkGray
    }
}

# 4. Create the Zip (7-Zip)
# --- Zip creation (prefer installed 7-Zip in Program Files) ---
$sevenZip = Join-Path $env:ProgramFiles "7-Zip\7z.exe"
if (-not (Test-Path $sevenZip)) {
    $sevenZip = Join-Path ${env:ProgramFiles(x86)} "7-Zip\7z.exe"
}

if (-not (Test-Path $sevenZip)) {
    throw "7-Zip not found at Program Files. Install 7-Zip or fix installation."
}

# Verify 7-Zip is runnable
& $sevenZip -h | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "7-Zip found but not runnable (exit code: $LASTEXITCODE)."
}

Write-Host "Verified 7-Zip: $sevenZip" -ForegroundColor Green

$zipFullPath = Join-Path $PSScriptRoot $zipName

Push-Location $buildDir
try {
    # Use call operator (&) instead of Start-Process to avoid path/argument weirdness
    & $sevenZip a -tzip $zipFullPath "$pluginName\*" -mx=9 -y | Out-Host
    if ($LASTEXITCODE -ne 0) { throw "7-Zip failed (exit code: $LASTEXITCODE)." }
}
finally { Pop-Location }

Remove-Item -Recurse -Force $buildDir
Write-Host "Success! Local zip created: $zipName" -ForegroundColor Green

# 5. GitHub CLI Release
$answer = Read-Host "Do you want to create a Release on GitHub with this ZIP file? (y/n)"
if ($answer -eq 'y') {
    if (Get-Command "gh" -ErrorAction SilentlyContinue) {
        
        # Förbered release-noteringar (Använder changelog-texten om den finns)
        $releaseNotes = if ($latestNotes) { $latestNotes } else { "Release for version $version" }
        $releaseNotes += "`n`nFull changelog: https://github.com/morno/whmcs-price/blob/$currentBranch/CHANGELOG.md"

        $releaseFlags = @("create", "v$version", "$zipName", "--title", "Version $version", "--notes", $releaseNotes, "--target", "$currentBranch")
        
        if ($version -match "-(b|rc|beta|alpha)") {
            Write-Host "Detected pre-release version. Adding --prerelease flag." -ForegroundColor Yellow
            $releaseFlags += "--prerelease"
        }

        Write-Host "Creating GitHub Release for version ${version} on branch ${currentBranch}..." -ForegroundColor Yellow
        gh $releaseFlags
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "Release successfully uploaded to GitHub!" -ForegroundColor Green
        } else {
            Write-Host "Upload failed." -ForegroundColor Red
        }
    } else {
        Write-Host "Could not find 'gh.exe'." -ForegroundColor Red
    }
}