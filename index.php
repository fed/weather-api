<?php

header('Content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With');

// ----------------------------------------------------
// Hit the OpenWeatherMap API
// ----------------------------------------------------
$owm_api_key = 'df44f0116a96d066cf9aba38b715486b';
$owm_endpoint = 'https://api.openweathermap.org/data/2.5/' .
  'weather?q=Freyre,Cordoba&units=metric&lang=es&appid=' . $owm_api_key;
$owm_response = file_get_contents($owm_endpoint);
$owm_data = json_decode($owm_response, TRUE);

// ----------------------------------------------------
// Hit the Local Weather Station API
// ----------------------------------------------------
$local_station_endpoint = 'http://magya.omixom.com/xmlDatosEstaciones.php';
$local_station_response = file_get_contents($local_station_endpoint);

// Parse XML to JSON
$local_station_xml_data = simplexml_load_string($local_station_response);
$local_station_json_data = json_decode(json_encode($local_station_xml_data), TRUE);

// Grab weather stations
$stations = $local_station_json_data['RedEstaciones']['Estacion'];

// From all stations, filter out the one with ID 30128 (Freyre)
$freyre = array_filter($stations, function ($station) {
  return $station['numero'] == '30128';
});

// Get the weather data from the station
$weather = array_values($freyre)[0];

// Grab missing data from OpenWeatherMap
$weather['presion'] = $owm_data['main']['pressure'] . ' hPa';
$weather['altura'] = $owm_data['main']['sea_level'] . ' msnm';
$weather['imagen'] = array(
  'id' => $owm_data['weather'][0]['id'],
  'tipo' => $owm_data['weather'][0]['main'],
  'descripcion' => $owm_data['weather'][0]['description'],
  'icono' => $owm_data['weather'][0]['icon'],
  'url' => 'https://' . $_SERVER['SERVER_NAME'] . '/iconos/' . $owm_data['weather'][0]['icon'] . '.svg'
);

// Add units
$weather['temperatura'] = $weather['temperatura'] . '°';
$weather['temperaturaMaxima'] = $weather['temperaturaMaxima'] . '°';
$weather['temperaturaMinima'] = $weather['temperaturaMinima'] . '°';
$weather['sensacionTermica'] = $weather['sensacionTermica'] . '°';
$weather['humedad'] = $weather['humedad'] . '%';
$weather['puntoRocio'] = $weather['puntoRocio'] . '°';
$weather['velocidadViento'] = $weather['velocidadViento'] . ' km/h';
$weather['precipitacionDia'] = $weather['precipitacionDia'] . ' mm';
$weather['precipitacionSemana'] = $weather['precipitacionSemana'] . ' mm';
$weather['precipitacionMes'] = $weather['precipitacionMes'] . ' mm';

// Return the data as a JSON object
$json_response = json_encode($weather);

echo $json_response;
