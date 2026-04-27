$env:APP_URL = 'http://127.0.0.1:8000'
$base = 'http://127.0.0.1:8000'
$p = Start-Process -FilePath 'C:\xampp\php\php.exe' -ArgumentList '-S','127.0.0.1:8000','-t','public','public/index.php' -WorkingDirectory 'C:\Users\Saurav.Soni\Desktop\compliance' -WindowStyle Hidden -PassThru
Start-Sleep -Seconds 2

try {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    Invoke-WebRequest -Uri "$base/login" -WebSession $s -UseBasicParsing | Out-Null
    Invoke-WebRequest -Uri "$base/login" -Method Post -WebSession $s -Body @{ email='admin@easyhome.com'; password='admin123' } -MaximumRedirection 5 -UseBasicParsing | Out-Null

    $createPage = Invoke-WebRequest -Uri "$base/compliances/create" -WebSession $s -UseBasicParsing
    if ($createPage.Content -match 'Sign In' -or $createPage.Content -notmatch 'Create New Compliance') {
        throw 'Login/session failed before create page.'
    }
    $authId = [regex]::Match($createPage.Content, 'name="authority_id"[\s\S]*?<option value="(\d+)"').Groups[1].Value
    $ownerId = [regex]::Match($createPage.Content, 'name="owner_id"[\s\S]*?<option value="(\d+)"').Groups[1].Value
    $reviewerId = [regex]::Match($createPage.Content, 'name="reviewer_id"[\s\S]*?<option value="(\d+)"').Groups[1].Value
    $approverId = [regex]::Match($createPage.Content, 'name="approver_id"[\s\S]*?<option value="(\d+)"').Groups[1].Value
    if (-not $authId -or -not $ownerId -or -not $reviewerId -or -not $approverId) {
        throw 'Could not parse authority/owner/reviewer/approver IDs from create form.'
    }
    Write-Output ("E2E_PARSE auth={0} owner={1} reviewer={2} approver={3}" -f $authId, $ownerId, $reviewerId, $approverId)

    $now = Get-Date
    $title = 'E2E Compliance ' + $now.ToString('yyyyMMddHHmmss')
    $start = $now.AddDays(-2).ToString('yyyy-MM-dd')
    $due = $now.AddDays(5).ToString('yyyy-MM-dd')
    $exp = $now.AddDays(3).ToString('yyyy-MM-dd')
    $rem = $now.AddDays(1).ToString('yyyy-MM-dd')

    $createResp = $null
    $createLocation = ''
    $createBody = ''
    try {
        $createResp = Invoke-WebRequest -Uri "$base/compliances/create" -Method Post -WebSession $s -Body @{
        title = $title
        authority_id = $authId
        circular_reference = 'E2E-REF'
        department = 'Compliance'
        risk_level = 'medium'
        priority = 'medium'
        frequency = 'monthly'
        description = 'E2E flow'
        objective_text = 'obj'
        penalty_impact = 'none'
        owner_id = $ownerId
        workflow_type = 'three-level'
        reviewer_id = $reviewerId
        approver_id = $approverId
        evidence_required = '0'
        start_date = $start
        due_date = $due
        expected_date = $exp
        reminder_date = $rem
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
    $id = [regex]::Match($createLocation, '/compliance/view/(\d+)').Groups[1].Value
    if (-not $id) {
        $errMsg = ''
        $m = [regex]::Match($createBody, '<div class="alert alert-danger">([\s\S]*?)</div>')
        if ($m.Success) {
            $errMsg = (($m.Groups[1].Value -replace '<[^>]+>', '') -replace '\s+', ' ').Trim()
        }
        if ($errMsg -eq '') {
            $s1 = [regex]::Match($createBody, '<title>([\s\S]*?)</title>')
            $titleTxt = if ($s1.Success) { ($s1.Groups[1].Value -replace '\s+', ' ').Trim() } else { 'n/a' }
            $s2 = [regex]::Match($createBody, '<h1[^>]*>([\s\S]*?)</h1>')
            $h1Txt = if ($s2.Success) { (($s2.Groups[1].Value -replace '<[^>]+>', '') -replace '\s+', ' ').Trim() } else { 'n/a' }
            $errMsg = "page_title=$titleTxt; h1=$h1Txt"
        }
        $safeErr = 'n/a'
        if ($errMsg -ne '') { $safeErr = $errMsg }
        throw ("Create failed; location={0}; error={1}" -f $createLocation, $safeErr)
    }

    Invoke-WebRequest -Uri "$base/compliances/submit/$id" -Method Post -WebSession $s -Body @{ maker_comment='submitted by e2e'; completion_date=(Get-Date).ToString('yyyy-MM-dd') } -MaximumRedirection 5 -UseBasicParsing | Out-Null
    # Forward step is needed when workflow resolves to 3-level.
    Invoke-WebRequest -Uri "$base/compliances/forward/$id" -Method Post -WebSession $s -Body @{ review_comment='forwarded by e2e' } -MaximumRedirection 5 -UseBasicParsing | Out-Null
    Invoke-WebRequest -Uri "$base/compliances/approve/$id" -Method Post -WebSession $s -Body @{ final_comment='approved by e2e' } -MaximumRedirection 5 -UseBasicParsing | Out-Null

    $dbCheck = & 'C:\xampp\php\php.exe' 'C:\Users\Saurav.Soni\Desktop\compliance\scripts\inspect_compliance.php' $id
    $isCompleted = [bool](($dbCheck -join "`n") -match '"status":"completed"')

    $sections = @('/dashboard','/calendar','/compliance','/financial-ratios','/reports','/circular-intelligence','/doa/list','/itrisk/dashboard','/organization','/roles-permissions','/settings','/billing')
    $secOut = @()
    foreach ($u in $sections) {
        try {
            $r = Invoke-WebRequest -Uri ($base + $u) -WebSession $s -UseBasicParsing -MaximumRedirection 5
            $secOut += "$u=$($r.StatusCode)"
        } catch {
            $secOut += "$u=ERR"
        }
    }

    Write-Output "E2E_ID=$id"
    Write-Output "E2E_COMPLETED=$isCompleted"
    Write-Output ("SECTIONS=" + ($secOut -join ','))
}
finally {
    Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue
}
