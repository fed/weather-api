<?php

// ----------------------------------------------------
// CORS: Allow requests from all origins
// ----------------------------------------------------
header("Content-type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: X-Requested-With");

// ----------------------------------------------------
// Hit the yr.no API
// ----------------------------------------------------
$yr_endpoint = "https://www.yr.no/place/Argentina/Córdoba/Freyre/forecast.xml";
$yr_response = file_get_contents($yr_endpoint); // XML response

// Parse XML to JSON.
$yr_xml_data = simplexml_load_string($yr_response);
$yr_json_data = json_decode(json_encode($yr_xml_data), TRUE);

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
// Parse forecast from yr.no API response
// ----------------------------------------------------
function parse_forecast($day) {
  return array(
    "from" => $day["@attributes"]["from"],
    "to" => $day["@attributes"]["to"],
    "temperature" => array(
      "label" => "Temperatura esperada",
      "value" => floatval($day["temperature"]["@attributes"]["value"]),
      "unit" => "°C"
    ),
    "pressure" => array(
      "label" => "Presión atmosférica esperada",
      "value" => floatval($day["pressure"]["@attributes"]["value"]),
      "unit" => $day["pressure"]["@attributes"]["unit"]
    ),
    "precipitation" => array(
      "label" => "Precipitación esperada",
      "value" => floatval($day["precipitation"]["@attributes"]["value"]),
      "unit" => "mm"
    ),
    "wind" => array(
      "speed" => array(
        "label" => "Velocidad esperada del viento",
        "value" => floatval(number_format($day["windSpeed"]["@attributes"]["mps"] * 3.6, 2)),
        "unit" => "km/h",
        "description" => $day["windSpeed"]["@attributes"]["name"]
      ),
      "direction" => array(
        "label" => "Dirección esperada del viento",
        "value" => floatval($day["windDirection"]["@attributes"]["deg"]),
        "unit" => "°",
        "code" => $day["windDirection"]["@attributes"]["code"],
        "description" => $day["windDirection"]["@attributes"]["name"]
      )
    ),
    "icon" => array(
      "description" => $day["symbol"]["@attributes"]["name"],
      "filename" => $day["symbol"]["@attributes"]["var"],
      "url" => "https://" . $_SERVER["SERVER_NAME"] . "/icons/" . $day["symbol"]["@attributes"]["var"] . ".svg"
    )
  );
}

$parsed_forecast = array_map("parse_forecast", $yr_json_data["forecast"]["tabular"]["time"]);

// ----------------------------------------------------
// Parse response data
// ----------------------------------------------------
$data = array(
  "location" => array(
    "name" => "Freyre, Córdoba, Argentina",
    "latitude" => floatval($local_station_data["latitud"]),
    "longitude" => floatval($local_station_data["longitud"]),
    "altitude" => floatval($yr_json_data["location"]["location"]["@attributes"]["altitude"])
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
      "value" => $parsed_forecast[0]["pressure"]["value"],
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
    "sun" => array(
      "rise" => $yr_json_data["sun"]["@attributes"]["rise"],
      "set" => $yr_json_data["sun"]["@attributes"]["set"]
    ),
    "icon" => $parsed_forecast[0]["icon"]
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
