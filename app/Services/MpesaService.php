<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    /**
     * Base URL
     */
    private function baseUrl(): string
    {
        $env = config('mpesa.env', 'sandbox');

        return config("mpesa.base_urls.$env", 'https://sandbox.safaricom.co.ke');
    }

    /**
     * HTTP Client
     */
    private function http()
    {
        $http = Http::acceptJson()->timeout(30);

        if ($bundle = config('mpesa.ca_bundle')) {
            $http = $http->withOptions([
                'verify' => $bundle,
            ]);
        } elseif (config('mpesa.disable_ssl_verify', false)) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }

    /**
     * OAuth Token
     */
    public function accessToken(): string
    {
        $response = $this->http()
            ->withBasicAuth(
                config('mpesa.consumer_key'),
                config('mpesa.consumer_secret')
            )
            ->get(
                $this->baseUrl() . '/oauth/v1/generate',
                [
                    'grant_type' => 'client_credentials',
                ]
            );

        Log::info('===== M-PESA OAUTH =====');
        Log::info('Status', [
            'status' => $response->status(),
        ]);
        Log::info('Body', [
            'body' => $response->body(),
        ]);

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response->json('access_token');
    }

    /**
     * STK Push
     */
    public function stkPush(array $payload): array
    {
        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        $password = base64_encode(
            config('mpesa.shortcode')
            . config('mpesa.passkey')
            . $timestamp
        );

        $request = [
            'BusinessShortCode' => config('mpesa.shortcode'),
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => (int)$payload['amount'],
            'PartyA'            => $payload['phone'],
            'PartyB'            => config('mpesa.partyb'),
            'PhoneNumber'       => $payload['phone'],
            'CallBackURL'       => config('mpesa.callback_url'),
            'AccountReference'  => $payload['reference'],
            'TransactionDesc'   => $payload['description'],
        ];

        Log::info('===== STK REQUEST =====');
        Log::info($request);

        $response = $this->http()
            ->withToken($this->accessToken())
            ->post(
                $this->baseUrl() . '/mpesa/stkpush/v1/processrequest',
                $request
            );

        Log::info('===== STK RESPONSE =====');
        Log::info([
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response->json();
    }

    /**
     * STK Query
     */
    public function stkQuery(string $checkoutRequestID): array
    {
        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        $password = base64_encode(
            config('mpesa.shortcode')
            . config('mpesa.passkey')
            . $timestamp
        );

        $request = [
            'BusinessShortCode' => config('mpesa.shortcode'),
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestID,
        ];

        Log::info('===== STK QUERY =====');
        Log::info($request);

        $response = $this->http()
            ->withToken($this->accessToken())
            ->post(
                $this->baseUrl() . '/mpesa/stkpushquery/v1/query',
                $request
            );

        Log::info('===== STK QUERY RESPONSE =====');
        Log::info([
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response->json();
    }
}