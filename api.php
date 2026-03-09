<?php
/**
 * IVR Lottery Registration API
 * Handles PBX callbacks for lottery registration flow
 *
 * Flow:
 * 1. Press 1 to register
 * 2. Ask "Did you study Mishnayot?" - DTMF input (1=yes, 2=no)
 * 3. Success message with confirmation number (3 digits)
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

// Flow parameters (chained by PBX)
$step1Register  = $_GET['step1_register'] ?? '';
$step2Mishnayot = $_GET['step2_mishnayot'] ?? '';

// Handle hangup
if ($pbxCallStatus === 'HANGUP') {
    echo json_encode(["status" => "ok"]);
    exit;
}

// Determine current step based on which parameters exist
if (!empty($step2Mishnayot)) {
    // Step 3: Mishnayot answered - save and play confirmation
    $confirmNum = getNextConfirmation();
    saveRegistration($pbxPhone, $pbxCallId, $step2Mishnayot, $confirmNum);

    $numPadded = str_pad($confirmNum, 3, '0', STR_PAD_LEFT);

    echo json_encode([
        "type" => "audioPlayer",
        "name" => "done",
        "files" => [
            ["text" => "נרשמתם בהצלחה להגרלה"],
            ["text" => "מספר האישור שלכם הוא"],
            ["digits" => $numPadded],
            ["text" => "תודה רבה ובהצלחה"]
        ]
    ]);

} elseif (!empty($step1Register)) {
    // Step 2: Registered - ask about Mishnayot (1=yes, 2=no)
    echo json_encode([
        "type" => "simpleMenu",
        "name" => "step2_mishnayot",
        "times" => 3,
        "timeout" => 10,
        "enabledKeys" => "1,2",
        "setMusic" => "no",
        "extensionChange" => "",
        "errorReturn" => "ERROR",
        "files" => [
            ["text" => "האם למדתם את המשניות? הקישו 1 עבור כן, הקישו 2 עבור לא"]
        ]
    ]);

} else {
    // Step 1: Initial call - press 1 to register
    echo json_encode([
        "type" => "simpleMenu",
        "name" => "step1_register",
        "times" => 3,
        "timeout" => 10,
        "enabledKeys" => "1",
        "setMusic" => "no",
        "extensionChange" => "",
        "errorReturn" => "ERROR",
        "files" => [
            ["text" => "להרשמה להגרלה הקישו 1"]
        ]
    ]);
}

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

function saveRegistration($phone, $callId, $mishnayotAnswer, $confirmNum) {
    $registrations = [];
    if (file_exists(DATA_FILE)) {
        $registrations = json_decode(file_get_contents(DATA_FILE), true) ?: [];
    }

    $registrations[] = [
        'id' => $confirmNum,
        'phone' => $phone,
        'callId' => $callId,
        'mishnayotAnswer' => $mishnayotAnswer,
        'confirmNumber' => str_pad($confirmNum, 3, '0', STR_PAD_LEFT),
        'date' => date('Y-m-d H:i:s'),
        'timestamp' => time()
    ];

    file_put_contents(DATA_FILE, json_encode($registrations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
