# Massive Comprehensive Multi-Module Live Production Edge Case Test Suite
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$baseUrl = "https://crm.easysolutins24.in"

$results = [System.Collections.Generic.List[PSCustomObject]]::new()

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

function Record-Test {
    param(
        [string]$Module,
        [string]$TestId,
        [string]$Type, # POSITIVE, NEGATIVE, ADHOC
        [string]$Desc,
        [int]$HttpStatus,
        [bool]$ExpectSuccess,
        [string]$RawContent
    )
    $passed = $false
    if ($ExpectSuccess) {
        $passed = ($HttpStatus -ge 200 -and $HttpStatus -lt 300)
    } else {
        $passed = ($HttpStatus -ge 400 -and $HttpStatus -lt 500)
    }

    $color = if ($passed) { "Green" } else { "Red" }
    Write-Host "[$TestId] [$Module] ($Type) $Desc -> Status: $HttpStatus | Passed: $passed" -ForegroundColor $color
    if (-not $passed) {
        Write-Host "   Detail: $RawContent" -ForegroundColor DarkRed
    }

    $results.Add([PSCustomObject]@{
        Module = $Module
        TestId = $TestId
        Type = $Type
        Description = $Desc
        StatusCode = $HttpStatus
        Passed = $passed
        Content = $RawContent
    })
}

Write-Host "=== Authenticating All 4 Actors ===" -ForegroundColor Cyan

# 1. Super Admin
$authSuper = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{
    grant_type = "password"; client_id = "crm-super"; email = "super@pharmacrm.local"; password = "Password@123"
}
$superToken = $authSuper.Json.data.access_token
$superH = @{ "Authorization" = "Bearer $superToken" }

# 2. Franchise Admin
$authAdmin = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{
    grant_type = "password"; client_id = "crm-admin"; email = "admin@pharmacrm.local"; password = "Password@123"; franchise_code = "MUMBAI"
}
$adminToken = $authAdmin.Json.data.access_token
$adminH = @{ "Authorization" = "Bearer $adminToken" }

# 3. Sales Rep
$authSales = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{
    grant_type = "password"; client_id = "crm-sales"; email = "sales@pharmacrm.local"; password = "Password@123"; franchise_code = "MUMBAI"
}
$salesToken = $authSales.Json.data.access_token
$salesH = @{ "Authorization" = "Bearer $salesToken" }

# 4. Distributor Portal
$authDist = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{
    grant_type = "password"; client_id = "crm-portal"; email = "portal@pharmacrm.local"; password = "Password@123"; franchise_code = "MUMBAI"
}
$distToken = $authDist.Json.data.access_token
$distH = @{ "Authorization" = "Bearer $distToken" }

Write-Host "Super Admin Token: $([bool]$superToken)"
Write-Host "Franchise Admin Token: $([bool]$adminToken)"
Write-Host "Sales Rep Token: $([bool]$salesToken)"
Write-Host "Distributor Token: $([bool]$distToken)"

# =========================================================================
# MODULE 1: AUTH & OAUTH (10 Edge Cases)
# =========================================================================
Write-Host "`n--- Testing Module 1: Auth & OAuth ---" -ForegroundColor Yellow

