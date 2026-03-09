<?php
/**
 * IVR Lottery Registration API
 * Handles PBX callbacks for lottery registration flow
 *
 * Flow:
 * 1. Record full name (max 5 sec)
 * 2. Record "Did you study Mishnayot?" answer
 * 3. Get digit 1 confirmation
 * 4. Success message with confirmation number
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
$step1Name      = $_GET['step1_name'] ?? '';
$step2Mishnayot = $_GET['step2_mishnayot'] ?? '';
$step3Confirm   = $_GET['step3_confirm'] ?? '';

// Handle hangup
if ($pbxCallStatus === 'HANGUP') {
    echo json_encode(["status" => "ok"]);
    exit;
}

// Determine current step based on which parameters exist
if (!empty($step3Confirm)) {
    // Step 4: Confirmation received - save and play success
    $confirmNum = getNextConfirmation();
    saveRegistration($pbxPhone, $pbxCallId, $step1Name, $step2Mishnayot, $confirmNum);

    $numPadded = str_pad($confirmNum, 4, '0', STR_PAD_LEFT);

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

} elseif (!empty($step2Mishnayot)) {
    // Step 3: Mishnayot recording done - ask for confirmation (digit 1)
    echo json_encode([
        "type" => "simpleMenu",
        "name" => "step3_confirm",
        "times" => 3,
        "timeout" => 10,
        "enabledKeys" => "1",
        "setMusic" => "no",
        "extensionChange" => "",
        "errorReturn" => "ERROR",
        "files" => [
            ["text" => "לאישור ההרשמה הקישו 1"]
        ]
    ]);

} elseif (!empty($step1Name)) {
    // Step 2: Name recorded - ask about Mishnayot
    echo json_encode([
        "type" => "record",
        "name" => "step2_mishnayot",
        "max" => 5,
        "min" => 1,
        "confirm" => "no",
        "fileName" => "lottery_" . $pbxCallId . "_001",
        "files" => [
            ["text" => "האם למדתם את המשניות? אנא הקליטו את תשובתכם"]
        ]
    ]);

} else {
    // Step 1: Initial call - welcome + record name
    echo json_encode([
        [
            "type" => "audioPlayer",
            "name" => "welcome",
            "files" => [
                ["text" => "שלום וברוכים הבאים למערכת ההרשמה להגרלה"]
            ]
        ],
        [
            "type" => "record",
            "name" => "step1_name",
            "max" => 5,
            "min" => 1,
            "confirm" => "no",
            "fileName" => "lottery_" . $pbxCallId . "_000",
            "files" => [
                ["text" => "אנא הקליטו את שמכם המלא"]
            ]
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

function saveRegistration($phone, $callId, $nameRecording, $mishnayotRecording, $confirmNum) {
    $registrations = [];
    if (file_exists(DATA_FILE)) {
        $registrations = json_decode(file_get_contents(DATA_FILE), true) ?: [];
    }

    $registrations[] = [
        'id' => $confirmNum,
        'phone' => $phone,
        'callId' => $callId,
        'nameRecording' => $nameRecording,
        'mishnayotRecording' => $mishnayotRecording,
        'confirmNumber' => str_pad($confirmNum, 4, '0', STR_PAD_LEFT),
        'date' => date('Y-m-d H:i:s'),
        'timestamp' => time()
    ];

    file_put_contents(DATA_FILE, json_encode($registrations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
