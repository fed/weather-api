<?php

header('Content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With');

// Hit the API
$endpoint = 'http://magya.omixom.com/xmlDatosEstaciones.php';
$curl_instance = curl_init();

curl_setopt($curl_instance, CURLOPT_URL, $endpoint);
curl_setopt($curl_instance, CURLOPT_HEADER, 0);
curl_setopt($curl_instance, CURLOPT_RETURNTRANSFER, 1);

$response = curl_exec($curl_instance);

curl_close($curl_instance);

// Parse XML to JSON
$xml_weather_data = simplexml_load_string($response);
$json_weather_data = json_encode($xml_weather_data);
$array_weather_data = json_decode($json_weather_data, TRUE);

// Grab weather stations
$stations = $array_weather_data['RedEstaciones']['Estacion'];

// From all stations, filter out the one with ID 30128 (Freyre)
$freyre = array_filter($stations, function ($station) {
  return $station['numero'] == '30128';
});

// Get the weather data from the station
$weather = array_values($freyre)[0];

// Return the data as a JSON object
$response = json_encode($weather);

echo $response;
