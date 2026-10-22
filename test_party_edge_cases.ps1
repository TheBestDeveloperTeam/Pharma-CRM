# Live Production Party Creation Edge Case Test Suite
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$baseUrl = "https://crm.easysolutins24.in"

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

Write-Host "=== Authenticating Super Admin & Franchise Admin ===" -ForegroundColor Cyan

# 1. Super Admin Auth
$authSuper = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{
    grant_type = "password"
    client_id = "crm-super"
    email = "super@pharmacrm.local"
    password = "Password@123"
}
$superToken = $authSuper.Json.data.access_token
Write-Host "Super Admin Auth Status: $($authSuper.StatusCode)"

# 2. Franchise Admin Auth
$authAdmin = Invoke-Api -Method "POST" -Path "/api/v1/oauth/token" -Body @{
    grant_type = "password"
    client_id = "crm-admin"
    email = "admin@pharmacrm.local"
    password = "Password@123"
    franchise_code = "MUMBAI"
}
$adminToken = $authAdmin.Json.data.access_token
Write-Host "Franchise Admin Auth Status: $($authAdmin.StatusCode)"

$superHeaders = @{ "Authorization" = "Bearer $superToken" }
$adminHeaders = @{ "Authorization" = "Bearer $adminToken" }

$tests = [System.Collections.Generic.List[PSCustomObject]]::new()
function Log-Test {
    param([string]$Id, [string]$Role, [string]$Desc, [int]$Status, [bool]$Expected, [string]$Body)
    $pass = ($Status -eq 200 -or $Status -eq 201)
    if (-not $Expected) { $pass = ($Status -ge 400 -and $Status -lt 500) }
    $color = if ($pass) { "Green" } else { "Red" }
    Write-Host "[$Id] ($Role) $Desc -> Status: $Status | Success: $pass" -ForegroundColor $color
    if (-not $pass) {
        Write-Host "   Detail: $Body" -ForegroundColor DarkRed
    }
    $tests.Add([PSCustomObject]@{
        Id = $Id
        Role = $Role
        Description = $Desc
        StatusCode = $Status
        Passed = $pass
        Response = $Body
    })
}

Write-Host "`n=== Running 9+ Edge Case Tests for Party Creation on Live Production ===" -ForegroundColor Cyan

# Edge Case 1: Super Admin creating party with completely brand new unique UNREGISTERED pincode (Dynamic Provisioning)
$pin1 = "98" + (Get-Random -Minimum 1000 -Maximum 9999)
$r1 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $superHeaders -Body @{
    firm_name = "Edge 1 Super Dynamic Pin " + (Get-Random -Minimum 100 -Maximum 999)
    contact_name = "Dr. Sameer Joshi"
    mobile = "98" + (Get-Random -Minimum 10000000 -Maximum 99999999)
    email = "super_edge1_" + (Get-Random) + "@testpharma.local"
    pincode = $pin1
    party_type = "DISTRIBUTOR"
    credit_limit = 500000
    opening_outstanding = 0
}
Log-Test -Id "EDGE-01" -Role "SUPER_ADMIN" -Desc "Create party with completely NEW UNREGISTERED pincode ($pin1)" -Status $r1.StatusCode -Expected $true -Body $r1.Content

# Edge Case 2: Franchise Admin creating party with another NEW UNREGISTERED pincode
$pin2 = "87" + (Get-Random -Minimum 1000 -Maximum 9999)
$r2 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $adminHeaders -Body @{
    firm_name = "Edge 2 Admin Dynamic Pin " + (Get-Random -Minimum 100 -Maximum 999)
    contact_name = "Kavita Rao"
    mobile = "97" + (Get-Random -Minimum 10000000 -Maximum 99999999)
    email = "admin_edge2_" + (Get-Random) + "@testpharma.local"
    pincode = $pin2
    party_type = "STOCKIST"
    credit_limit = 200000
}
Log-Test -Id "EDGE-02" -Role "FRANCHISE_ADMIN" -Desc "Admin create party with brand new pincode ($pin2)" -Status $r2.StatusCode -Expected $true -Body $r2.Content

