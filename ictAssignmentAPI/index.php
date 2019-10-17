<?php
header("Access-Control-Allow-Origin: *", false);
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: *");

  $request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

  $params = $_SERVER['QUERY_STRING'];

  switch ($request) {
    case "/getsession":
		apiRequests($params);
    break;
    case "":
      apiRequests();
    break;

  }

  function apiRequests($apiRouteAddress)
  {
    $data = file_get_contents($apiRouteAddress);
    echo json_encode($data);
  }
?>