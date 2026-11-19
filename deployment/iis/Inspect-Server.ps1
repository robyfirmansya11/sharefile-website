# Read-only inspection. Run locally on ASI01 from PowerShell as administrator.
# Does not install IIS, change permissions, access SMB contents, or change AD.
$ErrorActionPreference = 'Stop'
Write-Output '=== Server ==='
Get-CimInstance Win32_ComputerSystem | Select-Object Name, Domain, DomainRole
Write-Output '=== Windows features ==='
if (Get-Command Get-WindowsFeature -ErrorAction SilentlyContinue) {
    Get-WindowsFeature Web-Server, Web-Windows-Auth, Web-CGI, Web-Mgmt-Console | Select-Object Name, InstallState
}
Write-Output '=== AD domain (when module is available) ==='
if (Get-Command Get-ADDomain -ErrorAction SilentlyContinue) {
    Get-ADDomain | Select-Object DNSRoot, NetBIOSName
}
Write-Output '=== Share configuration (no file listing) ==='
if (Get-Command Get-SmbShare -ErrorAction SilentlyContinue) {
    Get-SmbShare -Name SMB -ErrorAction SilentlyContinue | Select-Object Name, Path, FolderEnumerationMode
}
Write-Output '=== IIS bindings (when IIS is installed) ==='
if (Get-Module -ListAvailable WebAdministration) {
    Import-Module WebAdministration
    Get-WebBinding | Select-Object protocol, bindingInformation
}