# Edge Case 3: Super Admin creating party with EXPLICIT franchise_ref in body
$r3 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $superHeaders -Body @{
    franchise_ref = "FRN-MUMBAI000000000001"
    firm_name = "Edge 3 Super Explicit Frn " + (Get-Random -Minimum 100 -Maximum 999)
    contact_name = "Amitabh Sen"
    mobile = "96" + (Get-Random -Minimum 10000000 -Maximum 99999999)
    email = "super_edge3_" + (Get-Random) + "@testpharma.local"
    pincode = "400001"
    party_type = "DISTRIBUTOR"
}
Log-Test -Id "EDGE-03" -Role "SUPER_ADMIN" -Desc "Create party specifying explicit franchise_ref in body" -Status $r3.StatusCode -Expected $true -Body $r3.Content

# Edge Case 4: Super Admin creating party using X-Franchise-Ref Header
$superHeadersWithFrn = @{
    "Authorization" = "Bearer $superToken"
    "X-Franchise-Ref" = "FRN-MUMBAI000000000001"
}
$r4 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $superHeadersWithFrn -Body @{
    firm_name = "Edge 4 Super Header Frn " + (Get-Random -Minimum 100 -Maximum 999)
    contact_name = "Deepak Chawla"
    mobile = "95" + (Get-Random -Minimum 10000000 -Maximum 99999999)
    email = "super_edge4_" + (Get-Random) + "@testpharma.local"
    pincode = "400001"
    party_type = "DISTRIBUTOR"
}
Log-Test -Id "EDGE-04" -Role "SUPER_ADMIN" -Desc "Create party with X-Franchise-Ref header" -Status $r4.StatusCode -Expected $true -Body $r4.Content

# Edge Case 5: Full comprehensive payload (all fields, valid GSTIN, drug license, dates, terms, sales rep)
$sharedMobile = "94" + (Get-Random -Minimum 10000000 -Maximum 99999999)
$sharedGstin = "27" + ("ABCDE" + (Get-Random -Minimum 1000 -Maximum 9999)) + "F1Z5"
$sharedDrugNo = "DL-MH-2026-" + (Get-Random -Minimum 10000 -Maximum 99999)

$r5 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $adminHeaders -Body @{
    firm_name = "Edge 5 Full Spec Party " + (Get-Random -Minimum 100 -Maximum 999)
    contact_name = "Harish Mehta"
    mobile = $sharedMobile
    whatsapp = $sharedMobile
    email = "edge5_" + (Get-Random) + "@testpharma.local"
    gstin = $sharedGstin
    drug_license_no = $sharedDrugNo
    drug_license_validity = "2028-12-31"
    party_type = "DISTRIBUTOR"
    billing_address = "Building 4, Andheri East, Mumbai"
    shipping_address = "Building 4, Andheri East, Mumbai"
    state_ref = "STA-MAHARASHTRA00001"
    district_ref = "DST-MUMBAISUBURB0001"
    city_ref = "CTY-ANDHERI000000001"
    pincode = "400053"
    tier_ref = "TIR-DISTRIB000000001"
    sales_user_ref = "USR-SALESREP000000001"
    credit_limit = 750000
    opening_outstanding = 5000
    payment_terms = "30 days"
    payment_terms_days = 30
    agreement_from = "2026-10-01"
    agreement_to = "2028-10-01"
}
Log-Test -Id "EDGE-05" -Role "FRANCHISE_ADMIN" -Desc "Create party with complete valid fields & commercial terms" -Status $r5.StatusCode -Expected $true -Body $r5.Content

# Edge Case 6: Duplicate GSTIN validation rejection (409 / 422 Expected)
$r6 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $adminHeaders -Body @{
    firm_name = "Edge 6 Duplicate GSTIN Firm"
    contact_name = "Ramesh Shah"
    mobile = "93" + (Get-Random -Minimum 10000000 -Maximum 99999999)
    email = "edge6_" + (Get-Random) + "@testpharma.local"
    gstin = $sharedGstin # Duplicate from EDGE-05
    pincode = "400001"
}
Log-Test -Id "EDGE-06" -Role "FRANCHISE_ADMIN" -Desc "Reject duplicate GSTIN within franchise (Expected 4xx)" -Status $r6.StatusCode -Expected $false -Body $r6.Content

