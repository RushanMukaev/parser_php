<?php
require 'vendor/autoload.php';

class Parser {
    private $ch;
    private $baseUrl = 'https://www.autozap.ru';
    private $cookieFile = 'cookies.txt';

    public function __construct() {
        $this->initCurl();
    }

    private function initCurl() {
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_REFERER => $this->baseUrl,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml',
                'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control: no-cache',
                'Connection: keep-alive'
            ]
        ]);
    }

    public function searchProducts($searchQuery) {
        if (empty($searchQuery)) {
            throw new Exception("Введите артикул товара!");
        }

        try {

            $html = $this->makeRequest("/goods?code=" . urlencode($searchQuery));
            $doc = phpQuery::newDocument($html);

            // Проверяем наличие нескольких производителей
            $brandLinks = $doc->find('#goodLnk1');
            if ($brandLinks->length > 0) {
                $firstBrandLink = pq($brandLinks->get(0))->attr('href');
                $html = $this->makeRequest($firstBrandLink);
                $doc = phpQuery::newDocument($html);
            }

            $results = $this->parseResults($doc, $searchQuery);

            return $results;

        } catch (Exception $e) {
            error_log("Ошибка поиска товара '$searchQuery': " . $e->getMessage());
            throw $e;
        }
    }

    private function makeRequest($url) {
        // Добавляем случайную задержку
        usleep(rand(500000, 1500000));

        $url = $this->baseUrl . $url;
        curl_setopt($this->ch, CURLOPT_URL, $url);

        $response = curl_exec($this->ch);
        if ($response === false) {
            throw new Exception("CURL Error: " . curl_error($this->ch));
        }

        return $response;
    }

    private function parseResults($doc, $searchQuery) {
        $results = [];
        $arrItems = $doc->find('#tabGoods tr');

        $brandItems = '';
        $nameItems = '';
        $articleItems = '';

        foreach($arrItems as $item) {
            $pq = pq($item);

            if(!trim($pq->find('td.price span:first')->text())) {
                continue;
            }

            $brandItems = trim($pq->find('td.producer')->contents()->eq(0)->text()) ?: $brandItems;
            $nameItems = trim($pq->find('td.name a')->contents()->eq(0)->text()) ?: $nameItems;
            $articleItems = trim($pq->find('td.code')->contents()->eq(0)->text()) ?: $articleItems;

            // На странице выходят также аналоги с другим артикулом, что бы их не парсить прерываю цикл
            // Если проверку отключить, спарсит и аналоги
            if($articleItems != $searchQuery) {
                break;
            }

            $result = [
                'name' => $nameItems,
                'price' => $this->cleanPrice($pq->find('td.price span:first')->text()),
                'article' => $articleItems,
                'brand' => $brandItems,
                'count' => trim($pq->find('.storehouse span')->text()),
                'time' => (int)trim($pq->find('.article')->text()),
                'id' => $pq->find('input[type="hidden"][id^="g"]')->attr('value')
            ];

            if ($this->validateResult($result)) {
                $results[] = $result;
            }
        }

        return $results;
    }

    private function cleanPrice($price) {
        return preg_replace('/[^0-9.]/', '', $price);
    }

    private function validateResult($result) {
        return !empty($result['price']) && !empty($result['article']);
    }


    public function __destruct() {
        if ($this->ch) {
            curl_close($this->ch);
        }
    }
}

// Использование
try {
    $searcher = new Parser();
    $results = $searcher->searchProducts('17177');
    print_r($results);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
