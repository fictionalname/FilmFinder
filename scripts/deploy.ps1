<#
.SYNOPSIS
    Uploads the Film Finder project folder to an FTP host (Jolt.co.uk compatible) using PowerShell.

.DESCRIPTION
    Uses System.Net.FtpWebRequest with explicit TLS, passive mode, and verbose logging so you can see every directory creation and file transfer.
    Reads credentials from environment variables or `.env.deploy` (per the PHP helper) so you can keep secrets out of source control.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts/deploy.ps1

.PARAMETER EnvFile
    Path to a `.env.deploy` file with DEPLOY_* keys (falls back to environment variables when missing).
#>
[CmdletBinding()]
param(
    [string]$EnvFile = ".env.deploy"
)

function Load-DeployEnv {
    param([string]$Path)
    $pairs = @{}
    if (-not (Test-Path $Path)) {
        return $pairs
    }

    Get-Content $Path | ForEach-Object {
        $line = $_.Trim()
        if ([string]::IsNullOrWhiteSpace($line) -or $line.StartsWith("#")) {
            return
        }
        if ($line -notmatch "=") {
            return
        }
        $split = $line -split "=", 2
        $key = $split[0].Trim()
        $value = $split[1].Trim().Trim("'`"")
        $pairs[$key] = $value
    }

    return $pairs
}

function Get-Setting {
    param($Key, $Default = "")
    if ($env[$Key]) {
        return $env[$Key]
    }
    if ($DeployEnv.ContainsKey($Key)) {
        return $DeployEnv[$Key]
    }
    return $Default
}

function New-FtpRequest {
    param($Uri, $Method)
    $request = [System.Net.FtpWebRequest]::Create($Uri)
    $request.Method = $Method
    $request.Credentials = $Credentials
    $request.UseBinary = $true
    $request.UsePassive = $UsePassive
    $request.EnableSsl = $true
    $request.KeepAlive = $false
    $request.Timeout = 120000
    return $request
}

function Ensure-RemoteDirectory {
    param($Path)
    if ([string]::IsNullOrWhiteSpace($Path)) {
        return
    }
    $trimmed = $Path.Trim("/")
    if ($trimmed -eq "") {
        return
    }

    $segments = $trimmed -split "/"
    $accum = ""
    foreach ($segment in $segments) {
        $accum = if ($accum) { "$accum/$segment" } else { $segment }
        $dirUri = "$BaseUri/$accum"
        Write-Verbose "Ensuring remote folder: $accum"
        try {
            $req = New-FtpRequest -Uri $dirUri -Method [System.Net.WebRequestMethods+Ftp]::MakeDirectory
            $req.GetResponse() | Out-Null
        } catch {
            $message = $_.Exception.Response.StatusDescription
            if ($message -notmatch "file exists") {
                Write-Verbose " - skip (probably already exists): $message"
            }
        }
    }
}

function Upload-File {
    param($LocalPath, $RemotePath)
    $uri = "$BaseUri/$RemotePath"
    Write-Host "Uploading $LocalPath -> $uri"
    try {
        $bytes = [System.IO.File]::ReadAllBytes($LocalPath)
        $request = New-FtpRequest -Uri $uri -Method [System.Net.WebRequestMethods+Ftp]::UploadFile
        $request.ContentLength = $bytes.Length
        $stream = $request.GetRequestStream()
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        $request.GetResponse() | Out-Null
    } catch {
        Write-Warning "Failed to upload ${LocalPath}: $($_.Exception.Message)"
    }
}

[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
[System.Net.ServicePointManager]::ServerCertificateValidationCallback = { return $true }

$DeployEnv = Load-DeployEnv -Path $EnvFile
$Host = Get-Setting -Key "DEPLOY_HOST"
$User = Get-Setting -Key "DEPLOY_USER"
$Pass = Get-Setting -Key "DEPLOY_PASS"
$PathValue = Get-Setting -Key "DEPLOY_PATH" -Default "/public_html/filmfinder"
$Port = [int](Get-Setting -Key "DEPLOY_PORT" -Default "21")
$UsePassive = [bool]::Parse((Get-Setting -Key "DEPLOY_PASSIVE" -Default "true"))

if (-not $Host -or -not $User -or -not $Pass) {
    Write-Error "Missing DEPLOY_HOST/USER/PASS. Update $EnvFile or set environment variables."
    exit 1
}

$RemoteRootPath = ($PathValue.Trim()).Trim("/")
if ($RemoteRootPath -eq "." -or $RemoteRootPath -eq "") {
    $RemoteRootPath = ""
}
$BaseUri = "ftp://$Host`:$Port"

$Credentials = New-Object System.Net.NetworkCredential($User, $Pass)

Write-Host "Deploying to $BaseUri (TLS, passive=$UsePassive) -> root '$RemoteRootPath'"

$root = (Get-Location).ProviderPath
$ignoreGit = "\\.git\\"
$ignoreCache = "\\storage\\cache\\"

$files = Get-ChildItem -Recurse -File -Force | Where-Object {
    $local = $_.FullName
    if ($local -like "*$ignoreGit*") { return $false }
    if ($local -like "*$ignoreCache*") { return $false }
    if ($_.Name -match '^(\\.env|deploy\\.ps1|deploy\\.php)$') { return $false }
    return $true
}

$total = $files.Count
Write-Host "Uploading $total files..."

[int]$count = 0
foreach ($file in $files) {
    $relative = $file.FullName.Substring($root.Length).TrimStart('\')
    $remoteRelative = ($relative -replace '\\','/')
    $remoteDir = [System.IO.Path]::GetDirectoryName($remoteRelative) -replace '\\','/'
    $remoteDir = $remoteDir -replace '^(\./)+',''
    if ($remoteDir -and $RemoteRootPath) {
        $fullDir = "$RemoteRootPath/$remoteDir"
    } elseif ($remoteDir) {
        $fullDir = $remoteDir
    } elseif ($RemoteRootPath) {
        $fullDir = $RemoteRootPath
    } else {
        $fullDir = ""
    }

    Ensure-RemoteDirectory -Path $fullDir
    $perFileName = [System.IO.Path]::GetFileName($remoteRelative)
    $remoteFilePath = if ($fullDir) { "$fullDir/$perFileName" } else { $perFileName }
    Upload-File -LocalPath $file.FullName -RemotePath ($remoteFilePath.TrimStart("/"))
    $count++
}

Write-Host "Uploaded $count files."

