param (
    [string]$FilePath = "$PSScriptRoot/../database/vps_seed_data.sql"
)

if (-not (Test-Path $FilePath)) {
    if (Test-Path "database/vps_seed_data.sql") {
        $FilePath = "database/vps_seed_data.sql"
    } elseif (Test-Path "vps_seed_data.sql") {
        $FilePath = "vps_seed_data.sql"
    } else {
        Write-Error "File not found: $FilePath"
        exit 1
    }
}

$resolvedPath = (Resolve-Path $FilePath).Path
Write-Host "Target SQL file: $resolvedPath"
Write-Host "Reading file..."
$text = [System.IO.File]::ReadAllText($resolvedPath, [System.Text.Encoding]::UTF8)

$insertCountBefore = ([regex]::Matches($text, '(?i)\bINSERT\s+INTO\b')).Count
Write-Host "Original 'INSERT INTO' statements: $insertCountBefore"

$originalLength = $text.Length
if ($text.Contains("`0")) {
    Write-Host "Removing null characters..."
    $text = $text.Replace("`0", "")
    $nullCount = $originalLength - $text.Length
    Write-Host "Removed $nullCount null characters."
} else {
    Write-Host "No null characters found."
}

Write-Host "Normalizing line endings..."
$text = $text.Replace("`r`n", "`n").Replace("`r", "`n")

if ($text.Length -gt 0 -and $text[0] -eq [char]0xFEFF) {
    Write-Host "Removing UTF-8 BOM..."
    $text = $text.Substring(1)
}

$insertCountAfter = ([regex]::Matches($text, '(?i)\bINSERT\s+INTO\b')).Count
Write-Host "Final 'INSERT INTO' statements: $insertCountAfter"

Write-Host "Saving file (UTF-8 without BOM)..."
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($resolvedPath, $text, $utf8NoBom)

$finalFileInfo = New-Object System.IO.FileInfo($resolvedPath)
Write-Host "Final file size: $($finalFileInfo.Length) bytes"
Write-Host "Done!"
