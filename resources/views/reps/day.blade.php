<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $rep->name }} - Daily Tracking Playback ({{ $date }})</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        body {
            background-color: #f8fafc;
            color: #1e293b;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #334155;
            z-index: 1000;
        }
        .header-title {
            font-size: 1.15rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-badge {
            background-color: #0284c7;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 9999px;
            font-weight: 600;
        }
        .header-controls {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stats-badge {
            font-size: 0.82rem;
            background: #1e293b;
            padding: 5px 12px;
            border-radius: 6px;
            border: 1px solid #334155;
            color: #94a3b8;
        }
        .stats-badge strong {
            color: #38bdf8;
        }
        .date-picker {
            background: #1e293b;
            border: 1px solid #475569;
            color: #fff;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        .main-container {
            flex: 1;
            display: flex;
            height: calc(100vh - 57px);
            overflow: hidden;
        }
        .timeline-sidebar {
            width: 380px;
            background-color: #ffffff;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 2px 0 8px rgba(0,0,0,0.03);
        }
        .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            background-color: #ffffff;
        }
        .sidebar-header h2 {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
        }
        .sidebar-header p {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 2px;
        }
        .timeline-list {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
        }
        .timeline-item {
            display: flex;
            gap: 14px;
            margin-bottom: 16px;
            position: relative;
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }
        .timeline-item:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
        }
        .timeline-item.active {
            background-color: #f0f9ff;
            border-color: #0284c7;
        }
        .timeline-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .timeline-icon.moving {
            background-color: #e0f2fe;
            color: #0284c7;
            border: 2px solid #bae6fd;
        }
        .timeline-icon.stop {
            background-color: #fee2e2;
            color: #dc2626;
            border: 2px solid #fca5a5;
        }
        .timeline-content {
            flex: 1;
        }
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .timeline-title {
            font-size: 0.88rem;
            font-weight: 600;
            color: #0f172a;
        }
        .timeline-time {
            font-size: 0.78rem;
            font-weight: 500;
            color: #64748b;
        }
        .timeline-desc {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 2px;
        }
        .badge-duration {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 5px;
        }
        .badge-duration.moving {
            background-color: #f1f5f9;
            color: #475569;
        }
        .badge-duration.stop {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .map-container {
            flex: 1;
            position: relative;
            background-color: #e2e8f0;
        }
        #map {
            width: 100%;
            height: 100%;
        }
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <header>
        <div class="header-title">
            <span>{{ $rep->name }}</span>
            <span class="header-badge">Field Rep Tracking</span>
        </div>
        <div class="header-controls">
            <div class="stats-badge">
                Raw Points: <strong>{{ number_format($totalRawPoints) }}</strong>
                &bull; Display Points (RDP): <strong>{{ number_format($simplifiedPointCount) }}</strong>
                @if($totalRawPoints > 0)
                    ({{ round((1 - ($simplifiedPointCount / $totalRawPoints)) * 100, 1) }}% reduction)
                @endif
            </div>
            <form method="GET" action="{{ route('reps.day', $rep) }}">
                <input type="date" name="date" value="{{ $date }}" class="date-picker" onchange="this.form.submit()">
            </form>
        </div>
    </header>

    <div class="main-container">
        <!-- Sidebar Timeline -->
        <aside class="timeline-sidebar">
            <div class="sidebar-header">
                <h2>Shift Timeline</h2>
                <p>{{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }} &bull; {{ count($stops) }} Stationary Stops</p>
            </div>
            <div class="timeline-list">
                @forelse($timeline as $idx => $event)
                    <div class="timeline-item"
                         onclick="focusEvent({{ $event['lat'] ?? 'null' }}, {{ $event['lng'] ?? 'null' }}, '{{ $event['title'] }}', '{{ $event['duration_text'] }}', this)">
                        <div class="timeline-icon {{ $event['type'] }}">
                            @if($event['type'] === 'stop')
                                &#9632;
                            @else
                                &#8595;
                            @endif
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-title">{{ $event['title'] }}</span>
                                <span class="timeline-time">{{ $event['started_at'] }} - {{ $event['ended_at'] }}</span>
                            </div>
                            <div class="timeline-desc">{{ $event['subtitle'] }}</div>
                            <span class="badge-duration {{ $event['type'] }}">{{ $event['duration_text'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <p>No location points recorded for this representative on {{ $date }}.</p>
                    </div>
                @endforelse
            </div>
        </aside>

        <!-- Right Side: Leaflet OpenStreetMap View -->
        <main class="map-container">
            <div id="map"></div>
        </main>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        const trackPoints = @json($track);
        const stops = @json($stops);

        // Initialize Leaflet map
        const defaultCenter = trackPoints.length > 0
            ? [trackPoints[0].lat, trackPoints[0].lng]
            : [11.0510, 76.0710];

        const map = L.map('map').setView(defaultCenter, 14);

        // OpenStreetMap tile layer (no API key required)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        let stopMarkers = [];
        let stopCircles = [];

        if (trackPoints.length > 0) {
            // Draw path polyline
            const latLngs = trackPoints.map(p => [p.lat, p.lng]);
            const polyline = L.polyline(latLngs, {
                color: '#0284c7',
                weight: 4,
                opacity: 0.85,
                smoothFactor: 1
            }).addTo(map);

            // Fit map bounds to track
            map.fitBounds(polyline.getBounds(), { padding: [40, 40] });

            // Start marker
            L.circleMarker([trackPoints[0].lat, trackPoints[0].lng], {
                radius: 8,
                fillColor: '#22c55e',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.95
            }).bindPopup("<strong>Shift Started</strong><br>" + trackPoints[0].recorded_at).addTo(map);

            // End marker
            const last = trackPoints[trackPoints.length - 1];
            L.circleMarker([last.lat, last.lng], {
                radius: 8,
                fillColor: '#ef4444',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.95
            }).bindPopup("<strong>Shift Ended</strong><br>" + last.recorded_at).addTo(map);
        }

        // Plot stationary stops
        stops.forEach((stop, index) => {
            const lat = Number(stop.lat);
            const lng = Number(stop.lng);
            const durationMin = Math.round(stop.duration_seconds / 60);

            // 50-meter radius boundary representation
            const circle = L.circle([lat, lng], {
                radius: 50,
                color: '#dc2626',
                fillColor: '#fca5a5',
                fillOpacity: 0.25,
                weight: 1.5,
                dashArray: '4, 4'
            }).addTo(map);
            stopCircles.push(circle);

            const marker = L.circleMarker([lat, lng], {
                radius: 9,
                fillColor: '#dc2626',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 1
            }).bindPopup(`
                <div style="font-size: 13px; line-height: 1.4;">
                    <strong style="color: #b91c1c;">Stop #${index + 1}</strong> (${durationMin} mins)<br>
                    <strong>Start:</strong> ${stop.started_at}<br>
                    <strong>End:</strong> ${stop.ended_at}<br>
                    <strong>Radius:</strong> &le; 50 meters
                </div>
            `).addTo(map);

            stopMarkers.push({ lat, lng, marker });
        });

        function focusEvent(lat, lng, title, duration, element) {
            document.querySelectorAll('.timeline-item').forEach(el => el.classList.remove('active'));
            if (element) {
                element.classList.add('active');
            }

            if (lat !== null && lng !== null) {
                map.flyTo([lat, lng], 17, { duration: 1.2 });
                const found = stopMarkers.find(m => Math.abs(m.lat - lat) < 1e-5 && Math.abs(m.lng - lng) < 1e-5);
                if (found) {
                    found.marker.openPopup();
                }
            }
        }
    </script>
</body>
</html>
