<#
  Gives the site a new version number so browsers and Cloudflare fetch fresh files instead of cached copies.

  Run it from the project folder before you upload changed files:
      powershell -ExecutionPolicy Bypass -File tools\bump-version.ps1

  It rewrites the ?v=... on the CSS/JS links and on the card pictures in every page, and the ASSET_VERSION line in the
  scripts that load the hole pictures. Then upload the changed pages and files as usual (the pages and the scripts always
  change when the version changes).

  The version looks like 20261002-1: today's date, plus a counter that goes up each time you run it on the same day.
#>

$root = Split-Path -Parent $PSScriptRoot
$utf8 = New-Object System.Text.UTF8Encoding($false)

# Work out the next version from the one currently in index.html.
$index = [System.IO.File]::ReadAllText((Join-Path $root 'index.html'))
$today = Get-Date -Format 'yyyyMMdd'
$counter = 1
if ($index -match 'styles\.css\?v=(\d{8})-(\d+)' -and $Matches[1] -eq $today) { $counter = [int]$Matches[2] + 1 }
$version = "$today-$counter"

$pages = Get-ChildItem -Path $root -Filter '*.html' | Where-Object { $_.Name -ne 'palette-preview.html' }
foreach ($page in $pages) {
    $text = [System.IO.File]::ReadAllText($page.FullName)
    $new = [regex]::Replace($text, '(assets/(?:css|js)/[\w-]+\.(?:css|js))(\?v=[\w-]+)?', ('$1?v=' + $version))
    $new = [regex]::Replace($new, '(assets/images/holes/vector/hole-\d-card\.svg)(\?v=[\w-]+)?', ('$1?v=' + $version))
    if ($new -ne $text) { [System.IO.File]::WriteAllText($page.FullName, $new, $utf8) }
}

foreach ($script in Get-ChildItem -Path (Join-Path $root 'assets\js') -Filter '*.js') {
    $text = [System.IO.File]::ReadAllText($script.FullName)
    $new = [regex]::Replace($text, 'var ASSET_VERSION = "[\w-]+";', ('var ASSET_VERSION = "' + $version + '";'))
    if ($new -ne $text) { [System.IO.File]::WriteAllText($script.FullName, $new, $utf8) }
}

Write-Host "Site version is now $version"
Write-Host "Upload: every .html page and assets\js\*.js (they changed), plus any other files you edited."
