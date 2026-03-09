<?php
/**
 * IVR Lottery Registration API
 * Handles PBX callbacks for lottery registration flow
 *
 * Flow:
 * 1. Caller enters - immediately gets confirmation number (3 digits)
 */

header('Content-Type: application/json; charset=utf-8');

// Data file path
define('DATA_FILE', __DIR__ . '/data/registrations.json');
define('COUNTER_FILE', __DIR__ . '/data/counter.json');

// Get all PBX parameters
$pbxPhone      = $_GET['PBXphone'] ?? '';
$pbxNum        = $_GET['PBXnum'] ?? '';
$pbxDid        = $_GET['PBXdid'] ?? '';
$pbxCallId     = $_GET['PBXcallId'] ?? '';
$pbxCallType   = $_GET['PBXcallType'] ?? '';
$pbxCallStatus = $_GET['PBXcallStatus'] ?? '';
$pbxExtId      = $_GET['PBXextensionId'] ?? '';
$pbxExtPath    = $_GET['PBXextensionPath'] ?? '';

// Handle hangup
if ($pbxCallStatus === 'HANGUP') {
    echo json_encode(["status" => "ok"]);
    exit;
}

// Immediate registration - save and play confirmation
$confirmNum = getNextConfirmation();
saveRegistration($pbxPhone, $pbxCallId, $confirmNum);

$numPadded = str_pad($confirmNum, 3, '0', STR_PAD_LEFT);

echo json_encode([
    "type" => "audioPlayer",
    "name" => "done",
    "beepPlay" => "NO",
    "files" => [
        ["fileName" => "001"],
        ["digits" => $numPadded]
    ]
]);

// ============ Helper Functions ============

function getNextConfirmation() {
    $counter = 0;
    if (file_exists(COUNTER_FILE)) {
        $data = json_decode(file_get_contents(COUNTER_FILE), true);
        $counter = $data['counter'] ?? 0;
    }
    $counter++;
    file_put_contents(COUNTER_FILE, json_encode(['counter' => $counter]), LOCK_EX);
    return $counter;
}

function saveRegistration($phone, $callId, $confirmNum) {
    $registrations = [];
    if (file_exists(DATA_FILE)) {
        $registrations = json_decode(file_get_contents(DATA_FILE), true) ?: [];
    }

    $registrations[] = [
        'id' => $confirmNum,
        'phone' => $phone,
        'callId' => $callId,
        'confirmNumber' => str_pad($confirmNum, 3, '0', STR_PAD_LEFT),
        'date' => date('Y-m-d H:i:s'),
        'timestamp' => time()
    ];

    file_put_contents(DATA_FILE, json_encode($registrations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
