<?php
// FILE: /app/helpers/GoogleMapsService.php

/**
 * SplashEstate CRM - Google Maps Service
 * Standalone Google Maps API integration for geocoding and mapping
 */

class GoogleMapsService {

    private $apiKey;
    private $geocodeEndpoint = 'https://maps.googleapis.com/maps/api/geocode/json';
    private $staticMapEndpoint = 'https://maps.googleapis.com/maps/api/staticmap';

    /**
     * Constructor
     * @param string $apiKey Google Maps API key
     */
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: (defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '');

        if (empty($this->apiKey)) {
            throw new Exception('Google Maps API key is required');
        }
    }

    /**
     * Geocode an address to get latitude and longitude
     * @param string $address Full address string
     * @return array|false Array with lat/lng or false on failure
     */
    public function geocodeAddress($address) {
        if (empty($address)) {
            return false;
        }

        $url = $this->geocodeEndpoint . '?' . http_build_query(array(
            'address' => $address,
            'key' => $this->apiKey
        ));

        $response = $this->makeRequest($url);

        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);

        if ($data['status'] !== 'OK' || empty($data['results'])) {
            error_log('Geocoding failed: ' . $data['status']);
            return false;
        }

        $result = $data['results'][0];
        $location = $result['geometry']['location'];

        return array(
            'latitude' => $location['lat'],
            'longitude' => $location['lng'],
            'formatted_address' => isset($result['formatted_address']) ? $result['formatted_address'] : $address,
            'place_id' => isset($result['place_id']) ? $result['place_id'] : null
        );
    }

    /**
     * Reverse geocode coordinates to get address
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return array|false Address components or false
     */
    public function reverseGeocode($lat, $lng) {
        if (empty($lat) || empty($lng)) {
            return false;
        }

        $url = $this->geocodeEndpoint . '?' . http_build_query(array(
            'latlng' => $lat . ',' . $lng,
            'key' => $this->apiKey
        ));

        $response = $this->makeRequest($url);

        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);

        if ($data['status'] !== 'OK' || empty($data['results'])) {
            error_log('Reverse geocoding failed: ' . $data['status']);
            return false;
        }

        $result = $data['results'][0];

        return array(
            'formatted_address' => isset($result['formatted_address']) ? $result['formatted_address'] : '',
            'address_components' => isset($result['address_components']) ? $result['address_components'] : array(),
            'place_id' => isset($result['place_id']) ? $result['place_id'] : null
        );
    }

    /**
     * Get static map image URL
     * @param array $params Map parameters
     * @return string Map image URL
     */
    public function getStaticMapUrl($params) {
        $defaults = array(
            'size' => '600x400',
            'zoom' => 15,
            'maptype' => 'roadmap',
            'key' => $this->apiKey
        );

        $params = array_merge($defaults, $params);

        // Add marker if coordinates provided
        if (isset($params['lat']) && isset($params['lng'])) {
            $params['markers'] = 'color:red|' . $params['lat'] . ',' . $params['lng'];
            unset($params['lat'], $params['lng']);
        }

        return $this->staticMapEndpoint . '?' . http_build_query($params);
    }

    /**
     * Get embed map HTML
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param array $options Map options
     * @return string HTML for embedded map
     */
    public function getEmbedMap($lat, $lng, $options = array()) {
        $defaults = array(
            'width' => '100%',
            'height' => '400px',
            'zoom' => 15
        );

        $options = array_merge($defaults, $options);

        $mapId = 'map_' . uniqid();

        $html = '<div id="' . $mapId . '" style="width: ' . $options['width'] . '; height: ' . $options['height'] . ';"></div>';
        $html .= '<script>';
        $html .= 'function initMap_' . $mapId . '() {';
        $html .= '  var location = {lat: ' . $lat . ', lng: ' . $lng . '};';
        $html .= '  var map = new google.maps.Map(document.getElementById("' . $mapId . '"), {';
        $html .= '    zoom: ' . $options['zoom'] . ',';
        $html .= '    center: location';
        $html .= '  });';
        $html .= '  var marker = new google.maps.Marker({';
        $html .= '    position: location,';
        $html .= '    map: map';
        $html .= '  });';
        $html .= '}';
        $html .= 'if (typeof google !== "undefined") { initMap_' . $mapId . '(); }';
        $html .= '</script>';

        return $html;
    }

    /**
     * Get map script tag
     * @param string $callback Optional callback function name
     * @return string Script tag for Google Maps JS API
     */
    public function getMapScript($callback = null) {
        $url = 'https://maps.googleapis.com/maps/api/js?key=' . $this->apiKey;

        if ($callback) {
            $url .= '&callback=' . $callback;
        }

        return '<script src="' . $url . '" async defer></script>';
    }

    /**
     * Calculate distance between two coordinates (in kilometers)
     * @param float $lat1 Latitude of point 1
     * @param float $lng1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lng2 Longitude of point 2
     * @return float Distance in kilometers
     */
    public function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earthRadius = 6371; // kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Test API connection
     * @return array Test result
     */
    public function testConnection() {
        try {
            $result = $this->geocodeAddress('1600 Amphitheatre Parkway, Mountain View, CA');

            if ($result && isset($result['latitude'])) {
                return array(
                    'success' => true,
                    'message' => 'Google Maps API connection successful'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Failed to geocode test address'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Make HTTP request to Google Maps API
     * @param string $url Request URL
     * @return string|false Response body or false
     */
    private function makeRequest($url) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($error) {
            error_log('Google Maps API request failed: ' . $error);
            return false;
        }

        if ($httpCode !== 200) {
            error_log('Google Maps API returned HTTP ' . $httpCode);
            return false;
        }

        return $response;
    }

    /**
     * Parse address components from geocoding result
     * @param array $addressComponents Address components from Google
     * @return array Parsed address fields
     */
    public function parseAddressComponents($addressComponents) {
        $parsed = array(
            'street_number' => '',
            'route' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'postal_code' => ''
        );

        foreach ($addressComponents as $component) {
            $types = $component['types'];

            if (in_array('street_number', $types)) {
                $parsed['street_number'] = $component['long_name'];
            } elseif (in_array('route', $types)) {
                $parsed['route'] = $component['long_name'];
            } elseif (in_array('locality', $types)) {
                $parsed['city'] = $component['long_name'];
            } elseif (in_array('administrative_area_level_1', $types)) {
                $parsed['state'] = $component['short_name'];
            } elseif (in_array('country', $types)) {
                $parsed['country'] = $component['long_name'];
            } elseif (in_array('postal_code', $types)) {
                $parsed['postal_code'] = $component['long_name'];
            }
        }

        $parsed['address'] = trim($parsed['street_number'] . ' ' . $parsed['route']);

        return $parsed;
    }
}
