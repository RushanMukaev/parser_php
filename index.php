<?php
require 'vendor/autoload.php';

function searchProducts($searchQuery) {
    $baseUrl = 'https://www.autozap.ru';
    $results = [];

    // Инициализация cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_REFERER, $baseUrl,);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml',
            'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache',
            'Connection: keep-alive'

        ]
    );

    // Первый запрос на поиск
    $searchUrl = $baseUrl . '/goods?code=' . urlencode($searchQuery);
    curl_setopt($ch, CURLOPT_URL, $searchUrl);
    $html = curl_exec($ch);

    $doc = phpQuery::newDocument($html);

    $manufacturerLinks = $doc->find('#goodLnk1');

    if ($manufacturerLinks->length > 0) {
        // Если есть выбор производителя, берем первую ссылку
        $firstManufacturerLink = pq($manufacturerLinks->get(0))->attr('href');

        // Переходим по ссылке первого производителя
        curl_setopt($ch, CURLOPT_URL, $baseUrl . $firstManufacturerLink);
        $html = curl_exec($ch);
        $doc = phpQuery::newDocument($html);
    }

    // Парсим результаты поиска

    $arrItems = $doc->find('#tabGoods tr');

    $brandItems = '';
    $nameItems = '';
    $articleItems = '';

    foreach($arrItems as $item) {


        $pq = pq($item);

        if(!trim($pq->find('td.price span:first')->text())) {
            continue;
        }

        // Получаем все необходимые данные
        if(trim($pq->find('td.producer')->contents()->eq(0)->text())) {
            $brandItems = trim($pq->find('td.producer')->contents()->eq(0)->text());
        }
        if(trim($pq->find('td.name a')->contents()->eq(0)->text())) {
            $nameItems = trim($pq->find('td.name')->contents()->eq(0)->text());
        }
        if(trim($pq->find('td.code')->contents()->eq(0)->text())) {
            $articleItems = trim($pq->find('td.code')->contents()->eq(0)->text());
        }

        if($articleItems != $searchQuery) {
            break;
        }

        $name = $nameItems;
        $price = trim($pq->find('td.price span:first')->text());
        $article = $articleItems;
        $brand = $brandItems;
        $availability = trim($pq->find('.storehouse span')->text());
        $deliveryTime = (int)trim($pq->find('.article')->text());

        // Ищем скрытый input с id, начинающимся на 'g'
        $hiddenInput = $pq->find('input[type="hidden"][id^="g"]');
        $offerId = $hiddenInput->attr('value');

        $results[] = [
            'name' => $name,
            'price' => $price,
            'article' => $article,
            'brand' => $brand,
            'count' => $availability,
            'time' => $deliveryTime,
            'id' => $offerId
        ];
    }

    curl_close($ch);

    // Записываем результаты в JSON файл
    $jsonData = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents('search_results.json', $jsonData);

    return $results;
}

// Пример использования
try {
    $searchQuery = 'GIR01009'; // или любой другой поисковый запрос
    $results = searchProducts($searchQuery);

    // Выводим результаты для проверки
    print_r($results);

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}
