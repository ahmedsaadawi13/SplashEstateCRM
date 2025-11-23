<?php
// FILE: /app/views/partials/property_map.php
// Reusable property map component
// Required variables: $property (array with address, latitude, longitude)
// Optional variables: $height (default: 400px), $showStaticFallback (default: true)

$mapHeight = isset($height) ? $height : '400px';
$showStatic = isset($showStaticFallback) ? $showStaticFallback : true;
$mapId = 'property_map_' . uniqid();

// Check if Google Maps is configured
$mapsConfigured = defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY);
?>

<div class="property-map-container">
    <?php if ($mapsConfigured && !empty($property['latitude']) && !empty($property['longitude'])): ?>
        <!-- Interactive Google Map -->
        <div id="<?php echo $mapId; ?>" class="property-map" style="width: 100%; height: <?php echo $mapHeight; ?>;"></div>

        <script>
        (function() {
            var lat = <?php echo $property['latitude']; ?>;
            var lng = <?php echo $property['longitude']; ?>;
            var address = <?php echo json_encode($property['address'] . ', ' . $property['city'] . ', ' . $property['state']); ?>;

            function initMap_<?php echo $mapId; ?>() {
                var location = {lat: lat, lng: lng};

                var map = new google.maps.Map(document.getElementById('<?php echo $mapId; ?>'), {
                    zoom: 15,
                    center: location,
                    mapTypeId: 'roadmap',
                    streetViewControl: true,
                    mapTypeControl: true,
                    fullscreenControl: true
                });

                var marker = new google.maps.Marker({
                    position: location,
                    map: map,
                    title: address,
                    animation: google.maps.Animation.DROP
                });

                var infoWindow = new google.maps.InfoWindow({
                    content: '<div style="padding: 10px;"><strong><?php echo htmlspecialchars($property['title']); ?></strong><br>' +
                            '<small>' + address + '</small></div>'
                });

                marker.addListener('click', function() {
                    infoWindow.open(map, marker);
                });

                // Open info window by default
                infoWindow.open(map, marker);
            }

            // Initialize when Google Maps API is loaded
            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                initMap_<?php echo $mapId; ?>();
            } else {
                // Wait for API to load
                window.addEventListener('load', function() {
                    if (typeof google !== 'undefined') {
                        initMap_<?php echo $mapId; ?>();
                    }
                });
            }
        })();
        </script>

    <?php elseif ($showStatic && $mapsConfigured && !empty($property['latitude']) && !empty($property['longitude'])): ?>
        <!-- Static Map Fallback -->
        <?php
        try {
            require_once APP_PATH . '/helpers/GoogleMapsService.php';
            $mapsService = new GoogleMapsService();
            $staticMapUrl = $mapsService->getStaticMapUrl(array(
                'lat' => $property['latitude'],
                'lng' => $property['longitude'],
                'size' => '800x400',
                'zoom' => 15
            ));
        } catch (Exception $e) {
            $staticMapUrl = null;
        }
        ?>

        <?php if ($staticMapUrl): ?>
            <img src="<?php echo htmlspecialchars($staticMapUrl); ?>"
                 alt="Property Location Map"
                 class="property-static-map"
                 style="width: 100%; height: <?php echo $mapHeight; ?>; object-fit: cover;">
        <?php endif; ?>

    <?php else: ?>
        <!-- No Map Available -->
        <div class="property-map-placeholder" style="width: 100%; height: <?php echo $mapHeight; ?>; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
            <div style="text-align: center; color: #999;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <p style="margin: 10px 0 0 0;">
                    <?php if (!$mapsConfigured): ?>
                        Map display not configured
                    <?php elseif (empty($property['latitude']) || empty($property['longitude'])): ?>
                        Location coordinates not available
                    <?php else: ?>
                        Map not available
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Address Display -->
    <div class="property-address-info" style="margin-top: 16px; padding: 16px; background: #f8f9fa; border-radius: 6px;">
        <div style="display: flex; align-items: start; gap: 12px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#007bff" style="flex-shrink: 0; margin-top: 2px;">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke-width="2"></path>
                <circle cx="12" cy="10" r="3" stroke-width="2"></circle>
            </svg>
            <div style="flex: 1;">
                <strong><?php echo htmlspecialchars($property['address']); ?></strong><br>
                <span style="color: #666;">
                    <?php echo htmlspecialchars($property['city'] . ', ' . $property['state'] . ' ' . $property['zip']); ?>
                </span>

                <?php if (!empty($property['latitude']) && !empty($property['longitude'])): ?>
                    <br>
                    <small style="color: #999;">
                        Coordinates: <?php echo number_format($property['latitude'], 6); ?>, <?php echo number_format($property['longitude'], 6); ?>
                    </small>
                <?php endif; ?>

                <!-- External Map Links -->
                <div style="margin-top: 8px;">
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($property['address'] . ', ' . $property['city'] . ', ' . $property['state']); ?>"
                       target="_blank"
                       class="btn btn-sm btn-outline"
                       style="display: inline-block; padding: 6px 12px; margin-right: 8px; text-decoration: none; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        Open in Google Maps
                    </a>

                    <?php if (!empty($property['latitude']) && !empty($property['longitude'])): ?>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $property['latitude']; ?>,<?php echo $property['longitude']; ?>"
                           target="_blank"
                           class="btn btn-sm btn-outline"
                           style="display: inline-block; padding: 6px 12px; text-decoration: none; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                            Get Directions
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.property-map {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.property-static-map {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.property-map-placeholder {
    border: 2px dashed #ddd;
}

.property-address-info {
    font-size: 14px;
}

.btn-sm {
    transition: all 0.2s ease;
}

.btn-sm:hover {
    background: #f0f0f0;
    border-color: #007bff;
    color: #007bff;
}
</style>