$t = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{ grant_type = "invalid_grant" }
Record-Test -Module "Auth" -TestId "AUTH-E01" -Type "NEGATIVE" -Desc "Reject invalid grant_type" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{ grant_type = "password"; client_id = "fake-client"; email = "a@b.com"; password = "123" }
Record-Test -Module "Auth" -TestId "AUTH-E02" -Type "NEGATIVE" -Desc "Reject unregistered client_id" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{ grant_type = "password"; client_id = "crm-admin"; email = "admin@pharmacrm.local"; password = "WrongPassword999"; franchise_code = "MUMBAI" }
Record-Test -Module "Auth" -TestId "AUTH-E03" -Type "NEGATIVE" -Desc "Reject incorrect password with timing protection" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{ grant_type = "password"; client_id = "crm-admin"; email = "admin@pharmacrm.local"; password = "Password@123"; franchise_code = "NONEXISTENT_CODE" }
Record-Test -Module "Auth" -TestId "AUTH-E04" -Type "NEGATIVE" -Desc "Reject invalid franchise_code" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{ grant_type = "password"; client_id = "crm-super"; email = "admin@pharmacrm.local"; password = "Password@123" }
Record-Test -Module "Auth" -TestId "AUTH-E05" -Type "NEGATIVE" -Desc "Reject franchise admin accessing super client surface" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/auth/me" -Headers @{ "Authorization" = "Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.invalid.signature" }
Record-Test -Module "Auth" -TestId "AUTH-E06" -Type "NEGATIVE" -Desc "Reject malformed / forged JWT token" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/auth/me"
Record-Test -Module "Auth" -TestId "AUTH-E07" -Type "NEGATIVE" -Desc "Reject missing Authorization header" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/auth/me" -Headers $superH
Record-Test -Module "Auth" -TestId "AUTH-E08" -Type "POSITIVE" -Desc "Super Admin introspect profile via /auth/me" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/auth/me" -Headers $adminH
Record-Test -Module "Auth" -TestId "AUTH-E09" -Type "POSITIVE" -Desc "Franchise Admin introspect profile & scopes" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/auth/me" -Headers $salesH
Record-Test -Module "Auth" -TestId "AUTH-E10" -Type "POSITIVE" -Desc "Sales Rep introspect profile & hierarchy" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content


# =========================================================================
# MODULE 2: GEOGRAPHIC MASTERS (10 Edge Cases)
# =========================================================================
Write-Host "`n--- Testing Module 2: Geographic Masters ---" -ForegroundColor Yellow

$t = Invoke-Api -Method "GET" -Path "/api/v1/geo/states"
Record-Test -Module "Geo" -TestId "GEO-E01" -Type "POSITIVE" -Desc "List all registered Indian states" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/geo/districts?state_ref=STA-MAHARASHTRA00001"
Record-Test -Module "Geo" -TestId "GEO-E02" -Type "POSITIVE" -Desc "List districts for valid state_ref" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/geo/districts?state_ref=STA-NONEXISTENT999"
Record-Test -Module "Geo" -TestId "GEO-E03" -Type "ADHOC" -Desc "Districts query for non-existent state returns empty array" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/geo/cities?district_ref=DST-MUMBAICITY000001"
Record-Test -Module "Geo" -TestId "GEO-E04" -Type "POSITIVE" -Desc "List cities for valid district_ref" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/geo/pincodes?city_ref=CTY-MUMBAI000000001"
Record-Test -Module "Geo" -TestId "GEO-E05" -Type "POSITIVE" -Desc "List pincodes for valid city_ref" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/geo/states" -Headers $adminH -Body @{ state_name = "Test State"; state_code = "TS" }
Record-Test -Module "Geo" -TestId "GEO-E06" -Type "NEGATIVE" -Desc "Franchise Admin blocked from mutating platform state master" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$newStateCode = "T" + (Get-Random -Minimum 10 -Maximum 99)
$t = Invoke-Api -Method "POST" -Path "/api/v1/geo/states" -Headers $superH -Body @{ state_name = "New State $newStateCode"; state_code = $newStateCode }
Record-Test -Module "Geo" -TestId "GEO-E07" -Type "POSITIVE" -Desc "Super Admin authorized to create state" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/geo/states" -Headers $superH -Body @{ state_name = "Duplicate Code State"; state_code = $newStateCode }
Record-Test -Module "Geo" -TestId "GEO-E08" -Type "NEGATIVE" -Desc "Reject duplicate state_code" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/geo/pincodes" -Headers $superH -Body @{ pincode = "99999"; city_ref = "CTY-MUMBAI000000001" }
Record-Test -Module "Geo" -TestId "GEO-E09" -Type "NEGATIVE" -Desc "Reject invalid 5-digit pincode format" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$newPin = "9" + (Get-Random -Minimum 10000 -Maximum 99999)
$t = Invoke-Api -Method "POST" -Path "/api/v1/geo/pincodes" -Headers $superH -Body @{ pincode = $newPin; state_ref = "STA-MAHARASHTRA00001"; district_ref = "DST-MUMBAICITY000001"; city_ref = "CTY-MUMBAI000000001" }
Record-Test -Module "Geo" -TestId "GEO-E10" -Type "POSITIVE" -Desc "Super Admin successfully add new validated 6-digit pincode" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content


