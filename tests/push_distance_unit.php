<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Push.php';

function verify(bool $valid, string $message): void {
    if (!$valid) throw new RuntimeException($message);
}

verify(Push::withinDistance(null, null, null, null, null), 'Users without configured radius must retain existing behavior.');
verify(Push::withinDistance(50, -23.5505, -46.6333, -23.5505, -46.6333), 'Zero distance must be allowed.');
verify(!Push::withinDistance(5, -23.5505, -46.6333, -22.9068, -43.1729), 'Far distance must be blocked.');
verify(!Push::withinDistance(5, -23.5505, -46.6333, null, null), 'Unknown shift coordinates must be blocked.');
verify(Push::withinDistance(10, 0, 0, 0, 0.05), 'Nearby points must be allowed.');
verify(!Push::withinDistance(5, 0, 0, 0, 0.05), 'Distance beyond 5km must be blocked.');
verify(!Push::withinDistance(50, null, null, 0, 0), 'Unknown origin must be blocked for configured radius.');
echo "Notification radius unit: PASS\n";
