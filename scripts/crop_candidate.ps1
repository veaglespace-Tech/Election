Add-Type -AssemblyName System.Drawing
$img = [System.Drawing.Image]::FromFile((Join-Path (Get-Location) 'icon.png'))
$w = 310
$h = 325
$bmp = New-Object System.Drawing.Bitmap $w, $h
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$dest = New-Object System.Drawing.Rectangle 0, 0, $w, $h
$g.DrawImage($img, $dest, 350, 275, $w, $h, [System.Drawing.GraphicsUnit]::Pixel)
$g.Dispose()
$img.Dispose()
$outPath = Join-Path (Get-Location) 'images\candidate_photo.png'
$bmp.Save($outPath, [System.Drawing.Imaging.ImageFormat]::Png)
$bmp.Dispose()
Write-Output "Done: $(Test-Path $outPath)"
