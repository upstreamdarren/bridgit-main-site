$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$manifestPath = Join-Path $projectRoot "src\data\partners.json"
$destination = Join-Path $projectRoot "public\images\clients"

New-Item -ItemType Directory -Force -Path $destination | Out-Null

$partners = Get-Content -Raw -Encoding utf8 $manifestPath | ConvertFrom-Json
foreach ($partner in $partners) {
    $target = Join-Path $destination $partner.file
    Invoke-WebRequest -UseBasicParsing -Uri $partner.source -OutFile $target
    Write-Host "Downloaded $($partner.name)"
}

Write-Host "Synced $($partners.Count) client and partner logos."
