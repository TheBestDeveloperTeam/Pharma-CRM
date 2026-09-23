$baseUrl = "http://localhost:8000"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$results = [System.Collections.Generic.List[PSCustomObject]]::new()

function Record-Result {
    param(
        [string]$TestId,
        [string]$Module,
        [string]$Role,
        [string]$Description,
        [string]$Endpoint,
        [int]$HttpStatus,
        [bool]$Success,
        [string]$Detail
    )
    $obj = [PSCustomObject]@{
        ID = $TestId
        Module = $Module
        Role = $Role
        Description = $Description
        Endpoint = $Endpoint
        Status = $HttpStatus
        Success = $Success
        Detail = $Detail
    }
    $results.Add($obj)
    $statusColor = if ($Success) { "Green" } else { "Red" }
    Write-Host "[$TestId] ($Role) $Description -> Status: $HttpStatus | Success: $Success" -ForegroundColor $statusColor
    if (-not $Success) {
        Write-Host "   Detail: $Detail" -ForegroundColor DarkRed
    }
}

function Invoke-Api {
    param(
        [string]$Method,
        [string]$Path,
        [hashtable]$Headers = @{},
        $Body = $null
    )
    $url = "$baseUrl$Path"
    $h = @{ "accept" = "application/json" }
    foreach ($k in $Headers.Keys) { $h[$k] = $Headers[$k] }

    $bodyStr = $null
    if ($Body -ne $null) {
        if ($Body -is [string]) { $bodyStr = $Body }
        else { $bodyStr = ($Body | ConvertTo-Json -Compress -Depth 10) }
        $h["Content-Type"] = "application/json"
    }

    try {
        $params = @{
            Uri = $url
            Method = $Method
            Headers = $h
            UseBasicParsing = $true
        }
        if ($bodyStr -ne $null) { $params["Body"] = $bodyStr }
        $resp = Invoke-WebRequest @params
        $json = $null
        try { $json = $resp.Content | ConvertFrom-Json } catch {}
        return @{
            StatusCode = [int]$resp.StatusCode
            Content = $resp.Content
            Json = $json
            Error = $null
        }
    } catch [System.Net.WebException] {
        $resp = $_.Exception.Response
        $code = 0
        $content = ""
        if ($resp -ne $null) {
            $code = [int]$resp.StatusCode
            $stream = $resp.GetResponseStream()
            $reader = [System.IO.StreamReader]::new($stream)
            $content = $reader.ReadToEnd()
        }
        $json = $null
        try { $json = $content | ConvertFrom-Json } catch {}
        return @{
            StatusCode = $code
            Content = $content
            Json = $json
            Error = $_.Exception.Message
        }
    } catch {
        return @{
            StatusCode = 0
            Content = ""
            Json = $null
            Error = $_.ToString()
        }
    }
}

Write-Host "=== Starting Live CRM API Multi-Role Comprehensive Verification ===" -ForegroundColor Cyan

# -------------------------------------------------------------
# Module 1: System Health & Public APIs
# -------------------------------------------------------------
$t1 = Invoke-Api -Method "GET" -Path "/api/v1/health"
Record-Result -TestId "SYS-01" -Module "System" -Role "Anonymous" -Description "API Health check" -Endpoint "/api/v1/health" `
    -HttpStatus $t1.StatusCode -Success ($t1.StatusCode -eq 200 -and $t1.Json.success -eq $true) -Detail $t1.Content

$t2 = Invoke-Api -Method "GET" -Path "/api/v1/ready"
Record-Result -TestId "SYS-02" -Module "System" -Role "Anonymous" -Description "API Readiness & DB check" -Endpoint "/api/v1/ready" `
    -HttpStatus $t2.StatusCode -Success ($t2.StatusCode -eq 200 -and $t2.Json.success -eq $true) -Detail $t2.Content

$t3 = Invoke-Api -Method "GET" -Path "/api/v1/geo/states"
Record-Result -TestId "SYS-03" -Module "System" -Role "Anonymous" -Description "Geo states master lookup" -Endpoint "/api/v1/geo/states" `
    -HttpStatus $t3.StatusCode -Success ($t3.StatusCode -eq 200 -and $t3.Json.success -eq $true) -Detail $t3.Content

$t4 = Invoke-Api -Method "GET" -Path "/api/v1/geo/districts?state_ref=STATE-MAH"
Record-Result -TestId "SYS-04" -Module "System" -Role "Anonymous" -Description "Geo districts lookup" -Endpoint "/api/v1/geo/districts" `
    -HttpStatus $t4.StatusCode -Success ($t4.StatusCode -eq 200) -Detail $t4.Content

