<?php

require __DIR__ . '/../vendor/autoload.php';

use Ovh\Api;

/*
|--------------------------------------------------------------------------
| CONFIG (use ENV in real projects)
|--------------------------------------------------------------------------
*/
$applicationKey    = getenv('OVH_APP_KEY') ?: '00442608fb7c452e';
$applicationSecret = getenv('OVH_APP_SECRET') ?: '4f28de0518ec91241e32b961d260232f';
$consumerKey       = getenv('OVH_CONSUMER_KEY') ?: '874d1207d8f34843016611c3070e08cd';

$endpoint = 'ovh-eu';

$ovh = new Api(
    $applicationKey,
    $applicationSecret,
    $endpoint,
    $consumerKey
);

/*
|--------------------------------------------------------------------------
| CLI Helpers
|--------------------------------------------------------------------------
*/
function choose(array $items, string $label): string
{
    foreach ($items as $i => $item) {
        echo "[$i] $item\n";
    }

    echo "\nChoose $label (default 0): ";
    $choice = trim(fgets(STDIN));

    if ($choice === '' || !isset($items[$choice])) {
        $choice = 0;
    }

    echo "→ Using $label: {$items[$choice]}\n\n";
    return $items[$choice];
}

function extractId(string $label): string
{
    preg_match('/\((.*?)\)/', $label, $m);
    return $m[1] ?? '';
}

/*
|--------------------------------------------------------------------------
| 1. PROJECT
|--------------------------------------------------------------------------
*/
$projects = $ovh->get('/cloud/project');
$projectId = choose($projects, 'project');

/*
|--------------------------------------------------------------------------
| 2. REGIONS (only instance-capable)
|--------------------------------------------------------------------------
*/
$regions = $ovh->get("/cloud/project/$projectId/region");
$validRegions = [];

foreach ($regions as $region) {
    try {
        $flavors = $ovh->get("/cloud/project/$projectId/flavor", [
            'region' => $region
        ]);

        if (!empty($flavors)) {
            $validRegions[] = $region;
        }
    } catch (Throwable $e) {
        continue;
    }
}

if (empty($validRegions)) {
    exit("❌ No usable regions found\n");
}

$region = choose($validRegions, 'region');

/*
|--------------------------------------------------------------------------
| 3. FLAVORS
|--------------------------------------------------------------------------
*/
$flavors = $ovh->get("/cloud/project/$projectId/flavor", [
    'region' => $region
]);

$flavorOptions = [];

foreach ($flavors as $f) {
    if (!$f['available']) continue;

    $flavorOptions[] =
        "{$f['name']} | {$f['vcpus']} vCPU | {$f['ram']}MB RAM | {$f['disk']}GB Disk ({$f['id']})";
}

$flavorLabel = choose($flavorOptions, 'flavor');
$flavorId = extractId($flavorLabel);

/*
|--------------------------------------------------------------------------
| 4. IMAGES (Linux only)
|--------------------------------------------------------------------------
*/
$images = $ovh->get("/cloud/project/$projectId/image", [
    'region' => $region
]);

$imageOptions = [];

foreach ($images as $img) {
    if ($img['type'] !== 'linux') continue;

    $imageOptions[] = "{$img['name']} ({$img['id']})";
}

$imageLabel = choose($imageOptions, 'image');
$imageId = extractId($imageLabel);

/*
|--------------------------------------------------------------------------
| 5. CREATE INSTANCE
|--------------------------------------------------------------------------
*/
$instanceName = 'prod-server-' . date('Ymd-His');

echo "🚀 Creating instance...\n\n";

$instance = $ovh->post("/cloud/project/$projectId/instance", [
    'name'            => $instanceName,
    'region'          => $region,
    'flavorId'        => $flavorId,
    'imageId'         => $imageId,
    'monthlyBilling'  => false, // hourly billing
]);

$instanceId = $instance['id'];

echo "🆔 Instance ID: $instanceId\n";

/*
|--------------------------------------------------------------------------
| 6. WAIT UNTIL ACTIVE
|--------------------------------------------------------------------------
*/
do {
    sleep(10);
    $details = $ovh->get("/cloud/project/$projectId/instance/$instanceId");
    echo "⏳ Status: {$details['status']}\n";
} while ($details['status'] !== 'ACTIVE');

/*
|--------------------------------------------------------------------------
| 7. GET PUBLIC IP
|--------------------------------------------------------------------------
*/
$publicIp = null;

foreach ($details['ipAddresses'] as $ip) {
    if ($ip['type'] === 'public') {
        $publicIp = $ip['ip'];
        break;
    }
}

echo "\n🎉 SERVER READY\n";
echo "🌍 Public IP: $publicIp\n";
echo "🔐 SSH: ssh ubuntu@$publicIp\n";
