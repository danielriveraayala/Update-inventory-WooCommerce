<?php
require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;

// Conexión WooCommerce API destino
// ================================
$url_API_woo = 'https://tu-tienda.com';
$ck_API_woo = 'ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
$cs_API_woo = 'cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

$woocommerce = new Client(
    $url_API_woo,
    $ck_API_woo,
    $cs_API_woo,
    ['version' => 'wc/v3', 'verify_ssl' => false, 'timeout' => 20000]
);
// ================================


// Conexión API origen
// ===================
$url_API = "/data-example/data.json";


$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_URL, $url_API);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

echo "➜ Obteniendo datos de origen [$url_API] \n";
$items_origin = curl_exec($ch);
curl_close($ch);

if (!$items_origin) {
    exit('❗Error en API origen');
}
// ===================


// Obtenemos datos de la API de origen
$items_origin = json_decode($items_origin, true);

// formamos el parámetro de lista de SKUs a actualizar
$param_sku = [];
foreach ($items_origin as $item) {
    /*echo "************************************\n";
    echo "PRODUCTO ➜ " . $item['title'] . "\n";
    echo "SKU ➜ " . $item['sku'] . "\n";
    echo "VENDOR ➜ " . $item['vendor'] . "\n";
    echo "QUANTITY ➜ " . $item['quantity'] . "\n";
    echo "BARCODE ➜ " . $item['barcode'] . "\n";
    echo "************************************\n\n\n";*/
    $param_sku[] = $item['sku'];
}

//realizamos iteraciones de 20 en 20 SKUs encontrados en nuestro origen
$iteraciones = 20;
$array = range(0, ceil(count($param_sku))); // crea un array del 0 al N
for ($i = 0; $i < count($array); $i += $iteraciones) { // recorre de 20 en 20
    $products = array_slice($param_sku, $i, $iteraciones); // obtiene un subarray de 20 elementos

    $start = $i + 1; // obtiene el índice de inicio del subarray
    $end = $i + $iteraciones; // obtiene el índice de fin del subarray
    if ($end > count($array)) { // ajusta el rango si se excede el tamaño del array
        $end = count($array);
    }
    echo "➜ Obteniendo datos de destino [$url_API_woo] del $start al $end \n";
// Obtenemos todos los productos de la lista de SKUs
    $products = $woocommerce->get('products?sku=' . implode(',', $products));


// Construimos la data en base a los productos recuperados
    $item_data = [];
    foreach ($products as $product) {

        // Filtramos el array de origen por sku
        $sku = $product->sku;
        $search_item = array_filter($items_origin, function ($item) use ($sku) {
            return $item['sku'] == $sku;
        });
        $search_item = reset($search_item);

        // Formamos el array a actualizar
        $item_data[] = [
            'id' => $product->id,
            //'regular_price' => $search_item['price'],
            'stock_quantity' => $search_item['quantity'],
        ];

    }

// Construimos información a actualizar en lotes
    $data = [
        'update' => $item_data,
    ];

    echo "➜ Actualización en lote ... \n";
// Actualización en lotes
    $result = $woocommerce->post('products/batch', $data);

    if (!$result) {
        echo("❗Error al actualizar productos \n");
    } else {
        print("✔ Productos actualizados correctamente \n");
    }
}