# -------------------------------------------------------------
# Module 2: Authentication Workflow for all 4 Roles
# -------------------------------------------------------------
# 2.1 Super Admin Auth
$authSuper = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{
    grant_type = "password"
    client_id = "crm-super"
    email = "super@pharmacrm.local"
    password = "Password@123"
}
$superToken = $authSuper.Json.data.access_token
Record-Result -TestId "AUTH-01" -Module "Auth" -Role "SUPER_ADMIN" -Description "OAuth token grant for Super Admin" -Endpoint "/api/v1/oauth/token" `
    -HttpStatus $authSuper.StatusCode -Success ($authSuper.StatusCode -eq 200 -and [string]::IsNullOrEmpty($superToken) -eq $false) -Detail $authSuper.Content

# 2.2 Franchise Admin Auth
$authAdmin = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{
    grant_type = "password"
    client_id = "crm-admin"
    email = "admin@pharmacrm.local"
    password = "Password@123"
    franchise_code = "MUMBAI"
}
$adminToken = $authAdmin.Json.data.access_token
Record-Result -TestId "AUTH-02" -Module "Auth" -Role "FRANCHISE_ADMIN" -Description "OAuth token grant for Franchise Admin" -Endpoint "/api/v1/oauth/token" `
    -HttpStatus $authAdmin.StatusCode -Success ($authAdmin.StatusCode -eq 200 -and [string]::IsNullOrEmpty($adminToken) -eq $false) -Detail $authAdmin.Content

# 2.3 Sales Rep Auth
$authSales = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{
    grant_type = "password"
    client_id = "crm-sales"
    email = "sales@pharmacrm.local"
    password = "Password@123"
    franchise_code = "MUMBAI"
}
$salesToken = $authSales.Json.data.access_token
Record-Result -TestId "AUTH-03" -Module "Auth" -Role "SALES" -Description "OAuth token grant for Sales Representative" -Endpoint "/api/v1/oauth/token" `
    -HttpStatus $authSales.StatusCode -Success ($authSales.StatusCode -eq 200 -and [string]::IsNullOrEmpty($salesToken) -eq $false) -Detail $authSales.Content

# 2.4 Distributor Auth
$authDistributor = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{
    grant_type = "password"
    client_id = "crm-portal"
    email = "portal@pharmacrm.local"
    password = "Password@123"
    franchise_code = "MUMBAI"
}
$distToken = $authDistributor.Json.data.access_token
Record-Result -TestId "AUTH-04" -Module "Auth" -Role "DISTRIBUTOR" -Description "OAuth token grant for Distributor Portal" -Endpoint "/api/v1/oauth/token" `
    -HttpStatus $authDistributor.StatusCode -Success ($authDistributor.StatusCode -eq 200 -and [string]::IsNullOrEmpty($distToken) -eq $false) -Detail $authDistributor.Content

# -------------------------------------------------------------
# Module 3: Super Admin Platform Level Workflows
# -------------------------------------------------------------
$superHeaders = @{ "Authorization" = "Bearer $superToken" }

$s1 = Invoke-Api -Method "GET" -Path "/api/v1/super/dashboard/stats" -Headers $superHeaders
Record-Result -TestId "SUP-01" -Module "SuperAdmin" -Role "SUPER_ADMIN" -Description "Fetch global platform dashboard metrics" -Endpoint "/api/v1/super/dashboard/stats" `
    -HttpStatus $s1.StatusCode -Success ($s1.StatusCode -eq 200 -and $s1.Json.success -eq $true) -Detail $s1.Content

$s2 = Invoke-Api -Method "GET" -Path "/api/v1/super/organizations" -Headers $superHeaders
Record-Result -TestId "SUP-02" -Module "SuperAdmin" -Role "SUPER_ADMIN" -Description "List platform master organizations" -Endpoint "/api/v1/super/organizations" `
    -HttpStatus $s2.StatusCode -Success ($s2.StatusCode -eq 200 -and $s2.Json.success -eq $true) -Detail $s2.Content

