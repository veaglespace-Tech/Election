$filePath = "vps_seed_data.sql"
$bytes = [System.IO.File]::ReadAllBytes($filePath)
Write-Host "Original bytes: $($bytes.Length)"

# If it's UTF-16LE, bytes[0]=0xFF and bytes[1]=0xFE, OR every second byte is 0x00 for ASCII text.
if ($bytes.Length -ge 2 -and (($bytes[0] -eq 0xFF -and $bytes[1] -eq 0xFE) -or $bytes[1] -eq 0x00)) {
    Write-Host "File appears to be UTF-16LE"
    $text = [System.Text.Encoding]::Unicode.GetString($bytes)
} elseif ($bytes.Length -ge 2 -and (($bytes[0] -eq 0xFE -and $bytes[1] -eq 0xFF) -or $bytes[0] -eq 0x00)) {
    Write-Host "File appears to be UTF-16BE"
    $text = [System.Text.Encoding]::BigEndianUnicode.GetString($bytes)
} else {
    Write-Host "Decoding as UTF-8 or standard..."
    $text = [System.Text.Encoding]::UTF8.GetString($bytes)
}

Write-Host "Text length: $($text.Length)"
Write-Host "First 100 characters:"
Write-Host $text.Substring(0, [math]::Min($text.Length, 100))

# Find insert count
$insertCount = ([regex]::Matches($text, "(?i)insert\s+into")).Count
Write-Host "Found $insertCount 'INSERT INTO' statements."

# To clean the file:
# The user wants "remove all NULL bytes" and "UTF-8 encoding".
# But if it's UTF-16, the null bytes ARE the encoding. Removing them from UTF-16 makes it ASCII-ish, but destroys Marathi characters.
# The correct way to "remove null bytes and make it standard UTF-8" is to decode it as UTF-16 first (which consumes the null bytes naturally for ascii), and then encode it as UTF-8 (which won't have null bytes unless there are actual U+0000 characters).
# Are there actual U+0000 characters in the decoded text?
$actualNullCount = 0
for ($i=0; $i -lt $text.Length; $i++) { if ($text[$i] -eq [char]0) { $actualNullCount++ } }
Write-Host "Actual U+0000 chars in text: $actualNullCount"

if ($actualNullCount -gt 0) {
    Write-Host "Removing actual null chars from text..."
    $text = $text.Replace("`0", "")
}

# Normalize CRLF
Write-Host "Normalizing CRLF..."
$text = $text.Replace("`r`n", "`n").Replace("`r", "`n")

Write-Host "Saving to final file as UTF-8 (no BOM)..."
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($filePath, $text, $utf8NoBom)

$finalBytes = [System.IO.File]::ReadAllBytes($filePath)
$finalNullCount = ($finalBytes | Where-Object { $_ -eq 0 }).Count
Write-Host "Final null bytes count in file: $finalNullCount"
Write-Host "Final file size: $($finalBytes.Length)"