# Edge Case 7: Duplicate Mobile Number rejection (409 / 422 Expected)
$r7 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $adminHeaders -Body @{
    firm_name = "Edge 7 Duplicate Mobile Firm"
    contact_name = "Suresh Patel"
    mobile = $sharedMobile # Duplicate from EDGE-05
    email = "edge7_" + (Get-Random) + "@testpharma.local"
    pincode = "400001"
}
Log-Test -Id "EDGE-07" -Role "FRANCHISE_ADMIN" -Desc "Reject duplicate mobile number within franchise (Expected 4xx)" -Status $r7.StatusCode -Expected $false -Body $r7.Content

# Edge Case 8: Invalid agreement dates (agreement_to < agreement_from) (422 Expected)
$r8 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $adminHeaders -Body @{
    firm_name = "Edge 8 Invalid Dates Party"
    mobile = "92" + (Get-Random -Minimum 10000000 -Maximum 99999999)
    pincode = "400001"
    agreement_from = "2027-01-01"
    agreement_to = "2026-01-01"
}
Log-Test -Id "EDGE-08" -Role "FRANCHISE_ADMIN" -Desc "Reject agreement_to earlier than agreement_from (Expected 422)" -Status $r8.StatusCode -Expected $false -Body $r8.Content

# Edge Case 9: Invalid Pincode format (e.g. 5 digits or letters) (422 Expected)
$r9 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $adminHeaders -Body @{
    firm_name = "Edge 9 Bad Pin Party"
    mobile = "91" + (Get-Random -Minimum 10000000 -Maximum 99999999)
    pincode = "4000A1"
}
Log-Test -Id "EDGE-09" -Role "FRANCHISE_ADMIN" -Desc "Reject invalid pincode format (Expected 422)" -Status $r9.StatusCode -Expected $false -Body $r9.Content

# Edge Case 10: Missing mandatory firm_name (422 Expected)
$r10 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $adminHeaders -Body @{
    contact_name = "Only Contact Name"
    mobile = "90" + (Get-Random -Minimum 10000000 -Maximum 99999999)
    pincode = "400001"
}
Log-Test -Id "EDGE-10" -Role "FRANCHISE_ADMIN" -Desc "Reject missing firm_name (Expected 422)" -Status $r10.StatusCode -Expected $false -Body $r10.Content

# Edge Case 11: Non-existent pricing tier reference (422 Expected)
$r11 = Invoke-Api -Method "POST" -Path "/api/v1/admin/parties" -Headers $adminHeaders -Body @{
    firm_name = "Edge 11 Bad Tier Party"
    mobile = "89" + (Get-Random -Minimum 10000000 -Maximum 99999999)
    tier_ref = "TIR-NONEXISTENT99999"
    pincode = "400001"
}
Log-Test -Id "EDGE-11" -Role "FRANCHISE_ADMIN" -Desc "Reject invalid/non-existent tier_ref (Expected 422)" -Status $r11.StatusCode -Expected $false -Body $r11.Content

Write-Host "`n=======================================================" -ForegroundColor Cyan
Write-Host " LIVE EDGE CASE TEST RESULTS SUMMARY" -ForegroundColor Cyan
Write-Host "=======================================================" -ForegroundColor Cyan
$passedCount = ($tests | Where-Object { $_.Passed -eq $true }).Count
$totalCount = $tests.Count
Write-Host "Total Edge Cases Tested : $totalCount"
Write-Host "Passed Edge Cases       : $passedCount"
Write-Host "Failed Edge Cases       : $($totalCount - $passedCount)"
Write-Host "Success Rate            : $([math]::Round(($passedCount / $totalCount) * 100, 2))%"
