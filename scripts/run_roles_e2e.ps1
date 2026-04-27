$ErrorActionPreference = 'Stop'
$env:APP_URL = 'http://127.0.0.1:8000'
$base = 'http://127.0.0.1:8000'

function New-LoginSession([string]$email, [string]$password) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    Invoke-WebRequest -Uri "$base/login" -WebSession $s -UseBasicParsing | Out-Null
    Invoke-WebRequest -Uri "$base/login" -Method Post -WebSession $s -Body @{ email = $email; password = $password } -MaximumRedirection 5 -UseBasicParsing | Out-Null
    return $s
}

function Get-ComplianceJson([int]$id) {
    $lines = & 'C:\xampp\php\php.exe' 'C:\Users\Saurav.Soni\Desktop\compliance\scripts\inspect_compliance.php' $id
    $jsonLine = $lines | Select-Object -First 1
    if (-not $jsonLine -or $jsonLine -eq 'NOT_FOUND') { return $null }
    return ($jsonLine | ConvertFrom-Json)
}

function Try-Post([Microsoft.PowerShell.Commands.WebRequestSession]$session, [string]$path, [hashtable]$body) {
    try {
        $r = Invoke-WebRequest -Uri ($base + $path) -Method Post -WebSession $session -Body $body -MaximumRedirection 5 -UseBasicParsing
        return @{ ok = $true; code = [int]$r.StatusCode }
    } catch {
        if ($_.Exception.Response) {
            return @{ ok = $false; code = [int]$_.Exception.Response.StatusCode.value__ }
        }
        return @{ ok = $false; code = 0 }
    }
}

function Get-DemoUserMap() {
    $lines = & 'C:\xampp\php\php.exe' 'C:\Users\Saurav.Soni\Desktop\compliance\scripts\inspect_demo_users.php'
    $map = @{}
    foreach ($line in $lines) {
        if (-not $line) { continue }
        $parts = $line -split '\|'
        if ($parts.Count -lt 5) { continue }
        $email = ''
        if ($parts.Count -ge 1) { $email = ('' + $parts[0]).Trim().ToLowerInvariant() }
        $id = 0
        if ($parts.Count -ge 2) { $id = [int]('' + $parts[1]) }
        $status = ''
        if ($parts.Count -ge 5) { $status = ('' + $parts[4]).Trim().ToLowerInvariant() }
        if ($email -ne '' -and $id -gt 0 -and $status -eq 'active') {
            $map[$email] = $id
        }
    }
    return $map
}

$p = Start-Process -FilePath 'C:\xampp\php\php.exe' -ArgumentList '-S', '127.0.0.1:8000', '-t', 'public', 'public/index.php' -WorkingDirectory 'C:\Users\Saurav.Soni\Desktop\compliance' -WindowStyle Hidden -PassThru
Start-Sleep -Seconds 2