# =========================================================================
# MODULE 3: SUPER ADMIN PLATFORM MANAGEMENT (10 Edge Cases)
# =========================================================================
Write-Host "`n--- Testing Module 3: Super Admin Platform ---" -ForegroundColor Yellow

$t = Invoke-Api -Method "GET" -Path "/api/v1/super/organizations" -Headers $superH
Record-Test -Module "SuperAdmin" -TestId "SUP-E01" -Type "POSITIVE" -Desc "Super Admin list platform organizations" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/super/organizations" -Headers $adminH
Record-Test -Module "SuperAdmin" -TestId "SUP-E02" -Type "NEGATIVE" -Desc "Franchise Admin blocked from viewing super organizations" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/super/franchises" -Headers $superH
Record-Test -Module "SuperAdmin" -TestId "SUP-E03" -Type "POSITIVE" -Desc "Super Admin list franchises" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/super/dashboard/stats" -Headers $superH
Record-Test -Module "SuperAdmin" -TestId "SUP-E04" -Type "POSITIVE" -Desc "Super Admin view platform aggregate metrics" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/super/dashboard/stats" -Headers $distH
Record-Test -Module "SuperAdmin" -TestId "SUP-E05" -Type "NEGATIVE" -Desc "Distributor blocked from viewing super dashboard stats" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$randFrnCode = "TEST" + (Get-Random -Minimum 100 -Maximum 999)
$t = Invoke-Api -Method "POST" -Path "/api/v1/super/franchises" -Headers $superH -Body @{
    org_ref = "ORG-PLATFORM0000000001"
    franchise_name = "Franchise $randFrnCode"
    franchise_code = $randFrnCode
    contact_email = "frn_${randFrnCode}@example.com"
}
Record-Test -Module "SuperAdmin" -TestId "SUP-E06" -Type "POSITIVE" -Desc "Super Admin create new commercial franchise tenant" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/super/franchises" -Headers $superH -Body @{
    org_ref = "ORG-PLATFORM0000000001"
    franchise_name = "Duplicate Franchise"
    franchise_code = $randFrnCode # Duplicate code
}
Record-Test -Module "SuperAdmin" -TestId "SUP-E07" -Type "NEGATIVE" -Desc "Reject duplicate franchise_code" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/super/audit-logs" -Headers $superH
Record-Test -Module "SuperAdmin" -TestId "SUP-E08" -Type "POSITIVE" -Desc "Super Admin query immutable system audit logs" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/super/audit-logs" -Headers $salesH
Record-Test -Module "SuperAdmin" -TestId "SUP-E09" -Type "NEGATIVE" -Desc "Sales rep blocked from platform audit logs" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/super/franchises/FRN-NONEXISTENT999" -Headers $superH
Record-Test -Module "SuperAdmin" -TestId "SUP-E10" -Type "NEGATIVE" -Desc "Return 404 for non-existent franchise ref" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content