$s3 = Invoke-Api -Method "GET" -Path "/api/v1/super/franchises" -Headers $superHeaders
Record-Result -TestId "SUP-03" -Module "SuperAdmin" -Role "SUPER_ADMIN" -Description "List all active franchises" -Endpoint "/api/v1/super/franchises" `
    -HttpStatus $s3.StatusCode -Success ($s3.StatusCode -eq 200 -and $s3.Json.success -eq $true) -Detail $s3.Content

$s4 = Invoke-Api -Method "GET" -Path "/api/v1/super/audit" -Headers $superHeaders
Record-Result -TestId "SUP-04" -Module "SuperAdmin" -Role "SUPER_ADMIN" -Description "View security and action audit logs" -Endpoint "/api/v1/super/audit" `
    -HttpStatus $s4.StatusCode -Success ($s4.StatusCode -eq 200 -and $s4.Json.success -eq $true) -Detail $s4.Content

# -------------------------------------------------------------
# Module 4: Franchise Admin Catalog & Masters Management
# -------------------------------------------------------------
$adminHeaders = @{ "Authorization" = "Bearer $adminToken" }

$adm1 = Invoke-Api -Method "GET" -Path "/api/v1/admin/categories" -Headers $adminHeaders
Record-Result -TestId "ADM-01" -Module "Catalog" -Role "FRANCHISE_ADMIN" -Description "List product categories" -Endpoint "/api/v1/admin/categories" `
    -HttpStatus $adm1.StatusCode -Success ($adm1.StatusCode -eq 200 -and $adm1.Json.success -eq $true) -Detail $adm1.Content

$adm2 = Invoke-Api -Method "GET" -Path "/api/v1/admin/tiers" -Headers $adminHeaders
Record-Result -TestId "ADM-02" -Module "Masters" -Role "FRANCHISE_ADMIN" -Description "List pricing tiers" -Endpoint "/api/v1/admin/tiers" `
    -HttpStatus $adm2.StatusCode -Success ($adm2.StatusCode -eq 200 -and $adm2.Json.success -eq $true) -Detail $adm2.Content

$adm3 = Invoke-Api -Method "GET" -Path "/api/v1/admin/products" -Headers $adminHeaders
Record-Result -TestId "ADM-03" -Module "Catalog" -Role "FRANCHISE_ADMIN" -Description "List products with stock & rates" -Endpoint "/api/v1/admin/products" `
    -HttpStatus $adm3.StatusCode -Success ($adm3.StatusCode -eq 200 -and $adm3.Json.success -eq $true) -Detail $adm3.Content

$adm4 = Invoke-Api -Method "GET" -Path "/api/v1/admin/users" -Headers $adminHeaders
Record-Result -TestId "ADM-04" -Module "Users" -Role "FRANCHISE_ADMIN" -Description "List franchise team members" -Endpoint "/api/v1/admin/users" `
    -HttpStatus $adm4.StatusCode -Success ($adm4.StatusCode -eq 200 -and $adm4.Json.success -eq $true) -Detail $adm4.Content

$adm5 = Invoke-Api -Method "GET" -Path "/api/v1/admin/settings" -Headers $adminHeaders
Record-Result -TestId "ADM-05" -Module "Settings" -Role "FRANCHISE_ADMIN" -Description "View franchise configuration settings" -Endpoint "/api/v1/admin/settings" `
    -HttpStatus $adm5.StatusCode -Success ($adm5.StatusCode -eq 200 -and $adm5.Json.success -eq $true) -Detail $adm5.Content

# -------------------------------------------------------------
# Module 5: CRM Leads, Parties, & Territory Workflow
# -------------------------------------------------------------
$lead1 = Invoke-Api -Method "GET" -Path "/api/v1/admin/leads" -Headers $adminHeaders
Record-Result -TestId "LEAD-01" -Module "Leads" -Role "FRANCHISE_ADMIN" -Description "List sales leads" -Endpoint "/api/v1/admin/leads" `
    -HttpStatus $lead1.StatusCode -Success ($lead1.StatusCode -eq 200 -and $lead1.Json.success -eq $true) -Detail $lead1.Content

