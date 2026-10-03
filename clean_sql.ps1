$filePath = "vps_seed_data.sql"
Write-Host "Reading $filePath..."
$bytes = [System.IO.File]::ReadAllBytes($filePath)
Write-Host "Original size: $($bytes.Length) bytes"

# Use MemoryStream for faster processing than List
$ms = New-Object System.IO.MemoryStream
$nullCount = 0

for ($i = 0; $i -lt $bytes.Length; $i++) {
    if ($bytes[$i] -eq 0) {
        $nullCount++
    } else {
        $ms.WriteByte($bytes[$i])
    }
}
Write-Host "Null bytes found: $nullCount"

$cleanedArray = $ms.ToArray()

# Check for UTF-8 BOM
$startIndex = 0
if ($cleanedArray.Length -ge 3 -and $cleanedArray[0] -eq 0xEF -and $cleanedArray[1] -eq 0xBB -and $cleanedArray[2] -eq 0xBF) {
    Write-Host "UTF-8 BOM found. Removing it."
    $startIndex = 3
}

$textBytes = New-Object byte[] ($cleanedArray.Length - $startIndex)
[System.Array]::Copy($cleanedArray, $startIndex, $textBytes, 0, $textBytes.Length)

Write-Host "Decoding to UTF-8 text..."
$text = [System.Text.Encoding]::UTF8.GetString($textBytes)

# Normalize CRLF
$text = $text -replace "`r`n", "`n" -replace "`r", "`n"

# Count inserts
$insertCount = ([regex]::Matches($text, "(?i)INSERT\s+INTO\s+`?electors`?")).Count
Write-Host "Final INSERT count: $insertCount"

Write-Host "Saving file..."
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($filePath, $text, $utf8NoBom)

# Verify
$finalBytes = [System.IO.File]::ReadAllBytes($filePath)
$finalNullCount = 0
for ($i = 0; $i -lt $finalBytes.Length; $i++) {
    if ($finalBytes[$i] -eq 0) {
        $finalNullCount++
    }
}
Write-Host "Final null bytes count: $finalNullCount"
Write-Host "Final file size: $($finalBytes.Length) bytes"
Write-Host "Done!"
