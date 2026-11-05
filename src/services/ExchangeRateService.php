<?php

class ExchangeRateService
{
    public function getRate(string $currency): array|false
    {

        if (!in_array($currency, ['USD', 'EUR', 'GBP'], true)) {
            return false;
        }

        $url = 'https://api.frankfurter.dev/v2/rate/cad/'
            . strtolower($currency);

        $ch = curl_init($url);

        // Return the response and limit how long we wait
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);


        // Do not use failed responses
        if ($body === false || $status !== 200) {
            return false;
        }

        $result = json_decode($body, true);

        // Check the external response before using it
        if (
            !is_array($result) ||
            !isset($result['rate'], $result['date']) ||
            !is_numeric($result['rate']) ||
            !is_finite((float) $result['rate']) ||
            $result['rate'] <= 0 ||
            !is_string($result['date'])
        ) {
            return false;
        }

        return [
            'rate' => (float) $result['rate'],
            'date' => $result['date']
        ];
    }
}