$uniqLead = "Test Lead " + (Get-Random -Minimum 1000 -Maximum 9999)
$leadCreate = Invoke-Api -Method "POST" -Path "/api/v1/admin/leads" -Headers $adminHeaders -Body @{
    firm_name = $uniqLead
    contact_name = "Dr. Sameer Khan"
    mobile = "98" + (Get-Random -Minimum 10000000 -Maximum 99999999)
    email = "lead_" + (Get-Random) + "@example.com"
    city = "Mumbai"
    source = "DIRECT"
}
$createdLeadRef = $leadCreate.Json.data.lead_ref
Record-Result -TestId "LEAD-02" -Module "Leads" -Role "FRANCHISE_ADMIN" -Description "Create new sales lead" -Endpoint "/api/v1/admin/leads" `
    -HttpStatus $leadCreate.StatusCode -Success ($leadCreate.StatusCode -eq 201 -or $leadCreate.StatusCode -eq 200) -Detail $leadCreate.Content

$pty1 = Invoke-Api -Method "GET" -Path "/api/v1/admin/parties" -Headers $adminHeaders
Record-Result -TestId "PTY-01" -Module "Parties" -Role "FRANCHISE_ADMIN" -Description "List registered distributor parties" -Endpoint "/api/v1/admin/parties" `
    -HttpStatus $pty1.StatusCode -Success ($pty1.StatusCode -eq 200 -and $pty1.Json.success -eq $true) -Detail $pty1.Content

$ter1 = Invoke-Api -Method "GET" -Path "/api/v1/admin/territories" -Headers $adminHeaders
Record-Result -TestId "TER-01" -Module "Territories" -Role "FRANCHISE_ADMIN" -Description "List assigned territories" -Endpoint "/api/v1/admin/territories" `
    -HttpStatus $ter1.StatusCode -Success ($ter1.StatusCode -eq 200 -and $ter1.Json.success -eq $true) -Detail $ter1.Content

# -------------------------------------------------------------
# Module 6: Orders, Invoicing, Billing & Dispatches (Admin)
# -------------------------------------------------------------
$ord1 = Invoke-Api -Method "GET" -Path "/api/v1/admin/orders" -Headers $adminHeaders
Record-Result -TestId "ORD-01" -Module "Orders" -Role "FRANCHISE_ADMIN" -Description "List franchise orders" -Endpoint "/api/v1/admin/orders" `
    -HttpStatus $ord1.StatusCode -Success ($ord1.StatusCode -eq 200 -and $ord1.Json.success -eq $true) -Detail $ord1.Content

$inv1 = Invoke-Api -Method "GET" -Path "/api/v1/admin/invoices" -Headers $adminHeaders
Record-Result -TestId "INV-01" -Module "Invoices" -Role "FRANCHISE_ADMIN" -Description "List franchise tax invoices" -Endpoint "/api/v1/admin/invoices" `
    -HttpStatus $inv1.StatusCode -Success ($inv1.StatusCode -eq 200 -and $inv1.Json.success -eq $true) -Detail $inv1.Content

$disp1 = Invoke-Api -Method "GET" -Path "/api/v1/admin/dispatches" -Headers $adminHeaders
Record-Result -TestId "DISP-01" -Module "Dispatches" -Role "FRANCHISE_ADMIN" -Description "List order shipments & LR details" -Endpoint "/api/v1/admin/dispatches" `
    -HttpStatus $disp1.StatusCode -Success ($disp1.StatusCode -eq 200 -and $disp1.Json.success -eq $true) -Detail $disp1.Content

$pay1 = Invoke-Api -Method "GET" -Path "/api/v1/admin/payments" -Headers $adminHeaders
Record-Result -TestId "PAY-01" -Module "Payments" -Role "FRANCHISE_ADMIN" -Description "List payments & ledger allocations" -Endpoint "/api/v1/admin/payments" `
    -HttpStatus $pay1.StatusCode -Success ($pay1.StatusCode -eq 200 -and $pay1.Json.success -eq $true) -Detail $pay1.Content

# -------------------------------------------------------------
# Module 7: Sales Representative Workflows & Permissions Scoping
# -------------------------------------------------------------
$salesHeaders = @{ "Authorization" = "Bearer $salesToken" }

$sl1 = Invoke-Api -Method "GET" -Path "/api/v1/auth/me" -Headers $salesHeaders
Record-Result -TestId "SALES-01" -Module "Sales" -Role "SALES" -Description "Verify sales rep profile and scope" -Endpoint "/api/v1/auth/me" `
    -HttpStatus $sl1.StatusCode -Success ($sl1.StatusCode -eq 200 -and $sl1.Json.data.role -eq "SALES") -Detail $sl1.Content

