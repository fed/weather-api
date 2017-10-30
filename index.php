<?php

// ----------------------------------------------------
// CORS: Allow requests from all origins
// ----------------------------------------------------
header("Content-type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: X-Requested-With");

// ----------------------------------------------------
// Hit the OpenWeatherMap API
// ----------------------------------------------------
$owm_api_key = "df44f0116a96d066cf9aba38b715486b";
$owm_endpoint = "https://api.openweathermap.org/data/2.5/" .
  "weather?q=Freyre,Cordoba&units=metric&lang=es&appid=" . $owm_api_key;
$owm_response = file_get_contents($owm_endpoint);
$owm_data = json_decode($owm_response, TRUE);

// ----------------------------------------------------
// Hit the Local Weather Station API
// ----------------------------------------------------
$local_station_endpoint = "http://magya.omixom.com/xmlDatosEstaciones.php";
$local_station_response = file_get_contents($local_station_endpoint); // XML response

// Parse XML to JSON.
$local_station_xml_data = simplexml_load_string($local_station_response);
$local_station_json_data = json_decode(json_encode($local_station_xml_data), TRUE);

// Grab data from all weather stations.
$all_stations = $local_station_json_data["RedEstaciones"]["Estacion"];

// From all stations, filter out the one with ID 30128 (Freyre).
// This returns an array with a single element.
$local_station_data_array = array_filter($all_stations, function ($station) {
  return $station["numero"] == "30128";
});

// Return a flattened array of weather data.
$local_station_data = array_values($local_station_data_array)[0];

// ----------------------------------------------------
// Response metadata
// ----------------------------------------------------
$meta = array(
  "code" => 200,
  "message" => "OK"
);

// ----------------------------------------------------
// Parse response data
// ----------------------------------------------------
$data = array(
  "updated_at" => $local_station_data["fechaMuestra"],
  "location" => array(
    "name" => "Freyre, Córdoba, Argentina",
    "latitude" => floatval($local_station_data["latitud"]),
    "longitude" => floatval($local_station_data["longitud"]),
    "altitude" => $owm_data["main"]["sea_level"]
  ),
  "temperature" => array(
    "current" => array(
      "label" => "Temperatura actual",
      "value" => floatval($local_station_data["temperatura"]),
      "unit" => "°C"
    ),
    "max" => array(
      "label" => "Temperatura máxima registrada hoy",
      "value" => floatval($local_station_data["temperaturaMaxima"]),
      "unit" => "°C"
    ),
    "min" => array(
      "label" => "Temperatura mínima registrada hoy",
      "value" => floatval($local_station_data["temperaturaMinima"]),
      "unit" => "°C"
    ),
    "feelsLike" => array(
      "label" => "Sensación térmica",
      "value" => floatval($local_station_data["sensacionTermica"]),
      "unit" => "°C"
    )
  ),
  "humidity" => array(
    "label" => "Humedad porcentual",
    "value" => floatval($local_station_data["humidity"]),
    "unit" => "%"
  ),
  "pressure" => array(
    "label" => "Presión atmosférica",
    "value" => $owm_data["main"]["pressure"],
    "unit" => "hPa"
  ),
  "dewPoint" => array(
    "label" => "Punto de rocío",
    "value" => floatval($local_station_data["puntoRocio"]),
    "unit" => "°C"
  ),
  "wind" => array(
    "speed" => array(
      "label" => "Velocidad del viento",
      "value" => floatval($local_station_data["velocidadViento"]),
      "unit" => "km/h"
    ),
    "direction" => array(
      "label" => "Dirección del viento",
      "value" => $local_station_data["direccionViento"],
      "unit" => null
    )
  ),
  "cummulativeRain" => array(
    "day" => array(
      "label" => "Precipitación acumulada hoy",
      "value" => floatval($local_station_data["precipitacionDia"]),
      "unit" => "mm"
    ),
    "week" => array(
      "label" => "Precipitación acumulada esta semana",
      "value" => floatval($local_station_data["precipitacionSemana"]),
      "unit" => "mm"
    ),
    "month" => array(
      "label" => "Precipitación acumulada en el mes en curso",
      "value" => floatval($local_station_data["precipitacionMes"]),
      "unit" => "mm"
    ),
    "year" => array(
      "label" => "Precipitación acumulada en el transcurso del año",
      "value" => null,
      "unit" => "mm"
    )
  ),
  "icon" => array(
    "id" => $owm_data["weather"][0]["id"],
    "main" => $owm_data["weather"][0]["main"],
    "description" => $owm_data["weather"][0]["description"],
    "filename" => $owm_data["weather"][0]["icon"],
    "url" => "//" . $_SERVER["SERVER_NAME"] . "/icons/" . $owm_data["weather"][0]["icon"] . ".svg"
  )
);

// ----------------------------------------------------
// Return the data as a JSON object
// ---------------------------------------------------
$json_response = json_encode(array(
  "meta" => $meta,
  "data" => $data
));

echo $json_response;