try {
    $admin = New-LoginSession 'admin@easyhome.com' 'admin123'
    $maker = New-LoginSession 'maker@easyhome.com' 'maker123'
    $reviewer = New-LoginSession 'reviewer@demo.com' 'Reviewer@123'
    $approver = New-LoginSession 'approver@demo.com' 'Approver@123'
    $userMap = Get-DemoUserMap
    $makerId = 0
    $reviewerId = 0
    $approverId = 0
    if ($userMap.ContainsKey('maker@easyhome.com')) { $makerId = [int]$userMap['maker@easyhome.com'] }
    if ($userMap.ContainsKey('reviewer@demo.com')) { $reviewerId = [int]$userMap['reviewer@demo.com'] }
    if ($userMap.ContainsKey('approver@demo.com')) { $approverId = [int]$userMap['approver@demo.com'] }
    if ($makerId -lt 1 -or $reviewerId -lt 1 -or $approverId -lt 1) {
        throw 'Could not resolve active demo user IDs for maker/reviewer/approver.'
    }

    $createPage = Invoke-WebRequest -Uri "$base/compliances/create" -WebSession $admin -UseBasicParsing
    $authorityId = [regex]::Match($createPage.Content, 'name="authority_id"[\s\S]*?<option value="(\d+)"').Groups[1].Value
    if (-not $authorityId) { throw 'Could not parse authority_id from create form.' }

    $now = Get-Date
    $title = 'Role E2E ' + $now.ToString('yyyyMMddHHmmss')
    $createResp = $null
    $createLocation = ''
    $createBody = ''
    try {
        $createResp = Invoke-WebRequest -Uri "$base/compliances/create" -Method Post -WebSession $admin -Body @{
        title = $title
        authority_id = $authorityId
        circular_reference = 'ROLE-E2E'
        department = 'Compliance'
        risk_level = 'medium'
        priority = 'medium'
        frequency = 'monthly'
        description = 'Role-based flow'
        objective_text = 'obj'
        penalty_impact = 'none'
        owner_id = $makerId
        workflow_type = 'three-level'
        reviewer_id = $reviewerId
        approver_id = $approverId
        evidence_required = '0'
        start_date = $now.AddDays(-2).ToString('yyyy-MM-dd')
        due_date = $now.AddDays(5).ToString('yyyy-MM-dd')
        expected_date = $now.AddDays(3).ToString('yyyy-MM-dd')
        reminder_date = $now.AddDays(1).ToString('yyyy-MM-dd')
        } -MaximumRedirection 0 -UseBasicParsing
    } catch {
        if ($_.Exception.Response) {
            $createLocation = [string]$_.Exception.Response.Headers['Location']
            try {
                $sr = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $createBody = $sr.ReadToEnd()
                $sr.Close()
            } catch {}
        } else {
            throw
        }
    }
    $createUri = ''
    if ($createResp -and $createResp.BaseResponse -and $createResp.BaseResponse.ResponseUri) {
        $createUri = $createResp.BaseResponse.ResponseUri.AbsoluteUri
        $createBody = [string]$createResp.Content
    }
    if (-not $createLocation -and $createUri) { $createLocation = $createUri }
    $id = [int]([regex]::Match($createLocation, '/compliance/view/(\d+)').Groups[1].Value)
    if ($id -lt 1) {
        $errMsg = ''
        $m = [regex]::Match($createBody, '<div class="alert alert-danger">([\s\S]*?)</div>')
        if ($m.Success) {
            $errMsg = (($m.Groups[1].Value -replace '<[^>]+>', '') -replace '\s+', ' ').Trim()
        }
        $safeErr = 'n/a'
        if ($errMsg -ne '') { $safeErr = $errMsg }
        throw ("Could not parse created compliance id from {0}; error={1}" -f $createLocation, $safeErr)
    }

    $failures = New-Object System.Collections.Generic.List[string]

    # Role boundary checks
    $mkApprove = Try-Post $maker "/compliances/approve/$id" @{ final_comment = 'maker should not approve' }
    $st1 = Get-ComplianceJson $id
    if ($st1.status -eq 'completed') { $failures.Add('Maker unexpectedly approved compliance.') }

    $rvCreate = Try-Post $reviewer '/compliances/create' @{ title = 'x' }
    if ($rvCreate.code -ne 200 -and $rvCreate.code -ne 302) { $failures.Add('Reviewer create endpoint returned unexpected status code.') }

    # Happy path
    $mkSubmit = Try-Post $maker "/compliances/submit/$id" @{ maker_comment = 'submitted by maker'; completion_date = (Get-Date).ToString('yyyy-MM-dd') }
    $afterSubmit = Get-ComplianceJson $id
    if ($afterSubmit.status -notin @('submitted', 'under_review')) { $failures.Add("Unexpected status after maker submit: $($afterSubmit.status)") }

    $rvForward = Try-Post $reviewer "/compliances/forward/$id" @{ review_comment = 'forwarded by reviewer' }
    $afterForward = Get-ComplianceJson $id
    if ($afterForward.status -ne 'under_review') { $failures.Add("Unexpected status after reviewer forward: $($afterForward.status)") }

    $rvApprove = Try-Post $reviewer "/compliances/approve/$id" @{ final_comment = 'reviewer should not approve' }
    $afterRvApprove = Get-ComplianceJson $id
    if ($afterRvApprove.status -eq 'completed') { $failures.Add('Reviewer unexpectedly approved compliance.') }

    $apApprove = Try-Post $approver "/compliances/approve/$id" @{ final_comment = 'approved by approver' }
    $final = Get-ComplianceJson $id
    if ($final.status -ne 'completed') { $failures.Add("Approver approval did not complete compliance. Final status: $($final.status)") }

    # Sidebar admin-only pages should not be available to non-admin
    foreach ($pair in @(
        @{ name = 'maker'; s = $maker },
        @{ name = 'reviewer'; s = $reviewer },
        @{ name = 'approver'; s = $approver }
    )) {
        foreach ($path in @('/authority-matrix', '/bulk-upload')) {
            $r = Invoke-WebRequest -Uri ($base + $path) -WebSession $pair.s -UseBasicParsing -MaximumRedirection 5
            if ($r.Content -match 'Authority Matrix' -and $path -eq '/authority-matrix') {
                $failures.Add("$($pair.name) can access admin-only $path")
            }
            if ($r.Content -match 'Bulk Upload' -and $path -eq '/bulk-upload') {
                $failures.Add("$($pair.name) can access admin-only $path")
            }
        }
    }

    Write-Output "ROLE_E2E_ID=$id"
    Write-Output "ROLE_E2E_FINAL_STATUS=$($final.status)"
    if ($failures.Count -eq 0) {
        Write-Output 'ROLE_E2E_RESULT=PASS'
    } else {
        Write-Output 'ROLE_E2E_RESULT=FAIL'
        foreach ($f in $failures) { Write-Output ("FAILURE: " + $f) }
        exit 1
    }
}
finally {
    Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue
}