# =========================================================================
# MODULE 4: PRODUCTS, CATALOG & INVENTORY (12 Edge Cases)
# =========================================================================
Write-Host "`n--- Testing Module 4: Products & Inventory ---" -ForegroundColor Yellow

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/categories" -Headers $adminH
$activeCatRef = if ($t.Json.data -and $t.Json.data.Count -gt 0) { $t.Json.data[0].category_ref } else { $null }
Record-Test -Module "Catalog" -TestId "CAT-E01" -Type "POSITIVE" -Desc "List product categories" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/products" -Headers $adminH
$firstProdRef = if ($t.Json.data -and $t.Json.data.Count -gt 0) { $t.Json.data[0].product_ref } else { "PRD-AMOX250000000001" }
Record-Test -Module "Catalog" -TestId "PRD-E01" -Type "POSITIVE" -Desc "List products with pricing & GST" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$sku = "SKU-" + (Get-Random -Minimum 1000 -Maximum 9999)
$prdBody = @{
    product_name = "Amoxicillin 500mg $sku"
    sku = $sku
    hsn_code = "3004"
    packing = "10x10 Tablets"
    gst_percent = 12.00
    mrp = 150.00
    pts = 90.00
    ptr = 100.00
}
if ($activeCatRef) { $prdBody["category_ref"] = $activeCatRef }
$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/products" -Headers $adminH -Body $prdBody
$createdProdRef = $t.Json.data.product_ref
Record-Test -Module "Catalog" -TestId "PRD-E02" -Type "POSITIVE" -Desc "Create active pharmaceutical product with GST & MRP" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/products" -Headers $adminH -Body @{
    product_name = "Duplicate SKU Product"
    sku = $sku
}
Record-Test -Module "Catalog" -TestId "PRD-E03" -Type "NEGATIVE" -Desc "Reject duplicate product SKU within franchise" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/products" -Headers $adminH -Body @{
    product_name = "Bad GST Product"
    sku = "SKU-BADGST-" + (Get-Random)
    gst_percent = 150.00 # Invalid percentage
}
Record-Test -Module "Catalog" -TestId "PRD-E04" -Type "NEGATIVE" -Desc "Reject invalid GST percentage > 100" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/inventory/batches" -Headers $adminH
Record-Test -Module "Inventory" -TestId "INV-E01" -Type "POSITIVE" -Desc "List inventory batches (FEFO tracked)" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/inventory/movements" -Headers $adminH
Record-Test -Module "Inventory" -TestId "INV-E02" -Type "POSITIVE" -Desc "Query inventory movement ledger" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$batchNo = "BAT-TEST-" + (Get-Random -Minimum 1000 -Maximum 9999)
$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/inventory/receive" -Headers $adminH -Body @{
    product_ref = $firstProdRef
    batch_no = $batchNo
    mfg_date = "2026-01-01"
    expiry_date = "2028-12-31"
    qty = 500
}
Record-Test -Module "Inventory" -TestId "INV-E03" -Type "POSITIVE" -Desc "Receive inward stock batch with manufacturing & expiry dates" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/inventory/receive" -Headers $adminH -Body @{
    product_ref = "PRD-AZITHRO5000000001"
    batch_number = "BAD-EXPIRY"
    mfg_date = "2028-01-01"
    expiry_date = "2026-01-01" # Earlier than MFG
    quantity = 100
}
Record-Test -Module "Inventory" -TestId "INV-E04" -Type "NEGATIVE" -Desc "Reject batch with expiry date earlier than manufacturing date" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/inventory/receive" -Headers $adminH -Body @{
    product_ref = "PRD-NONEXISTENT999"
    batch_number = "B99"
    quantity = 100
}
Record-Test -Module "Inventory" -TestId "INV-E05" -Type "NEGATIVE" -Desc "Reject stock receipt for non-existent product" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/near-expiry" -Headers $adminH
Record-Test -Module "Inventory" -TestId "INV-E06" -Type "POSITIVE" -Desc "Scan near-expiry stock alerts" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/tiers" -Headers $adminH
Record-Test -Module "Pricing" -TestId "PRC-E01" -Type "POSITIVE" -Desc "List pricing tiers (Distributor, Stockist, Retailer)" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content


# =========================================================================
# MODULE 5: LEADS & FOLLOW-UPS (10 Edge Cases)
# =========================================================================
Write-Host "`n--- Testing Module 5: Leads & Follow-ups ---" -ForegroundColor Yellow

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/leads" -Headers $adminH
Record-Test -Module "Leads" -TestId "LED-E01" -Type "POSITIVE" -Desc "List CRM sales leads" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$leadMob = "98" + (Get-Random -Minimum 10000000 -Maximum 99999999)
$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/leads" -Headers $adminH -Body @{
    firm_name = "Lead Firm " + (Get-Random -Minimum 100 -Maximum 999)
    contact_name = "Dr. Ananya Roy"
    mobile = $leadMob
    email = "lead_${leadMob}@example.com"
    city = "Mumbai"
    source = "INDIAMART"
}
$createdLeadRef = $t.Json.data.lead_ref
Record-Test -Module "Leads" -TestId "LED-E02" -Type "POSITIVE" -Desc "Create sales lead from B2B source" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/leads" -Headers $adminH -Body @{
    firm_name = "Duplicate Lead"
    mobile = $leadMob # Duplicate mobile
}
Record-Test -Module "Leads" -TestId "LED-E03" -Type "NEGATIVE" -Desc "Reject duplicate active lead mobile" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/leads" -Headers $adminH -Body @{
    contact_name = "Missing Firm"
}
Record-Test -Module "Leads" -TestId "LED-E04" -Type "NEGATIVE" -Desc "Reject lead missing mandatory firm_name" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

