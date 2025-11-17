<# 
.SYNOPSIS
    Uploads the Film Finder project to an FTP server with explicit TLS, showing verbose output for every directory and file.

.DESCRIPTION
    - Reads DEPLOY_* values from .env.deploy or environment variables.
    - Uses System.Net.FtpWebRequest with AUTH TLS + upload.
    - Prints each directory creation and file upload so you can track progress.

.USAGE
    powershell -ExecutionPolicy Bypass -File scripts/deploy.ps1
#>
[CmdletBinding()]
param(
    [string]$EnvFile = ".env.deploy"
)

function Load-EnvFile {
    param([string]$Path)
    $pairs = @{}
    if (-not (Test-Path $Path)) { return $pairs }
    Get-Content $Path | ForEach-Object {
        $line = $_.Trim()
        if ($line -match '^\s*$' -or $line.StartsWith("#")) { return }
        if ($line -notmatch "=") { return }
        $split = $line -split "=", 2
        $key = $split[0].Trim()
        $value = $split[1].Trim().Trim("'`"")
        $pairs[$key] = $value
    }
    return $pairs
}

function Get-DeploySetting {
    param($Key, $Default = "")
    $envValue = [System.Environment]::GetEnvironmentVariable($Key)
    if (-not [string]::IsNullOrWhiteSpace($envValue)) {
        return $envValue
    }
    if ($DeployEnv.ContainsKey($Key)) {
        return $DeployEnv[$Key]
    }
    return $Default
}

function New-AuthTlsRequest {
    param($Uri, $Method)
    $request = [System.Net.FtpWebRequest]::Create($Uri)
    $request.Credentials = $Credentials
    $request.Method = $Method
    $request.EnableSsl = $true
    $request.UsePassive = $UsePassive
    $request.KeepAlive = $false
    $request.ReadWriteTimeout = 120000
    $request.Timeout = 120000
    return $request
}

function Ensure-RemotePath {
    param($Path)
    if ([string]::IsNullOrWhiteSpace($Path)) { return }
    $segments = $Path.Trim("/").Split("/")
    $current = ""
    foreach ($segment in $segments) {
        if ([string]::IsNullOrWhiteSpace($segment)) { continue }
        $current = if ($current) { "$current/$segment" } else { $segment }
        $uri = "$BaseUri/$current"
        Write-Verbose "Ensuring directory $uri"
        try {
            $req = New-AuthTlsRequest -Uri $uri -Method ([System.Net.WebRequestMethods+Ftp]::MakeDirectory)
            $req.GetResponse() | Out-Null
        } catch {
            if (-not $_.Exception.Message.Contains("exists")) {
                Write-Verbose " - create skipped: $($_.Exception.Message)"
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
        $request = New-AuthTlsRequest -Uri $uri -Method ([System.Net.WebRequestMethods+Ftp]::UploadFile)
        $request.ContentLength = $bytes.Length
        $stream = $request.GetRequestStream()
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        $request.GetResponse() | Out-Null
    } catch {
        $message = "Failed to upload {0}: {1}" -f $LocalPath, $_.Exception.Message
        Write-Warning $message
    }
}

[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
[System.Net.ServicePointManager]::ServerCertificateValidationCallback = { return $true }

$DeployEnv = Load-EnvFile -Path $EnvFile
$FtpHost = Get-DeploySetting -Key "DEPLOY_HOST"
$FtpUser = Get-DeploySetting -Key "DEPLOY_USER"
$FtpPass = Get-DeploySetting -Key "DEPLOY_PASS"
$RemoteRoot = Get-DeploySetting -Key "DEPLOY_PATH" -Default "/public_html/filmfinder"
$Port = [int](Get-DeploySetting -Key "DEPLOY_PORT" -Default "21")
$UsePassive = [bool]::Parse((Get-DeploySetting -Key "DEPLOY_PASSIVE" -Default "true"))

if (-not $FtpHost -or -not $FtpUser -or -not $FtpPass) {
    Write-Error "Missing DEPLOY_HOST/USER/PASS. Update $EnvFile or set env vars."
    exit 1
}

$RemoteRoot = $RemoteRoot.Trim("/").Trim()
$BaseUri = if ($RemoteRoot) { "ftp://$FtpHost`:$Port/$RemoteRoot" } else { "ftp://$FtpHost`:$Port" }
$Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)

Write-Host "Deploying to $BaseUri (TLS, passive=$UsePassive)"

$rootPath = (Get-Location).ProviderPath
$files = Get-ChildItem -Recurse -File -Force | Where-Object {
    $full = $_.FullName
    if ($full -like "*\.git\*") { return $false }
    if ($full -like "*\storage\cache\*") { return $false }
    if ($_.Name -match '^\.env' -or $_.Name -match '^deploy\.ps1$' -or $_.Name -match '^deploy\.php$') { return $false }
    return $true
}

Write-Host "Uploading $($files.Count) files..."

foreach ($file in $files) {
    $relative = $file.FullName.Substring($rootPath.Length).TrimStart('\') -replace '\\','/'
    $remoteDir = [System.IO.Path]::GetDirectoryName($relative) -replace '\\','/'
    if ($remoteDir) {
        Ensure-RemotePath -Path $remoteDir
    }
    Upload-File -LocalPath $file.FullName -RemotePath $relative
}

Write-Host "Deployment finished."
