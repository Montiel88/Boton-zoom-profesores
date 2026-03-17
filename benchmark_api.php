<?php
// benchmark_api.php
require_once __DIR__ . '/includes/zoom_api.php';

$userId = '1cjx4QOuSICO9Eb-b8VeaA';
$from = date('Y-m-d', strtotime('-90 days'));
$to = date('Y-m-d');

function mark($label) {
    static $last = null;
    $now = microtime(true);
    if ($last !== null) {
        $diff = round(($now - $last) * 1000, 2);
        echo "[$label] Time: {$diff}ms\n";
    } else {
        echo "Starting benchmark for $label...\n";
    }
    $last = $now;
}

mark("Zoom API Setup");

// 1.1 Past Meetings
$pastMeetingsData = getPastMeetings($userId, $from, $to);
mark("getPastMeetings (Report API)");

// 1.2 Recordings
$recordingsData = getZoomRecordings($userId, $from, $to);
mark("getZoomRecordings (Recordings API)");

// 1.3 Live Meetings
$liveData = getLiveMeetings();
mark("getLiveMeetings (Metrics API)");

// 1.4 Scheduled Meetings
$rawMeetingsData = getZoomMeetings($userId);
mark("getZoomMeetings (Meeting API)");

$pastMeetings = $pastMeetingsData['meetings'] ?? [];
echo "Found " . count($pastMeetings) . " past meetings.\n";

// Bucle de procesamiento de participantes (The suspected bottleneck)
$participantsCalls = 0;
foreach ($pastMeetings as $m) {
    $participantsCount = $m['participants_count'] ?? 0;
    if ($participantsCount <= 0 && isset($m['uuid'])) {
        $participantsCalls++;
        getMeetingParticipants($m['uuid']);
    }
}
mark("Processing participants ($participantsCalls calls made)");

echo "Total benchmark finished.\n";
?>