if ($createdLeadRef) {
    $t = Invoke-Api -Method "GET" -Path "/api/v1/admin/leads/$createdLeadRef" -Headers $adminH
    Record-Test -Module "Leads" -TestId "LED-E05" -Type "POSITIVE" -Desc "Fetch single lead by reference ID" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

    $t = Invoke-Api -Method "POST" -Path "/api/v1/admin/leads/$createdLeadRef/assign" -Headers $adminH -Body @{
        assigned_user_ref = "USR-SALESREP000000001"
    }
    Record-Test -Module "Leads" -TestId "LED-E06" -Type "POSITIVE" -Desc "Assign lead to Sales Representative" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

    $t = Invoke-Api -Method "POST" -Path "/api/v1/admin/leads/$createdLeadRef/assign" -Headers $adminH -Body @{
        assigned_user_ref = "USR-NONEXISTENT999"
    }
    Record-Test -Module "Leads" -TestId "LED-E07" -Type "NEGATIVE" -Desc "Reject assigning lead to non-existent user" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content
}

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/follow-ups" -Headers $adminH
Record-Test -Module "FollowUps" -TestId "FUP-E01" -Type "POSITIVE" -Desc "List sales follow-ups" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/follow-ups" -Headers $adminH -Body @{
    lead_ref = $createdLeadRef
    followup_type = "CALL"
    scheduled_at = (Get-Date).AddDays(2).ToString("yyyy-MM-dd HH:mm:ss")
    notes = "Initial product sample inquiry"
}
$createdFupRef = $t.Json.data.followup_ref
Record-Test -Module "FollowUps" -TestId "FUP-E02" -Type "POSITIVE" -Desc "Schedule sales follow-up call" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/follow-ups" -Headers $adminH -Body @{
    lead_ref = "LED-NONEXISTENT999"
    followup_type = "VISIT"
    scheduled_at = (Get-Date).AddDays(1).ToString("yyyy-MM-dd HH:mm:ss")
}
Record-Test -Module "FollowUps" -TestId "FUP-E03" -Type "NEGATIVE" -Desc "Reject follow-up for non-existent lead" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content


# =========================================================================
# MODULE 6: ORDERS, BILLING & DISPATCH (12 Edge Cases)
# =========================================================================
Write-Host "`n--- Testing Module 6: Orders, Billing & Dispatch ---" -ForegroundColor Yellow

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/orders" -Headers $adminH
Record-Test -Module "Orders" -TestId "ORD-E01" -Type "POSITIVE" -Desc "List franchise sales orders" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

# Draft Order Creation
$partiesList = Invoke-Api -Method "GET" -Path "/api/v1/admin/parties?per_page=1" -Headers $adminH
$activePartyRef = if ($partiesList.Json.data -and $partiesList.Json.data.Count -gt 0) { $partiesList.Json.data[0].party_ref } else { "PAR-DELHISTOCK00001" }

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/orders" -Headers $adminH -Body @{
    party_ref = $activePartyRef
    items = @(
        @{ product_ref = $firstProdRef; paid_qty = 10 }
    )
}
$createdOrdRef = $t.Json.data.order_ref
Record-Test -Module "Orders" -TestId "ORD-E02" -Type "POSITIVE" -Desc "Create draft order with FEFO pricing calculation" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/orders" -Headers $adminH -Body @{
    party_ref = "PAR-NONEXISTENT999"
    items = @( @{ product_ref = "PRD-AZITHRO5000000001"; paid_qty = 10 } )
}
Record-Test -Module "Orders" -TestId "ORD-E03" -Type "NEGATIVE" -Desc "Reject order for non-existent party" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/orders" -Headers $adminH -Body @{
    party_ref = "PAR-APOLLODIST00001"
    items = @( @{ product_ref = "PRD-AZITHRO5000000001"; paid_qty = 0 } ) # Invalid 0 qty
}
Record-Test -Module "Orders" -TestId "ORD-E04" -Type "NEGATIVE" -Desc "Reject order item with 0 paid_qty" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/orders" -Headers $adminH -Body @{
    party_ref = "PAR-APOLLODIST00001"
    items = @() # Empty items
}
Record-Test -Module "Orders" -TestId "ORD-E05" -Type "NEGATIVE" -Desc "Reject order with empty items array" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

