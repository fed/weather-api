<?php

// ----------------------------------------------------
// CORS: Allow requests from all origins
// ----------------------------------------------------
header("Content-type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: X-Requested-With");

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

// From all stations, filter out the one with ID 30128.
// This returns an array with a single element.
$local_station_data_array = array_filter($all_stations, function ($station) {
  return $station["numero"] == "30128";
});

// Return a flattened array of weather data.
$local_station_data = array_values($local_station_data_array)[0];

// ----------------------------------------------------
// Hit the Weather Channel API
// ----------------------------------------------------
$wc_endpoint = "https://api.wunderground.com/api/bbc3eb6e6abf8b8e/geolookup/conditions/forecast/lang:SP/q/Argentina/Freyre.json";
$wc_response = file_get_contents($wc_endpoint); // JSON response
$wc_data = json_decode($wc_response, TRUE);

// ----------------------------------------------------
// Parse current conditions icon URL
// ----------------------------------------------------
$icon_url = str_replace("http://icons.wxug.com/i/c/k/", "https://weather-icons.argendev.com/", $wc_data["current_observation"]["icon_url"]);
$icon_url = str_replace(".gif", ".svg", $icon_url);

// ----------------------------------------------------
// Parse forecast from Weather Channel API response
// ----------------------------------------------------
function parse_forecast($entry) {
  $description = !empty($entry["fcttext_metric"]) ? $entry["fcttext_metric"] : $entry["fcttext"];

  return array(
    "period" => $entry["period"],
    "altIconUrl" => $entry["icon_url"],
    "iconUrl" => "https://weather-icons.argendev.com/" . $entry["icon"] . ".svg",
    "icon" => $entry["icon"],
    "title" => $entry["title"],
    "description" => $description,
    "chanceOfRain" => $entry["pop"] . "%"
  );
}

$parsed_forecast = array_map("parse_forecast", $wc_data["forecast"]["txt_forecast"]["forecastday"]);

// ----------------------------------------------------
// Parse response data
// ----------------------------------------------------
$data = array(
  "location" => array(
    "name" => "Freyre, Córdoba, Argentina",
    "latitude" => floatval($local_station_data["latitud"]),
    "longitude" => floatval($local_station_data["longitud"])
  ),
  "currentConditions" => array(
    "updatedAt" => $local_station_data["fechaMuestra"],
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
      "value" => floatval($local_station_data["humedad"]),
      "unit" => "%"
    ),
    "pressure" => array(
      "label" => "Presión atmosférica",
      "value" => floatval($wc_data["current_observation"]["pressure_mb"]),
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
        "value" => ucfirst(strtolower($local_station_data["direccionViento"])),
        "unit" => null
      )
    ),
    "cumulativeRainfall" => array(
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
      "filename" => $wc_data["current_observation"]["icon"],
      "url" => $icon_url,
      "altUrl" => $wc_data["current_observation"]["icon_url"],
      "description" => $wc_data["current_observation"]["weather"]
    )
  ),
  "forecast" => $parsed_forecast
);

// ----------------------------------------------------
// Response metadata
// ----------------------------------------------------
$meta = array(
  "code" => 200,
  "message" => "Success"
);

// ----------------------------------------------------
// Return the data as a JSON object
// ---------------------------------------------------
$json_response = json_encode(array(
  "meta" => $meta,
  "data" => $data
));

echo $json_response;
