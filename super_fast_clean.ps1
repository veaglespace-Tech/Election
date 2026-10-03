$filePath = "vps_seed_data.sql"
Write-Host "Reading $filePath as text..."
$text = [System.IO.File]::ReadAllText($filePath, [System.Text.Encoding]::UTF8)

$insertCountBefore = ([regex]::Matches($text, "(?i)insert\s+into")).Count
Write-Host "Original 'INSERT INTO' statements: $insertCountBefore"

$nullCount = $text.Split("`0").Count - 1
Write-Host "Null chars found: $nullCount"

if ($nullCount -gt 0) {
    Write-Host "Removing null characters..."
    $text = $text.Replace("`0", "")
}

Write-Host "Normalizing CRLF..."
$text = $text.Replace("`r`n", "`n").Replace("`r", "`n")

$insertCountAfter = ([regex]::Matches($text, "(?i)insert\s+into")).Count
Write-Host "Final 'INSERT INTO' statements: $insertCountAfter"

if ($text.Length -gt 0 -and $text[0] -eq [char]0xFEFF) {
    Write-Host "Removing BOM..."
    $text = $text.Substring(1)
}

Write-Host "Saving file..."
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($filePath, $text, $utf8NoBom)

$finalBytes = [System.IO.File]::ReadAllBytes($filePath)
$finalNullCount = 0
if ($text.Contains("`0")) {
    $finalNullCount = 1
}
Write-Host "Final null bytes count: $finalNullCount"
Write-Host "Final file size: $($finalBytes.Length) bytes"
Write-Host "Done!"
