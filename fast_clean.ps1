$filePath = "vps_seed_data.sql"
Write-Host "Reading $filePath as text..."
$text = [System.IO.File]::ReadAllText($filePath, [System.Text.Encoding]::UTF8)

$originalLength = $text.Length
Write-Host "Removing null characters..."
$text = $text.Replace("`0", "")
$nullCount = $originalLength - $text.Length
Write-Host "Removed $nullCount null characters."

Write-Host "Normalizing CRLF..."
$text = $text.Replace("`r`n", "`n").Replace("`r", "`n")

Write-Host "Counting INSERT INTO..."
$insertCount = ([regex]::Matches($text, "(?i)INSERT\s+INTO\s+`?electors`?")).Count
Write-Host "Final INSERT count: $insertCount"

Write-Host "Saving file..."
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($filePath, $text, $utf8NoBom)

Write-Host "Verifying..."
$finalText = [System.IO.File]::ReadAllText($filePath, [System.Text.Encoding]::UTF8)
if ($finalText.Contains("`0")) {
    Write-Host "Final null bytes count: >0 (FAILED)"
} else {
    Write-Host "Final null bytes count: 0"
}

$finalFileInfo = New-Object System.IO.FileInfo($filePath)
Write-Host "Final file size: $($finalFileInfo.Length) bytes"
Write-Host "Done!"