$sl2 = Invoke-Api -Method "GET" -Path "/api/v1/admin/leads" -Headers $salesHeaders
Record-Result -TestId "SALES-02" -Module "Sales" -Role "SALES" -Description "Sales rep accessing assigned leads" -Endpoint "/api/v1/admin/leads" `
    -HttpStatus $sl2.StatusCode -Success ($sl2.StatusCode -eq 200) -Detail $sl2.Content

$sl3 = Invoke-Api -Method "GET" -Path "/api/v1/admin/parties" -Headers $salesHeaders
Record-Result -TestId "SALES-03" -Module "Sales" -Role "SALES" -Description "Sales rep view parties list" -Endpoint "/api/v1/admin/parties" `
    -HttpStatus $sl3.StatusCode -Success ($sl3.StatusCode -eq 200) -Detail $sl3.Content

$sl4 = Invoke-Api -Method "GET" -Path "/api/v1/admin/orders" -Headers $salesHeaders
Record-Result -TestId "SALES-04" -Module "Sales" -Role "SALES" -Description "Sales rep viewing sales orders" -Endpoint "/api/v1/admin/orders" `
    -HttpStatus $sl4.StatusCode -Success ($sl4.StatusCode -eq 200) -Detail $sl4.Content

# -------------------------------------------------------------
# Module 8: Distributor Partner Portal Workflows
# -------------------------------------------------------------
$distHeaders = @{ "Authorization" = "Bearer $distToken" }

$dp1 = Invoke-Api -Method "GET" -Path "/api/v1/portal/profile" -Headers $distHeaders
Record-Result -TestId "PORTAL-01" -Module "Portal" -Role "DISTRIBUTOR" -Description "Distributor fetch firm profile" -Endpoint "/api/v1/portal/profile" `
    -HttpStatus $dp1.StatusCode -Success ($dp1.StatusCode -eq 200 -and $dp1.Json.success -eq $true) -Detail $dp1.Content

$dp2 = Invoke-Api -Method "GET" -Path "/api/v1/portal/catalogue" -Headers $distHeaders
Record-Result -TestId "PORTAL-02" -Module "Portal" -Role "DISTRIBUTOR" -Description "Distributor view product catalogue & rates" -Endpoint "/api/v1/portal/catalogue" `
    -HttpStatus $dp2.StatusCode -Success ($dp2.StatusCode -eq 200 -and $dp2.Json.success -eq $true) -Detail $dp2.Content

$dp3 = Invoke-Api -Method "GET" -Path "/api/v1/portal/orders" -Headers $distHeaders
Record-Result -TestId "PORTAL-03" -Module "Portal" -Role "DISTRIBUTOR" -Description "Distributor list purchase order history" -Endpoint "/api/v1/portal/orders" `
    -HttpStatus $dp3.StatusCode -Success ($dp3.StatusCode -eq 200 -and $dp3.Json.success -eq $true) -Detail $dp3.Content

$dp4 = Invoke-Api -Method "GET" -Path "/api/v1/portal/invoices" -Headers $distHeaders
Record-Result -TestId "PORTAL-04" -Module "Portal" -Role "DISTRIBUTOR" -Description "Distributor view billing invoices" -Endpoint "/api/v1/portal/invoices" `
    -HttpStatus $dp4.StatusCode -Success ($dp4.StatusCode -eq 200 -and $dp4.Json.success -eq $true) -Detail $dp4.Content

$dp5 = Invoke-Api -Method "GET" -Path "/api/v1/portal/dispatches" -Headers $distHeaders
Record-Result -TestId "PORTAL-05" -Module "Portal" -Role "DISTRIBUTOR" -Description "Distributor track shipments/dispatches" -Endpoint "/api/v1/portal/dispatches" `
    -HttpStatus $dp5.StatusCode -Success ($dp5.StatusCode -eq 200 -and $dp5.Json.success -eq $true) -Detail $dp5.Content

