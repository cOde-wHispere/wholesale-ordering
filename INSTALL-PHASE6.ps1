$ErrorActionPreference = 'Stop'

$PluginRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $PluginRoot

$pluginFile = Join-Path $PluginRoot 'src\Plugin.php'

if (-not (Test-Path $pluginFile)) {
    throw "src\Plugin.php was not found. Run this script from the wholesale-ordering plugin directory."
}

$source = Get-Content -Raw -Encoding UTF8 $pluginFile

if ($source -notmatch 'WholesaleOrdering\\Frontend\\Frontend') {
    $source = $source -replace (
        "use WholesaleOrdering\\Orders\\OrderIntegration;\r?\n"
    ), (
        "use WholesaleOrdering\Orders\OrderIntegration;" + [Environment]::NewLine +
        "use WholesaleOrdering\Frontend\Frontend;" + [Environment]::NewLine +
        "use WholesaleOrdering\Account\Account;" + [Environment]::NewLine
    )
}

if ($source -notmatch '\(new Frontend\(\)\)->register\(\)') {
    $source = $source -replace (
        "        PricingLeakageProtection::register\(\);\r?\n"
    ), (
        "        PricingLeakageProtection::register();" + [Environment]::NewLine +
        [Environment]::NewLine +
        "        /*" + [Environment]::NewLine +
        "         * Phase 6 customer-facing storefront and account experience." + [Environment]::NewLine +
        "         * WooCommerce remains the catalogue, cart, checkout and order engine." + [Environment]::NewLine +
        "         */" + [Environment]::NewLine +
        "        Frontend::register();" + [Environment]::NewLine +
        "        Account::register();" + [Environment]::NewLine
    )
}

$backup = "$pluginFile.phase6.bak"
if (-not (Test-Path $backup)) {
    Copy-Item $pluginFile $backup
}

Set-Content -Path $pluginFile -Value $source -Encoding UTF8

Write-Host "Phase 6 bootstrap wiring complete." -ForegroundColor Green
Write-Host "Backup: $backup"
Write-Host "Added: Frontend::register() and Account::register()"