if ($createdOrdRef) {
    $t = Invoke-Api -Method "GET" -Path "/api/v1/admin/orders/$createdOrdRef" -Headers $adminH
    Record-Test -Module "Orders" -TestId "ORD-E06" -Type "POSITIVE" -Desc "Fetch single order details & line items" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

    # Order transition must be DRAFT -> SUBMITTED -> CONFIRMED
    $tSub = Invoke-Api -Method "POST" -Path "/api/v1/admin/orders/$createdOrdRef/submit" -Headers $adminH
    $t = Invoke-Api -Method "POST" -Path "/api/v1/admin/orders/$createdOrdRef/confirm" -Headers $adminH
    Record-Test -Module "Orders" -TestId "ORD-E07" -Type "POSITIVE" -Desc "Confirm order and lock stock reservations" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content
}

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/invoices" -Headers $adminH
Record-Test -Module "Billing" -TestId "BIL-E01" -Type "POSITIVE" -Desc "List tax invoices" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/dispatches" -Headers $adminH
Record-Test -Module "Dispatch" -TestId "DSP-E01" -Type "POSITIVE" -Desc "List dispatches and LR shipment tracking" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/dispatches" -Headers $adminH -Body @{
    order_ref = "ORD-NONEXISTENT999"
}
Record-Test -Module "Dispatch" -TestId "DSP-E02" -Type "NEGATIVE" -Desc "Reject dispatch creation for non-existent order" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/payments" -Headers $adminH
Record-Test -Module "Payments" -TestId "PAY-E01" -Type "POSITIVE" -Desc "List party payment receipts" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$payRefNo = "UTR-" + (Get-Random -Minimum 100000 -Maximum 999999)
$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/payments" -Headers $adminH -Body @{
    party_ref = "PAR-APOLLODIST00001"
    amount = 5000.00
    mode = "NEFT"
    reference_no = $payRefNo
    payment_date = (Get-Date).ToString("yyyy-MM-dd")
}
Record-Test -Module "Payments" -TestId "PAY-E02" -Type "POSITIVE" -Desc "Record electronic payment ledger entry" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content


# =========================================================================
# MODULE 7: DISTRIBUTOR PORTAL & SELF SERVICE (10 Edge Cases)
# =========================================================================
Write-Host "`n--- Testing Module 7: Distributor Portal ---" -ForegroundColor Yellow

$t = Invoke-Api -Method "GET" -Path "/api/v1/portal/profile" -Headers $distH
Record-Test -Module "Portal" -TestId "PRT-E01" -Type "POSITIVE" -Desc "Distributor fetch firm profile" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/portal/profile" -Headers $salesH
Record-Test -Module "Portal" -TestId "PRT-E02" -Type "NEGATIVE" -Desc "Sales rep rejected from distributor portal profile" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/portal/catalogue" -Headers $distH
Record-Test -Module "Portal" -TestId "PRT-E03" -Type "POSITIVE" -Desc "Distributor browse product catalogue & tier pricing" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/portal/orders" -Headers $distH
Record-Test -Module "Portal" -TestId "PRT-E04" -Type "POSITIVE" -Desc "Distributor list historical purchase orders" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/portal/invoices" -Headers $distH
Record-Test -Module "Portal" -TestId "PRT-E05" -Type "POSITIVE" -Desc "Distributor list tax invoices" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/portal/outstanding" -Headers $distH
Record-Test -Module "Portal" -TestId "PRT-E06" -Type "POSITIVE" -Desc "Distributor check ledger outstanding & credit balance" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/portal/cart/calculate" -Headers $distH -Body @{
    items = @( @{ product_ref = $firstProdRef; quantity = 5 } )
}
Record-Test -Module "Portal" -TestId "PRT-E07" -Type "POSITIVE" -Desc "Distributor calculate cart schemes & discounts" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/portal/cart/calculate" -Headers $distH -Body @{
    items = @( @{ product_ref = "PRD-NONEXISTENT999"; quantity = 5 } )
}
Record-Test -Module "Portal" -TestId "PRT-E08" -Type "NEGATIVE" -Desc "Reject portal cart calculation for invalid product" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/portal/dispatches" -Headers $distH
Record-Test -Module "Portal" -TestId "PRT-E09" -Type "POSITIVE" -Desc "Distributor track shipments and courier LR" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/portal/ledger" -Headers $distH
Record-Test -Module "Portal" -TestId "PRT-E10" -Type "POSITIVE" -Desc "Distributor view transaction ledger statements" -HttpStatus $t.StatusCode -ExpectSuccess $true -RawContent $t.Content