$dp6 = Invoke-Api -Method "GET" -Path "/api/v1/portal/outstanding" -Headers $distHeaders
Record-Result -TestId "PORTAL-06" -Module "Portal" -Role "DISTRIBUTOR" -Description "Distributor ledger summary & outstanding" -Endpoint "/api/v1/portal/outstanding" `
    -HttpStatus $dp6.StatusCode -Success ($dp6.StatusCode -eq 200 -and $dp6.Json.success -eq $true) -Detail $dp6.Content

# -------------------------------------------------------------
# Module 9: Role-Based Access Control (RBAC) & Security Boundaries
# -------------------------------------------------------------
# 9.1 Distributor forbidden from accessing Super Admin stats
$sec1 = Invoke-Api -Method "GET" -Path "/api/v1/super/dashboard/stats" -Headers $distHeaders
$sec1Pass = ($sec1.StatusCode -eq 403 -or $sec1.StatusCode -eq 401)
Record-Result -TestId "SEC-01" -Module "RBAC" -Role "DISTRIBUTOR" -Description "Distributor BLOCKED from Super Admin stats (403 expected)" -Endpoint "/api/v1/super/dashboard/stats" `
    -HttpStatus $sec1.StatusCode -Success $sec1Pass -Detail "Status: $($sec1.StatusCode)"

# 9.2 Sales rep forbidden from Super Admin organizations
$sec2 = Invoke-Api -Method "GET" -Path "/api/v1/super/organizations" -Headers $salesHeaders
$sec2Pass = ($sec2.StatusCode -eq 403 -or $sec2.StatusCode -eq 401)
Record-Result -TestId "SEC-02" -Module "RBAC" -Role "SALES" -Description "Sales rep BLOCKED from Super Admin organizations (403 expected)" -Endpoint "/api/v1/super/organizations" `
    -HttpStatus $sec2.StatusCode -Success $sec2Pass -Detail "Status: $($sec2.StatusCode)"

# 9.3 Franchise Admin cannot access Super Admin metrics
$sec3 = Invoke-Api -Method "GET" -Path "/api/v1/super/dashboard/stats" -Headers $adminHeaders
$sec3Pass = ($sec3.StatusCode -eq 403 -or $sec3.StatusCode -eq 401)
Record-Result -TestId "SEC-03" -Module "RBAC" -Role "FRANCHISE_ADMIN" -Description "Franchise Admin BLOCKED from Super Admin dashboard (403 expected)" -Endpoint "/api/v1/super/dashboard/stats" `
    -HttpStatus $sec3.StatusCode -Success $sec3Pass -Detail "Status: $($sec3.StatusCode)"

# 9.4 Super Admin seamlessly accesses lower API via auto SignInAs
$sec4 = Invoke-Api -Method "GET" -Path "/api/v1/admin/categories" -Headers $superHeaders
$sec4Pass = ($sec4.StatusCode -eq 200)
Record-Result -TestId "SEC-04" -Module "RBAC" -Role "SUPER_ADMIN" -Description "Super Admin allowed Admin categories via auto SignInAs" -Endpoint "/api/v1/admin/categories" `
    -HttpStatus $sec4.StatusCode -Success $sec4Pass -Detail "Status: $($sec4.StatusCode)"

# 9.5 Super Admin seamlessly accesses Portal API via auto SignInAs
$sec5 = Invoke-Api -Method "GET" -Path "/api/v1/portal/catalogue" -Headers $superHeaders
$sec5Pass = ($sec5.StatusCode -eq 200)
Record-Result -TestId "SEC-05" -Module "RBAC" -Role "SUPER_ADMIN" -Description "Super Admin allowed Distributor catalogue via auto SignInAs" -Endpoint "/api/v1/portal/catalogue" `
    -HttpStatus $sec5.StatusCode -Success $sec5Pass -Detail "Status: $($sec5.StatusCode)"

# Summary calculation
$totalTests = $results.Count
$passedTests = ($results | Where-Object { $_.Success -eq $true }).Count
$failedTests = $totalTests - $passedTests

Write-Host "`n=======================================================" -ForegroundColor Yellow
Write-Host " LIVE TEST SUITE EXECUTION SUMMARY" -ForegroundColor Yellow
Write-Host " Total Unique Use Cases Tested : $totalTests" -ForegroundColor Cyan
Write-Host " Passed Tests                  : $passedTests" -ForegroundColor Green
Write-Host " Failed Tests                  : $failedTests" -ForegroundColor $(if ($failedTests -eq 0) { "Green" } else { "Red" })
Write-Host " Success Rate                  : $([math]::Round(($passedTests/$totalTests)*100, 2))%" -ForegroundColor Cyan
Write-Host "=======================================================" -ForegroundColor Yellow

$results | Export-Csv -Path "live_test_results.csv" -NoTypeInformation
Write-Host "Full test results exported to live_test_results.csv" -ForegroundColor Gray
