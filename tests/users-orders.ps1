param([string]$BaseUrl = 'http://localhost/pcpartsstore')
$ErrorActionPreference = 'Stop'
$results = [System.Collections.Generic.List[object]]::new()
function Check-Request($Method, $Path, $ExpectedStatus, $Body = $null) {
    $parameters = @{Uri="$BaseUrl$Path"; Method=$Method; SkipHttpErrorCheck=$true}
    if ($null -ne $Body) {
        $parameters.ContentType = 'application/json'
        $parameters.Body = if ($Body -is [string]) {$Body} else {$Body | ConvertTo-Json -Compress}
    }
    $response = Invoke-WebRequest @parameters
    if ($response.StatusCode -ne $ExpectedStatus) {
        throw "$Method $Path expected $ExpectedStatus, received $($response.StatusCode): $($response.Content)"
    }
    if ($response.Headers['Content-Type'] -notmatch 'application/json') { throw 'Expected JSON content type' }
    $results.Add([PSCustomObject]@{Method=$Method;Path=$Path;Expected=$ExpectedStatus;Actual=$response.StatusCode;Body=$response.Content})
    return ConvertFrom-Json -InputObject $response.Content -NoEnumerate
}
function Assert-Equal($Actual, $Expected, $Message) {
    if ($Actual -ne $Expected) { throw "$Message (expected $Expected, got $Actual)" }
}
$userId = $null
$orderId = $null
$usersBefore = @(Check-Request GET /users 200)
$ordersBefore = @(Check-Request GET /orders 200)
try {
    $null = Check-Request GET /users/2147483647 404
    $null = Check-Request GET /orders/2147483647 404
    $null = Check-Request POST /users 400 '{invalid'
    $null = Check-Request POST /users 400 @{first_name='Test';last_name='User';email='invalid'}
    $null = Check-Request POST /orders 400 @{user_id=0}
    $null = Check-Request POST /orders 404 @{user_id=2147483647}
    $email = 'build-test-' + [guid]::NewGuid().ToString('N') + '@example.com'
    $created = Check-Request POST /users 201 @{first_name='Build';last_name='Test';email=$email}
    $userId = [int]$created.id
    $user = Check-Request GET "/users/$userId" 200
    Assert-Equal $user.email $email 'Created email must persist'
    $null = Check-Request POST /users 409 @{first_name='Build';last_name='Test';email=$email}
    $null = Check-Request PATCH "/users/$userId" 400 @{first_name=$null}
    $null = Check-Request PATCH "/users/$userId" 400 @{unknown='field'}
    $null = Check-Request PATCH /users/2147483647 404 @{first_name='Missing'}
    $null = Check-Request PATCH "/users/$userId" 200 @{first_name='Updated'}
    $null = Check-Request PATCH "/users/$userId" 200 @{first_name='Updated'}
    Assert-Equal (Check-Request GET "/users/$userId" 200).first_name 'Updated' 'Updated name must persist'
    $null = Check-Request POST /orders 400 @{user_id=$userId;status='Invalid'}
    $created = Check-Request POST /orders 201 @{user_id=$userId}
    $orderId = [int]$created.id
    $order = Check-Request GET "/orders/$orderId" 200
    Assert-Equal $order.user_id $userId 'Order must reference test user'
    Assert-Equal $order.status 'Processing' 'Order must default to Processing'
    $null = Check-Request DELETE "/users/$userId" 409
    $null = Check-Request PATCH "/orders/$orderId" 400 @{status='Invalid'}
    $null = Check-Request PATCH /orders/2147483647 404 @{status='Shipped'}
    $null = Check-Request PATCH "/orders/$orderId" 200 @{status='Shipped'}
    $null = Check-Request PATCH "/orders/$orderId" 200 @{status='Shipped'}
    Assert-Equal (Check-Request GET "/orders/$orderId" 200).status 'Shipped' 'Updated status must persist'
    # Existing seed order has items. Its foreign key must prevent deletion.
    $null = Check-Request DELETE /orders/1 409
    $null = Check-Request DELETE "/orders/$orderId" 200
    $null = Check-Request GET "/orders/$orderId" 404
    $orderId = $null
    $null = Check-Request DELETE "/users/$userId" 200
    $null = Check-Request GET "/users/$userId" 404
    $userId = $null
    $null = Check-Request DELETE /users/2147483647 404
    $null = Check-Request DELETE /orders/2147483647 404
} finally {
    # Remove only temporary records created by this run, in dependency order.
    if ($orderId) { $null = Invoke-WebRequest -Uri "$BaseUrl/orders/$orderId" -Method DELETE -SkipHttpErrorCheck }
    if ($userId) { $null = Invoke-WebRequest -Uri "$BaseUrl/users/$userId" -Method DELETE -SkipHttpErrorCheck }
}
$usersAfter = @(Check-Request GET /users 200)
$ordersAfter = @(Check-Request GET /orders 200)
Assert-Equal ($usersAfter | ConvertTo-Json -Depth 8 -Compress) ($usersBefore | ConvertTo-Json -Depth 8 -Compress) 'User data must be restored'
Assert-Equal ($ordersAfter | ConvertTo-Json -Depth 8 -Compress) ($ordersBefore | ConvertTo-Json -Depth 8 -Compress) 'Order data must be restored'
$results | ConvertTo-Json -Depth 5
