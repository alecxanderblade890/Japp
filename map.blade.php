<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Mapbox Intersection Detection</title>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.js"></script>
    <script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script> <!-- Turf.js -->
    <style>
        #map { width: 100%; height: 900px; } /* Updated height */
        .reset-btn {
            position: absolute; top: 10px; right: 10px;
            z-index: 1000; background: red; color: white;
            padding: 10px; cursor: pointer; border: none;
        }
    </style>
</head>
<body>

<div id="map"></div>
<button class="reset-btn" onclick="resetMarkers()">Reset</button>

<script>
    const predefinedRoutes = [
    {
        route_name: "City Loop",
        waypoints: [
            [122.49978, 10.69344], 
            [122.51610, 10.68907],  
            [122.52393, 10.68906],  
            [122.53253, 10.69080],
            [122.54416, 10.69631],  
            [122.54978, 10.69918],  
            [122.55342, 10.69956],  
            [122.55549, 10.69989],  
            [122.55993, 10.70027],  
            [122.56795, 10.70150], 
            [122.56843, 10.70153],
            [122.56841, 10.70169],   
            [122.56903, 10.69661],  
            [122.57094, 10.69397],  
            [122.57363, 10.69202],  
            [122.57363, 10.69170],  
            [122.56931, 10.69203],  
            [122.56899, 10.69212],  
            [122.56799, 10.69194],  
            [122.56783, 10.69782],  
            [122.56486, 10.69772],  
            [122.56477, 10.69760],  
            [122.56472, 10.69337],  
            [122.56332, 10.69323],  
            [122.56315, 10.69475],  

        ]
    },
    {
        route_name: "Coastal Drive",
        waypoints: [
            [122.56010, 10.71500], // Start point
            [122.56150, 10.71720],
            [122.56300, 10.71950],
            [122.56480, 10.72230], // Midpoint
            [122.56690, 10.72500],
            [122.56850, 10.72750], // End point
        ]
    },
    {
        route_name: "Mountain Pass",
        waypoints: [
            [122.54500, 10.71000], // Start point
            [122.54730, 10.71300],
            [122.54980, 10.71650],
            [122.55220, 10.72000], // Midpoint
            [122.55500, 10.72380],
            [122.55850, 10.72650], // End point
        ]
    }
];
    mapboxgl.accessToken = 'pk.eyJ1IjoiYmxhZGU4OTAiLCJhIjoiY204czFwYjNjMHF4NjJtb21sYmFuYzhtbSJ9.tv4g_uT4Rqx0Lm4uzKBcog';

    const map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/streets-v11',
        center: [122.55195, 10.71996], // Default center
        zoom: 14
    });

    // Predefined route coordinates
    const predefinedStart = [122.53873, 10.75036]; // Example start point
    const predefinedEnd = [122.58266, 10.69177];

    let userMarkers = [];
    let predefinedRouteGeoJSON = null;

    function createMarker(lngLat, color) {
        const el = document.createElement('div');
        el.style.backgroundColor = color;
        el.style.width = '15px';
        el.style.height = '15px';
        el.style.borderRadius = '50%';

        return new mapboxgl.Marker({ element: el, draggable: false })
            .setLngLat(lngLat)
            .addTo(map);
    }

    function drawPredefinedRoute(route, routeId, color, saveTo) {
        let coordinates = route.waypoints.map(coord => coord.join(',')).join(';');
        let routeUrl = `https://api.mapbox.com/directions/v5/mapbox/driving/${coordinates}?geometries=geojson&overview=full&access_token=${mapboxgl.accessToken}`;

        fetch(routeUrl)
            .then(response => response.json())
            .then(data => {
                if (data.routes.length) {
                    const routeGeoJSON = data.routes[0].geometry;

                    // Remove previous layer if it exists
                    if (map.getLayer(routeId)) {
                        map.removeLayer(routeId);
                        map.removeSource(routeId);
                    }

                    // Add new route to the map
                    map.addSource(routeId, {
                        type: 'geojson',
                        data: {
                            type: 'Feature',
                            geometry: routeGeoJSON
                        }
                    });

                    map.addLayer({
                        id: routeId,
                        type: 'line',
                        source: routeId,
                        layout: { 'line-join': 'round', 'line-cap': 'round' },
                        paint: { 'line-color': color, 'line-width': 4 }
                    });

                    // Save to predefined route variable if specified
                    if (saveTo === 'predefined') {
                        predefinedRouteGeoJSON = routeGeoJSON;
                    }
                }
            })
            .catch(error => console.error(`Error fetching ${routeId} route:`, error));
    }


    function checkMarkerIntersections() {
        if (!predefinedRouteGeoJSON || userMarkers.length < 2) {
            console.warn("Predefined route not loaded or not enough markers placed.");
            return;
        }

        let predefinedLine = turf.lineString(predefinedRouteGeoJSON.coordinates);
        let startMarkerCoords = userMarkers[0].getLngLat().toArray();
        let endMarkerCoords = userMarkers[1].getLngLat().toArray();

        // Find nearest point on the predefined route for start and end markers
        let nearestStart = turf.nearestPointOnLine(predefinedLine, turf.point(startMarkerCoords));
        let nearestEnd = turf.nearestPointOnLine(predefinedLine, turf.point(endMarkerCoords));

        // Set a small threshold to check if points are on the route
        let threshold = 0.05; // Small distance threshold
        let startOnRoute = nearestStart.properties.dist <= threshold;
        let endOnRoute = nearestEnd.properties.dist <= threshold;

        if (startOnRoute && endOnRoute) {
            alert('🚀 Both Start and End Markers Intersect the Predefined Route!');
        } else if (startOnRoute) {
            alert('⚠️ Only the Start Marker Intersects the Predefined Route.');
        } else if (endOnRoute) {
            alert('⚠️ Only the End Marker Intersects the Predefined Route.');
        } else {
            alert('❌ Neither Start nor End Markers Intersect the Predefined Route.');
        }
    }

    // Draw predefined route (always stays)
    drawPredefinedRoute(predefinedRoutes[0], "city-loop-route", "#007bff", "predefined");

    // Left Click - Place Start & End Markers
    map.on('click', (e) => {
        if (userMarkers.length < 2) {
            const color = userMarkers.length === 0 ? 'blue' : 'red';
            const marker = createMarker(e.lngLat, color);
            userMarkers.push(marker);
            marker.getElement().addEventListener('click', () => removeMarker(marker));

            if (userMarkers.length === 2) {
                drawRoute(userMarkers[0].getLngLat().toArray(), userMarkers[1].getLngLat().toArray(), 'user-route', '#FF0000', 'user'); // Red polyline
                // Removed duplicate checkMarkerIntersections();
            }
        }
    });

    function removeMarker(marker) {
        marker.remove();
        userMarkers = userMarkers.filter(m => m !== marker);

        if (map.getLayer('user-route')) {
            map.removeLayer('user-route');
            map.removeSource('user-route');
        }
    }

    function resetMarkers() {
        userMarkers.forEach(m => m.remove());
        userMarkers = [];

        if (map.getLayer('user-route')) {
            map.removeLayer('user-route');
            map.removeSource('user-route');
        }
    }
</script>

</body>
</html>