# =========================================================================
# MODULE 8: SECURITY, RBAC & CROSS-TENANCY ISOLATION (10 Edge Cases)
# =========================================================================
Write-Host "`n--- Testing Module 8: Security & Cross-Tenancy ---" -ForegroundColor Yellow

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/users" -Headers $salesH
Record-Test -Module "Security" -TestId "SEC-E01" -Type "NEGATIVE" -Desc "Sales Rep blocked from Admin user management" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/users" -Headers $salesH -Body @{ full_name = "Hacker User" }
Record-Test -Module "Security" -TestId "SEC-E02" -Type "NEGATIVE" -Desc "Sales Rep blocked from creating new users" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/roles" -Headers $salesH
Record-Test -Module "Security" -TestId "SEC-E03" -Type "NEGATIVE" -Desc "Sales Rep blocked from RBAC roles catalogue" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/settings" -Headers $distH
Record-Test -Module "Security" -TestId "SEC-E04" -Type "NEGATIVE" -Desc "Distributor blocked from viewing franchise settings" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/settings" -Headers $distH -Body @{ key = "x"; value = "y" }
Record-Test -Module "Security" -TestId "SEC-E05" -Type "NEGATIVE" -Desc "Distributor blocked from modifying franchise settings" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/super/organizations" -Headers $salesH
Record-Test -Module "Security" -TestId "SEC-E06" -Type "NEGATIVE" -Desc "Sales Rep blocked from Super Admin organizations" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "GET" -Path "/api/v1/admin/inventory/batches" -Headers $distH
Record-Test -Module "Security" -TestId "SEC-E07" -Type "NEGATIVE" -Desc "Distributor blocked from raw internal warehouse inventory" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/pricing" -Headers $distH -Body @{ product_ref = "PRD-1"; rate = 10 }
Record-Test -Module "Security" -TestId "SEC-E08" -Type "NEGATIVE" -Desc "Distributor blocked from altering product pricing" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/schemes" -Headers $salesH -Body @{ scheme_name = "Discount" }
Record-Test -Module "Security" -TestId "SEC-E09" -Type "NEGATIVE" -Desc "Sales Rep blocked from creating trade schemes" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

$t = Invoke-Api -Method "POST" -Path "/api/v1/admin/territories/override" -Headers $distH -Body @{ territory_ref = "TER-1" }
Record-Test -Module "Security" -TestId "SEC-E10" -Type "NEGATIVE" -Desc "Distributor blocked from recording territory overrides" -HttpStatus $t.StatusCode -ExpectSuccess $false -RawContent $t.Content

Write-Host "`n=======================================================" -ForegroundColor Cyan
Write-Host " LIVE PRODUCTION SYSTEM-WIDE TEST SUITE COMPLETED" -ForegroundColor Cyan
Write-Host "=======================================================" -ForegroundColor Cyan
$passed = ($results | Where-Object { $_.Passed -eq $true }).Count
$total = $results.Count
$rate = [math]::Round(($passed / $total) * 100, 2)
Write-Host "Total Live Edge Cases Executed : $total"
Write-Host "Passed Test Cases              : $passed"
Write-Host "Failed Test Cases              : $($total - $passed)"
Write-Host "Overall System Success Rate    : $rate%" -ForegroundColor $(if ($rate -ge 90) { "Green" } else { "Yellow" })

# Export complete report
$results | Export-Csv -Path "live_system_test_report.csv" -NoTypeInformation
Write-Host "Full detailed report exported to: live_system_test_report.csv"
