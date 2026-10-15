Add-Type -AssemblyName System.Drawing
$img = [System.Drawing.Image]::FromFile((Join-Path (Get-Location) 'icon.png'))
# Candidate face is roughly between X: 320 to 580, Y: 275 to 580
$w = 260
$h = 315
$bmp = New-Object System.Drawing.Bitmap $w, $h
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$dest = New-Object System.Drawing.Rectangle 0, 0, $w, $h
$g.DrawImage($img, $dest, 320, 280, $w, $h, [System.Drawing.GraphicsUnit]::Pixel)
$g.Dispose()
$img.Dispose()
$outPath = Join-Path (Get-Location) 'images\candidate_photo.png'
$bmp.Save($outPath, [System.Drawing.Imaging.ImageFormat]::Png)
$bmp.Dispose()
Write-Output "Done tighter crop"
